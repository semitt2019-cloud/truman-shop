<?php
/**
 * Cron Job — รันซิงค์ทุก channel จาก ERP
 *
 * ตั้ง cron บน server:
 *   0 2 * * * php /path/to/prestashop/modules/myerpconnect/cron/sync_all.php >> /var/log/erp_sync.log 2>&1
 *
 * หรือเรียกผ่าน URL (ต้องใส่ token):
 *   https://truman.co.th/modules/myerpconnect/cron/sync_all.php?token=YOUR_CRON_TOKEN
 */

// ป้องกันการเรียกโดยไม่มีสิทธิ์
define('ALLOWED_CRON_TOKEN', getenv('MYERP_CRON_TOKEN') ?: 'CHANGE_THIS_TOKEN');

$is_cli = PHP_SAPI === 'cli';
if (!$is_cli) {
    $token = $_GET['token'] ?? '';
    if (!hash_equals(ALLOWED_CRON_TOKEN, $token)) {
        http_response_code(403);
        exit('Forbidden');
    }
}

// Bootstrap PrestaShop
$prestashop_root = dirname(__DIR__, 3); // /modules/myerpconnect → root
require_once $prestashop_root . '/config/config.inc.php';
require_once $prestashop_root . '/init.php';
require_once dirname(__DIR__) . '/classes/ErpApiClient.php';
require_once dirname(__DIR__) . '/classes/ShopeeApiClient.php';
require_once dirname(__DIR__) . '/classes/LazadaApiClient.php';
require_once dirname(__DIR__) . '/classes/MultiChannelSyncer.php';

$start = microtime(true);
echo '[' . date('Y-m-d H:i:s') . '] ERP Multi-Channel Sync started' . PHP_EOL;

try {
    $erp = new ErpApiClient(
        Configuration::get('MYERPCONNECT_API_URL'),
        Configuration::get('MYERPCONNECT_API_KEY')
    );

    $shopee = new ShopeeApiClient(
        (int)Configuration::get('MYERPCONNECT_SHOPEE_PARTNER_ID'),
        Configuration::get('MYERPCONNECT_SHOPEE_PARTNER_KEY'),
        (int)Configuration::get('MYERPCONNECT_SHOPEE_SHOP_ID'),
        Configuration::get('MYERPCONNECT_SHOPEE_ACCESS_TOKEN')
    );

    $lazada = new LazadaApiClient(
        Configuration::get('MYERPCONNECT_LAZADA_APP_KEY'),
        Configuration::get('MYERPCONNECT_LAZADA_APP_SECRET'),
        Configuration::get('MYERPCONNECT_LAZADA_ACCESS_TOKEN')
    );

    $retail_factor    = (float)(Configuration::get('MYERPCONNECT_RETAIL_FACTOR')    ?: 1.20);
    $wholesale_factor = (float)(Configuration::get('MYERPCONNECT_WHOLESALE_FACTOR') ?: 1.05);

    $syncer = new MultiChannelSyncer($erp, $shopee, $lazada, $retail_factor, $wholesale_factor);
    $stats  = $syncer->runFullSync();

    $elapsed = round(microtime(true) - $start, 2);
    echo '[' . date('Y-m-d H:i:s') . '] Done in ' . $elapsed . 's' . PHP_EOL;
    echo 'PrestaShop : updated=' . $stats['prestashop']['updated'] . ' errors=' . $stats['prestashop']['errors'] . PHP_EOL;
    echo 'Shopee     : updated=' . $stats['shopee']['updated']     . ' errors=' . $stats['shopee']['errors']     . PHP_EOL;
    echo 'Lazada     : updated=' . $stats['lazada']['updated']     . ' errors=' . $stats['lazada']['errors']     . PHP_EOL;

} catch (Throwable $e) {
    echo '[ERROR] ' . $e->getMessage() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
    exit(1);
}
