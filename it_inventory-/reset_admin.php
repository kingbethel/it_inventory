<?php
// C:\xampp\htdocs\it_inventory\reset_admin.php
require_once __DIR__ . '/config/database.php';

$username = 'admin';
$password = 'AdminPassword123!';
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

try {
    // Update or insert the admin user
    $stmt = $pdo->prepare("
        INSERT INTO users (username, password, full_name, email, role, status) 
        VALUES (:username, :password, 'System Administrator', 'it.admin@g2global.com', 'Admin', 'Active')
        ON DUPLICATE KEY UPDATE password = :password_update, status = 'Active'
    ");
    
    $stmt->execute([
        ':username'        => $username,
        ':password'        => $hashed_password,
        ':password_update' => $hashed_password
    ]);

    echo "<div style='font-family: sans-serif; padding: 20px; text-align: center;'>";
    echo "<h2 style='color: green;'>&#10004; Admin Password Successfully Synchronized!</h2>";
    echo "<p>Username: <b>admin</b></p>";
    echo "<p>Password: <b>AdminPassword123!</b></p>";
    echo "<a href='login.php' style='display:inline-block; padding:10px 20px; background:#0d6efd; color:#fff; text-decoration:none; border-radius:5px;'>Proceed to Login</a>";
    echo "</div>";
} catch (PDOException $e) {
    echo "Error updating admin user: " . $e->getMessage();
}