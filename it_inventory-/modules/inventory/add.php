<?php
// C:\xampp\htdocs\it_inventory\modules\inventory\add.php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth_check.php';

// Helper: Function para mag-generate ng garantisadong bago at bakanteng Asset Tag
function generate_guaranteed_asset_tag(PDO $pdo): string {
    $stmt = $pdo->query("SELECT asset_tag FROM devices WHERE asset_tag LIKE 'IT-%'");
    $all_tags = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $highest = 0;
    foreach ($all_tags as $t) {
        if (preg_match('/^IT-(\d+)$/i', trim($t), $matches)) {
            $val = (int)$matches[1];
            if ($val > $highest) {
                $highest = $val;
            }
        }
    }

    // Siguraduhing walang kapareho sa pamamagitan ng loop check sa database
    $candidate = $highest + 1;
    do {
        $tag = sprintf('IT-%04d', $candidate);
        $chk = $pdo->prepare("SELECT COUNT(*) FROM devices WHERE asset_tag = :tag");
        $chk->execute([':tag' => $tag]);
        if ($chk->fetchColumn() == 0) {
            return $tag; // Ito ang susunod na bakanteng tag!
        }
        $candidate++;
    } while (true);
}

// I-preview ang susunod na tag sa screen
$preview_tag = generate_guaranteed_asset_tag($pdo);

// Fetch Categories
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll();

$errors = [];

// Form submission bago mag-output ng HTML
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = "Security check failed (invalid token). Please refresh.";
    } else {
        $category_id      = (int)($_POST['category_id'] ?? 0);
        $brand            = trim($_POST['brand'] ?? '');
        $model            = trim($_POST['model'] ?? '');
        $serial_number    = trim($_POST['serial_number'] ?? '');
        $location         = trim($_POST['location'] ?? 'IT Storage');
        $purchase_date    = !empty($_POST['purchase_date']) ? $_POST['purchase_date'] : date('Y-m-d');
        $device_condition = $_POST['device_condition'] ?? 'Good';
        $status           = $_POST['status'] ?? 'Available';
        $remarks          = trim($_POST['remarks'] ?? '');

        // Validation para sa ibang fields
        if (empty($category_id)) { $errors[] = "Please select a category."; }
        if (empty($brand)) { $errors[] = "Brand is required."; }
        if (empty($model)) { $errors[] = "Model is required."; }
        if (empty($serial_number)) { $errors[] = "Serial number is required."; }

        // Tanging Serial Number na lang ang iche-check kung naulit sa ibang device
        if (empty($errors)) {
            $check_sn = $pdo->prepare("SELECT asset_tag, brand, model FROM devices WHERE serial_number = :sn LIMIT 1");
            $check_sn->execute([':sn' => $serial_number]);
            $existing = $check_sn->fetch();
            if ($existing) {
                $errors[] = "Ang Serial Number na <strong>" . sanitize($serial_number) . "</strong> ay nakarehistro na sa <strong>" . sanitize($existing['asset_tag']) . " (" . sanitize($existing['brand'] . ' ' . $existing['model']) . ")</strong>. Maglagay ng ibang Serial Number.";
            }
        }

        // KUSA AT AUTOMATIC NANG I-GENERATE NG SYSTEM ANG ASSET TAG BAGO I-INSERT
        if (empty($errors)) {
            try {
                // Generate fresh, guaranteed unique asset tag
                $final_asset_tag = generate_guaranteed_asset_tag($pdo);

                $insert_sql = "
                    INSERT INTO devices (asset_tag, category_id, brand, model, serial_number, location, purchase_date, device_condition, status, remarks)
                    VALUES (:asset_tag, :category_id, :brand, :model, :serial_number, :location, :purchase_date, :device_condition, :status, :remarks)
                ";
                $insert_stmt = $pdo->prepare($insert_sql);
                $insert_stmt->execute([
                    ':asset_tag'        => $final_asset_tag,
                    ':category_id'      => $category_id,
                    ':brand'            => $brand,
                    ':model'            => $model,
                    ':serial_number'    => $serial_number,
                    ':location'         => $location,
                    ':purchase_date'    => $purchase_date,
                    ':device_condition' => $device_condition,
                    ':status'           => $status,
                    ':remarks'          => $remarks
                ]);

                $new_id = $pdo->lastInsertId();
                log_activity($pdo, $_SESSION['user_id'], 'CREATE_DEVICE', 'Device', $new_id, "Added equipment {$final_asset_tag} ({$brand} {$model})");

                set_flash('success', "Matagumpay na nairehistro ang gamit na may Asset Tag: <strong>{$final_asset_tag}</strong>!");
                redirect('modules/inventory/index.php');
            } catch (PDOException $e) {
                $errors[] = "Database Error: " . $e->getMessage();
            }
        }
    }
}

