<?php
// C:\xampp\htdocs\it_inventory\login.php

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

// Redirect kung naka-login na
if (isset($_SESSION['user_id'])) {
    redirect('modules/dashboard/index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $error = 'Security validation failed (invalid token). Please try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if (empty($username) || empty($password)) {
            $error = 'Please enter both username and password.';
        } else {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username LIMIT 1");
            $stmt->execute([':username' => $username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                if ($user['status'] !== 'Active') {
                    $error = 'Your account has been deactivated. Please contact IT Management.';
                } else {
                    session_regenerate_id(true);

                    $_SESSION['user_id']   = (int)$user['id'];
                    $_SESSION['username']  = $user['username'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['role']      = $user['role'];

                    log_activity($pdo, $user['id'], 'LOGIN', 'Auth', $user['id'], 'User logged in successfully.');
                    redirect('modules/dashboard/index.php');
                }
            } else {
                $error = 'Invalid username or password.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - G2 Global IT Inventory</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f4f6f9;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
        }
        .login-card {
            width: 100%;
            max-width: 420px;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.06);
            background: #ffffff;
        }
        .login-header {
            border-bottom: 1px solid #f0f0f0;
            padding: 24px 28px 18px 28px;
            text-align: center;
        }
        .login-header h4 {
            margin: 0;
            font-weight: 700;
            color: #1a2530;
        }
        .login-header small {
            color: #6c757d;
        }
        .login-body {
            padding: 28px;
        }
        .password-toggle {
            cursor: pointer;
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="login-header">
        <h4 class="text-uppercase tracking-wide">G2 Global Inc.</h4>
        <small class="text-uppercase fw-semibold tracking-wide">IT Inventory System</small>
    </div>
    
    <div class="login-body">
        <?php display_flash(); ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger py-2 small" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i><?= sanitize($error); ?>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST" autocomplete="off">
            <?= csrf_field(); ?>

            <div class="mb-3">
                <label for="username" class="form-label fw-semibold text-secondary small">Username</label>
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted"><i class="bi bi-person"></i></span>
                    <input type="text" class="form-control" id="username" name="username" required autofocus placeholder="Enter your username">
                </div>
            </div>

            <!-- Password Input na may Show/Hide Eye Toggle -->
            <div class="mb-4">
                <label for="password" class="form-label fw-semibold text-secondary small">Password</label>
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted"><i class="bi bi-key"></i></span>
                    <input type="password" class="form-control" id="password" name="password" required placeholder="Enter your password">
                    <button class="btn btn-outline-secondary password-toggle" type="button" id="togglePasswordBtn" title="Show / Hide Password">
                        <i class="bi bi-eye" id="togglePasswordIcon"></i>
                    </button>
                </div>
            </div>

            <div class="d-grid">
                <button type="submit" class="btn btn-primary py-2 fw-semibold">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Sign In
                </button>
            </div>
        </form>
    </div>
    <div class="card-footer bg-light text-center py-2 text-muted small border-top-0">
        IT Staff Access Only &bull; Unauthorized access prohibited
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Show / Hide Password Script -->
<script>
    const togglePasswordBtn = document.getElementById('togglePasswordBtn');
    const passwordInput = document.getElementById('password'); 
    const togglePasswordIcon = document.getElementById('togglePasswordIcon');

    togglePasswordBtn.addEventListener('click', function () {
        // Toggle input type
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);

        // Toggle the icon
        if (type === 'text') {
            togglePasswordIcon.classList.remove('bi-eye');
            togglePasswordIcon.classList.add('bi-eye-slash');
        } else {
            togglePasswordIcon.classList.remove('bi-eye-slash');
            togglePasswordIcon.classList.add('bi-eye');
        }
    });
</script>
</body>
</html>