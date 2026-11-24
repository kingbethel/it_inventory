<?php
// C:\xampp\htdocs\it_inventory\modules\documents\generate.php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth_check.php';

// Fetch Active Employees
$employees = $pdo->query("SELECT id, employee_id, first_name, last_name, department, position FROM employees WHERE employment_status = 'Active' ORDER BY last_name ASC")->fetchAll();

// Fetch Available Devices
$devices = $pdo->query("
    SELECT d.id, d.asset_tag, d.brand, d.model, d.serial_number, c.name AS category_name
    FROM devices d
    JOIN categories c ON d.category_id = c.id
    WHERE d.status = 'Available'
    ORDER BY d.asset_tag ASC
")->fetchAll();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = "Security check failed. Please refresh.";
    } else {
        $employee_id   = (int)($_POST['employee_id'] ?? 0);
        $device_id     = (int)($_POST['device_id'] ?? 0);
        $assigned_date = trim($_POST['assigned_date'] ?? date('Y-m-d'));
        $condition     = $_POST['condition_on_assign'] ?? 'Good';
        $inclusions    = trim($_POST['inclusions'] ?? 'BOX AND CHARGER');

        if (empty($employee_id)) { $errors[] = "Please select an employee."; }
        if (empty($device_id)) { $errors[] = "Please select an equipment."; }

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                // 1. Insert Assignment & Liability record into database
                $ins_assign = $pdo->prepare("
                    INSERT INTO assignments (device_id, employee_id, assigned_by, assigned_date, condition_on_assign, status, remarks)
                    VALUES (:device_id, :employee_id, :assigned_by, :assigned_date, :cond, 'Active', :remarks)
                ");
                $ins_assign->execute([
                    ':device_id'     => $device_id,
                    ':employee_id'   => $employee_id,
                    ':assigned_by'   => $_SESSION['user_id'],
                    ':assigned_date' => $assigned_date,
                    ':cond'          => $condition,
                    ':remarks'       => $inclusions
                ]);
                $assignment_id = $pdo->lastInsertId();

                // 2. Mark Device as 'Assigned' in Inventory
                $upd_device = $pdo->prepare("UPDATE devices SET status = 'Assigned' WHERE id = :id");
                $upd_device->execute([':id' => $device_id]);

                $pdo->commit();

                log_activity($pdo, $_SESSION['user_id'], 'GENERATE_LIABILITY', 'Liability', $assignment_id, "Generated Liability Record #{$assignment_id}");

                // Flash message
                set_flash('success', "Official Liability Form successfully generated and saved to the database! Click 'Print Form' below to print.");
                
                // DITO KA NA IBABALIK SA LIABILITIES HUB (Hindi na sa Assignments!)
                redirect('modules/documents/index.php');
            } catch (PDOException $e) {
                $pdo->rollBack();
                $errors[] = "Database Error: " . $e->getMessage();
            }
        }
    }
}

$page_title = "Generate Liability Form";
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h3 class="fw-bold mb-0">Generate Liability Form</h3>
                <span class="text-muted small">Save turnover accountability record directly to the database</span>
            </div>
            <a href="index.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Back to Liabilities
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

        <div class="card shadow-sm border">
            <div class="card-body p-4">
                <form action="generate.php" method="POST">
                    <?= csrf_field(); ?>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Employee (Recipient) <span class="text-danger">*</span></label>
                            <select name="employee_id" class="form-select" required>
                                <option value="">-- Choose Employee --</option>
                                <?php foreach ($employees as $emp): ?>
                                    <option value="<?= $emp['id']; ?>">
                                        <?= e($emp['last_name'] . ', ' . $emp['first_name']); ?> (<?= e($emp['department']); ?> - <?= e($emp['position'] ?? 'Staff'); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Equipment to Turn Over <span class="text-danger">*</span></label>
                            <select name="device_id" class="form-select" required>
                                <option value="">-- Choose Equipment --</option>
                                <?php foreach ($devices as $dev): ?>
                                    <option value="<?= $dev['id']; ?>">
                                        <?= e($dev['asset_tag']); ?> &mdash; <?= e($dev['brand'] . ' ' . $dev['model']); ?> (SN: <?= e($dev['serial_number']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (empty($devices)): ?>
                                <small class="text-danger d-block mt-1">No devices currently available in Inventory.</small>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Turnover Date <span class="text-danger">*</span></label>
                            <input type="date" name="assigned_date" class="form-control" required value="<?= date('Y-m-d'); ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Condition <span class="text-danger">*</span></label>
                            <select name="condition_on_assign" class="form-select" required>
                                <option value="New">New</option>
                                <option value="Good" selected>Good</option>
                                <option value="Fair">Fair</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold small">Inclusions / Accessories (Appears under 'OTHERS' in G2 Form)</label>
                            <input type="text" name="inclusions" class="form-control" value="BOX AND CHARGER" placeholder="e.g. BOX AND CHARGER, BAG, MOUSE">
                        </div>
                    </div>

                    <hr class="my-4">

                    <div class="d-flex justify-content-end gap-2">
                        <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary px-4 fw-semibold" <?= empty($devices) ? 'disabled' : ''; ?>>
                            <i class="bi bi-check2-circle me-1"></i> Save & Generate Liability
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>