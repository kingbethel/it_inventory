<?php
// C:\xampp\htdocs\it_inventory\modules\employees\add.php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth_check.php';

$errors = [];

// Form submission processed BEFORE outputting HTML
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = "Security check failed. Please refresh and try again.";
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

        // Check if Employee ID already exists
        if (empty($errors)) {
            $check = $pdo->prepare("SELECT id FROM employees WHERE employee_id = :emp_id LIMIT 1");
            $check->execute([':emp_id' => $employee_id]);
            if ($check->fetch()) {
                $errors[] = "An employee with Employee ID '{$employee_id}' is already registered.";
            }
        }

        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO employees (employee_id, first_name, last_name, department, position, employment_status)
                    VALUES (:employee_id, :first_name, :last_name, :department, :position, :employment_status)
                ");
                $stmt->execute([
                    ':employee_id'       => $employee_id,
                    ':first_name'        => $first_name,
                    ':last_name'         => $last_name,
                    ':department'        => $department,
                    ':position'          => $position,
                    ':employment_status' => $employment_status
                ]);

                $new_id = $pdo->lastInsertId();
                log_activity($pdo, $_SESSION['user_id'], 'CREATE_EMPLOYEE', 'Employee', $new_id, "Registered employee {$employee_id} ({$first_name} {$last_name})");

                set_flash('success', "Employee {$employee_id} ({$first_name} {$last_name}) added successfully.");
                redirect('modules/employees/index.php');
            } catch (PDOException $e) {
                $errors[] = "Database Error: " . $e->getMessage();
            }
        }
    }
}

$page_title = "Register Employee";
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h3 class="fw-bold mb-0">Register Employee</h3>
                <span class="text-muted small">Add staff member details for equipment tracking</span>
            </div>
            <a href="index.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Back to Directory
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
                <form action="add.php" method="POST">
                    <?= csrf_field(); ?>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Employee ID <span class="text-danger">*</span></label>
                            <input type="text" name="employee_id" class="form-control" required placeholder="e.g. EMP-0101" value="<?= e($_POST['employee_id'] ?? ''); ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Employment Status <span class="text-danger">*</span></label>
                            <select name="employment_status" class="form-select" required>
                                <option value="Active" <?= (($_POST['employment_status'] ?? 'Active') === 'Active') ? 'selected' : ''; ?>>Active</option>
                                <option value="Resigned" <?= (($_POST['employment_status'] ?? '') === 'Resigned') ? 'selected' : ''; ?>>Resigned</option>
                                <option value="Terminated" <?= (($_POST['employment_status'] ?? '') === 'Terminated') ? 'selected' : ''; ?>>Terminated</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">First Name <span class="text-danger">*</span></label>
                            <input type="text" name="first_name" class="form-control" required placeholder="e.g. Maria" value="<?= e($_POST['first_name'] ?? ''); ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Last Name <span class="text-danger">*</span></label>
                            <input type="text" name="last_name" class="form-control" required placeholder="e.g. Santos" value="<?= e($_POST['last_name'] ?? ''); ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Department <span class="text-danger">*</span></label>
                            <input type="text" name="department" class="form-control" required placeholder="e.g. IT, Finance, Operations, HR" value="<?= e($_POST['department'] ?? ''); ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Position / Job Title (Optional)</label>
                            <input type="text" name="position" class="form-control" placeholder="e.g. Senior Accountant" value="<?= e($_POST['position'] ?? ''); ?>">
                        </div>
                    </div>

                    <hr class="my-4">

                    <div class="d-flex justify-content-end gap-2">
                        <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-check2-circle me-1"></i> Save Employee
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>