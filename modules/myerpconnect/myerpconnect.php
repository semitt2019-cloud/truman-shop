<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

// Interfaces (load before concrete classes)
require_once __DIR__ . '/classes/connectors/IErpConnector.php';
require_once __DIR__ . '/classes/connectors/IMarketplaceConnector.php';
require_once __DIR__ . '/classes/connectors/INotificationService.php';

// Concrete connectors
require_once __DIR__ . '/classes/connectors/ManualErpConnector.php';
require_once __DIR__ . '/classes/connectors/NullMarketplace.php';
require_once __DIR__ . '/classes/connectors/SmtpMailService.php';
require_once __DIR__ . '/classes/ErpApiClient.php';
require_once __DIR__ . '/classes/ShopeeApiClient.php';
require_once __DIR__ . '/classes/LazadaApiClient.php';
require_once __DIR__ . '/classes/MultiChannelSyncer.php';

// Factory (load last — needs all classes above)
require_once __DIR__ . '/classes/connectors/ConnectorFactory.php';

class MyErpConnect extends Module
{
    const CFG_KEYS = [
        'MYERPCONNECT_API_URL',
        'MYERPCONNECT_API_KEY',
        'MYERPCONNECT_RETAIL_FACTOR',
        'MYERPCONNECT_WHOLESALE_FACTOR',
        'MYERPCONNECT_SHOPEE_PARTNER_ID',
        'MYERPCONNECT_SHOPEE_PARTNER_KEY',
        'MYERPCONNECT_SHOPEE_SHOP_ID',
        'MYERPCONNECT_SHOPEE_ACCESS_TOKEN',
        'MYERPCONNECT_LAZADA_APP_KEY',
        'MYERPCONNECT_LAZADA_APP_SECRET',
        'MYERPCONNECT_LAZADA_ACCESS_TOKEN',
        'MYERPCONNECT_FLASH_SALE_END',
        'MYERPCONNECT_GROUP_RETAIL',
        'MYERPCONNECT_GROUP_MECHANIC',
        'MYERPCONNECT_GROUP_DEALER',
        'MYERPCONNECT_ADMIN_EMAIL',
    ];

    const TYPE_RETAIL   = 'retail';
    const TYPE_MECHANIC = 'mechanic';
    const TYPE_DEALER   = 'dealer';

    public $tabs = [
        [
            'name'              => 'ยืนยันตัวตนลูกค้า',
            'class_name'        => 'AdminCustomerVerification',
            'parent_class_name' => 'AdminParentCustomers',
            'visible'           => true,
            'icon'              => 'verified_user',
        ],
    ];

    public function __construct()
    {
        $this->name = 'myerpconnect';
        $this->tab  = 'administration';
        $this->version = '1.2.0';
        $this->author  = '2M RACING Dev';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = ['min' => '8.0.0', 'max' => _PS_VERSION_];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('ERP Multi-Channel Connector');
        $this->description = $this->l('ซิงค์ ERP + ระบบสมาชิกหลายระดับ + ยืนยันตัวตน + ราคาขายส่ง/ปลีก');
        $this->confirmUninstall = $this->l('คุณแน่ใจหรือไม่ว่าต้องการถอนการติดตั้ง?');
    }

    public function install()
    {
        return parent::install()
            && $this->registerHook('actionProductSave')
            && $this->registerHook('actionOrderStatusUpdate')
            && $this->registerHook('displayHeader')
            && $this->registerHook('displayProductListHeader')
            && $this->registerHook('displayProductMiniatureCustom')
            && $this->registerHook('displayCustomerAccountForm')
            && $this->registerHook('actionCustomerAccountAdd')
            && $this->registerHook('displayCustomerAccount')
            && $this->addColumns()
            && $this->installTables()
            && $this->createCustomerGroups()
            && $this->createUploadDir();
    }

    public function uninstall()
    {
        foreach (self::CFG_KEYS as $key) {
            Configuration::deleteByName($key);
        }
        $this->removeColumns();
        $this->uninstallTables();
        return parent::uninstall();
    }

