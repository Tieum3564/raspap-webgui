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
    // Try to load from persistent config first
    $persistentSettings = loadMobileClientSettingsFromDisk();
    if ($persistentSettings !== null) {
        // Also save to session for current request
        $_SESSION['mobileclient'] = $persistentSettings;
        return $persistentSettings;
    }

    // Fall back to session-only storage if no persistent config
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
    // Save to session immediately
    $_SESSION['mobileclient'] = $settings;

    // Try to persist to disk
    saveMobileClientSettingsToDisk($settings);
}

function getMobileClientConfigPath()
{
    return RASPI_CONFIG . '/mobileclient.json';
}

function loadMobileClientSettingsFromDisk()
{
    $configPath = getMobileClientConfigPath();

    if (!is_file($configPath) || !is_readable($configPath)) {
        return null;
    }

    $contents = @file_get_contents($configPath);
    if ($contents === false) {
        return null;
    }

    $data = @json_decode($contents, true);
    if (!is_array($data)) {
        return null;
    }

    return $data;
}

function saveMobileClientSettingsToDisk(array $settings)
{
    $configPath = getMobileClientConfigPath();
    $configDir = dirname($configPath);

    // Ensure directory exists with proper permissions
    if (!is_dir($configDir)) {
        @mkdir($configDir, 0755, true);
    }

    // Write settings as JSON with restricted permissions
    $jsonData = json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($jsonData === false) {
        error_log('Failed to encode mobile client settings as JSON');
        return false;
    }

    $tmpFile = $configPath . '.tmp';
    if (@file_put_contents($tmpFile, $jsonData, LOCK_EX) === false) {
        error_log('Failed to write temporary mobile client config: ' . $tmpFile);
        return false;
    }

    // Atomic move
    if (!@rename($tmpFile, $configPath)) {
        @unlink($tmpFile);
        error_log('Failed to finalize mobile client config: ' . $configPath);
        return false;
    }

    // Set restrictive permissions on config file (read/write for owner only)
    @chmod($configPath, 0600);

    return true;
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

function getMobileClientDeviceType($interface)
{
    // Detect device type based on interface naming conventions
    if (preg_match('/^hilink/i', $interface)) {
        return 'hilink';
    } elseif (preg_match('/^(enx|usb|rndis)/i', $interface)) {
        return 'usb_tethering';
    } elseif (preg_match('/^(wwan|ppp)/i', $interface)) {
        return 'modem';
    }

    return 'unknown';
}

function getMobileClientInterfaceLabel($interface)
{
    $type = getMobileClientDeviceType($interface);
    
    $labels = [
        'hilink' => 'Hilink Dongle',
        'usb_tethering' => 'USB Tethering',
        'modem' => 'Mobile Modem',
        'unknown' => 'Unknown'
    ];

    return ($labels[$type] ?? 'Mobile') . ' (' . htmlspecialchars($interface, ENT_QUOTES, 'UTF-8') . ')';
}

function executeMobileClientToggle(array $settings, $connect, $status)
{
    $deviceType = getMobileClientDeviceType($settings['device']);

    if ($deviceType === 'usb_tethering') {
        // USB tethering doesn't require explicit connection commands
        // It's automatically managed by the OS once plugged in
        if ($connect) {
            $status->addMessage(_('USB tethering is ready when device is connected'), 'info');
        } else {
            $status->addMessage(_('Disconnect the tethering device to stop'), 'info');
        }
        return [];
    }

    // Hilink dongle
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
    $deviceType = getMobileClientDeviceType($settings['device']);

    if ($deviceType === 'usb_tethering') {
        return getMobileClientInfoUSBTethering($settings['device']);
    } else {
        return getMobileClientInfoHilink($settings);
    }
}

function getMobileClientInfoUSBTethering($interface)
{
    $result = [
        'status' => 'down',
        'mode' => 'none',
        'signal' => 'N/A',
        'operator' => 'N/A',
        'ipaddress' => 'none',
        'device' => 'USB Tethering',
        'manufacturer' => 'Mobile Phone'
    ];

    // Check if interface is up
    exec("ip link show " . escapeshellarg($interface) . " 2>/dev/null", $linkOutput, $linkCode);
    if ($linkCode === 0 && !empty($linkOutput)) {
        // Check for IP address
        exec("ip addr show " . escapeshellarg($interface) . " 2>/dev/null | grep -oP '(?<=inet\\s)\\S+'", $addrOutput, $addrCode);
        if ($addrCode === 0 && !empty($addrOutput)) {
            $result['status'] = 'up';
            $result['ipaddress'] = trim($addrOutput[0]);
            $result['mode'] = 'USB Tethering';
        }
    }

    return $result;
}

function getMobileClientInfoHilink(array $settings)
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
