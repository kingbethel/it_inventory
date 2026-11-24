<?php
// C:\xampp\htdocs\it_inventory\modules\employees\edit.php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth_check.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    set_flash('danger', 'Invalid employee specified.');
    redirect('modules/employees/index.php');
}

$stmt = $pdo->prepare("SELECT * FROM employees WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $id]);
$employee = $stmt->fetch();

if (!$employee) {
    set_flash('danger', 'Employee record not found.');
    redirect('modules/employees/index.php');
}

$errors = [];

// Form submission processed BEFORE outputting HTML
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = "Security check failed. Please refresh.";
    } else {
        $employee_id       = trim($_POST['employee_id'] ?? '');
        $first_name        = trim($_POST['first_name'] ?? '');
        $last_name         = trim($_POST['last_name'] ?? '');
        $department        = trim($_POST['department'] ?? '');
        $position          = trim($_POST['position'] ?? '');
        $employment_status = $_POST['employment_status'] ?? 'Active';

        if (empty($employee_id)) { $errors[] = "Employee ID is required."; }
        if (empty($first_name)) { $errors[] = "First name is required."; }
        if (empty($last_name)) { $errors[] = "Last name is required."; }
        if (empty($department)) { $errors[] = "Department is required."; }

        // Duplicate check excluding self
        if (empty($errors)) {
            $chk = $pdo->prepare("SELECT id FROM employees WHERE employee_id = :emp_id AND id != :id LIMIT 1");
            $chk->execute([':emp_id' => $employee_id, ':id' => $id]);
            if ($chk->fetch()) {
                $errors[] = "Another employee already has the Employee ID '{$employee_id}'.";
            }
        }

        if (empty($errors)) {
            try {
                $update_stmt = $pdo->prepare("
                    UPDATE employees SET 
                        employee_id = :employee_id,
                        first_name = :first_name,
                        last_name = :last_name,
                        department = :department,
                        position = :position,
                        employment_status = :employment_status
                    WHERE id = :id
                ");
                $update_stmt->execute([
                    ':employee_id'       => $employee_id,
                    ':first_name'        => $first_name,
                    ':last_name'         => $last_name,
                    ':department'        => $department,
                    ':position'          => $position,
                    ':employment_status' => $employment_status,
                    ':id'                => $id
                ]);

                log_activity($pdo, $_SESSION['user_id'], 'UPDATE_EMPLOYEE', 'Employee', $id, "Updated details for employee {$employee_id}");
                set_flash('success', "Employee {$employee_id} updated successfully.");
                redirect("modules/employees/view.php?id={$id}");
            } catch (PDOException $e) {
                $errors[] = "Database error: " . $e->getMessage();
            }
        }
    }
}

$page_title = "Edit Employee";
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h3 class="fw-bold mb-0">Edit Employee Profile</h3>
                <span class="text-muted small">Update profile, job role, or employment status</span>
            </div>
            <a href="view.php?id=<?= $id; ?>" class="btn btn-outline-secondary btn-sm">
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
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Employee ID <span class="text-danger">*</span></label>
                            <input type="text" name="employee_id" class="form-control" required value="<?= e($employee['employee_id']); ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Employment Status <span class="text-danger">*</span></label>
                            <select name="employment_status" class="form-select" required>
                                <option value="Active" <?= ($employee['employment_status'] === 'Active') ? 'selected' : ''; ?>>Active</option>
                                <option value="Resigned" <?= ($employee['employment_status'] === 'Resigned') ? 'selected' : ''; ?>>Resigned</option>
                                <option value="Terminated" <?= ($employee['employment_status'] === 'Terminated') ? 'selected' : ''; ?>>Terminated</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">First Name <span class="text-danger">*</span></label>
                            <input type="text" name="first_name" class="form-control" required value="<?= e($employee['first_name']); ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Last Name <span class="text-danger">*</span></label>
                            <input type="text" name="last_name" class="form-control" required value="<?= e($employee['last_name']); ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Department <span class="text-danger">*</span></label>
                            <input type="text" name="department" class="form-control" required value="<?= e($employee['department']); ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Position</label>
                            <input type="text" name="position" class="form-control" value="<?= e($employee['position']); ?>">
                        </div>
                    </div>

                    <hr class="my-4">

                    <div class="d-flex justify-content-end gap-2">
                        <a href="view.php?id=<?= $id; ?>" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-save me-1"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>