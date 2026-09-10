<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();

$page_title = 'Dashboard';
include __DIR__ . '/includes/header.php';

if (current_role() === 'staff') {
    $stmt = $pdo->prepare('SELECT * FROM ot_requests WHERE staff_id = :id ORDER BY created_at DESC');
    $stmt->execute([':id' => current_user_id()]);
    $requests = $stmt->fetchAll();

    $approved_count = 0;
    $pending_count  = 0;
    $rejected_count = 0;
    $approved_hours = 0.0;
    foreach ($requests as $r) {
        if ($r['status'] === 'approved') {
            $approved_count++;
            $approved_hours += (float)$r['total_hours'];
        } elseif ($r['status'] === 'rejected') {
            $rejected_count++;
        } else {
            $pending_count++;
        }
    }
    $first_name = trim(explode(' ', $_SESSION['name'] ?? '')[0] ?? '');
    ?>
    <div class="dash-header">
      <div>
        <h1>Welcome, <?= h($first_name) ?></h1>
        <p class="subtitle">An overview of your overtime requests.</p>
      </div>
      <a href="submit_ot.php" class="btn-primary">Request Overtime</a>
    </div>

    <div class="stat-cards">
      <div class="stat-card">
        <div class="stat-card-head">
          <span class="stat-icon stat-icon-blue">
            <svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2"/><path d="M12 7v5l3.5 2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </span>
          <h3>Overtime Hours</h3>
        </div>
        <div class="stat-hours"><?= number_format($approved_hours, 2) ?> hrs</div>
        <div class="stat-hours-sub">Approved so far</div>
      </div>

      <div class="stat-card">
        <div class="stat-card-head">
          <span class="stat-icon stat-icon-green">
            <svg viewBox="0 0 24 24" fill="none"><path d="M7 3h7l4 4v14H7z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M14 3v4h4" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>
          </span>
          <h3>Request Stats</h3>
        </div>
        <div class="stat-row">
          <div>
            <span class="stat-num"><?= $approved_count ?></span>
            <span class="stat-label">Approved</span>
          </div>
          <div>
            <span class="stat-num"><?= $pending_count ?></span>
            <span class="stat-label">Pending</span>
          </div>
          <div>
            <span class="stat-num"><?= $rejected_count ?></span>
            <span class="stat-label">Rejected</span>
          </div>
        </div>
      </div>
    </div>

    <div class="request-panel">
      <div class="request-panel-head">
        <h2>Your Requests</h2>
      </div>
      <?php if (!$requests): ?>
        <p class="empty-state">No requests yet. <a href="submit_ot.php">Submit your first OT request</a>.</p>
      <?php else: ?>
        <table class="data-table">
          <thead>
            <tr><th>Date</th><th>Time</th><th>Hours</th><th>Status</th><th></th></tr>
          </thead>
          <tbody>
          <?php foreach ($requests as $r): ?>
            <tr>
              <td><?= h(date('d M Y', strtotime($r['ot_date']))) ?></td>
              <td><?= h(substr($r['start_time'], 0, 5)) ?>–<?= h(substr($r['end_time'], 0, 5)) ?></td>
              <td><?= h($r['total_hours']) ?></td>
              <td><span class="badge badge-<?= h($r['status']) ?>"><?= h(status_label($r['status'])) ?></span></td>
              <td><a href="request_detail.php?id=<?= (int)$r['id'] ?>">View</a></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
    <?php

} elseif (current_role() === 'approver') {
    /*
     * Person B: build the approver dashboard here.
     *   - En Salim (current_approval_stage() === 1) should see requests
     *     where status = 'pending_stage1'.
     *   - CK Teh (current_approval_stage() === 2) should see requests
     *     where status = 'pending_stage2'.
     *   - Join to `users` for the requesting staff member's name/staff_no.
     *   - Link each row to request_detail.php?id=... where the actual
     *     approve/reject controls live.
     *   - Reuse the .stat-cards / .request-panel / .data-table classes in
     *     css/style.css to keep the same visual language as the staff view.
     */
    ?>
    <h1>Requests awaiting your approval</h1>
    <p class="subtitle">Approver dashboard is under construction.</p>
    <div class="request-panel">
      <p class="empty-state">Nothing to show yet.</p>
    </div>
    <?php

} elseif (current_role() === 'admin') {
    ?>
    <h1>Admin</h1>
    <p class="subtitle"><a href="admin_users.php">Manage users</a></p>
    <?php
}

include __DIR__ . '/includes/footer.php';
