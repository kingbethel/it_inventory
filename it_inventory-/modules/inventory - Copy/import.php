<?php
// C:\xampp\htdocs\it_inventory\modules\inventory\import.php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth_check.php';

// Helper: Function para mag-generate ng next available Asset Tag
function get_next_available_tag(PDO $pdo, int &$current_counter): string {
    do {
        $tag = sprintf('IT-%04d', $current_counter);
        $chk = $pdo->prepare("SELECT COUNT(*) FROM devices WHERE asset_tag = :tag");
        $chk->execute([':tag' => $tag]);
        $current_counter++;
        if ($chk->fetchColumn() == 0) {
            return $tag;
        }
    } while (true);
}

// 1. Download Pre-Formatted CSV Template
if (isset($_GET['download_template'])) {
    if (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=g2_inventory_import_template.csv');

    $out = fopen('php://output', 'w');
    // Header Row
    fputcsv($out, ['category_name', 'brand', 'model', 'serial_number', 'location', 'purchase_date', 'device_condition', 'status', 'remarks']);
    // Sample Rows
    fputcsv($out, ['Laptop', 'Lenovo', 'ThinkPad T14', 'SN-LEN-9901', 'IT Storage', date('Y-m-d'), 'New', 'Available', 'Core i5, 16GB, 512GB SSD']);
    fputcsv($out, ['Laptop', 'Dell', 'Latitude 5420', 'SN-DEL-9902', 'IT Storage', date('Y-m-d'), 'Good', 'Available', 'Core i7, 16GB RAM']);
    fputcsv($out, ['Monitor', 'Dell', 'P2419H', 'SN-DEL-4411', 'IT Storage', date('Y-m-d'), 'New', 'Available', '24 inch IPS HDMI']);
    fputcsv($out, ['Other IT Equipment', 'Redmi', 'A7 Pro', '7441166QH16620', 'IT Storage', date('Y-m-d'), 'New', 'Available', 'Company Phone']);
    fclose($out);
    exit;
}

$errors = [];
$imported_count = 0;
$skipped_serials = [];

// 2. Process CSV Import Form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = "Security check failed. Please refresh.";
    } else {
        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = "Please select a valid CSV file.";
        } else {
            $file = $_FILES['csv_file'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if ($ext !== 'csv') {
                $errors[] = "Only comma-delimited (.csv) files are supported.";
            } else {
                $handle = fopen($file['tmp_name'], 'r');
                if ($handle === false) {
                    $errors[] = "Unable to read the uploaded CSV file.";
                } else {
                    // Check and strip UTF-8 BOM
                    $bom = fread($handle, 3);
                    if ($bom !== "\xEF\xBB\xBF") {
                        rewind($handle);
                    }

                    // Skip header row
                    $header = fgetcsv($handle);

                    // Pre-load categories into cache array (name => id)
                    $cats = $pdo->query("SELECT id, name FROM categories")->fetchAll();
                    $category_map = [];
                    foreach ($cats as $c) {
                        $category_map[strtolower(trim($c['name']))] = (int)$c['id'];
                    }

                    // Determine highest current asset tag number
                    $all_tags = $pdo->query("SELECT asset_tag FROM devices WHERE asset_tag LIKE 'IT-%'")->fetchAll(PDO::FETCH_COLUMN);
                    $highest = 0;
                    foreach ($all_tags as $t) {
                        if (preg_match('/^IT-(\d+)$/i', trim($t), $m)) {
                            $val = (int)$m[1];
                            if ($val > $highest) { $highest = $val; }
                        }
                    }
                    $tag_counter = $highest + 1;

                    // Prepared Statements
                    $check_sn_stmt = $pdo->prepare("SELECT id FROM devices WHERE serial_number = :sn LIMIT 1");
                    $insert_stmt = $pdo->prepare("
                        INSERT INTO devices (asset_tag, category_id, brand, model, serial_number, location, purchase_date, device_condition, status, remarks)
                        VALUES (:asset_tag, :cat_id, :brand, :model, :serial_number, :location, :purchase_date, :device_condition, :status, :remarks)
                    ");

                    $pdo->beginTransaction();
                    $row_num = 1;

                    try {
                        while (($row = fgetcsv($handle, 1000, ",")) !== false) {
                            $row_num++;

                            // Skip empty row
                            if (empty(array_filter($row))) {
                                continue;
                            }

                            $cat_name   = trim($row[0] ?? '');
                            $brand      = trim($row[1] ?? '');
                            $model      = trim($row[2] ?? '');
                            $serial_num = trim($row[3] ?? '');
                            $location   = !empty(trim($row[4] ?? '')) ? trim($row[4]) : 'IT Storage';
                            $p_date     = !empty(trim($row[5] ?? '')) ? trim($row[5]) : date('Y-m-d');
                            $condition  = in_array(trim($row[6] ?? ''), ['New', 'Good', 'Fair', 'Damaged']) ? trim($row[6]) : 'Good';
                            $status     = in_array(trim($row[7] ?? ''), ['Available', 'Under Repair', 'Retired']) ? trim($row[7]) : 'Available';
                            $remarks    = trim($row[8] ?? '');

                            // Validation: Brand, Model, Serial
                            if (empty($brand) || empty($model) || empty($serial_num)) {
                                continue;
                            }

                            // Match Category or default to 'Other IT Equipment'
                            $cat_key = strtolower($cat_name);
                            $cat_id = $category_map[$cat_key] ?? $category_map['other it equipment'] ?? 1;

                            // Check duplicate Serial Number
                            $check_sn_stmt->execute([':sn' => $serial_num]);
                            if ($check_sn_stmt->fetch()) {
                                $skipped_serials[] = $serial_num;
                                continue; // Skip existing serial to prevent collision
                            }

                            // Auto-generate tag
                            $new_asset_tag = get_next_available_tag($pdo, $tag_counter);

                            $insert_stmt->execute([
                                ':asset_tag'        => $new_asset_tag,
                                ':cat_id'           => $cat_id,
                                ':brand'            => $brand,
                                ':model'            => $model,
                                ':serial_number'    => $serial_num,
                                ':location'         => $location,
                                ':purchase_date'    => $p_date,
                                ':device_condition' => $condition,
                                ':status'           => $status,
                                ':remarks'          => $remarks
                            ]);

                            $imported_count++;
                        }

                        $pdo->commit();
                        fclose($handle);

                        log_activity($pdo, $_SESSION['user_id'], 'BULK_IMPORT_DEVICES', 'Device', null, "Imported {$imported_count} devices via CSV.");

                        $msg = "Bulk Equipment Import Complete: <strong>{$imported_count}</strong> devices successfully registered.";
                        if (!empty($skipped_serials)) {
                            $msg .= " (Skipped " . count($skipped_serials) . " items due to existing duplicate serial numbers).";
                        }

                        set_flash('success', $msg);
                        redirect('modules/inventory/index.php');
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        fclose($handle);
                        $errors[] = "Error on row {$row_num}: " . $e->getMessage();
                    }
                }
            }
        }
    }
}

