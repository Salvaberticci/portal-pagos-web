<?php
require_once __DIR__ . '/wisphub_credentials.php';
return [
    'api_key'      => WISP_HUB_API_KEY,
    'api_secret'   => defined('WISP_HUB_API_SECRET') ? WISP_HUB_API_SECRET : WISP_HUB_API_KEY,
    'base_url'     => defined('WISP_HUB_BASE_URL')   ? WISP_HUB_BASE_URL   : 'https://api.wisphub.net/api',
    'cron_secret'  => defined('WISP_HUB_CRON_SECRET') ? WISP_HUB_CRON_SECRET : '',
    'verify_ssl'   => defined('WISP_HUB_VERIFY_SSL') ? WISP_HUB_VERIFY_SSL : false,
    'account_ref'  => defined('WISP_HUB_ACTIVE_ACCOUNT') ? WISP_HUB_ACTIVE_ACCOUNT : 'sitelco',
];
