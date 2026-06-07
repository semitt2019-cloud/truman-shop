<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class ManualErpConnector implements IErpConnector
{
    public function fetchProducts(int $page, int $limit = 100): array
    {
        return [];
    }

    public function fetchStockBulk(array $skus): array
    {
        return [];
    }

    public function deductStock(int $order_id, array $items): bool
    {
        return true;
    }

    public function isAvailable(): bool
    {
        return false;
    }
}
