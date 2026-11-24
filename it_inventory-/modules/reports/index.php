<?php
// C:\xampp\htdocs\it_inventory\modules\reports\index.php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth_check.php';

$tab = $_GET['tab'] ?? 'reports';

// 1. Fetch Report Metrics
$counts = [
    'total'     => 0,
    'assigned'  => 0,
    'available' => 0,
    'repair'    => 0
];

try {
    $c_stmt = $pdo->query("
        SELECT 
            COUNT(*) AS total,
            SUM(CASE WHEN status = 'Assigned' THEN 1 ELSE 0 END) AS assigned,
            SUM(CASE WHEN status = 'Available' THEN 1 ELSE 0 END) AS available,
            SUM(CASE WHEN status = 'Under Repair' THEN 1 ELSE 0 END) AS repair
        FROM devices
    ");
    $d_counts = $c_stmt->fetch();
    $counts['total']     = (int)($d_counts['total'] ?? 0);
    $counts['assigned']  = (int)($d_counts['assigned'] ?? 0);
    $counts['available'] = (int)($d_counts['available'] ?? 0);
    $counts['repair']    = (int)($d_counts['repair'] ?? 0);
} catch (PDOException $e) {
    error_log($e->getMessage());
}

// 2. Fetch Employees for employee-specific history
$all_employees = $pdo->query("SELECT id, employee_id, first_name, last_name, department FROM employees ORDER BY last_name ASC")->fetchAll();

// 3. Fetch Activity Logs if on the Logs tab (with UNIQUE parameter names)
$logs = [];
$log_search = trim($_GET['log_search'] ?? '');

if ($tab === 'logs') {
    $log_sql = "
        SELECT l.*, u.username, u.full_name
        FROM activity_logs l
        LEFT JOIN users u ON l.user_id = u.id
        WHERE 1=1
    ";
    $log_params = [];

    if (!empty($log_search)) {
        $log_sql .= " AND (l.action LIKE :s1 OR l.entity_type LIKE :s2 OR l.details LIKE :s3 OR u.username LIKE :s4 OR u.full_name LIKE :s5)";
        $search_term = "%{$log_search}%";
        $log_params = [
            ':s1' => $search_term,
            ':s2' => $search_term,
            ':s3' => $search_term,
            ':s4' => $search_term,
            ':s5' => $search_term
        ];
    }

    $log_sql .= " ORDER BY l.id DESC LIMIT 100";
    $log_stmt = $pdo->prepare($log_sql);
    $log_stmt->execute($log_params);
    $logs = $log_stmt->fetchAll();
}

$page_title = "History & Reports";
require_once __DIR__ . '/../../includes/header.php';
?>

<!-- Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-0">History & Reports</h3>
        <span class="text-muted small">Generate printable inventory reports and audit system activities</span>
    </div>
    <a href="backup.php" class="btn btn-outline-success btn-sm fw-semibold">
        <i class="bi bi-database-down me-1"></i> Download Database Backup (.SQL)
    </a>
</div>

<!-- Navigation Pills -->
<ul class="nav nav-pills mb-4 bg-white p-2 rounded border shadow-sm">
    <li class="nav-item">
        <a class="nav-link <?= ($tab === 'reports') ? 'active fw-bold' : 'text-dark'; ?>" href="index.php?tab=reports">
            <i class="bi bi-file-earmark-bar-graph me-1"></i> Inventory Reports
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= ($tab === 'logs') ? 'active fw-bold' : 'text-dark'; ?>" href="index.php?tab=logs">
            <i class="bi bi-clock-history me-1"></i> System Activity Logs
        </a>
    </li>
</ul>

<?php if ($tab === 'reports'): ?>

    <!-- Summary Metrics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card p-3 border-primary border-start border-4 shadow-sm">
                <span class="text-muted small text-uppercase fw-semibold">Master Inventory</span>
                <h3 class="fw-bold text-dark mb-2"><?= $counts['total']; ?></h3>
                <a href="print.php?type=all" target="_blank" class="btn btn-sm btn-outline-primary w-100">
                    <i class="bi bi-printer me-1"></i> Print Full List
                </a>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card p-3 border-info border-start border-4 shadow-sm">
                <span class="text-muted small text-uppercase fw-semibold">Assigned Assets</span>
                <h3 class="fw-bold text-info mb-2"><?= $counts['assigned']; ?></h3>
                <a href="print.php?type=assigned" target="_blank" class="btn btn-sm btn-outline-info w-100">
                    <i class="bi bi-printer me-1"></i> Print Assigned
                </a>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card p-3 border-success border-start border-4 shadow-sm">
                <span class="text-muted small text-uppercase fw-semibold">Available Stock</span>
                <h3 class="fw-bold text-success mb-2"><?= $counts['available']; ?></h3>
                <a href="print.php?type=available" target="_blank" class="btn btn-sm btn-outline-success w-100">
                    <i class="bi bi-printer me-1"></i> Print Available
                </a>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card p-3 border-warning border-start border-4 shadow-sm">
                <span class="text-muted small text-uppercase fw-semibold">Under Repair</span>
                <h3 class="fw-bold text-warning mb-2"><?= $counts['repair']; ?></h3>
                <a href="print.php?type=repair" target="_blank" class="btn btn-sm btn-outline-warning w-100">
                    <i class="bi bi-printer me-1"></i> Print Under Repair
                </a>
            </div>
        </div>
    </div>

    <!-- Targeted Employee Assignment History Report -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white py-3 fw-bold">
            <i class="bi bi-person-lines-fill text-primary me-2"></i>Employee Hardware History Report
        </div>
        <div class="card-body">
            <p class="text-muted small">Generate an individual accountability history sheet for a specific employee showing past and current equipment issued to them.</p>
            <form action="print.php" method="GET" target="_blank" class="row g-2 align-items-center">
                <input type="hidden" name="type" value="employee_history">
                <div class="col-md-8">
                    <select name="employee_id" class="form-select" required>
                        <option value="">-- Choose Employee to Generate History --</option>
                        <?php foreach ($all_employees as $emp): ?>
                            <option value="<?= $emp['id']; ?>">
                                <?= e($emp['last_name'] . ', ' . $emp['first_name']); ?> (<?= e($emp['employee_id']); ?> &bull; <?= e($emp['department']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-dark w-100">
                        <i class="bi bi-printer me-1"></i> Generate Printable Report
                    </button>
                </div>
            </form>
        </div>
    </div>

<?php else: ?>

    <!-- System Activity Audit Logs -->
    <div class="card mb-3 shadow-sm border">
        <div class="card-body p-3">
            <form method="GET" action="index.php" class="row g-2 align-items-center">
                <input type="hidden" name="tab" value="logs">
                <div class="col-md-10">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                        <input type="text" name="log_search" class="form-control" placeholder="Search by action, staff name, device tag, or details..." value="<?= e($log_search); ?>">
                    </div>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-dark w-100">Search</button>
                    <a href="index.php?tab=logs" class="btn btn-sm btn-outline-secondary" title="Reset">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm border">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <span class="fw-bold text-dark"><i class="bi bi-journal-text text-secondary me-2"></i>Audit Trail (Latest 100 Entries)</span>
            <span class="badge bg-secondary"><?= count($logs); ?> Shown</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light text-secondary">
                        <tr>
                            <th class="ps-3">Date & Time</th>
                            <th>Action</th>
                            <th>Module / Entity</th>
                            <th>Description</th>
                            <th>Staff User</th>
                            <th>IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">No activity logs recorded yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td class="ps-3 text-muted"><?= date('Y-m-d H:i:s', strtotime($log['created_at'])); ?></td>
                                    <td>
                                        <?php
                                        $badge = match($log['action']) {
                                            'LOGIN', 'LOGOUT' => 'bg-secondary',
                                            'CREATE_DEVICE', 'CREATE_EMPLOYEE' => 'bg-primary',
                                            'UPDATE_DEVICE', 'UPDATE_EMPLOYEE' => 'bg-info text-dark',
                                            'ASSIGN_DEVICE' => 'bg-success',
                                            'RETURN_DEVICE' => 'bg-dark',
                                            'TRANSFER_DEVICE' => 'bg-warning text-dark',
                                            'UPLOAD_DOCUMENT' => 'bg-success',
                                            'DELETE_DOCUMENT' => 'bg-danger',
                                            default => 'bg-light text-dark border'
                                        };
                                        ?>
                                        <span class="badge <?= $badge; ?>"><?= e($log['action']); ?></span>
                                    </td>
                                    <td><span class="text-dark fw-semibold"><?= e($log['entity_type']); ?></span></td>
                                    <td><?= e($log['details']); ?></td>
                                    <td><span class="fw-semibold text-dark"><?= e($log['full_name'] ?? $log['username'] ?? 'System'); ?></span></td>
                                    <td class="text-muted font-monospace" style="font-size:0.75rem;"><?= e($log['ip_address'] ?? '127.0.0.1'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>