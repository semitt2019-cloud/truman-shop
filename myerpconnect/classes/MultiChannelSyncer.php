<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * MultiChannelSyncer
 * ประสานงานการซิงค์สินค้าจาก ERP → PrestaShop, Shopee, Lazada
 * รองรับราคา 2 ระดับ: ขายปลีก (retail) และ ขายส่ง (wholesale)
 */
class MultiChannelSyncer
{
    private ErpApiClient    $erp;
    private ShopeeApiClient $shopee;
    private LazadaApiClient $lazada;

    // PrestaShop Customer Group IDs (ปรับให้ตรงกับระบบจริง)
    const GROUP_RETAIL    = 1; // ลูกค้าทั่วไป
    const GROUP_WHOLESALE = 3; // ลูกค้าขายส่ง

    // Markup ราคาขายส่งจากราคา ERP (เช่น 0.85 = ลด 15%)
    private float $wholesale_factor;
    private float $retail_factor;

    private array $shopee_sku_map  = []; // ['ERP_SKU' => shopee_item_id]
    private array $lazada_sku_map  = []; // ['ERP_SKU' => lazada_sku_id]

    public function __construct(
        ErpApiClient    $erp,
        ShopeeApiClient $shopee,
        LazadaApiClient $lazada,
        float $retail_factor    = 1.20,  // บวก 20% จาก ERP cost
        float $wholesale_factor = 1.05   // บวก 5%  จาก ERP cost
    ) {
        $this->erp              = $erp;
        $this->shopee           = $shopee;
        $this->lazada           = $lazada;
        $this->retail_factor    = $retail_factor;
        $this->wholesale_factor = $wholesale_factor;
    }

    // ---------------------------------------------------------------
    // จุดเริ่มต้นหลัก — รัน full sync ทุก channel
    // ---------------------------------------------------------------
    public function runFullSync(): array
    {
        $stats = [
            'prestashop' => ['updated' => 0, 'errors' => 0],
            'shopee'     => ['updated' => 0, 'errors' => 0],
            'lazada'     => ['updated' => 0, 'errors' => 0],
        ];

        $this->buildShopeeSkuMap();
        $this->buildLazadaSkuMap();

        $page       = 1;
        $batch_size = 100;

        do {
            $products = $this->erp->fetchProducts($page, $batch_size);
            if (empty($products)) {
                break;
            }

            // เตรียม batch สำหรับ Shopee และ Lazada
            $shopee_price_batch  = [];
            $shopee_stock_batch  = [];
            $lazada_price_batch  = [];
            $lazada_stock_batch  = [];

            foreach ($products as $p) {
                $sku          = $p['reference'] ?? '';
                $retail_price = round($p['cost'] * $this->retail_factor, 2);
                $ws_price     = round($p['cost'] * $this->wholesale_factor, 2);
                $quantity     = (int)($p['quantity'] ?? 0);

                // --- PrestaShop ---
                try {
                    $this->syncToPrestaShop($p, $retail_price, $ws_price, $quantity);
                    $stats['prestashop']['updated']++;
                } catch (Exception $e) {
                    $stats['prestashop']['errors']++;
                    $this->log('PrestaShop sync error SKU ' . $sku . ': ' . $e->getMessage(), 3);
                }

                // เตรียม Shopee batch
                if (isset($this->shopee_sku_map[$sku])) {
                    $shopee_price_batch[] = [
                        'item_id' => $this->shopee_sku_map[$sku],
                        'price'   => $retail_price,
                    ];
                    $shopee_stock_batch[] = [
                        'item_id'  => $this->shopee_sku_map[$sku],
                        'quantity' => $quantity,
                    ];
                }

                // เตรียม Lazada batch
                if (isset($this->lazada_sku_map[$sku])) {
                    $lazada_price_batch[] = [
                        'sku_id' => $this->lazada_sku_map[$sku],
                        'price'  => $retail_price,
                    ];
                    $lazada_stock_batch[] = [
                        'sku_id'   => $this->lazada_sku_map[$sku],
                        'quantity' => $quantity,
                    ];
                }
            }

            // --- Shopee batch push ---
            if ($shopee_price_batch) {
                try {
                    $this->shopee->updatePrice($shopee_price_batch);
                    $this->shopee->updateStock($shopee_stock_batch);
                    $stats['shopee']['updated'] += count($shopee_price_batch);
                } catch (Exception $e) {
                    $stats['shopee']['errors']++;
                    $this->log('Shopee batch error page ' . $page . ': ' . $e->getMessage(), 3);
                }
            }

            // --- Lazada batch push ---
            if ($lazada_price_batch) {
                try {
                    $this->lazada->updatePrice($lazada_price_batch);
                    $this->lazada->updateStock($lazada_stock_batch);
                    $stats['lazada']['updated'] += count($lazada_price_batch);
                } catch (Exception $e) {
                    $stats['lazada']['errors']++;
                    $this->log('Lazada batch error page ' . $page . ': ' . $e->getMessage(), 3);
                }
            }

            $page++;
        } while (count($products) === $batch_size);

        $this->log('Full sync complete: ' . json_encode($stats), 1);
        return $stats;
    }

