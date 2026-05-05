<?php ob_start() ?>
  <?php if (!RASPI_MONITOR_ENABLED) : ?>
  <input type="submit" class="btn btn-outline-primary" name="SaveMobileClientSettings" value="<?php echo _('Save settings'); ?>" />
    <?php if ($serviceStatus === 'down') : ?>
    <input type="button" class="btn btn-success" name="StartMobileClient" value="<?php echo _('Connect'); ?>" />
    <?php else : ?>
    <input type="button" class="btn btn-warning" name="StopMobileClient" value="<?php echo _('Disconnect'); ?>" style="display: none;" />
    <?php endif; ?>
  <?php endif ?>
<?php $buttons = ob_get_clean(); ob_end_clean() ?>

<div class="row">
  <div class="col-lg-12">
    <div class="card shadow">
      <div class="card-header page-card-header">
        <div class="row align-items-center">
          <div class="col">
            <i class="fas fa-mobile-alt me-2"></i><?php echo _('Mobile data'); ?>
          </div>
          <div class="col">
            <button class="btn btn-light btn-icon-split btn-sm service-status float-end" type="button" disabled>
              <span class="icon text-gray-600"><i class="fas fa-circle service-status-indicator service-status-<?php echo $serviceStatus; ?>"></i></span>
              <span class="text service-status">hilink <?php echo strtolower($statusDisplay); ?></span>
            </button>
          </div>
        </div>
      </div>
      <div class="card-body">
        <?php $status->showMessages(); ?>
        <form role="form" action="mobileclient_conf" enctype="multipart/form-data" method="POST">
          <?php echo \RaspAP\Tokens\CSRF::hiddenField(); ?>

          <div class="nav-tabs-wrapper">
            <ul class="nav nav-tabs">
              <li class="nav-item"><a class="nav-link active" href="#mobileDevice" data-bs-toggle="tab"><?php echo _('Device'); ?></a></li>
              <li class="nav-item"><a class="nav-link" href="#mobileConnection" data-bs-toggle="tab"><?php echo _('Connection'); ?></a></li>
              <li class="nav-item"><a class="nav-link" href="#mobileStatus" data-bs-toggle="tab"><?php echo _('Status'); ?></a></li>
              <li class="nav-item"><a class="nav-link" href="#mobileLogs" data-bs-toggle="tab"><?php echo _('Logs'); ?></a></li>
            </ul>
          </div>

          <div class="tab-content p-0">
            <div class="tab-pane active" id="mobileDevice">
              <h4 class="mt-3"><?php echo _('Device'); ?></h4>
              <div class="mb-3">
                <label for="cbxMobileDevice"><?php echo _('Mobile interface'); ?></label>
                <select class="form-select" id="cbxMobileDevice" name="device">
                  <?php foreach ($interfaces as $iface) : ?>
                    <option value="<?php echo htmlspecialchars($iface, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $iface === $settings['device'] ? 'selected' : ''; ?>>
                      <?php echo getMobileClientInterfaceLabel($iface); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <small class="text-muted d-block mt-2"><?php echo _('Select the network interface providing mobile connectivity'); ?></small>
              </div>
              <div class="mb-3" id="hilinkSettings">
                <label for="txtHilinkHost"><?php echo _('Hilink host address'); ?></label>
                <input type="text" class="form-control" id="txtHilinkHost" name="hilink_host" value="<?php echo htmlspecialchars($settings['host'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="192.168.8.1" />
                <small class="text-muted d-block mt-2"><?php echo _('IP address of the Hilink device (usually 192.168.8.1 or 192.168.100.1)'); ?></small>
              </div>
            </div>

            <div class="tab-pane" id="mobileConnection">
              <h4 class="mt-3"><?php echo _('Connection'); ?></h4>
              <div class="mb-3">
                <label for="txtHilinkUser"><?php echo _('Username (optional)'); ?></label>
                <input type="text" class="form-control" id="txtHilinkUser" name="username" value="<?php echo htmlspecialchars($settings['username'], ENT_QUOTES, 'UTF-8'); ?>" autocomplete="username" />
              </div>
              <div class="mb-3">
                <label for="txtHilinkPassword"><?php echo _('Password (optional)'); ?></label>
                <input type="password" class="form-control" id="txtHilinkPassword" name="password" value="<?php echo htmlspecialchars($settings['password'], ENT_QUOTES, 'UTF-8'); ?>" autocomplete="current-password" />
              </div>
              <div class="mb-3">
                <label for="txtHilinkPin"><?php echo _('SIM PIN (optional)'); ?></label>
                <input type="text" class="form-control" id="txtHilinkPin" name="pin" value="<?php echo htmlspecialchars($settings['pin'], ENT_QUOTES, 'UTF-8'); ?>" pattern="[0-9]{4,8}" maxlength="8" />
              </div>
            </div>

            <div class="tab-pane" id="mobileStatus">
              <h4 class="mt-3"><?php echo _('Status'); ?></h4>
              <table class="table table-sm table-borderless w-auto">
                <tbody>
                  <tr><th scope="row"><?php echo _('Manufacturer'); ?></th><td id="mobileManufacturer"><?php echo htmlspecialchars($mobileInfo['manufacturer'], ENT_QUOTES, 'UTF-8'); ?></td></tr>
                  <tr><th scope="row"><?php echo _('Device'); ?></th><td id="mobileDevice"><?php echo htmlspecialchars($mobileInfo['device'], ENT_QUOTES, 'UTF-8'); ?></td></tr>
                  <tr><th scope="row"><?php echo _('Connection mode'); ?></th><td id="mobileMode"><?php echo htmlspecialchars($mobileInfo['mode'], ENT_QUOTES, 'UTF-8'); ?></td></tr>
                  <tr><th scope="row"><?php echo _('Signal quality'); ?></th><td id="mobileSignal"><?php echo htmlspecialchars($mobileInfo['signal'], ENT_QUOTES, 'UTF-8'); ?></td></tr>
                  <tr><th scope="row"><?php echo _('Operator'); ?></th><td id="mobileOperator"><?php echo htmlspecialchars($mobileInfo['operator'], ENT_QUOTES, 'UTF-8'); ?></td></tr>
                  <tr><th scope="row"><?php echo _('IP address'); ?></th><td id="mobileIpAddress"><?php echo htmlspecialchars($mobileInfo['ipaddress'], ENT_QUOTES, 'UTF-8'); ?></td></tr>
                </tbody>
              </table>
            </div>

            <div class="tab-pane" id="mobileLogs">
              <h4 class="mt-3"><?php echo _('Latest action output'); ?></h4>
              <?php if (empty($actionLog)) : ?>
                <p class="text-muted mb-0" id="mobileLogsEmpty"><?php echo _('No action output yet.'); ?></p>
              <?php else : ?>
                <pre class="mb-0 p-3 bg-light border rounded" style="max-height: 280px; overflow: auto;" id="mobileLogsContent"><?php echo implode("\n", $actionLog); ?></pre>
              <?php endif; ?>
            </div>
          </div>

          <div class="d-flex flex-wrap gap-2 mt-3">
            <?php echo $buttons; ?>
          </div>
        </form>
      </div>
      <div class="card-footer"><?php echo _('USB tethering and Hilink support can share this page. PPP mode wiring will follow in a later phase.'); ?></div>
    </div>
  </div>
</div>
