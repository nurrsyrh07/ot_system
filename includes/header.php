<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

$__logged_in = (bool) current_user_id();
$__current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($page_title) ? h($page_title) . ' — ' : '' ?>JCY Overtime Management System</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/style.css">
</head>
<body>
<?php if ($__logged_in): ?>
<div class="app-shell">
  <aside class="sidebar">
    <?php include __DIR__ . '/logo.php'; ?>
    <nav class="sidebar-nav">
      <a href="dashboard.php" class="<?= $__current_page === 'dashboard.php' ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24" fill="none"><rect x="3" y="3" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.8"/><rect x="14" y="3" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.8"/><rect x="3" y="14" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.8"/><rect x="14" y="14" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.8"/></svg>
        Dashboard
      </a>
      <?php if (current_role() === 'staff'): ?>
      <a href="submit_ot.php" class="<?= $__current_page === 'submit_ot.php' ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24" fill="none"><path d="M7 3h7l4 4v14H7z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M14 3v4h4" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M9.5 13h5M9.5 16h5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
        Request
      </a>
      <?php endif; ?>
      <?php if (current_role() === 'admin'): ?>
      <a href="admin_users.php" class="<?= $__current_page === 'admin_users.php' ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="8" r="3.2" stroke="currentColor" stroke-width="1.8"/><path d="M5 20c1.2-3.6 4-5.5 7-5.5s5.8 1.9 7 5.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
        Manage Users
      </a>
      <?php endif; ?>
    </nav>
    <a href="logout.php" class="sidebar-logout">
      <svg viewBox="0 0 24 24" fill="none"><path d="M9 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M13 16l4-4-4-4M17 12H9" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
      Log Out
    </a>
  </aside>
  <div class="main">
    <div class="topbar">
      <div class="search-box">
        <svg viewBox="0 0 24 24" fill="none"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="1.8"/><path d="m20 20-3.5-3.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
        <input type="text" id="dash-search" placeholder="Search your requests">
      </div>
      <div class="topbar-right">
        <div class="user-chip">
          <div class="avatar"><?= h(strtoupper(substr($_SESSION['name'] ?? '?', 0, 1))) ?></div>
          <div class="user-info">
            <span class="user-name"><?= h($_SESSION['name'] ?? '') ?></span>
            <span class="user-sub"><?= h($_SESSION['staff_no'] ?? '') ?></span>
          </div>
        </div>
      </div>
    </div>
    <div class="content">
    <?php $__flash = flash_get(); if ($__flash): ?>
      <div class="flash flash-<?= h($__flash['type']) ?>"><?= h($__flash['msg']) ?></div>
    <?php endif; ?>
<?php else: ?>
<div class="auth-shell">
  <?php include __DIR__ . '/logo.php'; ?>
  <div class="auth-page">
    <?php $__flash = flash_get(); if ($__flash): ?>
      <div class="flash flash-<?= h($__flash['type']) ?>"><?= h($__flash['msg']) ?></div>
    <?php endif; ?>
<?php endif; ?>
