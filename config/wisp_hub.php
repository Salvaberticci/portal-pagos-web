<?php
require_once __DIR__ . '/wisphub_credentials.php';
return [
    'api_key'    => WISP_HUB_API_KEY,
    'api_secret' => defined('WISP_HUB_API_SECRET') ? WISP_HUB_API_SECRET : WISP_HUB_API_KEY,
    'base_url'   => defined('WISP_HUB_BASE_URL')   ? WISP_HUB_BASE_URL   : 'https://sandbox-api.wisphub.net/api',
];
