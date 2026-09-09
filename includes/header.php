<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($page_title) ? h($page_title) . ' — ' : '' ?>OT Requests</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>
<header class="site-header">
  <div class="header-inner">
    <a class="brand" href="dashboard.php">OT Requests</a>
    <?php if (current_user_id()): ?>
    <nav class="site-nav">
      <?php if (current_role() === 'staff'): ?>
        <a href="submit_ot.php">New request</a>
      <?php endif; ?>
      <?php if (current_role() === 'admin'): ?>
        <a href="admin_users.php">Manage users</a>
      <?php endif; ?>
      <a href="dashboard.php">Dashboard</a>
      <span class="nav-user"><?= h($_SESSION['name']) ?></span>
      <a href="logout.php">Log out</a>
    </nav>
    <?php endif; ?>
  </div>
</header>
<main class="page">
<?php $__flash = flash_get(); if ($__flash): ?>
  <div class="flash flash-<?= h($__flash['type']) ?>"><?= h($__flash['msg']) ?></div>
<?php endif; ?>
