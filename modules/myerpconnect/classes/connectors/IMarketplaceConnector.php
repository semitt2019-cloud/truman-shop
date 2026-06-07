<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

interface IMarketplaceConnector
{
    public function getChannelName(): string;
    public function updatePrice(array $items): array;
    public function updateStock(array $items): array;
    public function isAvailable(): bool;
}
