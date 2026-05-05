<?php

require_once 'includes/config.php';

/**
 * Mobile data configuration page handler.
 */
function DisplayMobileClientConfig()
{
    $status = new \RaspAP\Messages\StatusMessage;
    $settings = getMobileClientSettings();
    $actionLog = [];

    if (!RASPI_MONITOR_ENABLED) {
        if (isset($_POST['SaveMobileClientSettings'])) {
            $settings['device'] = normalizeMobileClientDevice($_POST['device'] ?? $settings['device']);
            $settings['host'] = normalizeMobileClientHost($_POST['hilink_host'] ?? $settings['host']);
            $settings['username'] = trim($_POST['username'] ?? '');
            $settings['password'] = trim($_POST['password'] ?? '');
            $settings['pin'] = preg_replace('/[^0-9]/', '', $_POST['pin'] ?? '');

            saveMobileClientSettings($settings);
            $status->addMessage(_('Mobile data settings saved'), 'success');
        } elseif (isset($_POST['StartMobileClient'])) {
            $actionLog = executeMobileClientToggle($settings, true, $status);
        } elseif (isset($_POST['StopMobileClient'])) {
            $actionLog = executeMobileClientToggle($settings, false, $status);
        }
    }

    $interfaces = getMobileClientInterfaces();
    if (!in_array($settings['device'], $interfaces, true)) {
        array_unshift($interfaces, $settings['device']);
    }

    $mobileInfo = getMobileClientInfo($settings);
    $serviceStatus = $mobileInfo['status'];
    $statusDisplay = $serviceStatus === 'up' ? _('active') : _('inactive');

    echo renderTemplate(
        'mobileclient',
        compact('status', 'settings', 'interfaces', 'mobileInfo', 'serviceStatus', 'statusDisplay', 'actionLog')
    );
}

function getMobileClientSettings()
{
    if (!isset($_SESSION['mobileclient']) || !is_array($_SESSION['mobileclient'])) {
        $_SESSION['mobileclient'] = [
            'device' => getMobileClientDefaultDevice(),
            'host' => RASPI_MOBILEDATA_HILINK_HOST,
            'username' => '',
            'password' => '',
            'pin' => ''
        ];
    }

    return $_SESSION['mobileclient'];
}

function saveMobileClientSettings(array $settings)
{
    $_SESSION['mobileclient'] = $settings;
}

function getMobileClientDefaultDevice()
{
    $interfaces = getMobileClientInterfaces();
    foreach ($interfaces as $iface) {
        if (preg_match('/^hilink/i', $iface)) {
            return $iface;
        }
    }

    return $interfaces[0] ?? 'hilink0';
}

function normalizeMobileClientDevice($device)
{
    $device = trim((string) $device);
    if (!preg_match('/^[a-zA-Z0-9._:-]+$/', $device)) {
        return getMobileClientDefaultDevice();
    }

    return $device;
}

function normalizeMobileClientHost($host)
{
    $host = trim((string) $host);
    if ($host === '') {
        return RASPI_MOBILEDATA_HILINK_HOST;
    }

    if (filter_var($host, FILTER_VALIDATE_IP) || preg_match('/^[a-zA-Z0-9.-]+$/', $host)) {
        return $host;
    }

    return RASPI_MOBILEDATA_HILINK_HOST;
}

function getMobileClientInterfaces()
{
    exec("ip -o link show | awk -F': ' '{print $2}'", $output);

    $interfaces = [];
    foreach ($output as $line) {
        $iface = trim($line);
        if ($iface === '' || $iface === 'lo') {
            continue;
        }

        if (preg_match('/^(hilink|usb|enx|wwan|rndis|ppp)/i', $iface)) {
            $interfaces[] = $iface;
        }
    }

    $interfaces = array_values(array_unique($interfaces));
    sort($interfaces);
    return $interfaces;
}

function executeMobileClientToggle(array $settings, $connect, $status)
{
    $script = resolveMobileClientScript('onoff_huawei_hilink.sh');
    if ($script === null) {
        $status->addMessage(_('Unable to find onoff_huawei_hilink.sh on this system'), 'danger');
        return [];
    }

    $parts = [
        'sudo',
        escapeshellarg($script),
        '-c',
        (int) $connect,
        '-d',
        escapeshellarg($settings['device'])
    ];

    if ($settings['host'] !== '') {
        $parts[] = '-h';
        $parts[] = escapeshellarg($settings['host']);
    }
    if ($settings['username'] !== '') {
        $parts[] = '-u';
        $parts[] = escapeshellarg($settings['username']);
    }
    if ($settings['password'] !== '') {
        $parts[] = '-P';
        $parts[] = escapeshellarg($settings['password']);
    }
    if ($settings['pin'] !== '') {
        $parts[] = '-p';
        $parts[] = escapeshellarg($settings['pin']);
    }

    $command = implode(' ', $parts);
    exec($command . ' 2>&1', $output, $returnCode);

    if ($returnCode === 0) {
        $status->addMessage($connect ? _('Mobile data connection requested') : _('Mobile data disconnect requested'), 'success');
    } else {
        $status->addMessage(_('Unable to change mobile data state. Check permissions and device state.'), 'danger');
    }

    return sanitizeMobileClientOutput($output);
}

function getMobileClientInfo(array $settings)
{
    $fields = ['mode', 'signal', 'operator', 'ipaddress', 'device', 'manufacturer'];
    $result = [
        'status' => 'down',
        'mode' => 'none',
        'signal' => 'none',
        'operator' => 'none',
        'ipaddress' => 'none',
        'device' => 'none',
        'manufacturer' => 'none'
    ];

    foreach ($fields as $field) {
        $value = queryMobileClientInfoField($field, $settings['host']);
        if ($value !== '') {
            $result[$field] = $value;
        }
    }

    if (strcasecmp($result['mode'], 'none') !== 0) {
        $result['status'] = 'up';
    }

    return $result;
}

function queryMobileClientInfoField($field, $host)
{
    $script = resolveMobileClientScript('info_huawei.sh');
    if ($script === null) {
        return '';
    }

    $command = implode(' ', [
        'bash',
        escapeshellarg($script),
        escapeshellarg($field),
        'hilink',
        escapeshellarg($host)
    ]);

    exec($command . ' 2>/dev/null', $output, $returnCode);
    if ($returnCode !== 0 || empty($output)) {
        return '';
    }

    return trim(implode(' ', $output));
}

function resolveMobileClientScript($fileName)
{
    $candidates = [
        '/usr/local/sbin/' . $fileName,
        __DIR__ . '/../config/client_config/' . $fileName
    ];

    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            return $candidate;
        }
    }

    return null;
}

function sanitizeMobileClientOutput(array $output)
{
    $sanitized = [];
    foreach ($output as $line) {
        $line = preg_replace('/\x1b\[[0-9;]*[A-Za-z]/', '', (string) $line);
        $line = trim($line);
        if ($line !== '') {
            $sanitized[] = htmlspecialchars($line, ENT_QUOTES, 'UTF-8');
        }
    }

    return $sanitized;
}