$page_title = "Bulk Import Equipment";
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h3 class="fw-bold mb-0">Bulk Import Equipment</h3>
                <span class="text-muted small">Upload multiple hardware assets at once using an Excel CSV file</span>
            </div>
            <a href="index.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Back to Inventory
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

        <!-- Step 1: Download Template -->
        <div class="card mb-4 shadow-sm border border-primary border-opacity-25 bg-light">
            <div class="card-body p-4 d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="fw-bold text-primary mb-1"><i class="bi bi-file-earmark-spreadsheet me-2"></i>Step 1: Download Equipment CSV Template</h6>
                    <small class="text-muted">Use this pre-configured CSV with sample data. Asset tags will be automatically numbered by the system upon upload.</small>
                </div>
                <a href="import.php?download_template=1" class="btn btn-success fw-semibold text-nowrap">
                    <i class="bi bi-download me-1"></i> Download Template (.CSV)
                </a>
            </div>
        </div>

        <!-- Step 2: Upload CSV -->
        <div class="card shadow-sm border">
            <div class="card-body p-4">
                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-cloud-arrow-up me-2"></i>Step 2: Upload Filled CSV File</h6>

                <form action="import.php" method="POST" enctype="multipart/form-data">
                    <?= csrf_field(); ?>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Select CSV File <span class="text-danger">*</span></label>
                        <input type="file" name="csv_file" class="form-control" required accept=".csv">
                        <small class="text-muted">Must be a comma-delimited <strong>.csv</strong> file saved from Excel.</small>
                    </div>

                    <div class="alert alert-secondary py-2 small mb-4">
                        <strong>Expected Columns:</strong><br>
                        <code>category_name, brand, model, serial_number, location, purchase_date, device_condition, status, remarks</code>
                        <div class="mt-1 text-muted">
                            <em>Note: Asset tags are automatically assigned (e.g. IT-0001, IT-0002). Existing serial numbers will be skipped to prevent duplicates.</em>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary px-4 fw-semibold">
                            <i class="bi bi-upload me-1"></i> Start Import
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>