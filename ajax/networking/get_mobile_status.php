<?php

require_once '../../includes/autoload.php';
require_once '../../includes/CSRF.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';
require_once '../../includes/config.php';
require_once '../../includes/authenticate.php';
require_once '../../includes/mobileclient.php';

if (!RASPI_MOBILECLIENT_ENABLED) {
    echo json_encode(['status' => 'error', 'message' => 'Mobile client not enabled']);
    exit;
}

$settings = getMobileClientSettings();
$info = getMobileClientInfo($settings);

echo json_encode([
    'status' => 'success',
    'data' => $info
]);
