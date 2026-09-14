<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (current_user_id()) {
    redirect('dashboard.php');
}

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');

$error = null;
$success = false;
$user = null;

/*
 * Token must be exactly 64 hexadecimal characters.
 */
if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
    $error = 'This password reset link is invalid or has expired.';
} else {

    $token_hash = hash('sha256', $token);

    $stmt = $pdo->prepare(
        'SELECT pr.id, pr.user_id, pr.expires_at,
                u.name, u.email
         FROM password_resets pr
         INNER JOIN users u ON u.id = pr.user_id
         WHERE pr.token_hash = :token_hash
           AND pr.used_at IS NULL
           AND pr.expires_at > NOW()
           AND u.is_active = 1
         LIMIT 1'
    );

    $stmt->execute([
        ':token_hash' => $token_hash
    ]);

    $user = $stmt->fetch();

    if (!$user) {
        $error = 'This password reset link is invalid or has expired.';
    }
}

/*
 * Handle new password submission.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {

    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Your session expired. Please try again.';
    } else {

        $password = (string)($_POST['password'] ?? '');
        $confirm_password = (string)($_POST['confirm_password'] ?? '');

        if ($password === '' || $confirm_password === '') {
            $error = 'Please enter your new password.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters.';
        } elseif ($password !== $confirm_password) {
            $error = 'Passwords do not match.';
        } else {

            try {
                $pdo->beginTransaction();

                /*
                 * Lock the reset record so the same token
                 * cannot be used twice at the same time.
                 */
                $stmt = $pdo->prepare(
                    'SELECT id, user_id
                     FROM password_resets
                     WHERE token_hash = :token_hash
                       AND used_at IS NULL
                       AND expires_at > NOW()
                     LIMIT 1
                     FOR UPDATE'
                );

                $stmt->execute([
                    ':token_hash' => $token_hash
                ]);

                $reset = $stmt->fetch();

                if (!$reset) {
                    throw new RuntimeException(
                        'This password reset link is invalid or has expired.'
                    );
                }

                $new_password_hash = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                /*
                 * Update the user's password.
                 */
                $stmt = $pdo->prepare(
                    'UPDATE users
                     SET password_hash = :password_hash
                     WHERE id = :user_id
                     LIMIT 1'
                );

                $stmt->execute([
                    ':password_hash' => $new_password_hash,
                    ':user_id'       => (int)$reset['user_id']
                ]);

                /*
                 * Mark this reset link as used.
                 */
                $stmt = $pdo->prepare(
                    'UPDATE password_resets
                     SET used_at = NOW()
                     WHERE id = :reset_id'
                );

                $stmt->execute([
                    ':reset_id' => (int)$reset['id']
                ]);

                /*
                 * Remove any other unused reset links
                 * for the same user.
                 */
                $stmt = $pdo->prepare(
                    'DELETE FROM password_resets
                     WHERE user_id = :user_id
                       AND used_at IS NULL'
                );

                $stmt->execute([
                    ':user_id' => (int)$reset['user_id']
                ]);

                $pdo->commit();

                $success = true;

            } catch (Throwable $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $error = $e->getMessage();
            }
        }
    }
}

$page_title = 'Reset Password';
include __DIR__ . '/includes/header.php';
?>

<div class="auth-card">

  <?php if ($success): ?>

    <h1>Password Updated</h1>

    <p class="subtitle">
      Your password has been successfully changed.
    </p>

    <p>
      You can now log in using your new password.
    </p>

    <a href="login.php" class="button-link">
      Back to Login
    </a>

  <?php else: ?>

    <h1>Reset Password</h1>

    <p class="subtitle">
      Enter a new password for your account.
    </p>

    <?php if ($error): ?>
      <p class="form-error"><?= h($error) ?></p>
    <?php endif; ?>

    <?php if ($user && !$error): ?>

      <form method="post" novalidate>

        <input
          type="hidden"
          name="csrf_token"
          value="<?= h(csrf_token()) ?>"
        >

        <input
          type="hidden"
          name="token"
          value="<?= h($token) ?>"
        >

        <label for="password">
          New password
        </label>

        <div class="password-wrap">
          <input
            type="password"
            id="password"
            name="password"
            required
          >

          <button
            type="button"
            class="password-toggle"
            data-target="password"
            aria-label="Show password"
          >
            <svg viewBox="0 0 24 24" fill="none">
              <path
                d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"
                stroke="currentColor"
                stroke-width="1.8"
              />

              <circle
                cx="12"
                cy="12"
                r="3"
                stroke="currentColor"
                stroke-width="1.8"
              />
            </svg>
          </button>
        </div>

        <label for="confirm_password">
          Confirm new password
        </label>

        <div class="password-wrap">
          <input
            type="password"
            id="confirm_password"
            name="confirm_password"
            required
          >

          <button
            type="button"
            class="password-toggle"
            data-target="confirm_password"
            aria-label="Show password"
          >
            <svg viewBox="0 0 24 24" fill="none">
              <path
                d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"
                stroke="currentColor"
                stroke-width="1.8"
              />

              <circle
                cx="12"
                cy="12"
                r="3"
                stroke="currentColor"
                stroke-width="1.8"
              />
            </svg>
          </button>
        </div>

        <button type="submit">
          Reset Password
        </button>

      </form>

    <?php endif; ?>

    <p class="auth-switch">
      <a href="login.php">Back to login</a>
    </p>

  <?php endif; ?>

</div>

<script>
  document.querySelectorAll('.password-toggle').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var input = document.getElementById(btn.dataset.target);

      input.type = input.type === 'password'
        ? 'text'
        : 'password';
    });
  });
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
