<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/mail.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    die('Missing or invalid request id.');
}

/*
 * Load the OT request using Person A's database columns:
 * users.name, users.staff_no, users.email.
 */
$stmt = $pdo->prepare(
    'SELECT
        r.*,
        u.name AS staff_name,
        u.staff_no,
        u.email AS staff_email
     FROM ot_requests r
     JOIN users u
       ON u.id = r.staff_id
     WHERE r.id = :id
     LIMIT 1'
);

$stmt->execute([
    ':id' => $id
]);

$request = $stmt->fetch();

if (!$request) {
    http_response_code(404);

    $page_title = 'Not found';
    include __DIR__ . '/includes/header.php';

    echo '<p class="empty-state">Request not found.</p>';

    include __DIR__ . '/includes/footer.php';
    exit;
}

$role = current_role();
$userId = current_user_id();

$isOwner = (
    $role === 'staff'
    && (int)$request['staff_id'] === $userId
);

$isApprover = ($role === 'approver');

/*
 * Staff may view only their own requests.
 * Approvers may view requests because they need to process the approval chain.
 */
if (!$isOwner && !$isApprover) {
    http_response_code(403);
    die('You do not have access to this request.');
}

/*
 * Determine whether the logged-in approver is allowed to act on
 * this particular request.
 */
$approverStage = null;
$isMyTurn = false;

if ($isApprover) {
    $approverStage = current_approval_stage();

    if (
        $approverStage === 1
        && $request['status'] === 'pending_stage1'
    ) {
        $isMyTurn = true;
    } elseif (
        $approverStage === 2
        && $request['status'] === 'pending_stage2'
    ) {
        $isMyTurn = true;
    }
}

$errors = [];
$notice = null;

/*
 * Process Approve / Reject.
 *
 * CSRF is checked before changing the database.
 * The request row is locked before the status is checked again, which
 * prevents two submissions from processing the same stage simultaneously.
 */
if (
    $isMyTurn
    && $_SERVER['REQUEST_METHOD'] === 'POST'
) {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Your session expired. Please refresh the page and try again.';
    } else {
        $action = $_POST['action'] ?? '';
        $comment = trim($_POST['comment'] ?? '');

        if (!in_array($action, ['approve', 'reject'], true)) {
            $errors[] = 'Invalid approval action.';
        } elseif (strlen($comment) > 1000) {
            $errors[] = 'Comment must be 1000 characters or fewer.';
        } else {
            try {
                $pdo->beginTransaction();

                $lockStmt = $pdo->prepare(
                    'SELECT status
                     FROM ot_requests
                     WHERE id = :id
                     FOR UPDATE'
                );

                $lockStmt->execute([
                    ':id' => $id
                ]);

                $currentStatus = $lockStmt->fetchColumn();

                $expectedStatus = (
                    $approverStage === 1
                        ? 'pending_stage1'
                        : 'pending_stage2'
                );

                if ($currentStatus !== $expectedStatus) {
                    throw new RuntimeException(
                        'This request has already moved past this approval stage.'
                    );
                }

                if ($action === 'approve') {
                    $newStatus = (
                        $approverStage === 1
                            ? 'pending_stage2'
                            : 'approved'
                    );

                    $decision = 'approved';
                } else {
                    $newStatus = 'rejected';
                    $decision = 'rejected';
                }

                $update = $pdo->prepare(
                    'UPDATE ot_requests
                     SET status = :status
                     WHERE id = :id'
                );

                $update->execute([
                    ':status' => $newStatus,
                    ':id' => $id
                ]);

                $logStmt = $pdo->prepare(
                    'INSERT INTO ot_approvals
                        (request_id, approver_id, stage, decision, comment, acted_at)
                     VALUES
                        (:request_id, :approver_id, :stage, :decision, :comment, NOW())'
                );

                $logStmt->execute([
                    ':request_id' => $id,
                    ':approver_id' => $userId,
                    ':stage' => $approverStage,
                    ':decision' => $decision,
                    ':comment' => $comment !== '' ? $comment : null
                ]);

                $pdo->commit();

                /*
                 * Email Stage 2 only after Stage 1 approval.
                 * Mail failure must not undo the committed approval.
                 */
                if (
                    $action === 'approve'
                    && $approverStage === 1
                ) {
                    try {
                        notify_stage2_approver($id);
                    } catch (Throwable $mailError) {
                        error_log(
                            'OT system: Stage 2 notification failed — '
                            . $mailError->getMessage()
                        );
                    }
                }

                if ($action === 'approve') {
                    $notice = (
                        $approverStage === 1
                            ? 'Request approved and sent to Stage 2.'
                            : 'Request approved successfully.'
                    );
                } else {
                    $notice = 'Request rejected successfully.';
                }

                /*
                 * Reload the request after the action.
                 */
                $stmt->execute([
                    ':id' => $id
                ]);

                $request = $stmt->fetch();
                $isMyTurn = false;

            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log(
                    'OT system: approval action failed — '
                    . $e->getMessage()
                );

                $errors[] = (
                    $e->getMessage()
                    ?: 'Something went wrong. Please refresh and try again.'
                );
            }
        }
    }
}

