/**
 * Network Devices AJAX polling and rendering module
 */

let devicesPollingInterval = null;

export function pollDevices() {
    $.ajax({
        url: 'ajax/networking/get_devices.php',
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response && response.status === 'success') {
                renderDevices(response.active_clients || []);
            } else {
                showDevicesError('Unable to fetch device data');
            }
        },
        error: function() {
            showDevicesError('Unable to fetch device data');
        }
    });
}

function renderDevices(clients) {
    const $container = $('#devicesList');
    const $empty     = $('#devicesEmpty');
    const $error     = $('#devicesError');
    const $loading   = $('#devicesLoading');

    $loading.hide();
    $error.hide();

    if (!clients || clients.length === 0) {
        $container.hide();
        $empty.show();
        return;
    }

    $empty.hide();
    $container.show();

    // Wireless first, then by hostname/MAC
    const sorted = clients.slice().sort((a, b) => {
        if (a.connection_type !== b.connection_type) {
            if (a.connection_type === 'wireless') return -1;
            if (b.connection_type === 'wireless') return 1;
        }
        return (a.hostname || a.mac_address || '').localeCompare(b.hostname || b.mac_address || '');
    });

    $container.html(sorted.map(buildDeviceCard).join(''));
}

function buildDeviceCard(client) {
    const vendor   = escapeHtml(client.vendor || 'Unknown');
    const type     = client.connection_type || 'unknown';
    const typeLabel = type === 'wireless' ? 'Wireless' : (type === 'ethernet' ? 'Wired' : 'Unknown');
    const hostname = escapeHtml(client.hostname || '-');
    const ip       = escapeHtml(client.ip_address || '-');
    const mac      = escapeHtml(client.mac_address || '-');

    let signalRow = '';
    if (type === 'wireless' && client.signal_dbm != null) {
        const bars = buildSignalBar(client.signal_dbm);
        signalRow = `
            <div class="device-detail-row">
                <span class="device-detail-label">Signal</span>
                <span class="device-detail-value">
                    <div class="device-signal">
                        ${bars}
                        <span>${client.signal_dbm} dBm</span>
                    </div>
                </span>
            </div>`;
    }

    let durationRow = '';
    if (type === 'wireless' && client.connected_seconds != null) {
        const dur = formatDuration(client.connected_seconds);
        durationRow = `
            <div class="device-detail-row">
                <span class="device-detail-label">Connected</span>
                <span class="device-detail-value">${dur}</span>
            </div>`;
    }

    return `
        <div class="device-card" data-mac="${mac}">
            <div class="device-header">
                <div>
                    <p class="device-vendor">${vendor}</p>
                    <span class="device-type-badge ${type}">${typeLabel}</span>
                </div>
                <div class="device-status">
                    <i class="fas fa-check-circle"></i> Connected
                </div>
            </div>
            <div class="device-details">
                <div class="device-detail-row">
                    <span class="device-detail-label">Hostname</span>
                    <span class="device-detail-value">${hostname}</span>
                </div>
                <div class="device-detail-row">
                    <span class="device-detail-label">IP Address</span>
                    <span class="device-detail-value">${ip}</span>
                </div>
                <div class="device-detail-row">
                    <span class="device-detail-label">MAC Address</span>
                    <span class="device-detail-value font-monospace" style="font-size:0.8rem;">${mac}</span>
                </div>
                ${signalRow}
                ${durationRow}
            </div>
        </div>`;
}

function buildSignalBar(dbm) {
    const quality    = Math.max(0, Math.min(100, 2 * (dbm + 100)));
    const numBars    = 5;
    const activeBars = Math.ceil((quality / 100) * numBars);
    const colorClass = quality >= 75 ? 'active' : (quality >= 50 ? 'warn' : 'crit');

    const heights = [25, 40, 55, 70, 85]; // % height per segment
    let html = '<div class="signal-bar">';
    for (let i = 1; i <= numBars; i++) {
        const cls = i <= activeBars ? `signal-bar-segment ${colorClass}` : 'signal-bar-segment';
        html += `<div class="${cls}" style="height:${heights[i-1]}%;align-self:flex-end;"></div>`;
    }
    html += '</div>';
    return html;
}

function formatDuration(seconds) {
    if (seconds < 60)   return seconds + 's';
    if (seconds < 3600) return Math.floor(seconds / 60) + 'm';
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    return h + 'h ' + m + 'm';
}

function escapeHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function showDevicesError(message) {
    $('#devicesLoading').hide();
    $('#devicesList').hide();
    $('#devicesEmpty').hide();
    $('#devicesError').show();
    $('#devicesErrorText').text(message);
}

export function initDevicesAjax() {
    console.info("RaspAP Devices AJAX module initialized");

    const interval = window.DEVICES_POLL_INTERVAL || 5000;

    $('#devicesLoading').show();
    pollDevices();

    if (devicesPollingInterval) clearInterval(devicesPollingInterval);
    devicesPollingInterval = setInterval(pollDevices, interval);

    $('#devicesRefreshBtn').on('click', function() {
        const $icon = $(this).find('i');
        $icon.addClass('fa-spin');
        pollDevices();
        setTimeout(() => $icon.removeClass('fa-spin'), 400);
    });
}

$(window).on('beforeunload', function() {
    if (devicesPollingInterval) clearInterval(devicesPollingInterval);
});
