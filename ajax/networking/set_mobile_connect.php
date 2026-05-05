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

if (!isset($_POST['action']) || !in_array($_POST['action'], ['connect', 'disconnect'], true)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    exit;
}

if (RASPI_MONITOR_ENABLED) {
    echo json_encode(['status' => 'error', 'message' => 'Monitor mode is enabled']);
    exit;
}

$settings = getMobileClientSettings();
$connect = ($_POST['action'] === 'connect') ? 1 : 0;

$status = new \RaspAP\Messages\StatusMessage;
$actionLog = executeMobileClientToggle($settings, $connect, $status);

echo json_encode([
    'status' => 'success',
    'action' => $_POST['action'],
    'log' => $actionLog
]);