/*
 * Approval history.
 */
$histStmt = $pdo->prepare(
    'SELECT
        a.*,
        u.name AS approver_name
     FROM ot_approvals a
     JOIN users u
       ON u.id = a.approver_id
     WHERE a.request_id = :id
     ORDER BY a.acted_at ASC'
);

$histStmt->execute([
    ':id' => $id
]);

$history = $histStmt->fetchAll();

$page_title = 'Request #' . $id;
include __DIR__ . '/includes/header.php';
?>

<div class="dash-header no-print">
  <div>
    <h1>OT request #<?= (int)$request['id'] ?></h1>
    <p class="subtitle">Request details and approval history.</p>
  </div>

  <div style="display:flex; gap:0.75rem;">
    <button type="button" class="btn-secondary" onclick="window.print()">Print</button>
    <?php if ($isOwner && in_array($request['status'], ['pending_stage1', 'pending_stage2', 'approved'], true)): ?>
      <button type="button" class="btn-reject" id="withdraw-btn">Cancel request</button>
    <?php endif; ?>
    <?php if ($role === 'staff'): ?>
      <a href="dashboard.php" class="btn-secondary">Back to dashboard</a>
    <?php else: ?>
      <a href="dashboard.php" class="btn-secondary">Back to approvals</a>
    <?php endif; ?>
  </div>
</div>

<?php if ($isOwner && in_array($request['status'], ['pending_stage1', 'pending_stage2', 'approved'], true)): ?>
  <form method="post" action="cancel_request.php" id="withdraw-form" style="display:none;" class="no-print">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="id" value="<?= (int)$request['id'] ?>">
  </form>
  <script>
    document.getElementById('withdraw-btn').addEventListener('click', function () {
      if (confirm('Cancel this OT request? This cannot be undone.')) {
        document.getElementById('withdraw-form').submit();
      }
    });
  </script>
<?php endif; ?>

<?php foreach ($errors as $error): ?>
  <div class="form-error"><?= h($error) ?></div>
<?php endforeach; ?>

<?php if ($notice): ?>
  <div class="flash flash-success"><?= h($notice) ?></div>
<?php endif; ?>

<dl class="detail-grid">
  <dt>Staff</dt>
  <dd>
    <?= h($request['staff_name']) ?>
    <?php if (!empty($request['staff_no'])): ?>
      (<?= h($request['staff_no']) ?>)
    <?php endif; ?>
  </dd>

  <dt>Date</dt>
  <dd><?= h(date('d M Y', strtotime($request['ot_date']))) ?></dd>

  <dt>Time</dt>
  <dd>
    <?= h(substr($request['start_time'], 0, 5)) ?>
    –
    <?= h(substr($request['end_time'], 0, 5)) ?>
  </dd>

  <dt>Total hours</dt>
  <dd><?= h($request['total_hours']) ?></dd>

  <dt>Reason</dt>
  <dd><?= nl2br(h($request['reason'])) ?></dd>

  <dt>Status</dt>
  <dd>
    <span class="badge badge-<?= h($request['status']) ?>">
      <?= h(status_label($request['status'])) ?>
    </span>
  </dd>
