<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/mail.php';

if (current_user_id()) {
    redirect('dashboard.php');
}

$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Your session expired. Please try again.';
    } else {
        $identifier = trim($_POST['identifier'] ?? '');

        if ($identifier === '') {
            $error = 'Enter your staff number or email address.';
        } else {
            if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
                $stmt = $pdo->prepare(
                    'SELECT id, name, email, email_type
                     FROM users
                     WHERE email = :email AND is_active = 1
                     LIMIT 1'
                );
                $stmt->execute([':email' => $identifier]);
            } else {
                $stmt = $pdo->prepare(
                    'SELECT id, name, email, email_type
                     FROM users
                     WHERE staff_no = :staff_no AND is_active = 1
                     LIMIT 1'
                );
                $stmt->execute([':staff_no' => $identifier]);
            }

            $user = $stmt->fetch();

            if ($user && !empty($user['email'])) {
                // Remove previous unused reset tokens.
                $stmt = $pdo->prepare(
                    'DELETE FROM password_resets
                     WHERE user_id = :user_id AND used_at IS NULL'
                );
                $stmt->execute([':user_id' => (int)$user['id']]);

                $token = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $token);
                $expiresAt = date('Y-m-d H:i:s', time() + (30 * 60));

                $stmt = $pdo->prepare(
                    'INSERT INTO password_resets (user_id, token_hash, expires_at)
                     VALUES (:user_id, :token_hash, :expires_at)'
                );
                $stmt->execute([
                    ':user_id' => (int)$user['id'],
                    ':token_hash' => $tokenHash,
                    ':expires_at' => $expiresAt,
                ]);

                $resetUrl = rtrim(base_url(), '/') .
                    '/reset_password.php?token=' . urlencode($token);

                $emailTypeLabel = ($user['email_type'] ?? '') === 'personal'
                    ? 'your declared personal email address'
                    : 'your registered email address';

                $subject = 'Password Reset - JCY OT System';
                $body = "Hello {$user['name']},\n\n"
                    . "We received a request to reset your JCY OT System password.\n\n"
                    . "Click the link below to create a new password:\n"
                    . "{$resetUrl}\n\n"
                    . "This link will expire in 30 minutes and can only be used once.\n"
                    . "The link was sent to {$emailTypeLabel}.\n\n"
                    . "If you did not request a password reset, you can ignore this email.\n\n"
                    . "JCY OT System";

                send_ot_mail($user['email'], $user['name'], $subject, $body);
            }

            // Do not reveal whether the account exists or whether it has email.
            $message =
                'If the account is eligible for email recovery, a password reset link has been sent. '
                . 'If no email is registered, please contact HR or your supervisor for an in-person reset.';
        }
    }
}

$page_title = 'Forgot Password';
include __DIR__ . '/includes/header.php';
?>

<div class="auth-card">
  <h1>Forgot Password?</h1>

  <p class="subtitle">
    Enter your staff number or registered email address.
    Accounts with a registered email can receive a secure reset link.
  </p>

  <?php if ($error): ?>
    <p class="form-error"><?= h($error) ?></p>
  <?php endif; ?>

  <?php if ($message): ?>
    <p class="form-success"><?= h($message) ?></p>
  <?php endif; ?>

  <form method="post" novalidate>
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">

    <label for="identifier">Staff no. or email</label>
    <input type="text" id="identifier" name="identifier" required autofocus
           value="<?= h($_POST['identifier'] ?? '') ?>">

    <button type="submit">Continue</button>
  </form>

  <div class="manual-reset-help">
    <strong>No email on your account?</strong>
    <p>
      Please contact HR or your supervisor. They must verify your staff badge
      in person before issuing a one-time temporary password.
    </p>
  </div>

  <p class="auth-switch">
    Remember your password? <a href="login.php">Back to login</a>
  </p>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
