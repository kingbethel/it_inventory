<?php
// C:\xampp\htdocs\it_inventory\includes\functions.php

if (!ob_get_level()) {
    ob_start();
}

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

function sanitize(?string $data): string {
    return htmlspecialchars(trim((string)$data), ENT_QUOTES, 'UTF-8');
}

function e(?string $data): string {
    return sanitize($data);
}

function get_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . get_csrf_token() . '">';
}

function verify_csrf_token(?string $token): bool {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function set_flash(string $type, string $message): void {
    $_SESSION['flash'] = [
        'type'    => $type,
        'message' => $message
    ];
}

function display_flash(): void {
    if (isset($_SESSION['flash'])) {
        $type = $_SESSION['flash']['type'];
        $message = sanitize($_SESSION['flash']['message']);
        echo "
        <div class='alert alert-{$type} alert-dismissible fade show' role='alert'>
            {$message}
            <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
        </div>";
        unset($_SESSION['flash']);
    }
}

/**
 * Bulletproof Redirect (Handles both HTTP header and output-sent fallbacks)
 */
function redirect(string $path): void {
    $url = (str_starts_with($path, 'http')) ? $path : BASE_URL . ltrim($path, '/');
    
    if (!headers_sent()) {
        header("Location: {$url}");
    } else {
        echo "<script type='text/javascript'>window.location.href='{$url}';</script>";
        echo "<noscript><meta http-equiv='refresh' content='0;url={$url}'></noscript>";
    }
    exit;
}

function log_activity(PDO $pdo, ?int $user_id, string $action, string $entity_type, ?int $entity_id = null, ?string $details = null): void {
    try {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details, ip_address)
            VALUES (:user_id, :action, :entity_type, :entity_id, :details, :ip_address)
        ");
        $stmt->execute([
            ':user_id'     => $user_id,
            ':action'      => $action,
            ':entity_type' => $entity_type,
            ':entity_id'   => $entity_id,
            ':details'     => $details,
            ':ip_address'  => $ip_address
        ]);
    } catch (PDOException $e) {
        error_log("Activity log error: " . $e->getMessage());
    }
}