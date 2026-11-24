<?php
// C:\xampp\htdocs\it_inventory\modules\assignments\transfer.php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth_check.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    set_flash('danger', 'Invalid assignment specified.');
    redirect('modules/assignments/index.php');
}

$stmt = $pdo->prepare("
    SELECT a.*, 
           d.id AS device_id, d.asset_tag, d.brand, d.model, d.serial_number,
           e.id AS current_employee_id, e.employee_id AS emp_num, e.first_name, e.last_name, e.department
    FROM assignments a
    JOIN devices d ON a.device_id = d.id
    JOIN employees e ON a.employee_id = e.id
    WHERE a.id = :id AND a.status = 'Active'
    LIMIT 1
");
$stmt->execute([':id' => $id]);
$current = $stmt->fetch();

if (!$current) {
    set_flash('danger', 'Active assignment record not found.');
    redirect('modules/assignments/index.php');
}

$emp_stmt = $pdo->prepare("
    SELECT id, employee_id, first_name, last_name, department
    FROM employees
    WHERE employment_status = 'Active' AND id != :current_emp_id
    ORDER BY last_name ASC, first_name ASC
");
$emp_stmt->execute([':current_emp_id' => $current['current_employee_id']]);
$eligible_employees = $emp_stmt->fetchAll();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = "Security check failed. Please refresh.";
    } else {
        $new_employee_id       = (int)($_POST['new_employee_id'] ?? 0);
        $transfer_date         = trim($_POST['transfer_date'] ?? date('Y-m-d'));
        $condition_on_transfer = $_POST['condition_on_transfer'] ?? 'Good';
        $remarks               = trim($_POST['remarks'] ?? '');

        if (empty($new_employee_id)) { $errors[] = "Please select the new recipient employee."; }
        if (empty($transfer_date)) { $errors[] = "Transfer date is required."; }

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                $close_old = $pdo->prepare("
                    UPDATE assignments SET 
                        return_date = :return_date,
                        returned_to = :returned_to,
                        condition_on_return = :cond,
                        status = 'Transferred',
                        remarks = CONCAT(IFNULL(remarks, ''), ' [Transferred out: ', :remarks, ']')
                    WHERE id = :id
                ");
                $close_old->execute([
                    ':return_date' => $transfer_date,
                    ':returned_to' => $_SESSION['user_id'],
                    ':cond'        => $condition_on_transfer,
                    ':remarks'     => $remarks,
                    ':id'          => $id
                ]);

                $open_new = $pdo->prepare("
                    INSERT INTO assignments (device_id, employee_id, assigned_by, assigned_date, condition_on_assign, status, remarks)
                    VALUES (:device_id, :employee_id, :assigned_by, :assigned_date, :cond, 'Active', :remarks)
                ");
                $open_new->execute([
                    ':device_id'     => $current['device_id'],
                    ':employee_id'   => $new_employee_id,
                    ':assigned_by'   => $_SESSION['user_id'],
                    ':assigned_date' => $transfer_date,
                    ':cond'          => $condition_on_transfer,
                    ':remarks'       => "Transferred from {$current['first_name']} {$current['last_name']}. Note: {$remarks}"
                ]);
                $new_assignment_id = $pdo->lastInsertId();

                $upd_device = $pdo->prepare("UPDATE devices SET device_condition = :cond WHERE id = :id");
                $upd_device->execute([':cond' => $condition_on_transfer, ':id' => $current['device_id']]);

                $pdo->commit();

                log_activity($pdo, $_SESSION['user_id'], 'TRANSFER_DEVICE', 'Assignment', $new_assignment_id, "Device {$current['asset_tag']} transferred from {$current['first_name']} {$current['last_name']} to employee ID {$new_employee_id}");
                set_flash('success', "Equipment {$current['asset_tag']} successfully transferred.");
                redirect('modules/assignments/index.php');
            } catch (PDOException $e) {
                $pdo->rollBack();
                $errors[] = "Database Error: " . $e->getMessage();
            }
        }
    }
}

$page_title = "Transfer Equipment";
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h3 class="fw-bold mb-0">Transfer Equipment</h3>
                <span class="text-muted small">Hand over hardware directly to another employee</span>
            </div>
            <a href="index.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Back to Assignments
            </a>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger py-2">
                <ul class="mb-0 ps-3">
                    <?php foreach ($errors as $err): ?>
                        <li><?= e($err); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="card mb-3 bg-light border">
            <div class="card-body p-3">
                <div class="row g-2 small">
                    <div class="col-sm-6">
                        <span class="text-muted">Equipment:</span>
                        <strong class="d-block text-dark"><?= e($current['asset_tag']); ?> &bull; <?= e($current['brand'] . ' ' . $current['model']); ?></strong>
                        <span class="text-muted font-monospace">SN: <?= e($current['serial_number']); ?></span>
                    </div>
                    <div class="col-sm-6">
                        <span class="text-muted">Current Custodian:</span>
                        <strong class="d-block text-dark"><?= e($current['first_name'] . ' ' . $current['last_name']); ?></strong>
                        <span class="text-muted">ID: <?= e($current['emp_num']); ?> &bull; Dept: <?= e($current['department']); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-body p-4">
                <form action="transfer.php?id=<?= $id; ?>" method="POST">
                    <?= csrf_field(); ?>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Transfer To (New Custodian) <span class="text-danger">*</span></label>
                            <select name="new_employee_id" class="form-select" required>
                                <option value="">-- Choose New Recipient --</option>
                                <?php foreach ($eligible_employees as $emp): ?>
                                    <option value="<?= $emp['id']; ?>">
                                        <?= e($emp['last_name'] . ', ' . $emp['first_name']); ?> (<?= e($emp['employee_id']); ?> - <?= e($emp['department']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Transfer Date <span class="text-danger">*</span></label>
                            <input type="date" name="transfer_date" class="form-control" required value="<?= date('Y-m-d'); ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Condition on Transfer <span class="text-danger">*</span></label>
                            <select name="condition_on_transfer" class="form-select" required>
                                <option value="Good" selected>Good</option>
                                <option value="Fair">Fair</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Facilitated By</label>
                            <input type="text" class="form-control bg-light" disabled value="<?= e($_SESSION['full_name']); ?>">
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold small">Transfer Notes</label>
                            <input type="text" name="remarks" class="form-control" placeholder="e.g. Reassigned due to new role">
                        </div>
                    </div>

                    <hr class="my-4">

                    <div class="d-flex justify-content-end gap-2">
                        <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-info px-4" onclick="return confirm('Confirm transferring this device?');">
                            <i class="bi bi-arrow-left-right me-1"></i> Complete Transfer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>