<?php
// C:\xampp\htdocs\it_inventory\includes\auth_check.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    set_flash('danger', 'Please log in to access the system.');
    redirect('login.php');
}