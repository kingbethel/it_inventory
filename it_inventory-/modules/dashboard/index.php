<?php
// C:\xampp\htdocs\it_inventory\modules\dashboard\index.php

$page_title = "Dashboard";
require_once __DIR__ . '/../../includes/header.php';

// 1. Fetch KPI metrics from devices table
$stats = [
    'total'        => 0,
    'available'    => 0,
    'assigned'     => 0,
    'under_repair' => 0
];

try {
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'Available' THEN 1 ELSE 0 END) as available,
            SUM(CASE WHEN status = 'Assigned' THEN 1 ELSE 0 END) as assigned,
            SUM(CASE WHEN status = 'Under Repair' THEN 1 ELSE 0 END) as under_repair
        FROM devices
    ");
    $device_counts = $stmt->fetch();
    if ($device_counts) {
        $stats['total']        = (int)($device_counts['total'] ?? 0);
        $stats['available']    = (int)($device_counts['available'] ?? 0);
        $stats['assigned']     = (int)($device_counts['assigned'] ?? 0);
        $stats['under_repair'] = (int)($device_counts['under_repair'] ?? 0);
    }
} catch (PDOException $e) {
    error_log("Dashboard query error: " . $e->getMessage());
}

// 2. Fetch Recent 5 Assignments
$recent_assignments = [];
try {
    $stmt = $pdo->query("
        SELECT a.id, a.assigned_date, a.status,
               d.asset_tag, d.brand, d.model,
               e.employee_id, e.first_name, e.last_name, e.department
        FROM assignments a
        JOIN devices d ON a.device_id = d.id
        JOIN employees e ON a.employee_id = e.id
        ORDER BY a.created_at DESC
        LIMIT 5
    ");
    $recent_assignments = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Recent assignments query error: " . $e->getMessage());
}

// 3. Fetch Recent 5 Activity Logs
$recent_logs = [];
try {
    $stmt = $pdo->query("
        SELECT l.action, l.entity_type, l.details, l.created_at, u.username
        FROM activity_logs l
        LEFT JOIN users u ON l.user_id = u.id
        ORDER BY l.created_at DESC
        LIMIT 5
    ");
    $recent_logs = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Recent logs query error: " . $e->getMessage());
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-0">IT Dashboard</h3>
        <span class="text-muted small">Equipment summary and quick status overview</span>
    </div>
    <span class="badge bg-light text-dark border p-2">
        <i class="bi bi-clock me-1"></i> <?= date('F j, Y'); ?>
    </span>
</div>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card p-3 stat-card" style="border-left-color: #0d6efd;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <span class="text-muted small text-uppercase fw-semibold">Total Equipment</span>
                    <h2 class="fw-bold mb-0 text-dark"><?= $stats['total']; ?></h2>
                </div>
                <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-circle">
                    <i class="bi bi-boxes fs-3"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card p-3 stat-card" style="border-left-color: #198754;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <span class="text-muted small text-uppercase fw-semibold">Available</span>
                    <h2 class="fw-bold mb-0 text-success"><?= $stats['available']; ?></h2>
                </div>
                <div class="bg-success bg-opacity-10 text-success p-3 rounded-circle">
                    <i class="bi bi-check-circle fs-3"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card p-3 stat-card" style="border-left-color: #0dcaf0;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <span class="text-muted small text-uppercase fw-semibold">Assigned</span>
                    <h2 class="fw-bold mb-0 text-info"><?= $stats['assigned']; ?></h2>
                </div>
                <div class="bg-info bg-opacity-10 text-info p-3 rounded-circle">
                    <i class="bi bi-person-check fs-3"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card p-3 stat-card" style="border-left-color: #ffc107;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <span class="text-muted small text-uppercase fw-semibold">Under Repair</span>
                    <h2 class="fw-bold mb-0 text-warning"><?= $stats['under_repair']; ?></h2>
                </div>
                <div class="bg-warning bg-opacity-10 text-warning p-3 rounded-circle">
                    <i class="bi bi-tools fs-3"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Tables Grid -->
<div class="row g-4">
    <!-- Recent Device Assignments -->
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-arrow-left-right text-primary me-2"></i>Recent Assignments</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small text-secondary">
                            <tr>
                                <th>Asset Tag</th>
                                <th>Device</th>
                                <th>Assigned To</th>
                                <th>Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody class="small">
                            <?php if (empty($recent_assignments)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">No equipment assignments recorded yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recent_assignments as $item): ?>
                                    <tr>
                                        <td class="fw-bold text-primary"><?= e($item['asset_tag']); ?></td>
                                        <td><?= e($item['brand'] . ' ' . $item['model']); ?></td>
                                        <td>
                                            <?= e($item['first_name'] . ' ' . $item['last_name']); ?><br>
                                            <span class="text-muted" style="font-size:0.75rem;"><?= e($item['department']); ?></span>
                                        </td>
                                        <td><?= e($item['assigned_date']); ?></td>
                                        <td>
                                            <?php if ($item['status'] === 'Active'): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php elseif ($item['status'] === 'Returned'): ?>
                                                <span class="badge bg-secondary">Returned</span>
                                            <?php else: ?>
                                                <span class="badge bg-info">Transferred</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activity Trail -->
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-clock-history text-secondary me-2"></i>Recent Activities</h6>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush small">
                    <?php if (empty($recent_logs)): ?>
                        <li class="list-group-item text-center py-4 text-muted">No recent activities found.</li>
                    <?php else: ?>
                        <?php foreach ($recent_logs as $log): ?>
                            <li class="list-group-item px-3 py-2">
                                <div class="d-flex justify-content-between">
                                    <span class="fw-bold text-dark"><?= e($log['action']); ?> &bull; <?= e($log['entity_type']); ?></span>
                                    <span class="text-muted" style="font-size:0.75rem;"><?= date('M d, H:i', strtotime($log['created_at'])); ?></span>
                                </div>
                                <div class="text-muted"><?= e($log['details']); ?></div>
                                <small class="text-secondary">By: <?= e($log['username'] ?? 'System'); ?></small>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>