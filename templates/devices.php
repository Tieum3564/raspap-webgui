<div class="row">
  <div class="col-lg-12">
    <div class="card shadow mb-4">
      <div class="card-header page-card-header">
        <div class="row align-items-center">
          <div class="col">
            <i class="fas fa-sitemap me-2"></i><?php echo _('Network Devices'); ?>
          </div>
          <div class="col">
            <button class="btn btn-light btn-sm float-end" id="devicesRefreshBtn" type="button" title="<?php echo _('Refresh'); ?>">
              <i class="fas fa-sync-alt"></i>
            </button>
          </div>
        </div>
      </div>
      <div class="card-body">
        <?php $status->showMessages(); ?>
        
        <!-- Loading State -->
        <div id="devicesLoading" class="text-center py-5" style="display: none;">
          <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden"><?php echo _('Loading'); ?></span>
          </div>
          <p class="mt-2 text-muted"><?php echo _('Loading devices...'); ?></p>
        </div>
        
        <!-- Error State -->
        <div id="devicesError" class="alert alert-warning" role="alert" style="display: none;">
          <i class="fas fa-exclamation-triangle me-2"></i>
          <span id="devicesErrorText"></span>
        </div>
        
        <!-- Empty State -->
        <div id="devicesEmpty" class="text-center py-5" style="display: none;">
          <i class="fas fa-inbox fa-2x text-muted mb-3"></i>
          <p class="text-muted"><?php echo _('No connected devices'); ?></p>
        </div>
        
        <!-- Devices List -->
        <div id="devicesList" class="devices-grid" style="display: none;">
          <!-- Device cards will be injected here -->
        </div>
      </div>
      <div class="card-footer text-muted small">
        <i class="fas fa-info-circle me-1"></i>
        <span><?php echo sprintf(_('Updating every %d seconds'), $pollIntervalMs / 1000); ?></span>
      </div>
    </div>
  </div>
</div>

<style>
.devices-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
  gap: 1rem;
  padding: 0.5rem 0;
}

@media (max-width: 768px) {
  .devices-grid {
    grid-template-columns: 1fr;
  }
}

.device-card {
  border: 1px solid #e3e6f0;
  border-radius: 0.35rem;
  padding: 1rem;
  background-color: #fff;
  transition: box-shadow 0.2s ease;
}

.device-card:hover {
  box-shadow: 0 0.15rem 0.35rem rgba(58, 59, 69, 0.15);
}

.device-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 0.75rem;
  padding-bottom: 0.75rem;
  border-bottom: 1px solid #e3e6f0;
}

.device-vendor {
  font-weight: 600;
  color: #333;
  margin: 0;
}

.device-type-badge {
  display: inline-block;
  padding: 0.25rem 0.5rem;
  border-radius: 0.25rem;
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
}

.device-type-badge.wireless {
  background-color: #e8f4f8;
  color: #0c5460;
}

.device-type-badge.ethernet {
  background-color: #f0f5f8;
  color: #3e4851;
}

.device-details {
  font-size: 0.875rem;
  line-height: 1.5;
}

.device-detail-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 0.5rem;
}

.device-detail-label {
  color: #6e707e;
  font-weight: 500;
  min-width: 100px;
}

.device-detail-value {
  color: #333;
  word-break: break-all;
  text-align: right;
  flex-grow: 1;
  margin-left: 0.5rem;
}

.device-signal {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.signal-bar {
  display: inline-flex;
  gap: 2px;
  align-items: flex-end;
  height: 1.25rem;
}

.signal-bar-segment {
  width: 3px;
  background-color: #ccc;
}

.signal-bar-segment.active {
  background-color: #27ae60;
}

.signal-bar-segment.warn {
  background-color: #f39c12;
}

.signal-bar-segment.crit {
  background-color: #e74c3c;
}

.device-status {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.25rem 0.5rem;
  border-radius: 0.25rem;
  font-size: 0.75rem;
  background-color: #d4edda;
  color: #155724;
}

.device-status i {
  font-size: 0.7rem;
}
</style>

<script>
  // Pass polling interval to JavaScript
  window.DEVICES_POLL_INTERVAL = <?php echo intval($pollIntervalMs); ?>;
</script>
