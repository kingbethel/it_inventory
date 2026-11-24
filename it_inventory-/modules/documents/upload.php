<?php
// C:\xampp\htdocs\it_inventory\modules\documents\upload.php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth_check.php';

$preset_assignment_id = (int)($_GET['assignment_id'] ?? 0);
$preset_employee_id   = (int)($_GET['employee_id'] ?? 0);

// Fetch all active assignments for dropdown
$assignments = $pdo->query("
    SELECT a.id, a.assigned_date,
           d.id AS dev_id, d.asset_tag, d.brand, d.model,
           e.id AS emp_id, e.first_name, e.last_name, e.employee_id
    FROM assignments a
    JOIN devices d ON a.device_id = d.id
    JOIN employees e ON a.employee_id = e.id
    WHERE a.status = 'Active' OR a.id = {$preset_assignment_id}
    ORDER BY a.id DESC
")->fetchAll();

// Fetch all active employees (in case uploading document without a specific hardware assignment)
$employees = $pdo->query("
    SELECT id, employee_id, first_name, last_name, department 
    FROM employees 
    WHERE employment_status = 'Active' 
    ORDER BY last_name ASC
")->fetchAll();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = "Security check failed. Please refresh.";
    } else {
        $assignment_id = (int)($_POST['assignment_id'] ?? 0);
        $employee_id   = (int)($_POST['employee_id'] ?? 0);
        $document_type = $_POST['document_type'] ?? 'Liability Form';
        $remarks       = trim($_POST['remarks'] ?? '');

        if (!isset($_FILES['document_file']) || $_FILES['document_file']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = "Please select a valid file to upload.";
        } else {
            $file = $_FILES['document_file'];
            $max_size = 10 * 1024 * 1024; // 10MB limit

            if ($file['size'] > $max_size) {
                $errors[] = "File size exceeds the 10MB limit.";
            }

            $allowed_mimes = [
                'application/pdf' => 'pdf',
                'image/jpeg'      => 'jpg',
                'image/png'       => 'png'
            ];

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!array_key_exists($mime_type, $allowed_mimes)) {
                $errors[] = "Only PDF, JPG, and PNG files are accepted.";
            }

            if (empty($errors)) {
                $target_dir = __DIR__ . '/../../uploads/documents/';
                if (!is_dir($target_dir)) {
                    mkdir($target_dir, 0755, true);
                }

                $ext = $allowed_mimes[$mime_type];
                $secure_name = bin2hex(random_bytes(16)) . '.' . $ext;
                $target_path = $target_dir . $secure_name;

                if (move_uploaded_file($file['tmp_name'], $target_path)) {
                    $device_id = null;

                    // If linked to assignment, auto-detect device & employee
                    if ($assignment_id > 0) {
                        $info_stmt = $pdo->prepare("SELECT device_id, employee_id FROM assignments WHERE id = :id LIMIT 1");
                        $info_stmt->execute([':id' => $assignment_id]);
                        $assign_info = $info_stmt->fetch();
                        if ($assign_info) {
                            $device_id = $assign_info['device_id'];
                            $employee_id = $assign_info['employee_id'];
                        }
                    }

                    $ins = $pdo->prepare("
                        INSERT INTO documents (assignment_id, device_id, employee_id, document_type, original_name, file_path, file_size, mime_type, uploaded_by, remarks)
                        VALUES (:assignment_id, :device_id, :employee_id, :document_type, :original_name, :file_path, :file_size, :mime_type, :uploaded_by, :remarks)
                    ");
                    $ins->execute([
                        ':assignment_id' => $assignment_id > 0 ? $assignment_id : null,
                        ':device_id'     => $device_id,
                        ':employee_id'   => $employee_id > 0 ? $employee_id : null,
                        ':document_type' => $document_type,
                        ':original_name' => basename($file['name']),
                        ':file_path'     => $secure_name,
                        ':file_size'     => $file['size'],
                        ':mime_type'     => $mime_type,
                        ':uploaded_by'   => $_SESSION['user_id'],
                        ':remarks'       => $remarks
                    ]);

                    $doc_id = $pdo->lastInsertId();
                    log_activity($pdo, $_SESSION['user_id'], 'UPLOAD_DOCUMENT', 'Document', $doc_id, "Uploaded {$document_type}: {$file['name']}");

                    set_flash('success', "Liability form successfully uploaded and linked.");
                    if ($employee_id > 0) {
                        redirect("modules/employees/view.php?id={$employee_id}");
                    } else {
                        redirect('modules/documents/index.php');
                    }
                } else {
                    $errors[] = "Failed to save file. Check directory permissions.";
                }
            }
        }
    }
}

$page_title = "Upload Document";
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h3 class="fw-bold mb-0">Upload Liability / Document</h3>
                <span class="text-muted small">Link accountability slips or files directly to staff and hardware</span>
            </div>
            <a href="index.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Back
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
                <form action="upload.php" method="POST" enctype="multipart/form-data">
                    <?= csrf_field(); ?>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Document Type <span class="text-danger">*</span></label>
                            <select name="document_type" class="form-select" required>
                                <option value="Liability Form" selected>Liability Form (Signed Slip)</option>
                                <option value="Purchase Invoice">Purchase Invoice / Receipt</option>
                                <option value="Warranty">Warranty Certificate</option>
                                <option value="Maintenance Report">Maintenance Report</option>
                                <option value="Other">Other Document</option>
                            </select>
                        </div>

                        <!-- Link to Hardware Assignment (Pinakamadali) -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Link to Hardware Assignment</label>
                            <select name="assignment_id" class="form-select">
                                <option value="">-- Optional (Piliin kung may hawak na device) --</option>
                                <?php foreach ($assignments as $a): ?>
                                    <option value="<?= $a['id']; ?>" <?= ($preset_assignment_id == $a['id']) ? 'selected' : ''; ?>>
                                        <?= e($a['first_name'] . ' ' . $a['last_name']); ?> &bull; <?= e($a['asset_tag']); ?> (<?= e($a['brand'] . ' ' . $a['model']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- O piliin ang Employee directly -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Link Direct to Employees</label>
                            <select name="employee_id" class="form-select">
                                <option value="">-- Choose Employees --</option>
                                <?php foreach ($employees as $emp): ?>
                                    <option value="<?= $emp['id']; ?>" <?= ($preset_employee_id == $emp['id']) ? 'selected' : ''; ?>>
                                        <?= e($emp['last_name'] . ', ' . $emp['first_name']); ?> (<?= e($emp['employee_id']); ?> - <?= e($emp['department']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Select File (PDF, JPG, PNG &bull; Max 10MB) <span class="text-danger">*</span></label>
                            <input type="file" name="document_file" class="form-control" required accept=".pdf, .jpg, .jpeg, .png">
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold small">Remarks / Inspection Notes</label>
                            <input type="text" name="remarks" class="form-control" placeholder="Halimbawa: Signed hardcopy received on time">
                        </div>
                    </div>

                    <hr class="my-4">

                    <div class="d-flex justify-content-end gap-2">
                        <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-cloud-arrow-up me-1"></i> Upload & Link
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>