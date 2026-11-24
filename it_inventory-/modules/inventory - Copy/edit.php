<?php
// C:\xampp\htdocs\it_inventory\modules\inventory\edit.php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth_check.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    set_flash('danger', 'Invalid device ID.');
    redirect('modules/inventory/index.php');
}

// Fetch Device
$stmt = $pdo->prepare("SELECT * FROM devices WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $id]);
$device = $stmt->fetch();

if (!$device) {
    set_flash('danger', 'Equipment record not found.');
    redirect('modules/inventory/index.php');
}

$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll();
$errors = [];

// Handle Form Submission BEFORE HTML output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = "Security check failed. Please refresh and try again.";
    } else {
        $asset_tag        = trim($_POST['asset_tag'] ?? '');
        $category_id      = (int)($_POST['category_id'] ?? 0);
        $brand            = trim($_POST['brand'] ?? '');
        $model            = trim($_POST['model'] ?? '');
        $serial_number    = trim($_POST['serial_number'] ?? '');
        $location         = trim($_POST['location'] ?? 'IT Storage');
        $purchase_date    = !empty($_POST['purchase_date']) ? $_POST['purchase_date'] : null;
        $device_condition = $_POST['device_condition'] ?? 'Good';
        $status           = $_POST['status'] ?? $device['status'];
        $remarks          = trim($_POST['remarks'] ?? '');

        if (empty($asset_tag)) { $errors[] = "Asset tag is required."; }
        if (empty($category_id)) { $errors[] = "Category is required."; }
        if (empty($brand)) { $errors[] = "Brand is required."; }
        if (empty($model)) { $errors[] = "Model is required."; }
        if (empty($serial_number)) { $errors[] = "Serial number is required."; }

        // Duplicate checks
        if (empty($errors)) {
            $chk = $pdo->prepare("SELECT id FROM devices WHERE (asset_tag = :tag OR serial_number = :sn) AND id != :id LIMIT 1");
            $chk->execute([':tag' => $asset_tag, ':sn' => $serial_number, ':id' => $id]);
            if ($chk->fetch()) {
                $errors[] = "Another item is already using this Asset Tag or Serial Number.";
            }
        }

        // Prevent setting to 'Available' directly if active assignment exists
        if ($status === 'Available' && $device['status'] === 'Assigned') {
            $active_check = $pdo->prepare("SELECT id FROM assignments WHERE device_id = :dev_id AND status = 'Active' LIMIT 1");
            $active_check->execute([':dev_id' => $id]);
            if ($active_check->fetch()) {
                $errors[] = "This device is currently assigned to an employee. Please process a Return in the Assignments module first.";
            }
        }

        if (empty($errors)) {
            try {
                $update_sql = "
                    UPDATE devices SET 
                        asset_tag = :asset_tag,
                        category_id = :category_id,
                        brand = :brand,
                        model = :model,
                        serial_number = :serial_number,
                        location = :location,
                        purchase_date = :purchase_date,
                        device_condition = :device_condition,
                        status = :status,
                        remarks = :remarks
                    WHERE id = :id
                ";
                $update_stmt = $pdo->prepare($update_sql);
                $update_stmt->execute([
                    ':asset_tag'        => $asset_tag,
                    ':category_id'      => $category_id,
                    ':brand'            => $brand,
                    ':model'            => $model,
                    ':serial_number'    => $serial_number,
                    ':location'         => $location,
                    ':purchase_date'    => $purchase_date,
                    ':device_condition' => $device_condition,
                    ':status'           => $status,
                    ':remarks'          => $remarks,
                    ':id'               => $id
                ]);

                log_activity($pdo, $_SESSION['user_id'], 'UPDATE_DEVICE', 'Device', $id, "Updated device details for {$asset_tag}");
                set_flash('success', "Equipment {$asset_tag} updated successfully.");
                redirect("modules/inventory/view.php?id={$id}");
            } catch (PDOException $e) {
                $errors[] = "Database error: " . $e->getMessage();
            }
        }
    }
}

// Include HTML layout after redirects
$page_title = "Edit Equipment";
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h3 class="fw-bold mb-0">Edit Equipment</h3>
                <span class="text-muted small">Update specifications, serial numbers, and condition</span>
            </div>
            <a href="view.php?id=<?= $device['id']; ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Back to View
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
                <form action="edit.php?id=<?= $id; ?>" method="POST">
                    <?= csrf_field(); ?>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Asset Tag <span class="text-danger">*</span></label>
                            <input type="text" name="asset_tag" class="form-control" required value="<?= e($device['asset_tag']); ?>">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Category <span class="text-danger">*</span></label>
                            <select name="category_id" class="form-select" required>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id']; ?>" <?= ($device['category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                        <?= e($cat['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Serial Number <span class="text-danger">*</span></label>
                            <input type="text" name="serial_number" class="form-control font-monospace" required value="<?= e($device['serial_number']); ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Brand <span class="text-danger">*</span></label>
                            <input type="text" name="brand" class="form-control" required value="<?= e($device['brand']); ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Model <span class="text-danger">*</span></label>
                            <input type="text" name="model" class="form-control" required value="<?= e($device['model']); ?>">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Location</label>
                            <input type="text" name="location" class="form-control" value="<?= e($device['location']); ?>">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Purchase Date</label>
                            <input type="date" name="purchase_date" class="form-control" value="<?= e($device['purchase_date']); ?>">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Condition <span class="text-danger">*</span></label>
                            <select name="device_condition" class="form-select" required>
                                <option value="New" <?= ($device['device_condition'] === 'New') ? 'selected' : ''; ?>>New</option>
                                <option value="Good" <?= ($device['device_condition'] === 'Good') ? 'selected' : ''; ?>>Good</option>
                                <option value="Fair" <?= ($device['device_condition'] === 'Fair') ? 'selected' : ''; ?>>Fair</option>
                                <option value="Damaged" <?= ($device['device_condition'] === 'Damaged') ? 'selected' : ''; ?>>Damaged</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Status <span class="text-danger">*</span></label>
                            <select name="status" class="form-select" required>
                                <option value="Available" <?= ($device['status'] === 'Available') ? 'selected' : ''; ?>>Available</option>
                                <option value="Assigned" <?= ($device['status'] === 'Assigned') ? 'selected' : ''; ?> <?= ($device['status'] === 'Assigned') ? '' : 'disabled'; ?>>Assigned (Managed in Assignments)</option>
                                <option value="Under Repair" <?= ($device['status'] === 'Under Repair') ? 'selected' : ''; ?>>Under Repair</option>
                                <option value="Retired" <?= ($device['status'] === 'Retired') ? 'selected' : ''; ?>>Retired / Disposed</option>
                            </select>
                        </div>

                        <div class="col-md-8">
                            <label class="form-label fw-semibold small">Remarks / Notes</label>
                            <input type="text" name="remarks" class="form-control" value="<?= e($device['remarks']); ?>">
                        </div>
                    </div>

                    <hr class="my-4">

                    <div class="d-flex justify-content-end gap-2">
                        <a href="view.php?id=<?= $id; ?>" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-save me-1"></i> Update Equipment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>