    // ---------------------------------------------------------------
    // DB
    // ---------------------------------------------------------------
    private function installTables(): bool
    {
        $db = Db::getInstance();
        $p  = _DB_PREFIX_;

        $tables = [
            // ยืนยันตัวตนลูกค้า
            "CREATE TABLE IF NOT EXISTS `{$p}myerpconnect_verification` (
                `id_verification` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_customer`     INT UNSIGNED NOT NULL,
                `customer_type`   ENUM('retail','mechanic','dealer') NOT NULL DEFAULT 'retail',
                `status`          ENUM('approved','pending','rejected') NOT NULL DEFAULT 'pending',
                `doc_shop_photo`  VARCHAR(255) DEFAULT NULL,
                `doc_trade_reg`   VARCHAR(255) DEFAULT NULL,
                `doc_id_card`     VARCHAR(255) DEFAULT NULL,
                `admin_notes`     TEXT DEFAULT NULL,
                `date_add`        DATETIME NOT NULL,
                `date_upd`        DATETIME NOT NULL,
                PRIMARY KEY (`id_verification`),
                UNIQUE KEY `ux_customer` (`id_customer`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            // mapping สินค้า PS ↔ Shopee / Lazada / TikTok / ERP
            "CREATE TABLE IF NOT EXISTS `{$p}mec_product_channel` (
                `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_product`      INT UNSIGNED NOT NULL,
                `channel`         ENUM('shopee','lazada','tiktok','erp') NOT NULL,
                `channel_item_id` VARCHAR(128) NOT NULL DEFAULT '',
                `channel_sku_id`  VARCHAR(128) DEFAULT NULL,
                `active`          TINYINT(1) NOT NULL DEFAULT 1,
                `date_sync`       DATETIME DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `ux_product_channel` (`id_product`,`channel`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            // ราคาต่อสินค้าต่อ customer group (override PS specific-price)
            "CREATE TABLE IF NOT EXISTS `{$p}mec_price_tier` (
                `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_product` INT UNSIGNED NOT NULL,
                `id_group`   INT UNSIGNED NOT NULL,
                `price`      DECIMAL(20,6) NOT NULL DEFAULT 0.000000,
                `date_upd`   DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `ux_product_group` (`id_product`,`id_group`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            // Flash Sale campaigns
            "CREATE TABLE IF NOT EXISTS `{$p}mec_flash_sale` (
                `id_flash_sale` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name`          VARCHAR(128) NOT NULL,
                `date_start`    DATETIME NOT NULL,
                `date_end`      DATETIME NOT NULL,
                `active`        TINYINT(1) NOT NULL DEFAULT 1,
                `date_add`      DATETIME NOT NULL,
                PRIMARY KEY (`id_flash_sale`),
                KEY `idx_active_end` (`active`,`date_end`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            // สินค้าในแต่ละ Flash Sale
            "CREATE TABLE IF NOT EXISTS `{$p}mec_flash_sale_product` (
                `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_flash_sale` INT UNSIGNED NOT NULL,
                `id_product`    INT UNSIGNED NOT NULL,
                `price_special` DECIMAL(20,6) NOT NULL DEFAULT 0.000000,
                `qty_limit`     INT UNSIGNED DEFAULT NULL,
                `qty_sold`      INT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                UNIQUE KEY `ux_sale_product` (`id_flash_sale`,`id_product`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            // snapshot สต็อกแต่ละ channel
            "CREATE TABLE IF NOT EXISTS `{$p}mec_stock_channel` (
                `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_product` INT UNSIGNED NOT NULL,
                `channel`    ENUM('prestashop','shopee','lazada','tiktok','erp') NOT NULL,
                `qty`        INT NOT NULL DEFAULT 0,
                `date_sync`  DATETIME DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `ux_product_channel_stock` (`id_product`,`channel`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            // audit log การ sync
            "CREATE TABLE IF NOT EXISTS `{$p}mec_sync_log` (
                `id_log`   INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `channel`  VARCHAR(32) NOT NULL,
                `action`   VARCHAR(64) NOT NULL,
                `status`   ENUM('success','error','partial') NOT NULL,
                `records`  INT NOT NULL DEFAULT 0,
                `message`  TEXT DEFAULT NULL,
                `date_add` DATETIME NOT NULL,
                PRIMARY KEY (`id_log`),
                KEY `idx_channel_date` (`channel`,`date_add`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            // mapping PS order ↔ marketplace order
            "CREATE TABLE IF NOT EXISTS `{$p}mec_order_channel` (
                `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_order`         INT UNSIGNED NOT NULL,
                `channel`          ENUM('shopee','lazada','tiktok') NOT NULL,
                `channel_order_id` VARCHAR(128) NOT NULL,
                `channel_status`   VARCHAR(64) DEFAULT NULL,
                `date_add`         DATETIME NOT NULL,
                `date_upd`         DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `ux_order_channel` (`id_order`,`channel`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            // cache ข้อมูลสินค้าจาก ERP
            "CREATE TABLE IF NOT EXISTS `{$p}mec_erp_product` (
                `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_product` INT UNSIGNED NOT NULL,
                `erp_sku`    VARCHAR(64) NOT NULL,
                `cost_price` DECIMAL(20,6) NOT NULL DEFAULT 0.000000,
                `erp_stock`  INT NOT NULL DEFAULT 0,
                `erp_data`   TEXT DEFAULT NULL,
                `date_sync`  DATETIME DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `ux_product` (`id_product`),
                UNIQUE KEY `ux_erp_sku` (`erp_sku`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        ];

        foreach ($tables as $sql) {
            if (!$db->execute($sql)) {
                return false;
            }
        }

        return true;
    }

    private function uninstallTables(): void
    {
        $db = Db::getInstance();
        $p  = _DB_PREFIX_;

        foreach ([
            'mec_erp_product',
            'mec_order_channel',
            'mec_sync_log',
            'mec_stock_channel',
            'mec_flash_sale_product',
            'mec_flash_sale',
            'mec_price_tier',
            'mec_product_channel',
            'myerpconnect_verification',
        ] as $table) {
            $db->execute("DROP TABLE IF EXISTS `{$p}{$table}`");
        }
    }

    private function createCustomerGroups(): bool
    {
        $langs   = Language::getLanguages(false);
        $groups  = [
            'MYERPCONNECT_GROUP_RETAIL'   => ['th' => 'ลูกค้าทั่วไป (ปลีก)',      'en' => 'Retail Customer'],
            'MYERPCONNECT_GROUP_MECHANIC' => ['th' => 'ช่างซ่อม',                   'en' => 'Mechanic'],
            'MYERPCONNECT_GROUP_DEALER'   => ['th' => 'ร้านขายอะไหล่ (ส่ง)',       'en' => 'Parts Dealer'],
        ];

        foreach ($groups as $cfg_key => $names) {
            if ((int)Configuration::get($cfg_key) > 0) {
                continue;
            }
            $group = new Group();
            $group->reduction = 0;
            $group->price_display_method = PS_TAX_EXC;
            $group->show_prices = 1;
            foreach ($langs as $lang) {
                $iso = $lang['iso_code'];
                $group->name[(int)$lang['id_lang']] = $names[$iso] ?? $names['en'];
            }
            if ($group->add()) {
                Configuration::updateValue($cfg_key, (int)$group->id);
            }
        }

        return true;
    }

    private function addColumns(): bool
    {
        Db::getInstance()->execute('ALTER TABLE `' . _DB_PREFIX_ . 'product`
            ADD COLUMN IF NOT EXISTS `shopee_item_id` INT(11) NULL DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS `lazada_sku_id` VARCHAR(64) NULL DEFAULT NULL');
        return true;
    }

    private function removeColumns(): void {}

    private function createUploadDir(): bool
    {
        $dir = self::getVerificationUploadDir();
        return is_dir($dir);
    }

    // Static — ใช้ได้จาก front/admin controller
    public static function getVerificationUploadDir(): string
    {
        $dir = _PS_MODULE_DIR_ . 'myerpconnect/uploads/verification/';
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        $htaccess = $dir . '.htaccess';
        if (!file_exists($htaccess)) {
            file_put_contents($htaccess, "Options -Indexes\nDeny from all\n");
        }
        return $dir;
    }

    // ---------------------------------------------------------------
    // Back Office config
    // ---------------------------------------------------------------
    public function getContent(): string
    {
        $output = '';

        if (Tools::isSubmit('submit_myerpconnect')) {
            foreach (self::CFG_KEYS as $key) {
                Configuration::updateValue($key, Tools::getValue($key));
            }
            $output .= $this->displayConfirmation($this->l('บันทึกการตั้งค่าเรียบร้อยแล้ว'));
        }

        if (Tools::isSubmit('sync_now')) {
            try {
                $stats = $this->buildSyncer()->runFullSync();
                $output .= $this->displayConfirmation(
                    'Sync สำเร็จ — PS:' . $stats['prestashop']['updated']
                    . ' Shopee:' . $stats['shopee']['updated']
                    . ' Lazada:' . $stats['lazada']['updated']
                );
            } catch (Exception $e) {
                $output .= $this->displayError($e->getMessage());
            }
        }

        return $output . $this->renderForm();
    }

    private function renderForm(): string
    {
        $grp_info = '<div class="alert alert-info"><strong>กลุ่มลูกค้าที่สร้างแล้ว:</strong><br>'
            . 'ลูกค้าทั่วไป (Group ID: ' . (int)Configuration::get('MYERPCONNECT_GROUP_RETAIL') . ')<br>'
            . 'ช่างซ่อม (Group ID: ' . (int)Configuration::get('MYERPCONNECT_GROUP_MECHANIC') . ')<br>'
            . 'ร้านขายอะไหล่ (Group ID: ' . (int)Configuration::get('MYERPCONNECT_GROUP_DEALER') . ')'
            . '</div>';

        $fields_form = ['form' => [
            'legend' => ['title' => $this->l('ตั้งค่าการเชื่อมต่อ'), 'icon' => 'icon-cogs'],
            'input'  => [
                ['type' => 'html', 'html_content' => '<h4>ERP API</h4>',           'name' => ''],
                ['type' => 'text', 'label' => 'ERP API URL',            'name' => 'MYERPCONNECT_API_URL',           'required' => true],
                ['type' => 'text', 'label' => 'ERP API Key',            'name' => 'MYERPCONNECT_API_KEY',           'required' => true],
                ['type' => 'text', 'label' => 'Retail Price Factor',    'name' => 'MYERPCONNECT_RETAIL_FACTOR',     'desc' => 'เช่น 1.20 = บวก 20%'],
                ['type' => 'text', 'label' => 'Wholesale Price Factor', 'name' => 'MYERPCONNECT_WHOLESALE_FACTOR',  'desc' => 'เช่น 1.05 = บวก 5%'],
                ['type' => 'html', 'html_content' => '<h4>Shopee</h4>',            'name' => ''],
                ['type' => 'text', 'label' => 'Partner ID',   'name' => 'MYERPCONNECT_SHOPEE_PARTNER_ID'],
                ['type' => 'text', 'label' => 'Partner Key',  'name' => 'MYERPCONNECT_SHOPEE_PARTNER_KEY'],
                ['type' => 'text', 'label' => 'Shop ID',      'name' => 'MYERPCONNECT_SHOPEE_SHOP_ID'],
                ['type' => 'text', 'label' => 'Access Token', 'name' => 'MYERPCONNECT_SHOPEE_ACCESS_TOKEN'],
                ['type' => 'html', 'html_content' => '<h4>Lazada</h4>',            'name' => ''],
                ['type' => 'text', 'label' => 'App Key',      'name' => 'MYERPCONNECT_LAZADA_APP_KEY'],
                ['type' => 'text', 'label' => 'App Secret',   'name' => 'MYERPCONNECT_LAZADA_APP_SECRET'],
                ['type' => 'text', 'label' => 'Access Token', 'name' => 'MYERPCONNECT_LAZADA_ACCESS_TOKEN'],
                ['type' => 'html', 'html_content' => '<h4>Flash Sale</h4>',        'name' => ''],
                ['type' => 'text', 'label' => 'Flash Sale End (Unix Timestamp)', 'name' => 'MYERPCONNECT_FLASH_SALE_END', 'desc' => 'Unix timestamp สิ้นสุด Flash Sale — ว่าง/0 = ปิด'],
                ['type' => 'html', 'html_content' => '<h4>ระบบสมาชิก</h4>',      'name' => ''],
                ['type' => 'text', 'label' => 'อีเมลแจ้งเตือน Admin', 'name' => 'MYERPCONNECT_ADMIN_EMAIL', 'desc' => 'รับแจ้งเมื่อลูกค้าส่งเอกสารยืนยันตัวตน'],
                ['type' => 'html', 'html_content' => $grp_info, 'name' => ''],
            ],
            'submit'  => ['title' => $this->l('บันทึกการตั้งค่า'), 'class' => 'btn btn-default pull-right'],
            'buttons' => [[
                'title' => $this->l('Sync ทันที'),
                'name'  => 'sync_now',
                'type'  => 'submit',
                'class' => 'btn btn-info pull-right',
                'icon'  => 'process-icon-refresh',
            ]],
        ]];

        $helper = new HelperForm();
        $helper->module           = $this;
        $helper->name_controller  = $this->name;
        $helper->token            = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex     = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->submit_action    = 'submit_myerpconnect';
        $helper->default_form_language = (int)Configuration::get('PS_LANG_DEFAULT');

        foreach (self::CFG_KEYS as $key) {
            $helper->fields_value[$key] = Configuration::get($key);
        }

        return $helper->generateForm([$fields_form]);
    }

    // ---------------------------------------------------------------
    // Hooks — Frontend display
    // ---------------------------------------------------------------
    public function hookDisplayHeader(): void
    {
        $this->context->controller->addCSS($this->_path . 'views/css/shopee-style.css');
        $this->context->controller->addJS($this->_path . 'views/js/product-grid.js');
    }

    public function hookDisplayProductListHeader(array $params): string
    {
        $categories = Category::getSimpleCategories($this->context->language->id);
        foreach ($categories as &$cat) {
            $cat['url'] = $this->context->link->getCategoryLink(
                (int)$cat['id_category'], null, $this->context->language->id
            );
        }
        unset($cat);

        $orderby  = Tools::getValue('orderby',  'position');
        $orderway = Tools::getValue('orderway', 'asc');
        if (!in_array($orderby,  ['position','name','price','date_add','sales'], true)) { $orderby  = 'position'; }
        if (!in_array($orderway, ['asc','desc'], true)) { $orderway = 'asc'; }

        $flash_end_ts = (int)Configuration::get('MYERPCONNECT_FLASH_SALE_END');
        $flash_active = $flash_end_ts > time();

        $current_cat_id = (int)Tools::getValue('id_category', 0);
        $current_cat_name = '';
        if ($current_cat_id > 0) {
            $cat_obj = new Category($current_cat_id, $this->context->language->id);
            $current_cat_name = $cat_obj->name ?? '';
        }

        $this->context->smarty->assign([
            'categories'               => $categories,
            'current_category'         => $current_cat_id,
            'current_category_name'    => $current_cat_name,
            'orderby'                  => $orderby,
            'orderway'                 => $orderway,
            'flash_sale_active'        => $flash_active,
            'flash_sale_end_timestamp' => $flash_end_ts * 1000,
            'flash_sale_url'           => $flash_active
                ? $this->context->link->getCategoryLink($current_cat_id ?: 2)
                : null,
        ]);

        return $this->fetch('module:myerpconnect/views/templates/hook/product-listing-page.tpl');
    }

    public function hookDisplayProductMiniatureCustom(array $params): string
    {
        $product = $params['product'] ?? [];
        if (empty($product)) {
            return '';
        }

        $id_product = (int)($product['id_product'] ?? 0);
        $ws_factor  = (float)(Configuration::get('MYERPCONNECT_WHOLESALE_FACTOR') ?: 1.05);

        if ($id_product > 0) {
            $ws_raw = Db::getInstance()->getValue(
                'SELECT `wholesale_price` FROM `' . _DB_PREFIX_ . 'product` WHERE `id_product` = ' . $id_product
            );
            $product['wholesale_display'] = ($ws_raw > 0)
                ? Tools::displayPrice((float)$ws_raw * $ws_factor, $this->context->currency)
                : null;
            $product['is_wholesale'] = ($ws_raw > 0);

            $product['sold_count'] = (int)Db::getInstance()->getValue(
                'SELECT SUM(od.`product_quantity`)
                 FROM `' . _DB_PREFIX_ . 'order_detail` od
                 INNER JOIN `' . _DB_PREFIX_ . 'orders` o ON o.`id_order` = od.`id_order`
                 WHERE od.`product_id` = ' . $id_product . ' AND o.`valid` = 1'
            );
        }

        $this->context->smarty->assign(['product' => $product]);
        return $this->fetch('module:myerpconnect/views/templates/front/product-miniature.tpl');
    }

    // ---------------------------------------------------------------
    // Hook: Type selector ในฟอร์มสมัครสมาชิก
    // ---------------------------------------------------------------
    public function hookDisplayCustomerAccountForm(): string
    {
        return $this->fetch('module:myerpconnect/views/templates/hook/customer-type-selector.tpl');
    }

    // ---------------------------------------------------------------
    // Hook: บันทึก type หลังสมัครสมาชิกสำเร็จ
    // ---------------------------------------------------------------
    public function hookActionCustomerAccountAdd(array $params): void
    {
        $customer = $params['newCustomer'] ?? null;
        if (!($customer instanceof Customer)) {
            return;
        }

        $type = Tools::getValue('customer_type', self::TYPE_RETAIL);
        if (!in_array($type, [self::TYPE_RETAIL, self::TYPE_MECHANIC, self::TYPE_DEALER], true)) {
            $type = self::TYPE_RETAIL;
        }

        $id_customer = (int)$customer->id;
        $status      = ($type === self::TYPE_RETAIL) ? 'approved' : 'pending';
        $now         = date('Y-m-d H:i:s');

        Db::getInstance()->insert('myerpconnect_verification', [
            'id_customer'   => $id_customer,
            'customer_type' => pSQL($type),
            'status'        => pSQL($status),
            'date_add'      => $now,
            'date_upd'      => $now,
        ], false, true, Db::INSERT_IGNORE);

        $this->applyCustomerGroup($id_customer, $type, $status);

        if ($type !== self::TYPE_RETAIL) {
            $this->context->cookie->mec_pending_docs = 1;
        }
    }

    // ---------------------------------------------------------------
    // Hook: สถานะในหน้า My Account
    // ---------------------------------------------------------------
    public function hookDisplayCustomerAccount(): string
    {
        if (!$this->context->customer->isLogged()) {
            return '';
        }

        $id_customer = (int)$this->context->customer->id;
        $verification = Db::getInstance()->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . 'myerpconnect_verification`
             WHERE `id_customer` = ' . $id_customer
        );

        $show_banner = !empty($this->context->cookie->mec_pending_docs);
        if ($show_banner) {
            unset($this->context->cookie->mec_pending_docs);
        }

        $this->context->smarty->assign([
            'mec_verification'  => $verification,
            'mec_show_banner'   => $show_banner,
            'mec_upload_url'    => $this->context->link->getModuleLink('myerpconnect', 'verification'),
            'mec_type_labels'   => [
                'retail'   => 'ลูกค้าทั่วไป (ปลีก)',
                'mechanic' => 'ช่างซ่อม',
                'dealer'   => 'ร้านขายอะไหล่ (ส่ง)',
            ],
            'mec_status_labels' => [
                'approved' => 'อนุมัติแล้ว',
                'pending'  => 'รออนุมัติ',
                'rejected' => 'ปฏิเสธ',
            ],
        ]);

        return $this->fetch('module:myerpconnect/views/templates/hook/customer-account-verification.tpl');
    }

    // ---------------------------------------------------------------
    // Hook: ตัดสต็อก ERP เมื่อชำระเงิน
    // ---------------------------------------------------------------
    public function hookActionOrderStatusUpdate(array $params): void
    {
        $id_order   = (int)$params['id_order'];
        $new_status = $params['newOrderStatus'];

        if ((int)$new_status->id !== (int)Configuration::get('PS_OS_PAYMENT')) {
            return;
        }

        try {
            $order        = new Order($id_order);
            $order_details = $order->getOrderDetailList();
            $items = [];
            foreach ($order_details as $detail) {
                $items[] = [
                    'sku'      => $detail['product_reference'],
                    'quantity' => (int)$detail['product_quantity'],
                ];
            }
            $this->buildErpClient()->deductStock($id_order, $items);
            PrestaShopLogger::addLog(
                '[MyErpConnect] Stock deducted Order #' . $id_order . ' — ' . count($items) . ' SKUs',
                1, null, 'MyErpConnect', $id_order, true
            );
        } catch (Exception $e) {
            PrestaShopLogger::addLog(
                '[MyErpConnect] deductStock failed Order #' . $id_order . ': ' . $e->getMessage(),
                3, null, 'MyErpConnect', $id_order, true
            );
        }
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------
    public static function applyCustomerGroup(int $id_customer, string $type, string $status): void
    {
        $group_retail   = (int)Configuration::get('MYERPCONNECT_GROUP_RETAIL');
        $group_mechanic = (int)Configuration::get('MYERPCONNECT_GROUP_MECHANIC');
        $group_dealer   = (int)Configuration::get('MYERPCONNECT_GROUP_DEALER');
        $default_group  = (int)Configuration::get('PS_CUSTOMER_GROUP');

        if ($status === 'approved') {
            $group_id = match ($type) {
                self::TYPE_MECHANIC => $group_mechanic ?: $default_group,
                self::TYPE_DEALER   => $group_dealer   ?: $default_group,
                default             => $group_retail   ?: $default_group,
            };
        } else {
            $group_id = $group_retail ?: $default_group;
        }

        if ($group_id <= 0) {
            return;
        }

        $db = Db::getInstance();
        $db->execute(
            'UPDATE `' . _DB_PREFIX_ . 'customer`
             SET `id_default_group` = ' . $group_id . '
             WHERE `id_customer` = ' . $id_customer
        );
        $db->delete('customer_group', '`id_customer` = ' . $id_customer);
        $db->insert('customer_group', [
            'id_customer' => $id_customer,
            'id_group'    => $group_id,
        ]);
    }

    private function buildErpClient(): IErpConnector
    {
        return ConnectorFactory::erp();
    }

    private function buildSyncer(): MultiChannelSyncer
    {
        return new MultiChannelSyncer(
            ConnectorFactory::erp(),
            ConnectorFactory::shopee(),
            ConnectorFactory::lazada(),
            (float)(Configuration::get('MYERPCONNECT_RETAIL_FACTOR')    ?: 1.20),
            (float)(Configuration::get('MYERPCONNECT_WHOLESALE_FACTOR') ?: 1.05)
        );
    }
}
