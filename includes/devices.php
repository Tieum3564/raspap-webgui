<?php

require_once 'includes/config.php';

/**
 * Network Devices page handler
 */
function DisplayDevicesConfig()
{
    $status = new \RaspAP\Messages\StatusMessage;
    
    // Pass initial data to template
    // Device list is fetched dynamically via AJAX polling
    $pollIntervalMs = 5000; // 5 second polling interval
    $pageTitle = _('Network Devices');
    
    echo renderTemplate(
        'devices',
        compact('status', 'pollIntervalMs', 'pageTitle')
    );
}
