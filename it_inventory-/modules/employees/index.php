<?php
// C:\xampp\htdocs\it_inventory\modules\employees\index.php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth_check.php';

$search = trim($_GET['search'] ?? '');
$filter_dept = trim($_GET['department'] ?? '');
$filter_status = trim($_GET['status'] ?? '');

// Fetch unique departments
$dept_stmt = $pdo->query("SELECT DISTINCT department FROM employees WHERE department IS NOT NULL AND department != '' ORDER BY department ASC");
$departments = $dept_stmt->fetchAll(PDO::FETCH_COLUMN);

// Fetch employees
$sql = "SELECT * FROM employees WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND (employee_id LIKE :search OR first_name LIKE :search OR last_name LIKE :search OR position LIKE :search)";
    $params[':search'] = "%{$search}%";
}
if (!empty($filter_dept)) {
    $sql .= " AND department = :dept";
    $params[':dept'] = $filter_dept;
}
if (!empty($filter_status)) {
    $sql .= " AND employment_status = :status";
    $params[':status'] = $filter_status;
}

$sql .= " ORDER BY last_name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$employees = $stmt->fetchAll();

// Fetch active hardware assignments grouped by employee
$active_stmt = $pdo->query("
    SELECT a.id AS assignment_id, a.employee_id,
           d.id AS device_id, d.asset_tag, d.brand, d.model
    FROM assignments a
    JOIN devices d ON a.device_id = d.id
    WHERE a.status = 'Active'
");
$all_active = $active_stmt->fetchAll();

$employee_devices = [];
foreach ($all_active as $row) {
    $employee_devices[$row['employee_id']][] = $row;
}

$page_title = "Employees";
require_once __DIR__ . '/../../includes/header.php';
?>
<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-0">Employees</h3>
        <span class="text-muted small">Manage staff records and assigned company hardware</span>
    </div>
    <div class="d-flex gap-2">
        <!-- New Import CSV Button -->
        <a href="import.php" class="btn btn-outline-success btn-sm fw-semibold">
            <i class="bi bi-file-earmark-spreadsheet me-1"></i> Import CSV
        </a>
        <a href="../assignments/assign.php" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-laptop me-1"></i> Issue Equipment
        </a>
        <a href="add.php" class="btn btn-primary btn-sm">
            <i class="bi bi-person-plus me-1"></i> Add Employee
        </a>
    </div>
</div>

<!-- Search & Filter Bar -->
<div class="card mb-4 shadow-sm border">
    <div class="card-body p-3">
        <form method="GET" action="index.php" class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Search ID, Name, or Position..." value="<?= e($search); ?>">
                </div>
            </div>
            <div class="col-md-3">
                <select name="department" class="form-select form-select-sm">
                    <option value="">-- All Departments --</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?= e($dept); ?>" <?= ($filter_dept === $dept) ? 'selected' : ''; ?>>
                            <?= e($dept); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm">
                    <option value="">-- All Statuses --</option>
                    <option value="Active" <?= ($filter_status === 'Active') ? 'selected' : ''; ?>>Active</option>
                    <option value="Resigned" <?= ($filter_status === 'Resigned') ? 'selected' : ''; ?>>Resigned</option>
                    <option value="Terminated" <?= ($filter_status === 'Terminated') ? 'selected' : ''; ?>>Terminated</option>
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

<!-- Clean Employee Table -->
<div class="card shadow-sm border">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light text-secondary">
                    <tr>
                        <th class="ps-3" style="width: 15%;">Employee ID</th>
                        <th style="width: 20%;">Full Name</th>
                        <th style="width: 15%;">Department</th>
                        <th style="width: 15%;">Position</th>
                        <th style="width: 20%;">Assigned Equipment</th>
                        <th style="width: 5%;">Status</th>
                        <th class="text-end pe-3" style="width: 10%;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($employees)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-people fs-2 d-block mb-2"></i>
                                No employee records found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($employees as $emp): ?>
                            <?php $devices = $employee_devices[$emp['id']] ?? []; ?>
                            <tr>
                                <td class="ps-3 fw-bold">
                                    <a href="view.php?id=<?= $emp['id']; ?>" class="text-decoration-none text-primary">
                                        <?= e($emp['employee_id']); ?>
                                    </a>
                                </td>
                                <td class="fw-semibold text-dark">
                                    <?= e($emp['last_name'] . ', ' . $emp['first_name']); ?>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border"><?= e($emp['department']); ?></span>
                                </td>
                                <td class="text-muted">
                                    <?= !empty($emp['position']) ? e($emp['position']) : '-'; ?>
                                </td>
                                <td>
                                    <?php if (empty($devices)): ?>
                                        <span class="text-muted">None</span>
                                    <?php else: ?>
                                        <?php foreach ($devices as $dev): ?>
                                            <a href="../inventory/view.php?id=<?= $dev['device_id']; ?>" class="badge bg-primary text-decoration-none me-1 mb-1 d-inline-block">
                                                <i class="bi bi-laptop me-1"></i><?= e($dev['asset_tag']); ?>
                                            </a>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($emp['employment_status'] === 'Active'): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php elseif ($emp['employment_status'] === 'Resigned'): ?>
                                        <span class="badge bg-secondary">Resigned</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Terminated</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-3">
                                    <div class="btn-group btn-group-sm">
                                        <a href="view.php?id=<?= $emp['id']; ?>" class="btn btn-outline-secondary" title="View Profile">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="edit.php?id=<?= $emp['id']; ?>" class="btn btn-outline-primary" title="Edit Employee">
                                            <i class="bi bi-pencil"></i>
                                        </a>
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
        Total Employees: <strong><?= count($employees); ?></strong>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>