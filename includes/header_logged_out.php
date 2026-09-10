<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($page_title) ? h($page_title) . ' — ' : '' ?>JCY Overtime Management System</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/style.css">
</head>
<body>

<div class="auth-shell">
  <?php include __DIR__ . '/logo.php'; ?>

  <div class="auth-page">
    <?php $__flash = flash_get(); ?>
    <?php if ($__flash): ?>
      <div class="flash flash-<?= h($__flash['type']) ?>">
        <?= h($__flash['msg']) ?>
      </div>
    <?php endif; ?>