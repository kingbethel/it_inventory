<?php
// C:\xampp\htdocs\it_inventory\modules\assignments\print_liability.php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth_check.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    die("Invalid assignment ID.");
}

// Fetch complete assignment, device, and employee details
$stmt = $pdo->prepare("
    SELECT a.*,
           d.asset_tag, d.brand, d.model, d.serial_number, c.name AS category_name,
           e.employee_id, e.first_name, e.last_name, e.department, e.position,
           u.full_name AS issued_by_name
    FROM assignments a
    JOIN devices d ON a.device_id = d.id
    JOIN categories c ON d.category_id = c.id
    JOIN employees e ON a.employee_id = e.id
    JOIN users u ON a.assigned_by = u.id
    WHERE a.id = :id
    LIMIT 1
");
$stmt->execute([':id' => $id]);
$data = $stmt->fetch();

if (!$data) {
    die("Assignment record not found.");
}

$item_type = strtolower($data['category_name']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liability Form - <?= e($data['asset_tag']); ?> (<?= e($data['last_name']); ?>)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body {
            font-family: Arial, "Helvetica Neue", Helvetica, sans-serif;
            color: #111;
            background-color: #fff;
            padding: 40px 60px;
            font-size: 14.5px;
            line-height: 1.6;
        }
        .header-logo {
            font-size: 38px;
            font-weight: 900;
            color: #7b8e1e; /* G2 Olive Gold Tone */
            letter-spacing: -1px;
            display: inline-block;
        }
        .header-sub {
            font-size: 11px;
            font-weight: bold;
            color: #666;
            letter-spacing: 2px;
            margin-top: -8px;
        }
        .spec-table th, .spec-table td {
            padding: 4px 10px;
            font-size: 14px;
            vertical-align: top;
        }
        .spec-table th {
            width: 180px;
            text-transform: uppercase;
        }
        .signature-line {
            border-top: 1px solid #000;
            width: 250px;
            margin-top: 50px;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                padding: 15px 30px;
            }
        }
    </style>
</head>
<body>

<!-- Floating Print Controls (Hidden on Print) -->
<div class="no-print d-flex justify-content-between align-items-center bg-light p-3 rounded border mb-4 shadow-sm">
    <div>
        <strong>Official Equipment Turnover & Liability Slip</strong> &mdash;
        <span class="text-muted">Asset Tag: <?= e($data['asset_tag']); ?></span>
    </div>
    <div class="d-flex gap-2">
        <button onclick="window.print()" class="btn btn-primary btn-sm px-4 fw-semibold">
            <i class="bi bi-printer me-1"></i> Print / Save as PDF
        </button>
        <button onclick="window.close()" class="btn btn-outline-secondary btn-sm">
            Close
        </button>
    </div>
</div>

<!-- Header -->
<div class="mb-4">
    <div class="header-logo">G2 <span style="color:#2b5ca5;">&#127758;</span></div>
    <div class="header-sub">GLOBAL, INC.</div>
</div>

<!-- Date -->
<div class="mb-4">
    <?= date('F d, Y', strtotime($data['assigned_date'])); ?>
</div>

<!-- Employee Recipient Info -->
<div class="mb-4">
    <div class="fw-bold text-uppercase"><?= e($data['first_name'] . ' ' . $data['last_name']); ?></div>
    <div><?= !empty($data['position']) ? e($data['position']) : 'Staff'; ?></div>
    <div><?= e($data['department']); ?></div>
</div>

<!-- Salutation -->
<div class="mb-3">
    <strong>Dear <?= e($data['first_name']); ?>:</strong>
</div>

<p>
    We are pleased to formalize the turnover of Company <?= e($item_type); ?> with the following details:
</p>

<!-- Specifications Block (Exact layout from the sample) -->
<div class="my-4 ps-2">
    <table class="spec-table">
        <tr>
            <th>BRAND</th>
            <td>: &nbsp; <strong><?= e($data['brand']); ?></strong></td>
        </tr>
        <tr>
            <th>Series/Model</th>
            <td>: &nbsp; <strong><?= e($data['model']); ?></strong></td>
        </tr>
        <tr>
            <th>SERIAL #</th>
            <td>: &nbsp; <strong><?= e($data['serial_number']); ?></strong></td>
        </tr>
        <tr>
            <th>ASSET TAG</th>
            <td>: &nbsp; <strong><?= e($data['asset_tag']); ?></strong></td>
        </tr>
        <tr>
            <th>CONDITION</th>
            <td>: &nbsp; <?= e($data['condition_on_assign']); ?></td>
        </tr>
        <tr>
            <th>OTHERS</th>
            <td>: &nbsp; <?= !empty($data['remarks']) ? e($data['remarks']) : 'BOX AND CHARGER'; ?></td>
        </tr>
    </table>
</div>

<!-- Official Policy Stipulations -->
<div class="my-4">
    <p>
        This <?= e($item_type); ?> will be primarily used for all your official activities and not for personal use. 
        The <?= e($item_type); ?> is subject for recall if the Management proves that you used it in any illegal acts or other conditions not related to work.
    </p>

    <p>
        All repairs of the said <?= e($item_type); ?> are subject to the approval of the management provided that the damage is work related. 
        Own damage to the <?= e($item_type); ?> will be to your account. In case of loss please provide HRAD immediately with an incident report.
    </p>

    <p>
        We entrust you that you will take good care of this <?= e($item_type); ?>.
    </p>
</div>

<!-- Signatures Section -->
<div class="mt-5 pt-3">
    <!-- Prepared By -->
    <div class="mb-4">
        <div class="fw-bold text-uppercase"><?= e($data['issued_by_name']); ?></div>
        <div class="small text-muted">IT SUPERVISOR</div>
    </div>

    <!-- Approved By -->
    <div class="mb-5">
        <div class="small text-muted mb-3">Approved by:</div>
        <div class="fw-bold text-uppercase">GEORGE GO JR.</div>
        <div class="small text-muted">President</div>
    </div>

    <!-- Conforme -->
    <div class="mt-4">
        <div class="small text-muted mb-4">Conforme:</div>
        <div class="d-flex justify-content-between align-items-end" style="max-width: 620px;">
            <div>
                <div class="signature-line"></div>
                <div class="fw-bold text-uppercase mt-1"><?= e($data['first_name'] . ' ' . $data['last_name']); ?></div>
            </div>
            <div>
                <div class="signature-line" style="width: 200px;"></div>
                <div class="fw-bold mt-1 text-center">Date</div>
            </div>
        </div>
    </div>
</div>

</body>
</html>