<?php
// C:\xampp\htdocs\it_inventory\modules\documents\index.php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth_check.php';

$search = trim($_GET['search'] ?? '');
$filter_status = trim($_GET['status'] ?? ''); // Empty means show ALL lifetime records

// PERMANENT LIFETIME QUERY: Shows Active, Returned, and Transferred liabilities
$sql = "
    SELECT a.id AS assignment_id, a.assigned_date, a.return_date, a.status AS liability_status, 
           a.remarks AS inclusions, a.condition_on_assign, a.condition_on_return,
           d.id AS device_id, d.asset_tag, d.brand, d.model, d.serial_number, c.name AS category_name,
           e.id AS employee_id, e.employee_id AS emp_code, e.first_name, e.last_name, e.department, e.position,
           u.full_name AS issued_by_name
    FROM assignments a
    JOIN devices d ON a.device_id = d.id
    JOIN categories c ON d.category_id = c.id
    JOIN employees e ON a.employee_id = e.id
    JOIN users u ON a.assigned_by = u.id
    WHERE 1=1
";

$params = [];

if (!empty($search)) {
    $sql .= " AND (d.asset_tag LIKE :s1 OR d.brand LIKE :s2 OR d.model LIKE :s3 OR d.serial_number LIKE :s4 OR e.first_name LIKE :s5 OR e.last_name LIKE :s6 OR e.employee_id LIKE :s7)";
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
$liabilities = $stmt->fetchAll();

$page_title = "Liabilities Management";
require_once __DIR__ . '/../../includes/header.php';
?>

<!-- Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-0">Equipment Liabilities & Custody Archive</h3>
        <span class="text-muted small">Permanent lifetime records of all company equipment turnover slips and accountability forms</span>
    </div>
    <a href="generate.php" class="btn btn-primary btn-sm fw-semibold">
        <i class="bi bi-file-earmark-plus me-1"></i> + Generate Liability Form
    </a>
</div>

<!-- Search & Status Filter -->
<div class="card mb-4 shadow-sm border">
    <div class="card-body p-3">
        <form method="GET" action="index.php" class="row g-2 align-items-center">
            <div class="col-md-7">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Search by Employee Name, ID, Device, Serial Number, or Asset Tag..." value="<?= e($search); ?>">
                </div>
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select form-select-sm">
                    <option value="">-- All Records (Lifetime History) --</option>
                    <option value="Active" <?= ($filter_status === 'Active') ? 'selected' : ''; ?>>Active (Currently Issued)</option>
                    <option value="Returned" <?= ($filter_status === 'Returned') ? 'selected' : ''; ?>>Returned</option>
                    <option value="Transferred" <?= ($filter_status === 'Transferred') ? 'selected' : ''; ?>>Transferred</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-dark w-100">Filter</button>
                <a href="index.php" class="btn btn-sm btn-outline-secondary" title="Reset Filters"><i class="bi bi-arrow-counterclockwise"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- Permanent Liabilities Table -->
<div class="card shadow-sm border">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <span class="fw-bold text-dark">
            <i class="bi bi-journal-bookmark text-primary me-2"></i>Permanent Liability Database Records
        </span>
        <span class="badge bg-secondary"><?= count($liabilities); ?> Total Records</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light text-secondary">
                    <tr>
                        <th class="ps-3" style="width: 20%;">Employee (Recipient)</th>
                        <th style="width: 13%;">Department</th>
                        <th style="width: 25%;">Turned Over Equipment</th>
                        <th style="width: 14%;">Turnover Date</th>
                        <th style="width: 13%;">Status</th>
                        <th class="text-end pe-3" style="width: 15%;">Official Document</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($liabilities)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="bi bi-file-earmark-x fs-2 d-block mb-2"></i>
                                No liability records found. Click <strong>"+ Generate Liability Form"</strong> to create one.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($liabilities as $row): ?>
                            <tr>
                                <!-- Employee Name -->
                                <td class="ps-3">
                                    <div class="fw-bold text-dark">
                                        <?= e($row['first_name'] . ' ' . $row['last_name']); ?>
                                    </div>
                                    <small class="text-muted">ID: <?= e($row['emp_code']); ?> &bull; <?= e($row['position'] ?? 'Staff'); ?></small>
                                </td>

                                <!-- Department -->
                                <td>
                                    <span class="badge bg-light text-dark border"><?= e($row['department']); ?></span>
                                </td>

                                <!-- Equipment -->
                                <td>
                                    <span class="fw-bold text-primary"><?= e($row['asset_tag']); ?></span>
                                    <span class="fw-semibold text-dark">&bull; <?= e($row['brand'] . ' ' . $row['model']); ?></span>
                                    <div class="text-muted font-monospace" style="font-size:0.75rem;">
                                        SN: <?= e($row['serial_number']); ?>
                                    </div>
                                </td>

                                <!-- Turnover Date -->
                                <td>
                                    <div><?= date('M d, Y', strtotime($row['assigned_date'])); ?></div>
                                    <small class="text-muted"><?= e($row['inclusions'] ?? 'BOX AND CHARGER'); ?></small>
                                </td>

                                <!-- Permanent Status -->
                                <td>
                                    <?php if ($row['liability_status'] === 'Active'): ?>
                                        <span class="badge bg-success">Active / Issued</span>
                                    <?php elseif ($row['liability_status'] === 'Returned'): ?>
                                        <span class="badge bg-secondary">Returned</span>
                                        <div class="text-muted" style="font-size:0.7rem;">
                                            <?= !empty($row['return_date']) ? date('M d, Y', strtotime($row['return_date'])) : 'Returned'; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="badge bg-info">Transferred</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Action: Print Official Form Forever -->
                                <td class="text-end pe-3">
                                    <a href="../assignments/print_liability.php?id=<?= $row['assignment_id']; ?>" target="_blank" class="btn btn-outline-dark btn-sm py-0 fw-semibold">
                                        <i class="bi bi-printer me-1"></i>Print Form
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer bg-white border-top py-3 small text-muted">
        Total Lifetime Liabilities Recorded: <strong><?= count($liabilities); ?></strong>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>