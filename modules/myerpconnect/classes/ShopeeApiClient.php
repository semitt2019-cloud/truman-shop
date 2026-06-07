<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Shopee Open Platform API v2
 * Docs: https://open.shopee.com/documents
 */
class ShopeeApiClient implements IMarketplaceConnector
{
    const BASE_URL = 'https://partner.shopeemobile.com';

    private $partner_id;
    private $partner_key;
    private $shop_id;
    private $access_token;

    public function __construct(int $partner_id, string $partner_key, int $shop_id, string $access_token)
    {
        $this->partner_id   = $partner_id;
        $this->partner_key  = $partner_key;
        $this->shop_id      = $shop_id;
        $this->access_token = $access_token;
    }

    // อัปเดตราคาสินค้าบน Shopee
    // $items = [['item_id' => 123, 'price' => 99.00], ...]
    public function updatePrice(array $items): array
    {
        $payload = ['price_list' => array_map(fn($i) => [
            'item_id'     => $i['item_id'],
            'price'       => $i['price'],
            'original_price' => $i['original_price'] ?? $i['price'],
        ], $items)];

        return $this->post('/api/v2/product/update_price', $payload);
    }

    // อัปเดตสต็อกสินค้าบน Shopee
    // $items = [['item_id' => 123, 'model_id' => 0, 'seller_stock' => [['location_id' => '', 'stock' => 50]]], ...]
    public function updateStock(array $items): array
    {
        $stock_list = [];
        foreach ($items as $item) {
            $stock_list[] = [
                'item_id'      => $item['item_id'],
                'seller_stock' => [
                    ['location_id' => '', 'stock' => $item['quantity']],
                ],
            ];
        }

        return $this->post('/api/v2/product/update_stock', ['stock_list' => $stock_list]);
    }

    public function getChannelName(): string
    {
        return 'shopee';
    }

    public function isAvailable(): bool
    {
        return !empty($this->partner_id) && !empty($this->partner_key)
            && !empty($this->shop_id)    && !empty($this->access_token);
    }

    // ค้นหา item_id ของ Shopee จาก seller SKU
    public function getItemIdBySku(string $sku): ?int
    {
        $result = $this->get('/api/v2/product/get_item_list', [
            'offset'      => 0,
            'page_size'   => 100,
            'item_status' => 'NORMAL',
        ]);

        foreach ($result['response']['item'] ?? [] as $item) {
            if (($item['item_sku'] ?? '') === $sku) {
                return (int)$item['item_id'];
            }
        }
        return null;
    }

    // ---------------------------------------------------------------

    private function sign(string $path, int $timestamp): string
    {
        $base = $this->partner_id . $path . $timestamp . $this->access_token . $this->shop_id;
        return hash_hmac('sha256', $base, $this->partner_key);
    }

    private function buildQuery(string $path): string
    {
        $ts   = time();
        $sign = $this->sign($path, $ts);
        return http_build_query([
            'partner_id'   => $this->partner_id,
            'timestamp'    => $ts,
            'access_token' => $this->access_token,
            'shop_id'      => $this->shop_id,
            'sign'         => $sign,
        ]);
    }

    private function get(string $path, array $params = []): array
    {
        $url = self::BASE_URL . $path . '?' . $this->buildQuery($path) . '&' . http_build_query($params);
        return $this->request('GET', $url);
    }

    private function post(string $path, array $body): array
    {
        $url = self::BASE_URL . $path . '?' . $this->buildQuery($path);
        return $this->request('POST', $url, $body);
    }

    private function request(string $method, string $url, array $body = []): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_CUSTOMREQUEST  => $method,
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $response   = curl_exec($ch);
        $http_code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($curl_error) {
            throw new Exception('[Shopee] cURL: ' . $curl_error);
        }
        if ($http_code < 200 || $http_code >= 300) {
            throw new Exception('[Shopee] HTTP ' . $http_code . ': ' . $response);
        }

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('[Shopee] Invalid JSON');
        }
        if (!empty($decoded['error'])) {
            throw new Exception('[Shopee] API error: ' . $decoded['message'] . ' (' . $decoded['error'] . ')');
        }

        return $decoded;
    }
}
