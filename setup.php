<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Locks itself once any admin account exists — safe to leave on the server,
// but delete it after initial setup as good practice.
$stmt = $pdo->query("SELECT COUNT(*) AS c FROM users WHERE role = 'admin'");
$adminExists = (int)$stmt->fetch()['c'] > 0;

$error = null;
$success = false;

if (!$adminExists && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = 'Your session expired. Please try again.';
    } else {
        $staff_no = trim($_POST['staff_no'] ?? '');
        $name     = trim($_POST['name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        $confirm  = (string)($_POST['confirm'] ?? '');

        if ($staff_no === '' || $name === '' || $email === '' || $password === '') {
            $error = 'Please fill in all fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters.';
        } else {
            try {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $ins = $pdo->prepare(
                    'INSERT INTO users (staff_no, name, email, password_hash, role, is_active)
                     VALUES (:staff_no, :name, :email, :hash, "admin", 1)'
                );
                $ins->execute([
                    ':staff_no' => $staff_no,
                    ':name'     => $name,
                    ':email'    => $email,
                    ':hash'     => $hash,
                ]);
                $success = true;
            } catch (PDOException $e) {
                $error = 'Could not create account — staff number or email may already be in use.';
            }
        }
    }
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Initial setup — OT Requests</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>
<main class="page">
<div class="auth-card">
  <h1>Initial setup</h1>

  <?php if ($adminExists): ?>
    <p>Setup has already been completed. <a href="login.php">Go to login</a>.</p>
  <?php elseif ($success): ?>
    <p>Admin account created. <a href="login.php">Log in now</a>. For security, delete <code>setup.php</code> from the server.</p>
  <?php else: ?>
    <?php if ($error): ?><p class="form-error"><?= h($error) ?></p><?php endif; ?>
    <form method="post" novalidate>
      <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">

      <label for="staff_no">Staff no.</label>
      <input type="text" id="staff_no" name="staff_no" required value="<?= h($_POST['staff_no'] ?? '') ?>">

      <label for="name">Full name</label>
      <input type="text" id="name" name="name" required value="<?= h($_POST['name'] ?? '') ?>">

      <label for="email">Email</label>
      <input type="email" id="email" name="email" required value="<?= h($_POST['email'] ?? '') ?>">

      <label for="password">Password</label>
      <input type="password" id="password" name="password" required>

      <label for="confirm">Confirm password</label>
      <input type="password" id="confirm" name="confirm" required>

      <button type="submit">Create admin account</button>
    </form>
  <?php endif; ?>
</div>
</main>
</body>
</html>
