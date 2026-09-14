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
        $staff_no = trim($_POST['staff_no'] ?? '');
        $password = (string)($_POST['password'] ?? '');

        if ($staff_no === '' || $password === '') {
            $error = 'Enter your staff number and password.';
        } elseif (attempt_login($pdo, $staff_no, $password)) {
            redirect('dashboard.php');
        } else {
            // Check whether the credentials are correct but
            // the staff member is still waiting for HR confirmation.
            $stmt = $pdo->prepare(
                'SELECT role, category, password_hash
                 FROM users
                 WHERE staff_no = :staff_no AND is_active = 1
                 LIMIT 1'
            );
            $stmt->execute([':staff_no' => $staff_no]);
            $user = $stmt->fetch();

            $validPassword = $user && password_verify($password, $user['password_hash']);

            if ($validPassword && $user['role'] === 'staff' && $user['category'] === null) {
                $error = 'Your account is waiting for HR to confirm your category. Please try again after HR has completed the confirmation.';
            } else {
                $error = 'Incorrect staff number or password.';
            }
        }
    }
}

$page_title = 'Log in';
include __DIR__ . '/includes/header.php';
?>

<div class="auth-card">
  <h1>Welcome back!</h1>
  <p class="subtitle">Enter your staff number and password to log in.</p>

  <?php if ($error): ?>
    <p class="form-error"><?= h($error) ?></p>
  <?php endif; ?>

  <form method="post" novalidate>
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">

    <label for="staff_no">Staff no.</label>
    <input type="text" id="staff_no" name="staff_no" required autofocus
           value="<?= h($_POST['staff_no'] ?? '') ?>">

    <label for="password">Password</label>
    <div class="password-wrap">
      <input type="password" id="password" name="password" required>

      <button type="button"
              class="password-toggle"
              data-target="password"
              aria-label="Show password">

        <svg viewBox="0 0 24 24" fill="none">
          <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"
                stroke="currentColor"
                stroke-width="1.8"/>

          <circle cx="12"
                  cy="12"
                  r="3"
                  stroke="currentColor"
                  stroke-width="1.8"/>
        </svg>

      </button>
    </div>

    <button type="submit">Log in</button>
  </form>

  <p class="auth-switch">
    New staff? <a href="register.php">Create an account</a>
  </p>
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