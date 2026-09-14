<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();

if (empty($_SESSION['must_change_password'])) {
    redirect('dashboard.php');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Your session expired. Please try again.';
    } else {
        $password = (string)($_POST['password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');

        if ($password === '' || $confirm === '') {
            $error = 'Please enter your new password.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters.';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            $stmt = $pdo->prepare(
                'UPDATE users
                 SET password_hash = :password_hash,
                     must_change_password = 0,
                     temporary_password_expires_at = NULL
                 WHERE id = :id AND is_active = 1
                 LIMIT 1'
            );
            $stmt->execute([
                ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
                ':id' => current_user_id(),
            ]);

            $_SESSION['must_change_password'] = false;
            $_SESSION['temporary_password_expires_at'] = null;

            flash_set('Your password has been changed successfully.', 'success');
            redirect('dashboard.php');
        }
    }
}

$page_title = 'Change Temporary Password';
include __DIR__ . '/includes/header.php';
?>

<div class="auth-card">
  <h1>Create a new password</h1>
  <p class="subtitle">
    You are using a temporary password issued by HR or your supervisor.
    For security, you must create your own password before continuing.
  </p>

  <?php if ($error): ?>
    <p class="form-error"><?= h($error) ?></p>
  <?php endif; ?>

  <form method="post" novalidate>
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">

    <label for="password">New password</label>
    <div class="password-wrap">
      <input type="password" id="password" name="password" required autofocus>
      <button type="button" class="password-toggle" data-target="password" aria-label="Show password">
        <svg viewBox="0 0 24 24" fill="none">
          <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z" stroke="currentColor" stroke-width="1.8"/>
          <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.8"/>
        </svg>
      </button>
    </div>

    <label for="confirm_password">Confirm new password</label>
    <div class="password-wrap">
      <input type="password" id="confirm_password" name="confirm_password" required>
      <button type="button" class="password-toggle" data-target="confirm_password" aria-label="Show password">
        <svg viewBox="0 0 24 24" fill="none">
          <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z" stroke="currentColor" stroke-width="1.8"/>
          <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.8"/>
        </svg>
      </button>
    </div>

    <button type="submit">Change Password</button>
  </form>
</div>

<script>
document.querySelectorAll('.password-toggle').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var input = document.getElementById(btn.dataset.target);
    input.type = input.type === 'password' ? 'text' : 'password';
  });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