</dl>

<?php if ($isMyTurn): ?>

  <div class="form-panel approval-panel">
    <h2>Your approval</h2>
    <p class="subtitle">
      You are acting as <?= h(approval_stage_label($approverStage)) ?>.
    </p>

    <form method="post" novalidate>
      <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">

      <label for="comment">Comment (optional)</label>
      <textarea
        name="comment"
        id="comment"
        rows="4"
        maxlength="1000"
        placeholder="Add a comment if needed..."
      ></textarea>

      <div class="approval-buttons">
        <button
          type="submit"
          name="action"
          value="approve"
          class="btn btn-approve"
        >
          Approve
        </button>

        <button
          type="submit"
          name="action"
          value="reject"
          class="btn btn-reject"
        >
          Reject
        </button>
      </div>
    </form>
  </div>

<?php elseif ($isApprover): ?>

  <p class="read-only-note">
    <?php if (in_array($request['status'], ['approved', 'rejected'], true)): ?>
      This request has been closed.
    <?php elseif ($request['status'] === 'pending_stage1'): ?>
      This request is currently waiting for Stage 1 approval.
    <?php elseif ($request['status'] === 'pending_stage2'): ?>
      This request is currently waiting for Stage 2 approval.
    <?php else: ?>
      This request is view-only for you.
    <?php endif; ?>
  </p>

<?php endif; ?>

<h2>Approval history</h2>

<?php if (!$history): ?>

  <p class="empty-state">
    No decisions have been recorded yet.
  </p>

<?php else: ?>

  <ul class="history-list">
    <?php foreach ($history as $entry): ?>
      <li>
        <strong><?= h($entry['approver_name']) ?></strong>
        (stage <?= (int)$entry['stage'] ?>) —

        <span class="decision-<?= h($entry['decision']) ?>">
          <?= h(ucfirst($entry['decision'])) ?>
        </span>

        on
        <?= h(date('d M Y H:i', strtotime($entry['acted_at']))) ?>

        <?php if (!empty($entry['comment'])): ?>
          <br>
          <em><?= h($entry['comment']) ?></em>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>

<?php endif; ?>

<div class="print-only">
  <div class="print-header">
    <img src="assets/JCY_logo.png" alt="JCY HDD">
    <div>
      <h2>Overtime Request Form</h2>
      <div class="print-meta">Request #<?= (int)$request['id'] ?> · Printed <?= h(date('d M Y H:i')) ?></div>
    </div>
  </div>

  <table class="print-table">
    <tr><td>Staff Name</td><td><?= h($request['staff_name']) ?></td></tr>
    <tr><td>Staff No.</td><td><?= h($request['staff_no'] ?? '') ?></td></tr>
    <tr><td>OT Date</td><td><?= h(date('d M Y', strtotime($request['ot_date']))) ?></td></tr>
    <tr><td>Time</td><td><?= h(substr($request['start_time'], 0, 5)) ?> – <?= h(substr($request['end_time'], 0, 5)) ?></td></tr>
    <tr><td>Total Hours</td><td><?= h($request['total_hours']) ?></td></tr>
    <tr><td>Reason</td><td><?= nl2br(h($request['reason'])) ?></td></tr>
    <tr><td>Status</td><td><?= h(status_label($request['status'])) ?></td></tr>
  </table>

  <?php if ($history): ?>
    <table class="print-table">
      <?php foreach ($history as $entry): ?>
        <tr>
          <td><?= h($entry['approver_name']) ?> (Stage <?= (int)$entry['stage'] ?>)</td>
          <td>
            <?= h(ucfirst($entry['decision'])) ?> on <?= h(date('d M Y H:i', strtotime($entry['acted_at']))) ?>
            <?php if (!empty($entry['comment'])): ?> — <?= h($entry['comment']) ?><?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>

  <div class="print-signatures">
    <div class="print-sign-block">
      <div class="print-sign-line">Staff Signature / Date</div>
    </div>
    <div class="print-sign-block">
      <div class="print-sign-line">En Salim / Date</div>
    </div>
    <div class="print-sign-block">
      <div class="print-sign-line">CK Teh / Date</div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>