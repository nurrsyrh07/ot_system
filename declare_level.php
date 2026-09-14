<?php
/**
 * declare_level.php
 *
 * Reached from the link in the "new staff registered" email — no login
 * required, since HR won't have an OT system account. Security comes from
 * the random, one-time level_token embedded in the link, not from a
 * session.
 *
 * GET  shows a confirmation screen (so link-scanning/prefetching by email
 *      security tools can't silently consume the token — those only ever
 *      issue GET requests, never submit the form).
 * POST actually applies the level and burns the token so the link can't
 *      be reused.
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/mail.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

const VALID_CATEGORIES = ['below_ae' => 'Below Assistant Engineer', 'ae_above' => 'Assistant Engineer and Above'];

$token = $_GET['token'] ?? $_POST['token'] ?? '';
$category = $_GET['category'] ?? $_POST['category'] ?? $_GET['level'] ?? $_POST['level'] ?? '';

$error = null;
$done  = false;
$staff = null;

if ($token === '' || !isset(VALID_CATEGORIES[$category])) {
    $error = 'This link is invalid.';
} else {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE level_token = :token');
    $stmt->execute([':token' => $token]);
    $staff = $stmt->fetch();

    if (!$staff) {
        $error = 'This link is invalid or has already been used.';
    } elseif ($staff['category'] !== null) {
        $error = 'A category has already been set for this staff member.';
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $personalEmailNeedsConfirmation =
            ($staff['email_type'] ?? '') === 'personal' && !empty($staff['email']);

        if ($personalEmailNeedsConfirmation && empty($_POST['confirm_personal_email'])) {
            $error = 'Please confirm that you have verified the staff member\'s declared personal email address.';
        } else {
        $pdo->beginTransaction();
        try {
            // Re-check under lock in case two people click at once.
            $lock = $pdo->prepare('SELECT category FROM users WHERE id = :id FOR UPDATE');
            $lock->execute([':id' => $staff['id']]);
            $currentCategory = $lock->fetchColumn();

            if ($currentCategory !== null) {
                $pdo->rollBack();
                $error = 'A category has already been set for this staff member.';
            } else {
                $update = $pdo->prepare(
                    "UPDATE users
                     SET category = :category,
                         level_token = NULL,
                         level_set_at = NOW(),
                         personal_email_confirmed_at = CASE
                             WHEN email_type = 'personal' THEN NOW()
                             ELSE personal_email_confirmed_at
                         END
                     WHERE id = :id"
                );
                $update->execute([':category' => $category, ':id' => $staff['id']]);
                $pdo->commit();
                $done = true;

                // Notify the staff member if a company/personal email was
                // registered. No email means HR/supervisor should notify
                // the employee manually.
                if (!empty($staff['email'])) {
                    try {
                        notify_staff_category_confirmed((int)$staff['id']);
                    } catch (Throwable $mailError) {
                        error_log('OT system: staff approval notification failed — ' . $mailError->getMessage());
                    }
                }
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('OT system: declare_level failed — ' . $e->getMessage());
            $error = 'Something went wrong. Please try again.';
        }
        }
    }
}

$page_title = 'Set staff category';
include __DIR__ . '/includes/header.php';
?>
<div class="auth-card">
  <h1>Set staff category</h1>

  <?php if ($error): ?>

    <p class="form-error"><?= h($error) ?></p>

  <?php elseif ($done): ?>

    <p class="subtitle">
      <?= h($staff['name']) ?> (<?= h($staff['staff_no']) ?>) has been set as
      <strong><?= h(VALID_CATEGORIES[$category]) ?></strong>.
    </p>

  <?php else: ?>

    <p class="subtitle">Confirm this staff member's category:</p>

    <dl class="detail-grid">
      <dt>Name</dt>
      <dd><?= h($staff['name']) ?></dd>
      <dt>Staff No.</dt>
      <dd><?= h($staff['staff_no']) ?></dd>
      <dt>Department</dt>
      <dd><?= h($staff['department'] ?: '—') ?></dd>
      <dt>Category</dt>
      <dd><strong><?= h(VALID_CATEGORIES[$category]) ?></strong></dd>
    </dl>

    <form method="post">
      <input type="hidden" name="token" value="<?= h($token) ?>">
      <input type="hidden" name="category" value="<?= h($category) ?>">

      <?php if (($staff['email_type'] ?? '') === 'personal' && !empty($staff['email'])): ?>
        <div class="manual-reset-security-note" style="margin: 1.25rem 0 0;">
          <strong>Personal email verification required</strong>
          <p>
            This staff member declared that they do not have a company email.
            Before approving the category, verify in person that the personal
            email address below genuinely belongs to the staff member:
            <strong><?= h($staff['email']) ?></strong>
          </p>

          <label class="checkbox-field">
            <input type="checkbox" name="confirm_personal_email" value="1" required>
            <span>
              <strong>I have verified this personal email address with the staff member.</strong>
              <small>Do not approve the personal email based only on the registration form.</small>
            </span>
          </label>
        </div>
      <?php elseif (empty($staff['email'])): ?>
        <div class="manual-reset-security-note" style="margin: 1.25rem 0 0;">
          <strong>No email address</strong>
          <p>
            This staff member has no email address on file. After approval,
            HR or the supervisor should notify the staff member manually.
          </p>
        </div>
      <?php endif; ?>

      <button type="submit">Confirm — set as <?= h(VALID_CATEGORIES[$category]) ?></button>
    </form>

  <?php endif; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>