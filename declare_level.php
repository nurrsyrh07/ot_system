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
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

const VALID_LEVELS = ['operator' => 'Operator', 'leader' => 'Leader', 'engineer' => 'Engineer'];

$token = $_GET['token'] ?? $_POST['token'] ?? '';
$level = $_GET['level'] ?? $_POST['level'] ?? '';

$error = null;
$done  = false;
$staff = null;

if ($token === '' || !isset(VALID_LEVELS[$level])) {
    $error = 'This link is invalid.';
} else {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE level_token = :token');
    $stmt->execute([':token' => $token]);
    $staff = $stmt->fetch();

    if (!$staff) {
        $error = 'This link is invalid or has already been used.';
    } elseif ($staff['level'] !== null) {
        $error = 'A level has already been set for this staff member.';
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $pdo->beginTransaction();
        try {
            // Re-check under lock in case two people click at once.
            $lock = $pdo->prepare('SELECT level FROM users WHERE id = :id FOR UPDATE');
            $lock->execute([':id' => $staff['id']]);
            $currentLevel = $lock->fetchColumn();

            if ($currentLevel !== null) {
                $pdo->rollBack();
                $error = 'A level has already been set for this staff member.';
            } else {
                $update = $pdo->prepare(
                    'UPDATE users SET level = :level, level_token = NULL, level_set_at = NOW() WHERE id = :id'
                );
                $update->execute([':level' => $level, ':id' => $staff['id']]);
                $pdo->commit();
                $done = true;
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

$page_title = 'Set staff level';
include __DIR__ . '/includes/header.php';
?>
<div class="auth-card">
  <h1>Set staff level</h1>

  <?php if ($error): ?>

    <p class="form-error"><?= h($error) ?></p>

  <?php elseif ($done): ?>

    <p class="subtitle">
      <?= h($staff['name']) ?> (<?= h($staff['staff_no']) ?>) has been set as
      <strong><?= h(VALID_LEVELS[$level]) ?></strong>.
    </p>

  <?php else: ?>

    <p class="subtitle">Confirm this staff member's level:</p>

    <dl class="detail-grid">
      <dt>Name</dt>
      <dd><?= h($staff['name']) ?></dd>
      <dt>Staff No.</dt>
      <dd><?= h($staff['staff_no']) ?></dd>
      <dt>Department</dt>
      <dd><?= h($staff['department'] ?: '—') ?></dd>
      <dt>Level</dt>
      <dd><strong><?= h(VALID_LEVELS[$level]) ?></strong></dd>
    </dl>

    <form method="post">
      <input type="hidden" name="token" value="<?= h($token) ?>">
      <input type="hidden" name="level" value="<?= h($level) ?>">
      <button type="submit">Confirm — set as <?= h(VALID_LEVELS[$level]) ?></button>
    </form>

  <?php endif; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
