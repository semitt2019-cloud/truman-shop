<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class ConnectorFactory
{
    public static function erp(): IErpConnector
    {
        $url = Configuration::get('MYERPCONNECT_API_URL');
        $key = Configuration::get('MYERPCONNECT_API_KEY');

        if ($url && $key) {
            return new ErpApiClient($url, $key);
        }

        return new ManualErpConnector();
    }

    public static function shopee(): IMarketplaceConnector
    {
        $partner_id  = Configuration::get('MYERPCONNECT_SHOPEE_PARTNER_ID');
        $partner_key = Configuration::get('MYERPCONNECT_SHOPEE_PARTNER_KEY');
        $shop_id     = Configuration::get('MYERPCONNECT_SHOPEE_SHOP_ID');
        $token       = Configuration::get('MYERPCONNECT_SHOPEE_ACCESS_TOKEN');

        if ($partner_id && $partner_key && $shop_id && $token) {
            return new ShopeeApiClient((int)$partner_id, $partner_key, (int)$shop_id, $token);
        }

        return new NullMarketplace('shopee');
    }

    public static function lazada(): IMarketplaceConnector
    {
        $app_key    = Configuration::get('MYERPCONNECT_LAZADA_APP_KEY');
        $app_secret = Configuration::get('MYERPCONNECT_LAZADA_APP_SECRET');
        $token      = Configuration::get('MYERPCONNECT_LAZADA_ACCESS_TOKEN');

        if ($app_key && $app_secret && $token) {
            return new LazadaApiClient($app_key, $app_secret, $token);
        }

        return new NullMarketplace('lazada');
    }

    public static function notification(): INotificationService
    {
        return new SmtpMailService();
    }
}
