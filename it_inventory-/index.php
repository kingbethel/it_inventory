<?php
// C:\xampp\htdocs\it_inventory\index.php

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

if (isset($_SESSION['user_id'])) {
    redirect('modules/dashboard/index.php');
} else {
    redirect('login.php');
}