<?php
// C:\xampp\htdocs\it_inventory\modules\reports\print.php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth_check.php';

$type = $_GET['type'] ?? 'all';
$employee_id = (int)($_GET['employee_id'] ?? 0);

$title = "IT Inventory Report";
$rows = [];
$emp_details = null;

switch ($type) {
    case 'assigned':
        $title = "Currently Assigned Equipment Report";
        $sql = "
            SELECT d.asset_tag, d.brand, d.model, d.serial_number, c.name AS category_name,
                   e.employee_id, e.first_name, e.last_name, e.department,
                   a.assigned_date, a.condition_on_assign
            FROM assignments a
            JOIN devices d ON a.device_id = d.id
            JOIN categories c ON d.category_id = c.id
            JOIN employees e ON a.employee_id = e.id
            WHERE a.status = 'Active'
            ORDER BY e.department ASC, e.last_name ASC
        ";
        $rows = $pdo->query($sql)->fetchAll();
        break;

    case 'available':
        $title = "Available Equipment in Stock (Ready for Deployment)";
        $sql = "
            SELECT d.asset_tag, d.brand, d.model, d.serial_number, d.location, d.device_condition, c.name AS category_name
            FROM devices d
            JOIN categories c ON d.category_id = c.id
            WHERE d.status = 'Available'
            ORDER BY c.name ASC, d.asset_tag ASC
        ";
        $rows = $pdo->query($sql)->fetchAll();
        break;

    case 'repair':
        $title = "Equipment Under Repair / Servicing Report";
        $sql = "
            SELECT d.asset_tag, d.brand, d.model, d.serial_number, d.location, d.device_condition, d.remarks, c.name AS category_name
            FROM devices d
            JOIN categories c ON d.category_id = c.id
            WHERE d.status = 'Under Repair'
            ORDER BY d.id DESC
        ";
        $rows = $pdo->query($sql)->fetchAll();
        break;

    case 'employee_history':
        if ($employee_id <= 0) {
            die("Please select an employee.");
        }
        $emp_stmt = $pdo->prepare("SELECT * FROM employees WHERE id = :id LIMIT 1");
        $emp_stmt->execute([':id' => $employee_id]);
        $emp_details = $emp_stmt->fetch();
        if (!$emp_details) {
            die("Employee not found.");
        }

        $title = "Equipment Custody History: " . $emp_details['first_name'] . ' ' . $emp_details['last_name'];
        $sql = "
            SELECT a.assigned_date, a.return_date, a.condition_on_assign, a.condition_on_return, a.status AS assignment_status,
                   d.asset_tag, d.brand, d.model, d.serial_number, c.name AS category_name
            FROM assignments a
            JOIN devices d ON a.device_id = d.id
            JOIN categories c ON d.category_id = c.id
            WHERE a.employee_id = :emp_id
            ORDER BY a.id DESC
        ";
        $hist_stmt = $pdo->prepare($sql);
        $hist_stmt->execute([':emp_id' => $employee_id]);
        $rows = $hist_stmt->fetchAll();
        break;

    case 'all':
    default:
        $title = "Master IT Equipment Inventory Report";
        $sql = "
            SELECT d.asset_tag, d.brand, d.model, d.serial_number, d.location, d.device_condition, d.status,
                   c.name AS category_name,
                   CONCAT(e.first_name, ' ', e.last_name) AS holder
            FROM devices d
            JOIN categories c ON d.category_id = c.id
            LEFT JOIN assignments a ON d.id = a.device_id AND a.status = 'Active'
            LEFT JOIN employees e ON a.employee_id = e.id
            ORDER BY c.name ASC, d.asset_tag ASC
        ";
        $rows = $pdo->query($sql)->fetchAll();
        break;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title); ?> - G2 Global Inc.</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body {
            font-family: Arial, sans-serif;
            color: #212529;
            background-color: #fff;
            padding: 24px;
        }
        .report-header {
            border-bottom: 2px solid #0d6efd;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }
        .table th, .table td {
            font-size: 0.82rem;
            padding: 6px 8px;
        }
        .no-print {
            margin-bottom: 20px;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                padding: 0;
            }
            .table th {
                background-color: #f0f0f0 !important;
                -webkit-print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>

<!-- Print & Close Controls (Hidden in Print View) -->
<div class="no-print d-flex justify-content-between align-items-center bg-light p-3 rounded border">
    <div>
        <span class="fw-bold">Print Preview Mode</span> &mdash;
        <span class="text-muted small">Total records found: <?= count($rows); ?></span>
    </div>
    <div class="d-flex gap-2">
        <button onclick="window.print()" class="btn btn-primary btn-sm">
            <i class="bi bi-printer me-1"></i> Print / Save as PDF
        </button>
        <button onclick="window.close()" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-x-circle me-1"></i> Close Window
        </button>
    </div>
</div>

<!-- Company Letterhead -->
<div class="report-header d-flex justify-content-between align-items-end">
    <div>
        <h4 class="fw-bold mb-0 text-uppercase tracking-wide text-primary">G2 GLOBAL INC.</h4>
        <small class="text-muted fw-semibold">IT Department &bull; Asset Accountability & Inventory Management</small>
        <h5 class="fw-bold mt-2 mb-0 text-dark"><?= e($title); ?></h5>
        <?php if ($emp_details): ?>
            <div class="small mt-1 text-secondary">
                Employee: <strong><?= e($emp_details['first_name'] . ' ' . $emp_details['last_name']); ?></strong> 
                (ID: <?= e($emp_details['employee_id']); ?> &bull; Dept: <?= e($emp_details['department']); ?>)
            </div>
        <?php endif; ?>
    </div>
    <div class="text-end small text-muted">
        <div><strong>Date:</strong> <?= date('F j, Y, g:i a'); ?></div>
        <div><strong>Prepared By:</strong> <?= e($_SESSION['full_name']); ?> (<?= e($_SESSION['role']); ?>)</div>
    </div>
</div>

<!-- Table Layout Based on Report Type -->
<table class="table table-bordered table-striped align-middle mb-4">
    <thead class="table-light">
        <?php if ($type === 'assigned'): ?>
            <tr>
                <th>#</th>
                <th>Asset Tag</th>
                <th>Device</th>
                <th>Serial Number</th>
                <th>Assigned To</th>
                <th>Department</th>
                <th>Issued Date</th>
                <th>Condition</th>
            </tr>
        <?php elseif ($type === 'available'): ?>
            <tr>
                <th>#</th>
                <th>Asset Tag</th>
                <th>Category</th>
                <th>Brand & Model</th>
                <th>Serial Number</th>
                <th>Condition</th>
                <th>Location</th>
            </tr>
        <?php elseif ($type === 'repair'): ?>
            <tr>
                <th>#</th>
                <th>Asset Tag</th>
                <th>Category</th>
                <th>Brand & Model</th>
                <th>Serial Number</th>
                <th>Condition</th>
                <th>Remarks / Issue</th>
            </tr>
        <?php elseif ($type === 'employee_history'): ?>
            <tr>
                <th>#</th>
                <th>Asset Tag</th>
                <th>Device</th>
                <th>Category</th>
                <th>Serial Number</th>
                <th>Issued Date</th>
                <th>Return Date</th>
                <th>Status</th>
            </tr>
        <?php else: ?>
            <tr>
                <th>#</th>
                <th>Asset Tag</th>
                <th>Category</th>
                <th>Brand & Model</th>
                <th>Serial Number</th>
                <th>Location</th>
                <th>Status</th>
                <th>Current Custodian</th>
            </tr>
        <?php endif; ?>
    </thead>
    <tbody>
        <?php if (empty($rows)): ?>
            <tr>
                <td colspan="8" class="text-center py-4 text-muted">No records match this report criteria.</td>
            </tr>
        <?php else: ?>
            <?php $i = 1; foreach ($rows as $r): ?>
                <tr>
                    <td><?= $i++; ?></td>
                    <td class="fw-bold font-monospace"><?= e($r['asset_tag']); ?></td>

                    <?php if ($type === 'assigned'): ?>
                        <td><?= e($r['brand'] . ' ' . $r['model']); ?></td>
                        <td class="font-monospace"><?= e($r['serial_number']); ?></td>
                        <td class="fw-semibold"><?= e($r['first_name'] . ' ' . $r['last_name']); ?> (<?= e($r['employee_id']); ?>)</td>
                        <td><?= e($r['department']); ?></td>
                        <td><?= e($r['assigned_date']); ?></td>
                        <td><?= e($r['condition_on_assign']); ?></td>

                    <?php elseif ($type === 'available'): ?>
                        <td><?= e($r['category_name']); ?></td>
                        <td><?= e($r['brand'] . ' ' . $r['model']); ?></td>
                        <td class="font-monospace"><?= e($r['serial_number']); ?></td>
                        <td><?= e($r['device_condition']); ?></td>
                        <td><?= e($r['location']); ?></td>

                    <?php elseif ($type === 'repair'): ?>
                        <td><?= e($r['category_name']); ?></td>
                        <td><?= e($r['brand'] . ' ' . $r['model']); ?></td>
                        <td class="font-monospace"><?= e($r['serial_number']); ?></td>
                        <td><?= e($r['device_condition']); ?></td>
                        <td><?= e($r['remarks'] ?? 'Under maintenance'); ?></td>

                    <?php elseif ($type === 'employee_history'): ?>
                        <td><?= e($r['brand'] . ' ' . $r['model']); ?></td>
                        <td><?= e($r['category_name']); ?></td>
                        <td class="font-monospace"><?= e($r['serial_number']); ?></td>
                        <td><?= e($r['assigned_date']); ?> (<?= e($r['condition_on_assign']); ?>)</td>
                        <td><?= !empty($r['return_date']) ? e($r['return_date']) . ' (' . e($r['condition_on_return']) . ')' : 'Currently Held'; ?></td>
                        <td class="fw-bold"><?= e($r['assignment_status']); ?></td>

                    <?php else: ?>
                        <td><?= e($r['category_name']); ?></td>
                        <td><?= e($r['brand'] . ' ' . $r['model']); ?></td>
                        <td class="font-monospace"><?= e($r['serial_number']); ?></td>
                        <td><?= e($r['location']); ?></td>
                        <td><?= e($r['status']); ?></td>
                        <td><?= !empty($r['holder']) ? e($r['holder']) : '<span class="text-muted fst-italic">In Stock</span>'; ?></td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<!-- Sign-off Block for Print Document -->
<div class="row mt-5 pt-4 text-center small">
    <div class="col-4">
        <div class="border-top pt-2">
            <strong>Prepared By:</strong><br>
            <?= e($_SESSION['full_name']); ?><br>
            <span class="text-muted">IT Department</span>
        </div>
    </div>
    <div class="col-4 offset-4">
        <div class="border-top pt-2">
            <strong>Acknowledged / Approved By:</strong><br>
            _______________________________<br>
            <span class="text-muted">IT HEAD SUPERVISOR</span>
        </div>
    </div>
</div>

</body>
</html>