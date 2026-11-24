<?php
// C:\xampp\htdocs\it_inventory\modules\assignments\assign.php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth_check.php';

$preset_device_id   = (int)($_GET['device_id'] ?? 0);
$preset_employee_id = (int)($_GET['employee_id'] ?? 0);

// Fetch Available Devices
$dev_stmt = $pdo->query("
    SELECT d.id, d.asset_tag, d.brand, d.model, d.device_condition, c.name AS category_name
    FROM devices d
    JOIN categories c ON d.category_id = c.id
    WHERE d.status = 'Available' OR d.id = {$preset_device_id}
    ORDER BY d.asset_tag ASC
");
$available_devices = $dev_stmt->fetchAll();

// Fetch Active Employees
$emp_stmt = $pdo->query("
    SELECT id, employee_id, first_name, last_name, department
    FROM employees
    WHERE employment_status = 'Active' OR id = {$preset_employee_id}
    ORDER BY last_name ASC, first_name ASC
");
$active_employees = $emp_stmt->fetchAll();

$errors = [];

// Form submission processed BEFORE outputting HTML
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = "Security check failed. Please refresh.";
    } else {
        $device_id           = (int)($_POST['device_id'] ?? 0);
        $employee_id         = (int)($_POST['employee_id'] ?? 0);
        $assigned_date       = trim($_POST['assigned_date'] ?? date('Y-m-d'));
        $condition_on_assign = $_POST['condition_on_assign'] ?? 'Good';
        $remarks             = trim($_POST['remarks'] ?? '');

        if (empty($device_id)) { $errors[] = "Please select an equipment to issue."; }
        if (empty($employee_id)) { $errors[] = "Please select an employee."; }
        if (empty($assigned_date)) { $errors[] = "Date issued is required."; }

        if (empty($errors)) {
            $pdo->beginTransaction();
            $chk = $pdo->prepare("SELECT id, asset_tag, status FROM devices WHERE id = :id FOR UPDATE");
            $chk->execute([':id' => $device_id]);
            $device_check = $chk->fetch();

            if (!$device_check || $device_check['status'] !== 'Available') {
                $pdo->rollBack();
                $errors[] = "This device is no longer Available for assignment.";
            } else {
                try {
                    $ins = $pdo->prepare("
                        INSERT INTO assignments (device_id, employee_id, assigned_by, assigned_date, condition_on_assign, status, remarks)
                        VALUES (:device_id, :employee_id, :assigned_by, :assigned_date, :condition_on_assign, 'Active', :remarks)
                    ");
                    $ins->execute([
                        ':device_id'           => $device_id,
                        ':employee_id'         => $employee_id,
                        ':assigned_by'         => $_SESSION['user_id'],
                        ':assigned_date'       => $assigned_date,
                        ':condition_on_assign' => $condition_on_assign,
                        ':remarks'             => $remarks
                    ]);
                    $assignment_id = $pdo->lastInsertId();

                    $upd = $pdo->prepare("UPDATE devices SET status = 'Assigned' WHERE id = :id");
                    $upd->execute([':id' => $device_id]);

                    $pdo->commit();

                    log_activity($pdo, $_SESSION['user_id'], 'ASSIGN_DEVICE', 'Assignment', $assignment_id, "Assigned device {$device_check['asset_tag']} to employee ID {$employee_id}");

                    set_flash('success', "Equipment {$device_check['asset_tag']} successfully issued.");
                    redirect('modules/assignments/index.php');
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    $errors[] = "Database error: " . $e->getMessage();
                }
            }
        }
    }
}

$page_title = "Issue Equipment";
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h3 class="fw-bold mb-0">Issue Equipment</h3>
                <span class="text-muted small">Assign available IT hardware to an employee</span>
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

        <div class="card shadow-sm">
            <div class="card-body p-4">
                <form action="assign.php" method="POST">
                    <?= csrf_field(); ?>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Select Available Equipment <span class="text-danger">*</span></label>
                            <select name="device_id" class="form-select" required>
                                <option value="">-- Choose Equipment --</option>
                                <?php foreach ($available_devices as $dev): ?>
                                    <option value="<?= $dev['id']; ?>" <?= ($preset_device_id == $dev['id'] || ($_POST['device_id'] ?? '') == $dev['id']) ? 'selected' : ''; ?>>
                                        <?= e($dev['asset_tag']); ?> &mdash; <?= e($dev['brand'] . ' ' . $dev['model']); ?> (<?= e($dev['category_name']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (empty($available_devices)): ?>
                                <small class="text-danger d-block mt-1">Walang device na may status na 'Available'. Mag-add muna ng equipment sa Inventory.</small>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Assign To Employee <span class="text-danger">*</span></label>
                            <select name="employee_id" class="form-select" required>
                                <option value="">-- Choose Employee --</option>
                                <?php foreach ($active_employees as $emp): ?>
                                    <option value="<?= $emp['id']; ?>" <?= ($preset_employee_id == $emp['id'] || ($_POST['employee_id'] ?? '') == $emp['id']) ? 'selected' : ''; ?>>
                                        <?= e($emp['last_name'] . ', ' . $emp['first_name']); ?> (<?= e($emp['employee_id']); ?> - <?= e($emp['department']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Date Issued <span class="text-danger">*</span></label>
                            <input type="date" name="assigned_date" class="form-control" required value="<?= e($_POST['assigned_date'] ?? date('Y-m-d')); ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Condition on Issuance <span class="text-danger">*</span></label>
                            <select name="condition_on_assign" class="form-select" required>
                                <option value="New">New</option>
                                <option value="Good" selected>Good</option>
                                <option value="Fair">Fair</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold small">Remarks / Inclusions</label>
                            <input type="text" name="remarks" class="form-control" placeholder="e.g. Includes original charger, mouse, laptop bag" value="<?= e($_POST['remarks'] ?? ''); ?>">
                        </div>
                    </div>

                    <hr class="my-4">

                    <div class="d-flex justify-content-end gap-2">
                        <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-success px-4" <?= empty($available_devices) ? 'disabled' : ''; ?>>
                            <i class="bi bi-check2-circle me-1"></i> Complete Issuance
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>