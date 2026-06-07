<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class NullMarketplace implements IMarketplaceConnector
{
    private string $channel;

    public function __construct(string $channel = 'none')
    {
        $this->channel = $channel;
    }

    public function getChannelName(): string
    {
        return $this->channel;
    }

    public function updatePrice(array $items): array
    {
        return [];
    }

    public function updateStock(array $items): array
    {
        return [];
    }

    public function isAvailable(): bool
    {
        return false;
    }
}
