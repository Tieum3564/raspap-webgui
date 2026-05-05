import { getCSRFToken } from "../helpers.js";

let mobileStatusInterval;
const statusPollInterval = 3000; // 3 seconds
const statusFieldMapping = {
    'mode': '#mobileMode',
    'signal': '#mobileSignal',
    'operator': '#mobileOperator',
    'ipaddress': '#mobileIpAddress',
    'device': '#mobileDevice',
    'manufacturer': '#mobileManufacturer',
    'status': '.service-status-indicator'
};

export function pollMobileStatus() {
    const url = 'ajax/networking/get_mobile_status.php';
    
    $.ajax({
        url: url,
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success' && response.data) {
                updateMobileStatusUI(response.data);
            }
        },
        error: function(xhr, status, error) {
            console.warn("Mobile status poll error:", error);
        }
    });
}

function updateMobileStatusUI(data) {
    // Update status indicator circle
    const statusIcon = $('.service-status-indicator i');
    const statusText = $('.service-status span.text');
    if (statusIcon.length) {
        statusIcon.removeClass('service-status-up service-status-down');
        statusIcon.addClass(data.status === 'up' ? 'service-status-up' : 'service-status-down');
    }
    if (statusText.length) {
        statusText.text('hilink ' + (data.status === 'up' ? 'active' : 'inactive'));
    }

    // Update status tab fields
    Object.entries(statusFieldMapping).forEach(function([key, selector]) {
        if (key !== 'status' && data[key] !== undefined) {
            const $element = $(selector);
            if ($element.length) {
                // If it's a table cell, update the adjacent <td>
                if ($element.is('th')) {
                    $element.next('td').text(data[key]);
                } else {
                    $element.text(data[key]);
                }
            }
        }
    });

    // Update connection button state
    updateMobileActionButtons(data.status);
}

function updateMobileActionButtons(status) {
    const connectBtn = $('input[name="StartMobileClient"]');
    const disconnectBtn = $('input[name="StopMobileClient"]');

    if (status === 'up') {
        connectBtn.hide();
        disconnectBtn.show();
    } else {
        connectBtn.show();
        disconnectBtn.hide();
    }
}

export function initMobileConnect() {
    const $form = $('form[action="mobileclient_conf"]');
    const $deviceSelect = $('#cbxMobileDevice');
    const $hilinkSettings = $('#hilinkSettings');
    
    // Handle Connect button
    $form.on('click', 'input[name="StartMobileClient"]', function(e) {
        e.preventDefault();
        executeMobileAction('connect');
    });

    // Handle Disconnect button
    $form.on('click', 'input[name="StopMobileClient"]', function(e) {
        e.preventDefault();
        executeMobileAction('disconnect');
    });

    // Show/hide Hilink settings based on selected device type
    $deviceSelect.on('change', function() {
        const selectedInterface = $(this).val();
        const deviceType = getDeviceType(selectedInterface);
        
        if (deviceType === 'hilink') {
            $hilinkSettings.show();
        } else {
            $hilinkSettings.hide();
        }
    });

    // Initialize visibility on page load
    const initialDevice = $deviceSelect.val();
    const initialType = getDeviceType(initialDevice);
    if (initialType === 'hilink') {
        $hilinkSettings.show();
    } else {
        $hilinkSettings.hide();
    }
}

function getDeviceType(interfaceName) {
    // Match the PHP logic in getMobileClientDeviceType()
    if (/^hilink/i.test(interfaceName)) {
        return 'hilink';
    } else if (/^(enx|usb|rndis)/i.test(interfaceName)) {
        return 'usb_tethering';
    } else if (/^(wwan|ppp)/i.test(interfaceName)) {
        return 'modem';
    }
    return 'unknown';
}

function executeMobileAction(action) {
    const url = 'ajax/networking/set_mobile_connect.php';
    const csrfToken = getCSRFToken();

    const $statusArea = $('form[action="mobileclient_conf"] .card-body > div');
    const $logTab = $('#mobileLogs pre');

    // Show loading state
    $logTab.text('Executing ' + action + '...');

    $.ajax({
        url: url,
        type: 'POST',
        data: {
            'action': action,
            'csrf_token': csrfToken
        },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                if (response.log && response.log.length) {
                    $logTab.text(response.log.join('\n'));
                }
                
                // Display messages if any
                if (response.messages && response.messages.length) {
                    const messageHtml = response.messages.map(function(msg) {
                        const alertClass = msg.type === 'success' ? 'alert-success' : 'alert-danger';
                        return '<div class="alert ' + alertClass + ' alert-dismissible fade show">' +
                               msg.message +
                               '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
                    }).join('');
                    $statusArea.before(messageHtml);
                }

                // Force immediate status poll to update UI
                pollMobileStatus();
                
                // Resume polling
                if (mobileStatusInterval) {
                    clearInterval(mobileStatusInterval);
                }
                mobileStatusInterval = setInterval(pollMobileStatus, statusPollInterval);
            } else {
                $logTab.text('Error: ' + (response.message || 'Unknown error'));
            }
        },
        error: function(xhr, status, error) {
            console.error("Mobile action error:", error);
            $logTab.text('Error: ' + error);
        }
    });
}

export function initMobileClient_ajax() {
    console.info("RaspAP Mobile Client AJAX module initialized");
    
    if (!RASPI_MOBILECLIENT_ENABLED) {
        return;
    }

    // Initialize button handlers
    initMobileConnect();

    // Start polling for status updates
    mobileStatusInterval = setInterval(pollMobileStatus, statusPollInterval);
    
    // Do an immediate poll when page loads
    pollMobileStatus();
}
