<?php

require_once '../../includes/autoload.php';
require_once '../../includes/CSRF.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';
require_once '../../includes/config.php';
require_once '../../includes/authenticate.php';

header('Content-Type: application/json');

/**
 * Returns the list of active connected devices enriched with
 * wireless metrics and vendor info.
 */

$apInterface = $_SESSION['ap_interface'] ?? 'wlan0';

// --- Wireless station dump ---
$wirelessMetrics = getWirelessMetrics($apInterface);
$wirelessMacs    = array_keys($wirelessMetrics);

// --- Ethernet clients from ARP ---
$ethernetMacs = [];
$arpOut = shell_exec('ip neigh show 2>/dev/null');
if ($arpOut) {
    foreach (explode("\n", trim($arpOut)) as $line) {
        if (preg_match(
            '/^(\S+)\s+dev\s+(eth[0-9]+|en\w+)\s+lladdr\s+(\S+)\s+(REACHABLE|DELAY|PROBE)/',
            $line,
            $m
        )) {
            $ethernetMacs[] = strtoupper($m[3]);
        }
    }
}

// --- DHCP leases ---
$clients = [];
if (file_exists(RASPI_DNSMASQ_LEASES)) {
    $leaseLines = file(RASPI_DNSMASQ_LEASES, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($leaseLines as $line) {
        $f = preg_split('/\s+/', trim($line));
        if (count($f) < 5) {
            continue;
        }
        $mac    = strtoupper($f[1]);
        $client = [
            'timestamp'       => (int) $f[0],
            'mac_address'     => $f[1],
            'ip_address'      => $f[2],
            'hostname'        => $f[3] !== '*' ? $f[3] : '',
            'client_id'       => $f[4] !== '*' ? $f[4] : '',
            'vendor'          => getVendorName($mac),
        ];

        if (in_array($mac, $wirelessMacs, true)) {
            $client['connection_type'] = 'wireless';
            $m = $wirelessMetrics[$mac];
            if ($m['signal_dbm'] !== null)       $client['signal_dbm']       = $m['signal_dbm'];
            if ($m['connected_seconds'] !== null) $client['connected_seconds'] = $m['connected_seconds'];
            if ($m['inactive_ms'] !== null)       $client['inactive_ms']       = $m['inactive_ms'];
            $client['tx_bytes'] = $m['tx_bytes'];
            $client['rx_bytes'] = $m['rx_bytes'];
        } elseif (in_array($mac, $ethernetMacs, true)) {
            $client['connection_type'] = 'ethernet';
        } else {
            $client['connection_type'] = 'unknown';
        }

        $clients[] = $client;
    }
}

echo json_encode([
    'status'         => 'success',
    'active_clients' => $clients,
    'counts'         => [
        'total'    => count($clients),
        'wireless' => count(array_filter($clients, fn($c) => $c['connection_type'] === 'wireless')),
        'ethernet' => count(array_filter($clients, fn($c) => $c['connection_type'] === 'ethernet')),
    ],
]);

// ---------------------------------------------------------------------------

/**
 * Parse 'iw dev <interface> station dump' into a MAC-keyed metrics array.
 */
function getWirelessMetrics(string $interface): array
{
    $metrics = [];
    $cmd = 'iw dev ' . escapeshellarg($interface) . ' station dump 2>/dev/null';
    exec($cmd, $lines, $rc);
    if ($rc !== 0) {
        return $metrics;
    }

    $cur = null;
    foreach ($lines as $line) {
        if (preg_match('/^Station\s+([0-9a-f:]{17})\s+/i', $line, $m)) {
            $cur = strtoupper($m[1]);
            $metrics[$cur] = [
                'signal_dbm'       => null,
                'connected_seconds' => null,
                'inactive_ms'      => null,
                'tx_bytes'         => 0,
                'rx_bytes'         => 0,
            ];
            continue;
        }
        if ($cur === null) {
            continue;
        }
        $line = trim($line);
        if (preg_match('/^signal:\s*(-?\d+)/', $line, $m)) {
            $metrics[$cur]['signal_dbm'] = (int) $m[1];
        } elseif (preg_match('/^connected time:\s*(\d+)/', $line, $m)) {
            $metrics[$cur]['connected_seconds'] = (int) $m[1];
        } elseif (preg_match('/^inactive time:\s*(\d+)/', $line, $m)) {
            $metrics[$cur]['inactive_ms'] = (int) $m[1];
        } elseif (preg_match('/^tx bytes:\s*(\d+)/', $line, $m)) {
            $metrics[$cur]['tx_bytes'] = (int) $m[1];
        } elseif (preg_match('/^rx bytes:\s*(\d+)/', $line, $m)) {
            $metrics[$cur]['rx_bytes'] = (int) $m[1];
        }
    }
    return $metrics;
}

/**
 * Lightweight OUI vendor lookup from MAC prefix.
 */
function getVendorName(string $mac): string
{
    static $vendors = [
        '48:E2:30' => 'Raspberry Pi',
        'B8:27:EB' => 'Raspberry Pi',
        'DC:A6:32' => 'Raspberry Pi',
        'E4:5F:01' => 'Raspberry Pi',
        'D8:3A:DD' => 'Raspberry Pi',
        '00:17:F2' => 'Apple',
        '00:1B:63' => 'Apple',
        '00:1C:B3' => 'Apple',
        '00:1D:4F' => 'Apple',
        '00:1E:52' => 'Apple',
        '00:1F:F3' => 'Apple',
        '00:21:E9' => 'Apple',
        '00:23:12' => 'Apple',
        '00:23:32' => 'Apple',
        '00:24:36' => 'Apple',
        '00:25:00' => 'Apple',
        '00:25:BC' => 'Apple',
        '00:26:08' => 'Apple',
        '00:26:B0' => 'Apple',
        '04:0C:CE' => 'Apple',
        '04:15:52' => 'Apple',
        '04:1E:64' => 'Apple',
        '04:26:65' => 'Apple',
        '04:DB:56' => 'Apple',
        '04:F1:3E' => 'Apple',
        '58:1F:AA' => 'Apple',
        '5C:AA:BC' => 'Apple',
        '6C:96:CF' => 'Apple',
        'AC:BC:32' => 'Apple',
        'D8:5D:4C' => 'Apple',
        'F4:5C:89' => 'Apple',
        'FC:AA:14' => 'Apple',
        '2C:F0:5D' => 'Samsung',
        '44:F4:6D' => 'Samsung',
        '68:5B:35' => 'Samsung',
        'A4:12:69' => 'Samsung',
        '34:15:9C' => 'TP-Link',
        'A4:D1:D2' => 'TP-Link',
        'B0:35:9F' => 'TP-Link',
        'DC:0B:34' => 'TP-Link',
        '38:60:77' => 'Huawei',
        '78:31:F1' => 'Ubiquiti',
        '8C:FA:BA' => 'Asus',
        'C8:3A:35' => 'Asus',
        'D4:6E:0E' => 'Asus',
        '84:38:35' => 'Netgear',
        '50:E5:49' => 'Netgear',
        '90:18:87' => 'Netgear',
        'E8:9B:C5' => 'Netgear',
        'F8:B4:6A' => 'Netgear',
        '64:76:BA' => 'Intel',
        '7C:3B:E6' => 'Lenovo',
        'E4:95:6E' => 'Amazon',
        'E0:55:3D' => 'Sony',
        'BC:5C:F3' => 'Harman',
        'EC:1A:59' => 'LG',
        '44:F4:6D' => 'LG',
        'B8:8A:60' => 'LG',
    ];

    $prefix = strtoupper(substr($mac, 0, 8));
    return $vendors[$prefix] ?? 'Unknown';
}