$page_title = "Add Equipment";
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h3 class="fw-bold mb-0">Register New Equipment</h3>
                <span class="text-muted small">Enter hardware specifications and serial numbers</span>
            </div>
            <a href="index.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Back to Inventory
            </a>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger py-2">
                <ul class="mb-0 ps-3">
                    <?php foreach ($errors as $err): ?>
                        <li><?= $err; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm">
            <div class="card-body p-4">
                <form action="add.php" method="POST">
                    <?= csrf_field(); ?>

                    <div class="row g-3">
                        <!-- Automatic Asset Tag Preview (Disabled para hindi na kailangang galawin) -->
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small text-success">
                                <i class="bi bi-shield-check me-1"></i>  Asset Tag
                            </label>
                            <div class="input-group">
                                
                                <input type="text" class="form-control fw-bold text-success bg-light" value="<?= e($preview_tag); ?>" readonly>
                            </div>
                            <small class="text-muted" style="font-size:0.75rem;">AUTOMATIC.</small>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Category <span class="text-danger">*</span></label>
                            <select name="category_id" class="form-select" required>
                                <option value="">-- Select Category --</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id']; ?>" <?= (($_POST['category_id'] ?? '') == $cat['id']) ? 'selected' : ''; ?>>
                                        <?= e($cat['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Serial Number <span class="text-danger">*</span></label>
                            <input type="text" name="serial_number" class="form-control font-monospace" required placeholder="e.g. SN-8921820" value="<?= e($_POST['serial_number'] ?? ''); ?>">
                            <small class="text-muted" style="font-size:0.75rem;">Unique hardware serial number.</small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Brand / Manufacturer <span class="text-danger">*</span></label>
                            <input type="text" name="brand" class="form-control" required placeholder="e.g. Dell, Lenovo, HP, Cisco" value="<?= e($_POST['brand'] ?? ''); ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Model <span class="text-danger">*</span></label>
                            <input type="text" name="model" class="form-control" required placeholder="e.g. ThinkPad T14, Latitude 5420" value="<?= e($_POST['model'] ?? ''); ?>">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Physical Location</label>
                            <input type="text" name="location" class="form-control" value="<?= e($_POST['location'] ?? 'IT Storage Room'); ?>">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Purchase Date</label>
                            <input type="date" name="purchase_date" class="form-control" value="<?= e($_POST['purchase_date'] ?? date('Y-m-d')); ?>">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Device Condition <span class="text-danger">*</span></label>
                            <select name="device_condition" class="form-select" required>
                                <option value="New" <?= (($_POST['device_condition'] ?? '') === 'New') ? 'selected' : ''; ?>>New</option>
                                <option value="Good" <?= (($_POST['device_condition'] ?? 'Good') === 'Good') ? 'selected' : ''; ?>>Good</option>
                                <option value="Fair" <?= (($_POST['device_condition'] ?? '') === 'Fair') ? 'selected' : ''; ?>>Fair</option>
                                <option value="Damaged" <?= (($_POST['device_condition'] ?? '') === 'Damaged') ? 'selected' : ''; ?>>Damaged</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Initial Status <span class="text-danger">*</span></label>
                            <select name="status" class="form-select" required>
                                <option value="Available" <?= (($_POST['status'] ?? 'Available') === 'Available') ? 'selected' : ''; ?>>Available (In Stock)</option>
                                <option value="Under Repair" <?= (($_POST['status'] ?? '') === 'Under Repair') ? 'selected' : ''; ?>>Under Repair</option>
                                <option value="Retired" <?= (($_POST['status'] ?? '') === 'Retired') ? 'selected' : ''; ?>>Retired</option>
                            </select>
                        </div>

                        <div class="col-md-8">
                            <label class="form-label fw-semibold small">Remarks / Notes</label>
                            <input type="text" name="remarks" class="form-control" placeholder="e.g. 16GB RAM, 512GB SSD, Core i5" value="<?= e($_POST['remarks'] ?? ''); ?>">
                        </div>
                    </div>

                    <hr class="my-4">

                    <div class="d-flex justify-content-end gap-2">
                        <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary px-4 fw-semibold">
                            <i class="bi bi-check2-circle me-1"></i> Save Equipment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>