    // ---------------------------------------------------------------
    // PrestaShop: upsert สินค้า + ราคา 2 ระดับ (ขายส่ง/ขายปลีก)
    // ---------------------------------------------------------------
    private function syncToPrestaShop(array $p, float $retail_price, float $ws_price, int $quantity): void
    {
        $reference = pSQL($p['reference'] ?? '');
        if (empty($reference)) {
            throw new Exception('Empty SKU reference');
        }

        $id_product = (int)Db::getInstance()->getValue(
            'SELECT `id_product` FROM `' . _DB_PREFIX_ . 'product` WHERE `reference` = "' . $reference . '"'
        );

        if ($id_product) {
            $product = new Product($id_product);
        } else {
            $product = new Product();
            $product->reference         = $reference;
            $product->id_category_default = (int)Configuration::get('PS_HOME_CATEGORY');
            $product->active            = 1;
            $product->available_for_order = 1;
        }

        $lang_id = (int)Configuration::get('PS_LANG_DEFAULT');
        $product->name[$lang_id]        = pSQL($p['name'] ?? $reference);
        $product->description_short[$lang_id] = pSQL($p['description'] ?? '');
        $product->price = $retail_price; // ราคาฐาน = ขายปลีก

        $product->save();

        // ราคาขายส่ง (Specific Price สำหรับ Group ขายส่ง)
        $this->setGroupPrice($product->id, self::GROUP_WHOLESALE, $ws_price);

        // อัปเดตสต็อก
        StockAvailable::setQuantity($product->id, 0, $quantity);
    }

    // ตั้งราคา specific price สำหรับ customer group
    private function setGroupPrice(int $id_product, int $id_group, float $price): void
    {
        $id_sp = (int)Db::getInstance()->getValue(
            'SELECT `id_specific_price` FROM `' . _DB_PREFIX_ . 'specific_price`
             WHERE `id_product` = ' . $id_product . ' AND `id_group` = ' . $id_group . '
             AND `id_shop` = 0 AND `id_currency` = 0 AND `id_country` = 0'
        );

        if ($id_sp) {
            Db::getInstance()->update('specific_price', ['price' => $price], '`id_specific_price` = ' . $id_sp);
        } else {
            Db::getInstance()->insert('specific_price', [
                'id_product'         => $id_product,
                'id_product_attribute' => 0,
                'id_shop'            => 0,
                'id_currency'        => 0,
                'id_country'         => 0,
                'id_group'           => $id_group,
                'id_customer'        => 0,
                'price'              => $price,
                'from_quantity'      => 1,
                'reduction'          => 0,
                'reduction_type'     => 'amount',
                'reduction_tax'      => 1,
                'from'               => '0000-00-00 00:00:00',
                'to'                 => '0000-00-00 00:00:00',
            ]);
        }
    }

    // ---------------------------------------------------------------
    // สร้าง map: ERP SKU → Shopee item_id / Lazada sku_id
    // ---------------------------------------------------------------
    private function buildShopeeSkuMap(): void
    {
        // ดึงจาก ps_product ที่เคย map ไว้ใน column shopee_item_id
        // (หรือจะ query Shopee API โดยตรงก็ได้ แต่ช้ากว่า)
        $rows = Db::getInstance()->executeS(
            'SELECT `reference`, `shopee_item_id` FROM `' . _DB_PREFIX_ . 'product`
             WHERE `shopee_item_id` IS NOT NULL AND `shopee_item_id` != 0'
        );
        foreach ($rows as $row) {
            $this->shopee_sku_map[$row['reference']] = (int)$row['shopee_item_id'];
        }
    }

    private function buildLazadaSkuMap(): void
    {
        $rows = Db::getInstance()->executeS(
            'SELECT `reference`, `lazada_sku_id` FROM `' . _DB_PREFIX_ . 'product`
             WHERE `lazada_sku_id` IS NOT NULL AND `lazada_sku_id` != ""'
        );
        foreach ($rows as $row) {
            $this->lazada_sku_map[$row['reference']] = $row['lazada_sku_id'];
        }
    }

    private function log(string $message, int $severity = 1): void
    {
        PrestaShopLogger::addLog(
            '[MyErpConnect] ' . $message,
            $severity,
            null,
            'MultiChannelSyncer',
            null,
            true
        );
    }
}
