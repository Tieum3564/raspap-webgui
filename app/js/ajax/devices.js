/**
 * Network Devices AJAX polling and rendering module
 */

let devicesPollingInterval = null;
let devicesLastData = null;

export function pollDevices() {
    const url = '/api/clients';
    
    $.ajax({
        url: url,
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response && response.active_clients) {
                updateDevicesUI(response.active_clients);
                devicesLastData = response;
            }
        },
        error: function(xhr, status, error) {
            console.warn("Devices poll error:", error);
            showDevicesError("<?php echo _('Unable to fetch device data'); ?>");
        }
    });
}

function updateDevicesUI(clients) {
    const $container = $('#devicesList');
    const $empty = $('#devicesEmpty');
    const $error = $('#devicesError');
    const $loading = $('#devicesLoading');
    
    // Hide loading and error states
    $loading.hide();
    $error.hide();
    
    if (!clients || clients.length === 0) {
        $container.hide();
        $empty.show();
        return;
    }
    
    // Show devices container
    $empty.hide();
    $container.show();
    
    // Sort clients: wireless first, then ethernet, then by hostname/MAC
    const sorted = clients.sort((a, b) => {
        if (a.connection_type !== b.connection_type) {
            if (a.connection_type === 'wireless') return -1;
            if (b.connection_type === 'wireless') return 1;
        }
        return (a.hostname || a.mac_address || '').localeCompare(b.hostname || b.mac_address || '');
    });
    
    // Build HTML for each device
    let html = '';
    sorted.forEach(client => {
        html += buildDeviceCard(client);
    });
    
    $container.html(html);
}

function buildDeviceCard(client) {
    const vendor = client.vendor || 'Unknown';
    const type = client.connection_type || 'unknown';
    const typeLabel = type === 'wireless' ? '<?php echo _("Wireless"); ?>' : '<?php echo _("Wired"); ?>';
    const hostname = client.hostname || '-';
    const ip = client.ip_address || '-';
    const mac = client.mac_address || '-';
    
    // Calculate connection duration
    const duration = formatConnectionDuration(client.timestamp);
    
    // Build signal bar for wireless
    let signalHtml = '';
    if (type === 'wireless' && client.signal_dbm) {
        signalHtml = buildSignalBar(client.signal_dbm);
    }
    
    return `
        <div class="device-card" data-mac="${escapeHtml(mac)}">
            <div class="device-header">
                <div>
                    <p class="device-vendor">${escapeHtml(vendor)}</p>
                    <span class="device-type-badge ${type}">${typeLabel}</span>
                </div>
                <div class="device-status">
                    <i class="fas fa-check-circle"></i>
                    <?php echo _('Connected'); ?>
                </div>
            </div>
            <div class="device-details">
                <div class="device-detail-row">
                    <span class="device-detail-label"><?php echo _('Hostname'); ?></span>
                    <span class="device-detail-value">${escapeHtml(hostname)}</span>
                </div>
                <div class="device-detail-row">
                    <span class="device-detail-label"><?php echo _('IP Address'); ?></span>
                    <span class="device-detail-value">${escapeHtml(ip)}</span>
                </div>
                <div class="device-detail-row">
                    <span class="device-detail-label"><?php echo _('MAC Address'); ?></span>
                    <span class="device-detail-value font-monospace" style="font-size: 0.8rem;">${escapeHtml(mac)}</span>
                </div>
                ${type === 'wireless' && client.signal_dbm ? `
                    <div class="device-detail-row">
                        <span class="device-detail-label"><?php echo _('Signal'); ?></span>
                        <span class="device-detail-value">
                            <div class="device-signal">
                                ${signalHtml}
                                <span>${client.signal_dbm} dBm</span>
                            </div>
                        </span>
                    </div>
                ` : ''}
                ${duration ? `
                    <div class="device-detail-row">
                        <span class="device-detail-label"><?php echo _('Connected'); ?></span>
                        <span class="device-detail-value">${duration}</span>
                    </div>
                ` : ''}
            </div>
        </div>
    `;
}

function buildSignalBar(signalDbm) {
    // Convert dBm to quality percentage (-30dBm = 100%, -90dBm = 0%)
    const quality = Math.max(0, Math.min(100, 2 * (signalDbm + 100)));
    const numBars = 5;
    const activeBars = Math.ceil((quality / 100) * numBars);
    
    let bars = '<div class="signal-bar">';
    for (let i = 1; i <= numBars; i++) {
        let cssClass = 'signal-bar-segment';
        if (i <= activeBars) {
            if (quality >= 75) {
                cssClass += ' active';
            } else if (quality >= 50) {
                cssClass += ' warn';
            } else {
                cssClass += ' crit';
            }
        }
        bars += `<div class="${cssClass}"></div>`;
    }
    bars += '</div>';
    
    return bars;
}

function formatConnectionDuration(timestamp) {
    if (!timestamp) return '';
    
    // Calculate seconds since lease expiry (approximate connection time)
    const now = Math.floor(Date.now() / 1000);
    const seconds = Math.max(0, timestamp - now);
    
    if (seconds <= 0) return '< 1 min';
    
    if (seconds < 60) {
        return seconds + ' sec';
    } else if (seconds < 3600) {
        const mins = Math.floor(seconds / 60);
        return mins + ' min';
    } else if (seconds < 86400) {
        const hours = Math.floor(seconds / 3600);
        const mins = Math.floor((seconds % 3600) / 60);
        return hours + 'h ' + mins + 'm';
    } else {
        const days = Math.floor(seconds / 86400);
        return days + 'd';
    }
}

function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}

function showDevicesError(message) {
    $('#devicesError').show();
    $('#devicesErrorText').text(message);
    $('#devicesList').hide();
    $('#devicesEmpty').hide();
    $('#devicesLoading').hide();
}

export function initDevicesAjax() {
    console.info("RaspAP Devices AJAX module initialized");
    
    // Get polling interval from page
    const interval = window.DEVICES_POLL_INTERVAL || 5000;
    
    // Initial poll
    $('#devicesLoading').show();
    pollDevices();
    
    // Set up polling interval
    if (devicesPollingInterval) {
        clearInterval(devicesPollingInterval);
    }
    devicesPollingInterval = setInterval(pollDevices, interval);
    
    // Handle manual refresh button
    $('#devicesRefreshBtn').on('click', function() {
        $(this).find('i').addClass('fa-spin');
        pollDevices();
        setTimeout(() => {
            $(this).find('i').removeClass('fa-spin');
        }, 300);
    });
}

// Cleanup polling on page unload
$(window).on('beforeunload', function() {
    if (devicesPollingInterval) {
        clearInterval(devicesPollingInterval);
    }
});
