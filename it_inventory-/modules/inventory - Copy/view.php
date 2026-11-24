<?php
// C:\xampp\htdocs\it_inventory\modules\inventory\view.php

$page_title = "Device Profile";
require_once __DIR__ . '/../../includes/header.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    set_flash('danger', 'Invalid device specified.');
    redirect('modules/inventory/index.php');
}

// Fetch Device + Category
$stmt = $pdo->prepare("
    SELECT d.*, c.name AS category_name
    FROM devices d
    JOIN categories c ON d.category_id = c.id
    WHERE d.id = :id
    LIMIT 1
");
$stmt->execute([':id' => $id]);
$device = $stmt->fetch();

if (!$device) {
    set_flash('danger', 'Equipment record not found.');
    redirect('modules/inventory/index.php');
}

// Fetch Active Assignment (if any)
$assign_stmt = $pdo->prepare("
    SELECT a.*, e.employee_id, e.first_name, e.last_name, e.department, u.full_name as assigned_by_name
    FROM assignments a
    JOIN employees e ON a.employee_id = e.id
    JOIN users u ON a.assigned_by = u.id
    WHERE a.device_id = :dev_id AND a.status = 'Active'
    LIMIT 1
");
$assign_stmt->execute([':dev_id' => $id]);
$active_assignment = $assign_stmt->fetch();

// Fetch Assignment History for this equipment
$history_stmt = $pdo->prepare("
    SELECT a.*, e.first_name, e.last_name, e.department
    FROM assignments a
    JOIN employees e ON a.employee_id = e.id
    WHERE a.device_id = :dev_id
    ORDER BY a.id DESC
");
$history_stmt->execute([':dev_id' => $id]);
$history = $history_stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-0"><?= e($device['asset_tag']); ?> &bull; <?= e($device['brand'] . ' ' . $device['model']); ?></h3>
        <span class="text-muted small">Category: <?= e($device['category_name']); ?></span>
    </div>
    <div class="d-flex gap-2">
        <a href="index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Inventory List
        </a>
        <a href="edit.php?id=<?= $device['id']; ?>" class="btn btn-primary btn-sm">
            <i class="bi bi-pencil me-1"></i> Edit Equipment
        </a>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Specification Card -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header bg-white py-3 fw-bold">
                <i class="bi bi-info-circle text-primary me-2"></i>Hardware Details
            </div>
            <div class="card-body p-0">
                <table class="table table-bordered mb-0 small">
                    <tbody>
                        <tr>
                            <th class="w-35 bg-light">Asset Tag</th>
                            <td class="fw-bold text-primary"><?= e($device['asset_tag']); ?></td>
                        </tr>
                        <tr>
                            <th class="bg-light">Category</th>
                            <td><?= e($device['category_name']); ?></td>
                        </tr>
                        <tr>
                            <th class="bg-light">Brand & Model</th>
                            <td><?= e($device['brand'] . ' ' . $device['model']); ?></td>
                        </tr>
                        <tr>
                            <th class="bg-light">Serial Number</th>
                            <td class="font-monospace fw-semibold"><?= e($device['serial_number']); ?></td>
                        </tr>
                        <tr>
                            <th class="bg-light">Location</th>
                            <td><?= e($device['location']); ?></td>
                        </tr>
                        <tr>
                            <th class="bg-light">Purchase Date</th>
                            <td><?= !empty($device['purchase_date']) ? e($device['purchase_date']) : '<span class="text-muted">Not specified</span>'; ?></td>
                        </tr>
                        <tr>
                            <th class="bg-light">Condition</th>
                            <td><span class="badge bg-secondary"><?= e($device['device_condition']); ?></span></td>
                        </tr>
                        <tr>
                            <th class="bg-light">Remarks</th>
                            <td><?= !empty($device['remarks']) ? e($device['remarks']) : '<span class="text-muted">None</span>'; ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Current Status & Active Assignment Card -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header bg-white py-3 fw-bold d-flex justify-content-between align-items-center">
                <span><i class="bi bi-person-badge text-success me-2"></i>Current Assignment</span>
                <span class="badge <?= ($device['status'] === 'Available') ? 'bg-success' : (($device['status'] === 'Assigned') ? 'bg-primary' : 'bg-warning text-dark'); ?>">
                    <?= e($device['status']); ?>
                </span>
            </div>
            <div class="card-body">
                <?php if ($active_assignment): ?>
                    <div class="alert alert-primary py-2 d-flex align-items-center">
                        <i class="bi bi-check-circle-fill fs-4 me-2"></i>
                        <div>Currently Assigned to <strong><?= e($active_assignment['first_name'] . ' ' . $active_assignment['last_name']); ?></strong></div>
                    </div>
                    <ul class="list-group list-group-flush small">
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span class="text-muted">Employee ID:</span>
                            <span class="fw-semibold"><?= e($active_assignment['employee_id']); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span class="text-muted">Department:</span>
                            <span class="fw-semibold"><?= e($active_assignment['department']); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span class="text-muted">Date Issued:</span>
                            <span class="fw-semibold"><?= e($active_assignment['assigned_date']); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span class="text-muted">Condition When Issued:</span>
                            <span class="badge bg-light text-dark border"><?= e($active_assignment['condition_on_assign']); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span class="text-muted">Issued By:</span>
                            <span><?= e($active_assignment['assigned_by_name']); ?></span>
                        </li>
                    </ul>
                <?php else: ?>
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-box-seam fs-1 d-block mb-2 text-secondary"></i>
                        <p class="mb-0">This device is currently <strong><?= e($device['status']); ?></strong>.</p>
                        <small>It can be assigned to staff under the Employees & Assignments module.</small>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Complete Device Assignment Timeline -->
<div class="card">
    <div class="card-header bg-white py-3 fw-bold">
        <i class="bi bi-clock-history text-secondary me-2"></i>Assignment History For This Device
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Employee</th>
                        <th>Department</th>
                        <th>Issued Date</th>
                        <th>Condition on Issue</th>
                        <th>Return Date</th>
                        <th>Return Condition</th>
                        <th>Assignment Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($history)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">No historical assignment logs exist for this device.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($history as $h): ?>
                            <tr>
                                <td class="ps-3 fw-semibold"><?= e($h['first_name'] . ' ' . $h['last_name']); ?></td>
                                <td><?= e($h['department']); ?></td>
                                <td><?= e($h['assigned_date']); ?></td>
                                <td><span class="badge bg-light text-dark border"><?= e($h['condition_on_assign']); ?></span></td>
                                <td><?= !empty($h['return_date']) ? e($h['return_date']) : '<span class="text-muted fst-italic">Present</span>'; ?></td>
                                <td><?= !empty($h['condition_on_return']) ? '<span class="badge bg-light text-dark border">' . e($h['condition_on_return']) . '</span>' : '-'; ?></td>
                                <td>
                                    <?php if ($h['status'] === 'Active'): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php elseif ($h['status'] === 'Returned'): ?>
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

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>