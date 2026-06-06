<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Lazada Open Platform API
 * Docs: https://open.lazada.com/apps/doc/api
 */
class LazadaApiClient
{
    const BASE_URL = 'https://api.lazada.co.th/rest';

    private $app_key;
    private $app_secret;
    private $access_token;

    public function __construct(string $app_key, string $app_secret, string $access_token)
    {
        $this->app_key      = $app_key;
        $this->app_secret   = $app_secret;
        $this->access_token = $access_token;
    }

    // อัปเดตราคาสินค้า
    // $items = [['sku_id' => '123', 'price' => 99.00, 'special_price' => 89.00], ...]
    public function updatePrice(array $items): array
    {
        $skus = [];
        foreach ($items as $item) {
            $sku = ['SkuId' => $item['sku_id'], 'Price' => number_format($item['price'], 2, '.', '')];
            if (!empty($item['special_price'])) {
                $sku['SalePrice']      = number_format($item['special_price'], 2, '.', '');
                $sku['SaleStartDate']  = $item['sale_start'] ?? date('Y-m-d H:i');
                $sku['SaleEndDate']    = $item['sale_end'] ?? date('Y-m-d H:i', strtotime('+30 days'));
            }
            $skus[] = $sku;
        }

        return $this->post('/products/price/update', ['skus' => json_encode($skus)]);
    }

    // อัปเดตสต็อก
    // $items = [['sku_id' => '123', 'quantity' => 50], ...]
    public function updateStock(array $items): array
    {
        $skus = array_map(fn($i) => [
            'SkuId'    => $i['sku_id'],
            'SellableQuantity' => $i['quantity'],
        ], $items);

        return $this->post('/products/stock/update', ['skus' => json_encode($skus)]);
    }

    // ดึงรายการสินค้าทั้งหมดจาก Lazada (พร้อม sku_id)
    public function getProducts(int $offset = 0, int $limit = 50): array
    {
        $result = $this->get('/products/get', [
            'filter' => 'all',
            'offset' => $offset,
            'limit'  => $limit,
        ]);
        return $result['data']['products'] ?? [];
    }

    // ---------------------------------------------------------------

    private function sign(string $api_path, array $params): string
    {
        ksort($params);
        $str = $api_path;
        foreach ($params as $k => $v) {
            $str .= $k . $v;
        }
        return strtoupper(hash_hmac('sha256', $str, $this->app_secret));
    }

    private function get(string $path, array $params = []): array
    {
        $params = array_merge($params, $this->baseParams($path));
        $params['sign'] = $this->sign($path, $params);

        $url = self::BASE_URL . $path . '?' . http_build_query($params);
        return $this->request('GET', $url);
    }

    private function post(string $path, array $body): array
    {
        $base = $this->baseParams($path);
        $sign_params = array_merge($base, $body);
        $base['sign'] = $this->sign($path, $sign_params);

        $url = self::BASE_URL . $path . '?' . http_build_query($base);
        return $this->request('POST', $url, $body);
    }

    private function baseParams(string $path): array
    {
        return [
            'app_key'      => $this->app_key,
            'timestamp'    => (string)(time() * 1000),
            'access_token' => $this->access_token,
            'sign_method'  => 'sha256',
        ];
    }

    private function request(string $method, string $url, array $body = []): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_CUSTOMREQUEST  => $method,
        ]);

        if ($method === 'POST' && $body) {
            curl_setopt_array($ch, [
                CURLOPT_POSTFIELDS => http_build_query($body),
                CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            ]);
        }

        $response   = curl_exec($ch);
        $http_code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($curl_error) {
            throw new Exception('[Lazada] cURL: ' . $curl_error);
        }
        if ($http_code < 200 || $http_code >= 300) {
            throw new Exception('[Lazada] HTTP ' . $http_code . ': ' . $response);
        }

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('[Lazada] Invalid JSON');
        }
        if (($decoded['code'] ?? '0') !== '0') {
            throw new Exception('[Lazada] API error: ' . ($decoded['message'] ?? '') . ' code=' . $decoded['code']);
        }

        return $decoded;
    }
}
