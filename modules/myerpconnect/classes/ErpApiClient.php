<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class ErpApiClient implements IErpConnector
{
    private $api_url;
    private $api_key;
    private $timeout;

    public function __construct($api_url, $api_key, $timeout = 30)
    {
        $this->api_url = rtrim($api_url, '/');
        $this->api_key = $api_key;
        $this->timeout = $timeout;
    }

    // ดึงสินค้าทีละ batch — คืนค่า array ของสินค้าหรือ [] เมื่อหมด
    public function fetchProducts(int $page, int $limit = 100): array
    {
        $data = $this->get('/products', ['page' => $page, 'limit' => $limit]);
        return $data['products'] ?? $data ?? [];
    }

    // ดึงสต็อกทุก SKU ในครั้งเดียว (ถ้า ERP รองรับ bulk endpoint)
    public function fetchStockBulk(array $skus): array
    {
        $data = $this->post('/stock/query', ['skus' => $skus]);
        return $data['stocks'] ?? [];
    }

    // ส่งการตัดสต็อกหลัง order ชำระเงิน
    public function deductStock(int $order_id, array $items): bool
    {
        $this->post('/stock/deduct', [
            'order_id' => $order_id,
            'items'    => $items,
        ]);
        return true;
    }

    public function isAvailable(): bool
    {
        return !empty(Configuration::get('MYERPCONNECT_API_URL'))
            && !empty(Configuration::get('MYERPCONNECT_API_KEY'));
    }

    // ---------------------------------------------------------------

    private function get(string $path, array $params = []): array
    {
        $url = $this->api_url . $path;
        if ($params) {
            $url .= '?' . http_build_query($params);
        }
        return $this->request('GET', $url);
    }

    private function post(string $path, array $body): array
    {
        return $this->request('POST', $this->api_url . $path, $body);
    }

    private function request(string $method, string $url, array $body = []): array
    {
        $ch = curl_init($url);
        $headers = [
            'Authorization: Bearer ' . $this->api_key,
            'Accept: application/json',
            'Content-Type: application/json',
        ];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_CUSTOMREQUEST  => $method,
        ]);

        if ($method === 'POST' && $body) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $response   = curl_exec($ch);
        $http_code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($curl_error) {
            throw new Exception('[ERP] cURL error: ' . $curl_error);
        }
        if ($http_code < 200 || $http_code >= 300) {
            throw new Exception('[ERP] HTTP ' . $http_code . ': ' . $response);
        }

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('[ERP] Invalid JSON response');
        }

        return $decoded;
    }
}
