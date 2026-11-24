<?php
// C:\xampp\htdocs\it_inventory\includes\sidebar.php
$current_page = $_SERVER['PHP_SELF'];
?>
<nav id="sidebar">
    <!-- Clean Header (No Logo) -->
    <div class="p-3 border-bottom border-secondary">
        <a href="<?= BASE_URL; ?>modules/dashboard/index.php" class="text-white text-decoration-none d-block">
            <span class="fs-6 fw-bold text-uppercase d-block lh-1">G2 Global</span>
            <small class="text-muted" style="font-size: 0.75rem;">IT Inventory System</small>
        </a>
    </div>

    <!-- Navigation Menu Items -->
    <ul class="nav flex-column py-3">
        <li class="nav-item">
            <a class="nav-link <?= strpos($current_page, '/dashboard/') !== false ? 'active' : ''; ?>" 
               href="<?= BASE_URL; ?>modules/dashboard/index.php">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
        </li>

        <li class="nav-item">
            <a class="nav-link <?= strpos($current_page, '/inventory/') !== false ? 'active' : ''; ?>" 
               href="<?= BASE_URL; ?>modules/inventory/index.php">
                <i class="bi bi-laptop"></i> Inventory
            </a>
        </li>

        <li class="nav-item">
            <a class="nav-link <?= strpos($current_page, '/employees/') !== false ? 'active' : ''; ?>" 
               href="<?= BASE_URL; ?>modules/employees/index.php">
                <i class="bi bi-people"></i> Employees
            </a>
        </li>

        <li class="nav-item">
            <a class="nav-link <?= strpos($current_page, '/assignments/') !== false ? 'active' : ''; ?>" 
               href="<?= BASE_URL; ?>modules/assignments/index.php">
                <i class="bi bi-arrow-left-right"></i> Assignments
            </a>
        </li>

        <!-- PURE LIABILITIES MENU -->
        <li class="nav-item">
            <a class="nav-link <?= strpos($current_page, '/documents/') !== false ? 'active' : ''; ?>" 
               href="<?= BASE_URL; ?>modules/documents/index.php">
                <i class="bi bi-file-earmark-check"></i> Liabilities
            </a>
        </li>

        <li class="nav-item">
            <a class="nav-link <?= strpos($current_page, '/reports/') !== false ? 'active' : ''; ?>" 
               href="<?= BASE_URL; ?>modules/reports/index.php">
                <i class="bi bi-graph-up-arrow"></i> History & Reports
            </a>
        </li>
    </ul>

    <!-- Sign Out Button -->
    <div class="position-absolute bottom-0 w-100 p-3 border-top border-secondary text-muted small">
        <a href="<?= BASE_URL; ?>logout.php" class="btn btn-outline-danger btn-sm w-100">
            <i class="bi bi-box-arrow-right me-1"></i> Sign Out
        </a>
    </div>
</nav>