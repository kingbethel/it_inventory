<?php
// C:\xampp\htdocs\it_inventory\includes\header.php
require_once __DIR__ . '/auth_check.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? e($page_title) . ' - ' : ''; ?>G2 Global IT Inventory</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --sidebar-width: 250px;
            --topbar-height: 56px;
        }
        body {
            background-color: #f4f6f9;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            min-height: 100vh;
        }
        #sidebar {
            width: var(--sidebar-width);
            min-height: 100vh;
            background-color: #212529;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1000;
            transition: all 0.3s;
        }
        #sidebar .nav-link {
            color: #adb5bd;
            padding: 10px 20px;
            font-size: 0.92rem;
            display: flex;
            align-items: center;
            border-left: 3px solid transparent;
        }
        #sidebar .nav-link:hover {
            color: #ffffff;
            background-color: rgba(255, 255, 255, 0.05);
        }
        #sidebar .nav-link.active {
            color: #ffffff;
            background-color: rgba(13, 110, 253, 0.2);
            border-left-color: #0d6efd;
            font-weight: 600;
        }
        #sidebar .nav-link i {
            font-size: 1.15rem;
            margin-right: 12px;
        }
        #content-wrapper {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .top-navbar {
            background-color: #ffffff;
            border-bottom: 1px solid #dee2e6;
            height: var(--topbar-height);
            display: flex;
            align-items: center;
            padding: 0 24px;
        }
        .main-content {
            padding: 24px;
            flex: 1;
        }
        .card {
            border: 1px solid #dee2e6;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            border-radius: 6px;
        }
        .stat-card {
            border-left: 4px solid #0d6efd;
        }
        @media (max-width: 768px) {
            #sidebar {
                margin-left: calc(-1 * var(--sidebar-width));
            }
            #sidebar.active {
                margin-left: 0;
            }
            #content-wrapper {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>

<div class="d-flex">
    <?php require_once __DIR__ . '/sidebar.php'; ?>

    <div id="content-wrapper" class="w-100">
        <!-- Topbar -->
        <header class="top-navbar justify-content-between">
            <button class="btn btn-sm btn-outline-secondary d-md-none" id="sidebarToggle">
                <i class="bi bi-list"></i>
            </button>
            <div class="fw-semibold text-secondary">
                <i class="bi bi-building me-1"></i> G2 Global Inc. &bull; IT Department
            </div>
            <div class="dropdown">
                <a href="#" class="d-flex align-items-center text-dark text-decoration-none dropdown-toggle small" id="userMenu" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-person-circle fs-5 me-2 text-primary"></i>
                    <span class="fw-semibold"><?= e($_SESSION['full_name'] ?? 'User'); ?></span>
                    <span class="badge bg-secondary ms-2"><?= e($_SESSION['role'] ?? 'Staff'); ?></span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="userMenu">
                    <li><h6 class="dropdown-header"><?= e($_SESSION['username'] ?? ''); ?></h6></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="<?= BASE_URL; ?>logout.php"><i class="bi bi-box-arrow-right me-2"></i>Sign Out</a></li>
                </ul>
            </div>
        </header>

        <!-- Main Body Area -->
        <main class="main-content">
            <?php display_flash(); ?>