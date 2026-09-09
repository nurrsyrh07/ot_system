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
    ?>
    <h1>My OT requests</h1>
    <?php if (!$requests): ?>
      <p class="empty-state">You haven't submitted any OT requests yet. <a href="submit_ot.php">Submit one</a>.</p>
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
     */
    ?>
    <h1>Requests awaiting your approval</h1>
    <p class="empty-state">Approver dashboard is under construction.</p>
    <?php

} elseif (current_role() === 'admin') {
    ?>
    <h1>Admin</h1>
    <p><a href="admin_users.php">Manage users</a></p>
    <?php
}

include __DIR__ . '/includes/footer.php';
