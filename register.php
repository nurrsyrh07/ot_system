<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/mail.php';
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
        $staff_no   = trim($_POST['staff_no'] ?? '');
        $name       = trim($_POST['name'] ?? '');
        $department = trim($_POST['department'] ?? '');
        $email      = trim($_POST['email'] ?? '');
        $password   = (string)($_POST['password'] ?? '');
        $confirm    = (string)($_POST['confirm'] ?? '');

        if ($staff_no === '' || $name === '' || $email === '' || $password === '') {
            $error = 'Please fill in all required fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters.';
        } else {
            $check = $pdo->prepare('SELECT id FROM users WHERE staff_no = :staff_no OR email = :email');
            $check->execute([':staff_no' => $staff_no, ':email' => $email]);

            if ($check->fetch()) {
                $error = 'That staff number or email is already registered.';
            } else {
                $hash  = password_hash($password, PASSWORD_BCRYPT);
                $token = bin2hex(random_bytes(32));

                $ins = $pdo->prepare(
                    'INSERT INTO users (staff_no, name, department, email, password_hash, role, level_token, is_active)
                     VALUES (:staff_no, :name, :department, :email, :hash, "staff", :token, 1)'
                );
                $ins->execute([
                    ':staff_no'   => $staff_no,
                    ':name'       => $name,
                    ':department' => $department !== '' ? $department : null,
                    ':email'      => $email,
                    ':hash'       => $hash,
                    ':token'      => $token,
                ]);

                $newUserId = (int)$pdo->lastInsertId();

                // Let HR know so they can declare this person's level.
                // A mail failure here shouldn't block the account from
                // being created — HR can still be looped in manually.
                try {
                    notify_hr_new_staff($newUserId);
                } catch (Throwable $mailError) {
                    error_log('OT system: HR notification failed — ' . $mailError->getMessage());
                }

                flash_set('Account created. Please wait for HR to confirm your account before logging in.', 'success');
                redirect('login.php');
            }
        }
    }
}

$page_title = 'Register';
include __DIR__ . '/includes/header.php';
?>
<div class="auth-card">
  <h1>Create your account</h1>
  <p class="subtitle">Register with your staff number to get started.</p>

  <?php if ($error): ?><p class="form-error"><?= h($error) ?></p><?php endif; ?>

  <form method="post" novalidate>
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">

    <label for="staff_no">Staff no.</label>
    <input type="text" id="staff_no" name="staff_no" required autofocus
           value="<?= h($_POST['staff_no'] ?? '') ?>">

    <label for="name">Full name</label>
    <input type="text" id="name" name="name" required
           value="<?= h($_POST['name'] ?? '') ?>">

    <label for="department">Department</label>
    <input type="text" id="department" name="department"
           value="<?= h($_POST['department'] ?? '') ?>">

    <label for="email">Email</label>
    <input type="email" id="email" name="email" required
           value="<?= h($_POST['email'] ?? '') ?>">

    <label for="password">Password</label>
    <div class="password-wrap">
      <input type="password" id="password" name="password" required>
      <button type="button" class="password-toggle" data-target="password" aria-label="Show password">
        <svg viewBox="0 0 24 24" fill="none"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z" stroke="currentColor" stroke-width="1.8"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.8"/></svg>
      </button>
    </div>

    <label for="confirm">Confirm password</label>
    <div class="password-wrap">
      <input type="password" id="confirm" name="confirm" required>
      <button type="button" class="password-toggle" data-target="confirm" aria-label="Show password">
        <svg viewBox="0 0 24 24" fill="none"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z" stroke="currentColor" stroke-width="1.8"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.8"/></svg>
      </button>
    </div>

    <button type="submit">Create account</button>
  </form>

  <p class="auth-switch">Already have an account? <a href="login.php">Log in</a></p>
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