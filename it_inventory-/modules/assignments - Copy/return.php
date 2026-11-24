<?php
// C:\xampp\htdocs\it_inventory\modules\assignments\return.php

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
           e.employee_id, e.first_name, e.last_name, e.department
    FROM assignments a
    JOIN devices d ON a.device_id = d.id
    JOIN employees e ON a.employee_id = e.id
    WHERE a.id = :id AND a.status = 'Active'
    LIMIT 1
");
$stmt->execute([':id' => $id]);
$assignment = $stmt->fetch();

if (!$assignment) {
    set_flash('danger', 'Active assignment record not found.');
    redirect('modules/assignments/index.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = "Security check failed. Please refresh.";
    } else {
        $return_date         = trim($_POST['return_date'] ?? date('Y-m-d'));
        $condition_on_return = $_POST['condition_on_return'] ?? 'Good';
        $device_status       = $_POST['device_status'] ?? 'Available';
        $remarks             = trim($_POST['remarks'] ?? '');

        if (empty($return_date)) { $errors[] = "Return date is required."; }

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                $upd_assign = $pdo->prepare("
                    UPDATE assignments SET 
                        return_date = :return_date,
                        returned_to = :returned_to,
                        condition_on_return = :condition_on_return,
                        status = 'Returned',
                        remarks = CONCAT(IFNULL(remarks, ''), ' [Return Note: ', :remarks, ']')
                    WHERE id = :id
                ");
                $upd_assign->execute([
                    ':return_date'         => $return_date,
                    ':returned_to'         => $_SESSION['user_id'],
                    ':condition_on_return' => $condition_on_return,
                    ':remarks'             => $remarks,
                    ':id'                  => $id
                ]);

                $upd_device = $pdo->prepare("
                    UPDATE devices SET 
                        status = :status,
                        device_condition = :condition
                    WHERE id = :device_id
                ");
                $upd_device->execute([
                    ':status'    => $device_status,
                    ':condition' => $condition_on_return,
                    ':device_id' => $assignment['device_id']
                ]);

                $pdo->commit();

                log_activity($pdo, $_SESSION['user_id'], 'RETURN_DEVICE', 'Assignment', $id, "Device {$assignment['asset_tag']} returned by {$assignment['first_name']} {$assignment['last_name']}");
                set_flash('success', "Equipment {$assignment['asset_tag']} successfully returned.");
                redirect('modules/assignments/index.php');
            } catch (PDOException $e) {
                $pdo->rollBack();
                $errors[] = "Database Error: " . $e->getMessage();
            }
        }
    }
}

$page_title = "Return Equipment";
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h3 class="fw-bold mb-0">Return Equipment</h3>
                <span class="text-muted small">Surrender custody and return item to inventory</span>
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
                        <strong class="d-block text-dark"><?= e($assignment['asset_tag']); ?> &bull; <?= e($assignment['brand'] . ' ' . $assignment['model']); ?></strong>
                        <span class="text-muted font-monospace">SN: <?= e($assignment['serial_number']); ?></span>
                    </div>
                    <div class="col-sm-6">
                        <span class="text-muted">Held By:</span>
                        <strong class="d-block text-dark"><?= e($assignment['first_name'] . ' ' . $assignment['last_name']); ?> (<?= e($assignment['employee_id']); ?>)</strong>
                        <span class="text-muted">Issued on: <?= e($assignment['assigned_date']); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-body p-4">
                <form action="return.php?id=<?= $id; ?>" method="POST">
                    <?= csrf_field(); ?>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Return Date <span class="text-danger">*</span></label>
                            <input type="date" name="return_date" class="form-control" required value="<?= date('Y-m-d'); ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Condition Upon Return <span class="text-danger">*</span></label>
                            <select name="condition_on_return" class="form-select" required>
                                <option value="Good">Good</option>
                                <option value="Fair">Fair</option>
                                <option value="Damaged">Damaged</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">New Inventory Status <span class="text-danger">*</span></label>
                            <select name="device_status" class="form-select" required>
                                <option value="Available">Available (Ready to reissue)</option>
                                <option value="Under Repair">Under Repair (Needs maintenance)</option>
                                <option value="Retired">Retired / Disposed</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Received By</label>
                            <input type="text" class="form-control bg-light" disabled value="<?= e($_SESSION['full_name']); ?>">
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold small">Return Remarks / Inspection</label>
                            <input type="text" name="remarks" class="form-control" placeholder="e.g. Returned complete with accessories, no issues">
                        </div>
                    </div>

                    <hr class="my-4">

                    <div class="d-flex justify-content-end gap-2">
                        <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-success px-4" onclick="return confirm('Confirm returning this device to stock?');">
                            <i class="bi bi-arrow-return-left me-1"></i> Complete Return
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>