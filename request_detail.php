<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT r.*, u.name AS staff_name, u.staff_no
     FROM ot_requests r
     JOIN users u ON u.id = r.staff_id
     WHERE r.id = :id'
);
$stmt->execute([':id' => $id]);
$request = $stmt->fetch();

if (!$request) {
    http_response_code(404);
    $page_title = 'Not found';
    include __DIR__ . '/includes/header.php';
    echo '<p class="empty-state">Request not found.</p>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

$is_owner    = current_role() === 'staff' && (int)$request['staff_id'] === current_user_id();
$is_admin    = current_role() === 'admin';
$is_approver = current_role() === 'approver';

if (!$is_owner && !$is_admin && !$is_approver) {
    http_response_code(403);
    die('You do not have access to this request.');
}

$hist_stmt = $pdo->prepare(
    'SELECT a.*, u.name AS approver_name
     FROM ot_approvals a
     JOIN users u ON u.id = a.approver_id
     WHERE a.request_id = :id
     ORDER BY a.acted_at ASC'
);
$hist_stmt->execute([':id' => $id]);
$history = $hist_stmt->fetchAll();

$page_title = 'Request #' . $id;
include __DIR__ . '/includes/header.php';
?>

<h1>OT request #<?= (int)$request['id'] ?></h1>

<dl class="detail-grid">
  <dt>Staff</dt>
  <dd><?= h($request['staff_name']) ?> (<?= h($request['staff_no']) ?>)</dd>

  <dt>Date</dt>
  <dd><?= h(date('d M Y', strtotime($request['ot_date']))) ?></dd>

  <dt>Time</dt>
  <dd><?= h(substr($request['start_time'], 0, 5)) ?>–<?= h(substr($request['end_time'], 0, 5)) ?></dd>

  <dt>Total hours</dt>
  <dd><?= h($request['total_hours']) ?></dd>

  <dt>Reason</dt>
  <dd><?= nl2br(h($request['reason'])) ?></dd>

  <dt>Status</dt>
  <dd><span class="badge badge-<?= h($request['status']) ?>"><?= h(status_label($request['status'])) ?></span></dd>
</dl>

<h2>Approval history</h2>
<?php if (!$history): ?>
  <p class="empty-state">No decisions have been recorded yet.</p>
<?php else: ?>
  <ul class="history-list">
    <?php foreach ($history as $entry): ?>
      <li>
        <strong><?= h($entry['approver_name']) ?></strong>
        (stage <?= (int)$entry['stage'] ?>) —
        <span class="decision-<?= h($entry['decision']) ?>"><?= h(ucfirst($entry['decision'])) ?></span>
        on <?= h(date('d M Y H:i', strtotime($entry['acted_at']))) ?>
        <?php if (!empty($entry['comment'])): ?><br><em><?= h($entry['comment']) ?></em><?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>

<?php if ($is_approver): ?>
  <?php
  /*
   * Person B: add the Approve / Reject form here.
   *   - En Salim (current_approval_stage() === 1) acts when
   *     $request['status'] === 'pending_stage1'.
   *   - CK Teh (current_approval_stage() === 2) acts when
   *     $request['status'] === 'pending_stage2'.
   * On submit: insert a row into ot_approvals (request_id, approver_id,
   * stage, decision, comment), update ot_requests.status accordingly,
   * and — on a stage-1 approval — call notify_stage2_approver($id).
   * Everyone else (owner, admin) only sees the read-only view above.
   */
  ?>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
