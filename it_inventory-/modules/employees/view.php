<?php
// C:\xampp\htdocs\it_inventory\modules\employees\view.php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth_check.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    set_flash('danger', 'Invalid employee specified.');
    redirect('modules/employees/index.php');
}

// 1. Employee Profile
$stmt = $pdo->prepare("SELECT * FROM employees WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $id]);
$employee = $stmt->fetch();

if (!$employee) {
    set_flash('danger', 'Employee not found.');
    redirect('modules/employees/index.php');
}

// 2. Currently Assigned Devices kasama ang direct document ID kung may naka-attach na form
$active_dev_stmt = $pdo->prepare("
    SELECT a.id AS assignment_id, a.assigned_date, a.condition_on_assign,
           d.id AS device_id, d.asset_tag, d.brand, d.model, d.serial_number, c.name AS category_name,
           (SELECT doc.id FROM documents doc WHERE doc.assignment_id = a.id AND doc.document_type = 'Liability Form' LIMIT 1) as doc_id
    FROM assignments a
    JOIN devices d ON a.device_id = d.id
    JOIN categories c ON d.category_id = c.id
    WHERE a.employee_id = :emp_id AND a.status = 'Active'
    ORDER BY a.assigned_date DESC
");
$active_dev_stmt->execute([':emp_id' => $id]);
$active_devices = $active_dev_stmt->fetchAll();

// 3. Lahat ng Uploaded Documents / Liability Forms na naka-link sa Empleyadong ito
$docs_stmt = $pdo->prepare("
    SELECT doc.*, d.asset_tag, d.brand, d.model, u.full_name as uploaded_by_name
    FROM documents doc
    LEFT JOIN devices d ON doc.device_id = d.id
    LEFT JOIN users u ON doc.uploaded_by = u.id
    WHERE doc.employee_id = :emp_id
    ORDER BY doc.id DESC
");
$docs_stmt->execute([':emp_id' => $id]);
$employee_documents = $docs_stmt->fetchAll();

// 4. Past Devices History
$past_dev_stmt = $pdo->prepare("
    SELECT a.id AS assignment_id, a.assigned_date, a.return_date, a.status, a.condition_on_return,
           d.id AS device_id, d.asset_tag, d.brand, d.model, c.name AS category_name
    FROM assignments a
    JOIN devices d ON a.device_id = d.id
    JOIN categories c ON d.category_id = c.id
    WHERE a.employee_id = :emp_id AND a.status != 'Active'
    ORDER BY a.return_date DESC
");
$past_dev_stmt->execute([':emp_id' => $id]);
$past_devices = $past_dev_stmt->fetchAll();

$page_title = "Profile - " . $employee['first_name'] . ' ' . $employee['last_name'];
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-0"><?= e($employee['first_name'] . ' ' . $employee['last_name']); ?></h3>
        <span class="text-muted small">Employee ID: <strong><?= e($employee['employee_id']); ?></strong> &bull; Department: <strong><?= e($employee['department']); ?></strong></span>
    </div>
    <div class="d-flex gap-2">
        <a href="../documents/upload.php?employee_id=<?= $employee['id']; ?>" class="btn btn-warning btn-sm fw-semibold">
            <i class="bi bi-file-earmark-arrow-up me-1"></i> Upload Liability Form
        </a>
        <a href="../assignments/assign.php?employee_id=<?= $employee['id']; ?>" class="btn btn-success btn-sm">
            <i class="bi bi-laptop me-1"></i> Assign Device
        </a>
        <a href="edit.php?id=<?= $employee['id']; ?>" class="btn btn-primary btn-sm">
            <i class="bi bi-pencil me-1"></i> Edit Profile
        </a>
        <a href="index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Directory
        </a>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Profile Card -->
    <div class="col-lg-4">
        <div class="card h-100 shadow-sm">
            <div class="card-header bg-white py-3 fw-bold">
                <i class="bi bi-person text-primary me-2"></i>Staff Information
            </div>
            <div class="card-body p-0">
                <table class="table table-bordered mb-0 small">
                    <tbody>
                        <tr>
                            <th class="w-40 bg-light">Employee ID</th>
                            <td class="fw-bold text-primary"><?= e($employee['employee_id']); ?></td>
                        </tr>
                        <tr>
                            <th class="bg-light">Full Name</th>
                            <td><?= e($employee['first_name'] . ' ' . $employee['last_name']); ?></td>
                        </tr>
                        <tr>
                            <th class="bg-light">Department</th>
                            <td><span class="badge bg-light text-dark border"><?= e($employee['department']); ?></span></td>
                        </tr>
                        <tr>
                            <th class="bg-light">Position</th>
                            <td><?= !empty($employee['position']) ? e($employee['position']) : '-'; ?></td>
                        </tr>
                        <tr>
                            <th class="bg-light">Status</th>
                            <td>
                                <?php if ($employee['employment_status'] === 'Active'): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php elseif ($employee['employment_status'] === 'Resigned'): ?>
                                    <span class="badge bg-secondary">Resigned</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Terminated</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th class="bg-light">Record Created</th>
                            <td><?= date('M d, Y', strtotime($employee['created_at'])); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Active Hardware Held -->
    <div class="col-lg-8">
        <div class="card h-100 shadow-sm">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <span class="fw-bold"><i class="bi bi-laptop text-success me-2"></i>Currently Issued Hardware</span>
                <span class="badge bg-primary"><?= count($active_devices); ?> Active</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Asset Tag</th>
                                <th>Device Details</th>
                                <th>Category</th>
                                <th>Issued Date</th>
                                <th>Liability Form</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($active_devices)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">
                                        Walang gamit na naka-assign sa empleyadong ito sa kasalukuyan.<br>
                                        <a href="../assignments/assign.php?employee_id=<?= $employee['id']; ?>" class="btn btn-sm btn-outline-success mt-2">
                                            <i class="bi bi-plus-circle me-1"></i> Mag-assign ng Device
                                        </a>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($active_devices as $dev): ?>
                                    <tr>
                                        <td class="ps-3 fw-bold">
                                            <a href="../inventory/view.php?id=<?= $dev['device_id']; ?>" class="text-decoration-none text-primary">
                                                <?= e($dev['asset_tag']); ?>
                                            </a>
                                        </td>
                                        <td>
                                            <div class="fw-semibold text-dark"><?= e($dev['brand'] . ' ' . $dev['model']); ?></div>
                                            <span class="text-muted font-monospace" style="font-size:0.75rem;">SN: <?= e($dev['serial_number']); ?></span>
                                        </td>
                                        <td><span class="badge bg-light text-dark border"><?= e($dev['category_name']); ?></span></td>
                                        <td><?= e($dev['assigned_date']); ?></td>
                                        <td>
                                            <?php if (!empty($dev['doc_id'])): ?>
                                                <a href="../documents/view_file.php?id=<?= $dev['doc_id']; ?>" target="_blank" class="badge bg-success text-decoration-none">
                                                    <i class="bi bi-file-earmark-check me-1"></i> View Form
                                                </a>
                                            <?php else: ?>
                                                <a href="../documents/upload.php?assignment_id=<?= $dev['assignment_id']; ?>&employee_id=<?= $employee['id']; ?>" class="badge bg-warning text-dark text-decoration-none">
                                                    <i class="bi bi-upload me-1"></i> Attach Form
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Talaan ng Lahat ng Uploaded Liability Forms & Supporting Documents ni Employee -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <span class="fw-bold"><i class="bi bi-file-earmark-text text-warning me-2"></i>Uploaded Liability Forms & Documents</span>
        <a href="../documents/upload.php?employee_id=<?= $employee['id']; ?>" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-plus-lg me-1"></i> Upload New Document
        </a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Type</th>
                        <th>File Name</th>
                        <th>Related Equipment</th>
                        <th>Date Uploaded</th>
                        <th>Uploaded By</th>
                        <th class="text-end pe-3">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($employee_documents)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">
                                Walang naka-upload na document o liability slip para sa empleyadong ito.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($employee_documents as $edoc): ?>
                            <tr>
                                <td class="ps-3"><span class="badge bg-light text-dark border"><?= e($edoc['document_type']); ?></span></td>
                                <td class="fw-semibold">
                                    <?php if (str_starts_with($edoc['mime_type'], 'image/')): ?>
                                        <i class="bi bi-file-earmark-image text-success me-1"></i>
                                    <?php else: ?>
                                        <i class="bi bi-file-earmark-pdf text-danger me-1"></i>
                                    <?php endif; ?>
                                    <?= e($edoc['original_name']); ?>
                                </td>
                                <td>
                                    <?= !empty($edoc['asset_tag']) ? '<span class="fw-bold text-primary">' . e($edoc['asset_tag']) . '</span> (' . e($edoc['brand'] . ' ' . $edoc['model']) . ')' : '<span class="text-muted">General</span>'; ?>
                                </td>
                                <td><?= date('M d, Y', strtotime($edoc['uploaded_at'])); ?></td>
                                <td><?= e($edoc['uploaded_by_name'] ?? 'System'); ?></td>
                                <td class="text-end pe-3">
                                    <div class="btn-group btn-group-sm">
                                        <a href="../documents/view_file.php?id=<?= $edoc['id']; ?>" target="_blank" class="btn btn-outline-secondary" title="View">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="../documents/view_file.php?id=<?= $edoc['id']; ?>&download=1" class="btn btn-outline-primary" title="Download">
                                            <i class="bi bi-download"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Past Assignment History -->
<div class="card shadow-sm">
    <div class="card-header bg-white py-3 fw-bold">
        <i class="bi bi-clock-history text-secondary me-2"></i>Past Equipment Assignment History
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Asset Tag</th>
                        <th>Device</th>
                        <th>Issued Date</th>
                        <th>Returned Date</th>
                        <th>Return Condition</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($past_devices)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">No past returned equipment for this employee.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($past_devices as $p): ?>
                            <tr>
                                <td class="ps-3 fw-bold"><?= e($p['asset_tag']); ?></td>
                                <td><?= e($p['brand'] . ' ' . $p['model']); ?></td>
                                <td><?= e($p['assigned_date']); ?></td>
                                <td><?= e($p['return_date']); ?></td>
                                <td><span class="badge bg-light text-dark border"><?= e($p['condition_on_return'] ?? 'N/A'); ?></span></td>
                                <td>
                                    <?php if ($p['status'] === 'Returned'): ?>
                                        <span class="badge bg-secondary">Returned</span>
                                    <?php else: ?>
                                        <span class="badge bg-info">Transferred</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>