<?php
// C:\xampp\htdocs\it_inventory\modules\assignments\index.php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth_check.php';

$search = trim($_GET['search'] ?? '');
$filter_status = trim($_GET['status'] ?? 'Active'); // Default to Active assignments

$sql = "
    SELECT a.*, 
           d.id AS device_id, d.asset_tag, d.brand, d.model, d.serial_number, c.name AS category_name,
           e.id AS emp_table_id, e.employee_id AS emp_code, e.first_name, e.last_name, e.department,
           u1.full_name AS issued_by_name,
           u2.full_name AS returned_to_name
    FROM assignments a
    JOIN devices d ON a.device_id = d.id
    JOIN categories c ON d.category_id = c.id
    JOIN employees e ON a.employee_id = e.id
    JOIN users u1 ON a.assigned_by = u1.id
    LEFT JOIN users u2 ON a.returned_to = u2.id
    WHERE 1=1
";

$params = [];

if (!empty($search)) {
    $sql .= " AND (d.asset_tag LIKE :s1 OR d.brand LIKE :s2 OR d.model LIKE :s3 OR d.serial_number LIKE :s4 OR e.employee_id LIKE :s5 OR e.first_name LIKE :s6 OR e.last_name LIKE :s7)";
    $term = "%{$search}%";
    $params[':s1'] = $term;
    $params[':s2'] = $term;
    $params[':s3'] = $term;
    $params[':s4'] = $term;
    $params[':s5'] = $term;
    $params[':s6'] = $term;
    $params[':s7'] = $term;
}

if (!empty($filter_status)) {
    $sql .= " AND a.status = :status";
    $params[':status'] = $filter_status;
}

$sql .= " ORDER BY a.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$assignments = $stmt->fetchAll();

$page_title = "Device Assignments";
require_once __DIR__ . '/../../includes/header.php';
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-0">Device Assignments</h3>
        <span class="text-muted small">Manage hardware custody, print official liability forms, and process returns</span>
    </div>
    <div class="d-flex gap-2">
        <a href="../employees/index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-people me-1"></i> Employees
        </a>
        <a href="assign.php" class="btn btn-primary btn-sm">
            <i class="bi bi-laptop me-1"></i> Issue Equipment
        </a>
    </div>
</div>

<!-- Search & Filter Card -->
<div class="card mb-4 shadow-sm border">
    <div class="card-body p-3">
        <form method="GET" action="index.php" class="row g-2 align-items-center">
            <div class="col-md-6">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Search Asset Tag, Device, Serial, Employee Name or ID..." value="<?= e($search); ?>">
                </div>
            </div>
            <div class="col-md-4">
                <select name="status" class="form-select form-select-sm">
                    <option value="">-- All Statuses --</option>
                    <option value="Active" <?= ($filter_status === 'Active') ? 'selected' : ''; ?>>Active (Currently Issued)</option>
                    <option value="Returned" <?= ($filter_status === 'Returned') ? 'selected' : ''; ?>>Returned</option>
                    <option value="Transferred" <?= ($filter_status === 'Transferred') ? 'selected' : ''; ?>>Transferred</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-dark w-100">Filter</button>
                <a href="index.php" class="btn btn-sm btn-outline-secondary" title="Reset Filters">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Assignments Table -->
<div class="card shadow-sm border">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light text-secondary">
                    <tr>
                        <th class="ps-3" style="width: 14%;">Asset Tag</th>
                        <th style="width: 24%;">Device Details</th>
                        <th style="width: 22%;">Assigned To</th>
                        <th style="width: 14%;">Issued Date</th>
                        <th style="width: 10%;">Status</th>
                        <th class="text-end pe-3" style="width: 16%;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($assignments)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                                No assignment records found matching your selection.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($assignments as $a): ?>
                            <tr>
                                <!-- Asset Tag -->
                                <td class="ps-3 fw-bold">
                                    <a href="../inventory/view.php?id=<?= $a['device_id']; ?>" class="text-primary text-decoration-none">
                                        <?= e($a['asset_tag']); ?>
                                    </a>
                                </td>

                                <!-- Device Details -->
                                <td>
                                    <div class="fw-semibold text-dark"><?= e($a['brand'] . ' ' . $a['model']); ?></div>
                                    <span class="text-muted" style="font-size:0.75rem;">
                                        <?= e($a['category_name']); ?> &bull; SN: <?= e($a['serial_number']); ?>
                                    </span>
                                </td>

                                <!-- Employee Recipient -->
                                <td>
                                    <a href="../employees/view.php?id=<?= $a['emp_table_id']; ?>" class="text-dark fw-semibold text-decoration-none">
                                        <?= e($a['first_name'] . ' ' . $a['last_name']); ?>
                                    </a>
                                    <div class="text-muted" style="font-size:0.75rem;">
                                        ID: <?= e($a['emp_code']); ?> &bull; <?= e($a['department']); ?>
                                    </div>
                                </td>

                                <!-- Issued Date -->
                                <td>
                                    <div><?= e($a['assigned_date']); ?></div>
                                    <small class="text-muted">Cond: <?= e($a['condition_on_assign']); ?></small>
                                </td>

                                <!-- Status Badge -->
                                <td>
                                    <?php if ($a['status'] === 'Active'): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php elseif ($a['status'] === 'Returned'): ?>
                                        <span class="badge bg-secondary">Returned</span>
                                        <div class="text-muted" style="font-size:0.7rem;"><?= e($a['return_date']); ?></div>
                                    <?php else: ?>
                                        <span class="badge bg-info">Transferred</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Actions: Direct Print Liability Form + Return/Transfer -->
                                <td class="text-end pe-3">
                                    <div class="btn-group btn-group-sm">
                                        <!-- Official Liability Turnover Sheet -->
                                        <a href="print_liability.php?id=<?= $a['id']; ?>" target="_blank" class="btn btn-outline-dark py-0" title="Print Official Turnover Sheet">
                                            </i>Print Form
                                        </a>

                                        <?php if ($a['status'] === 'Active'): ?>
                                            <a href="return.php?id=<?= $a['id']; ?>" class="btn btn-outline-success py-0" title="Return device to inventory">
                                                Return
                                            </a>
                                            <a href="transfer.php?id=<?= $a['id']; ?>" class="btn btn-outline-info py-0" title="Transfer device to another employee">
                                                Transfer
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer bg-white border-top py-3 small text-muted">
        Total Records: <strong><?= count($assignments); ?></strong>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>