<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

interface IErpConnector
{
    public function fetchProducts(int $page, int $limit = 100): array;
    public function fetchStockBulk(array $skus): array;
    public function deductStock(int $order_id, array $items): bool;
    public function isAvailable(): bool;
}
