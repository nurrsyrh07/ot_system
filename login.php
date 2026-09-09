<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (current_user_id()) {
    redirect('dashboard.php');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Your session expired. Please try again.';
    } else {
        $identifier = trim($_POST['identifier'] ?? '');
        $password   = (string)($_POST['password'] ?? '');

        if ($identifier === '' || $password === '') {
            $error = 'Enter your staff number/email and password.';
        } elseif (attempt_login($pdo, $identifier, $password)) {
            redirect('dashboard.php');
        } else {
            $error = 'Incorrect staff number/email or password.';
        }
    }
}

$page_title = 'Log in';
include __DIR__ . '/includes/header.php';
?>
<div class="auth-card">
  <h1>Log in</h1>
  <?php if ($error): ?><p class="form-error"><?= h($error) ?></p><?php endif; ?>
  <form method="post" novalidate>
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">

    <label for="identifier">Staff no. or email</label>
    <input type="text" id="identifier" name="identifier" required autofocus
           value="<?= h($_POST['identifier'] ?? '') ?>">

    <label for="password">Password</label>
    <input type="password" id="password" name="password" required>

    <button type="submit">Log in</button>
  </form>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
