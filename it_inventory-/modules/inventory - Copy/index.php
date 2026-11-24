<?php
// C:\xampp\htdocs\it_inventory\modules\inventory\index.php

$page_title = "Equipment Inventory";
require_once __DIR__ . '/../../includes/header.php';

// Fetch categories for the filter dropdown
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll();

// Handle Search and Filter Query
$search = trim($_GET['search'] ?? '');
$filter_category = trim($_GET['category'] ?? '');
$filter_status = trim($_GET['status'] ?? '');

$sql = "
    SELECT d.*, c.name AS category_name,
           CONCAT(e.first_name, ' ', e.last_name) AS assigned_to_name
    FROM devices d
    JOIN categories c ON d.category_id = c.id
    LEFT JOIN assignments a ON d.id = a.device_id AND a.status = 'Active'
    LEFT JOIN employees e ON a.employee_id = e.id
    WHERE 1=1
";

$params = [];

if (!empty($search)) {
    $sql .= " AND (d.asset_tag LIKE :search OR d.brand LIKE :search OR d.model LIKE :search OR d.serial_number LIKE :search)";
    $params[':search'] = "%{$search}%";
}

if (!empty($filter_category)) {
    $sql .= " AND d.category_id = :category_id";
    $params[':category_id'] = $filter_category;
}

if (!empty($filter_status)) {
    $sql .= " AND d.status = :status";
    $params[':status'] = $filter_status;
}

$sql .= " ORDER BY d.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$devices = $stmt->fetchAll();
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-0">IT Equipment Inventory</h3>
        <span class="text-muted small">Manage, track, and monitor company hardware assets</span>
    </div>
    <div class="d-flex gap-2">
        <!-- New Bulk Import Button -->
        <a href="import.php" class="btn btn-outline-success btn-sm fw-semibold">
            <i class="bi bi-file-earmark-spreadsheet me-1"></i> Import CSV
        </a>
        <a href="add.php" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-circle me-1"></i> Add Equipment
        </a>
    </div>
</div>


<!-- Search & Filter Card -->
<div class="card mb-4">
    <div class="card-body p-3">
        <form method="GET" action="index.php" class="row g-2 align-items-center">
            <div class="col-md-4">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Search Tag, Brand, Model, Serial..." value="<?= e($search); ?>">
                </div>
            </div>
            <div class="col-md-3">
                <select name="category" class="form-select form-select-sm">
                    <option value="">-- All Categories --</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id']; ?>" <?= ($filter_category == $cat['id']) ? 'selected' : ''; ?>>
                            <?= e($cat['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select form-select-sm">
                    <option value="">-- All Statuses --</option>
                    <option value="Available" <?= ($filter_status === 'Available') ? 'selected' : ''; ?>>Available</option>
                    <option value="Assigned" <?= ($filter_status === 'Assigned') ? 'selected' : ''; ?>>Assigned</option>
                    <option value="Under Repair" <?= ($filter_status === 'Under Repair') ? 'selected' : ''; ?>>Under Repair</option>
                    <option value="Retired" <?= ($filter_status === 'Retired') ? 'selected' : ''; ?>>Retired</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-dark w-100">Filter</button>
                <a href="index.php" class="btn btn-sm btn-outline-secondary" title="Reset Filters"><i class="bi bi-arrow-counterclockwise"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- Inventory Table Card -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small text-secondary">
                    <tr>
                        <th class="ps-3">Asset Tag</th>
                        <th>Device Details</th>
                        <th>Category</th>
                        <th>Serial Number</th>
                        <th>Condition</th>
                        <th>Status</th>
                        <th>Current Holder</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody class="small">
                    <?php if (empty($devices)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                                No equipment found matching your criteria.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($devices as $d): ?>
                            <tr>
                                <td class="ps-3 fw-bold">
                                    <a href="view.php?id=<?= $d['id']; ?>" class="text-decoration-none text-primary">
                                        <?= e($d['asset_tag']); ?>
                                    </a>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= e($d['brand'] . ' ' . $d['model']); ?></div>
                                    <small class="text-muted"><i class="bi bi-geo-alt me-1"></i><?= e($d['location']); ?></small>
                                </td>
                                <td><span class="badge bg-light text-dark border"><?= e($d['category_name']); ?></span></td>
                                <td class="text-muted font-monospace"><?= e($d['serial_number']); ?></td>
                                <td>
                                    <?php
                                    $cond_class = match($d['device_condition']) {
                                        'New' => 'bg-info text-dark',
                                        'Good' => 'bg-success',
                                        'Fair' => 'bg-warning text-dark',
                                        'Damaged' => 'bg-danger',
                                        default => 'bg-secondary'
                                    };
                                    ?>
                                    <span class="badge <?= $cond_class; ?>"><?= e($d['device_condition']); ?></span>
                                </td>
                                <td>
                                    <?php
                                    $status_class = match($d['status']) {
                                        'Available' => 'badge bg-success',
                                        'Assigned' => 'badge bg-primary',
                                        'Under Repair' => 'badge bg-warning text-dark',
                                        'Retired' => 'badge bg-secondary',
                                        default => 'badge bg-secondary'
                                    };
                                    ?>
                                    <span class="<?= $status_class; ?>"><?= e($d['status']); ?></span>
                                </td>
                                <td>
                                    <?= !empty($d['assigned_to_name']) ? '<span class="text-dark fw-semibold"><i class="bi bi-person me-1"></i>' . e($d['assigned_to_name']) . '</span>' : '<span class="text-muted fst-italic">In Stock</span>'; ?>
                                </td>
                                <td class="text-end pe-3">
                                    <div class="btn-group btn-group-sm">
                                        <a href="view.php?id=<?= $d['id']; ?>" class="btn btn-outline-secondary" title="View Details">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="edit.php?id=<?= $d['id']; ?>" class="btn btn-outline-primary" title="Edit Equipment">
                                            <i class="bi bi-pencil"></i>
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
    <div class="card-footer bg-white border-top-0 py-3 small text-muted">
        Total Items Listed: <strong><?= count($devices); ?></strong>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>