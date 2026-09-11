<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();

$role = current_role();
$userId = current_user_id();
$claimMonth = $_GET['month'] ?? current_claim_month();
$period = claim_period($claimMonth);

$staffId = $userId;

if ($role === 'approver') {
    $requestedStaff = isset($_GET['staff_id']) ? (int)$_GET['staff_id'] : 0;
    if ($requestedStaff > 0) {
        $staffId = $requestedStaff;
    } else {
        $firstStaff = $pdo->query('SELECT id FROM users WHERE role = "staff" AND is_active = 1 ORDER BY name ASC LIMIT 1')->fetchColumn();
        $staffId = $firstStaff ? (int)$firstStaff : 0;
    }
}

$stmt = $pdo->prepare(
    'SELECT id, staff_no, name, department, category
     FROM users
     WHERE id = :id AND is_active = 1 AND role = "staff"
     LIMIT 1'
);
$stmt->execute([':id' => $staffId]);
$staff = $stmt->fetch();

if (!$staff) {
    http_response_code(404);
    die('Staff member not found.');
}

$stmt = $pdo->prepare(
    'SELECT id, ot_date, start_time, end_time, total_hours, reason
     FROM ot_requests
     WHERE staff_id = :staff_id
       AND status = "approved"
       AND ot_date BETWEEN :start_date AND :end_date
     ORDER BY ot_date ASC, start_time ASC, id ASC'
);
$stmt->execute([
    ':staff_id' => $staffId,
    ':start_date' => $period['start'],
    ':end_date' => $period['end'],
]);
$requests = $stmt->fetchAll();

$sundayRequests = [];
$weekdayRequests = [];
foreach ($requests as $request) {
    if ((int)date('w', strtotime($request['ot_date'])) === 0) {
        $sundayRequests[] = $request;
    } else {
        $weekdayRequests[] = $request;
    }
}

$category = $staff['category'];
$categoryRequests = $weekdayRequests;

$page_title = 'Monthly OT Forms';
include __DIR__ . '/includes/header.php';
?>

<div class="dash-header no-print">
  <div>
    <h1>Monthly OT Forms</h1>
    <p class="subtitle">
      <?= h($staff['name']) ?> (<?= h($staff['staff_no']) ?>) ·
      <?= h($period['label']) ?>
    </p>
  </div>
  <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
    <a href="calendar.php?y=<?= (int)substr($period['end'],0,4) ?>&m=<?= (int)substr($period['end'],5,2) ?><?= $role === 'approver' ? '&staff_id=' . (int)$staffId : '' ?>" class="btn-secondary">Back to Calendar</a>
    <button type="button" class="btn-primary" onclick="window.print()">Print Forms</button>
  </div>
</div>

<div class="form-panel no-print monthly-form-controls">
  <form method="get" style="display:flex;gap:1rem;align-items:end;flex-wrap:wrap;">
    <div>
      <label for="month">Claim month</label>
      <input type="month" id="month" name="month" value="<?= h($claimMonth) ?>">
    </div>
    <?php if ($role === 'approver'): ?>
      <div>
        <label for="staff_id">Staff</label>
        <select id="staff_id" name="staff_id">
          <?php
          $allStaff = $pdo->query('SELECT id, staff_no, name FROM users WHERE role = "staff" ORDER BY name ASC')->fetchAll();
          foreach ($allStaff as $person):
          ?>
            <option value="<?= (int)$person['id'] ?>" <?= (int)$person['id'] === $staffId ? 'selected' : '' ?>>
              <?= h($person['name']) ?> (<?= h($person['staff_no']) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    <?php endif; ?>
    <button type="submit" class="btn-secondary">Preview Selected Period</button>
  </form>
</div>

<?php if ($category === null): ?>
  <div class="form-error no-print">This staff member's category has not been confirmed by HR yet.</div>
<?php endif; ?>

<?php if ($category !== null): ?>
<section class="claim-document">
  <div class="claim-document-inner">
    <?php if ($category === 'ae_above'): ?>
      <div class="claim-title-center">
        <h1>JCY HDD TECHNOLOGY SDN BHD</h1>
        <h2>TEMPORARY MEAL ALLOWANCE CLAIM FORM</h2>
      </div>

      <div class="claim-meta-grid">
        <div><strong>Name</strong><span><?= h($staff['name']) ?></span></div>
        <div><strong>Staff No.</strong><span><?= h($staff['staff_no']) ?></span></div>
        <div><strong>Date</strong><span><?= h($period['label']) ?></span></div>
        <div><strong>Department</strong><span><?= h($staff['department'] ?: '—') ?></span></div>
      </div>

      <table class="claim-table">
        <thead>
          <tr>
            <th>Date</th>
            <th>Overtime From</th>
            <th>Overtime To</th>
            <th>Hours</th>
            <th>Reason</th>
            <th>Signature Head Department</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$categoryRequests): ?>
          <tr><td colspan="6" class="claim-empty">No approved Monday–Saturday OT records for this period.</td></tr>
        <?php else: ?>
          <?php foreach ($categoryRequests as $r): ?>
            <tr>
              <td><?= h(date('d/m/Y', strtotime($r['ot_date']))) ?></td>
              <td><?= h(substr($r['start_time'],0,5)) ?></td>
              <td><?= h(substr($r['end_time'],0,5)) ?></td>
              <td><?= h(number_format((float)$r['total_hours'],2)) ?></td>
              <td><?= nl2br(h($r['reason'])) ?></td>
              <td></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>

      <div class="claim-signatures two-col">
        <div><strong>Approved by</strong><div class="signature-line"></div><span>Dept Manager</span></div>
        <div><strong>Verified by</strong><div class="signature-line"></div><span>General Manager</span></div>
      </div>

      <div class="claim-notes">
        <strong>Note:-</strong>
        <ol>
          <li>For salary RM 2000 above (below assistant manager)</li>
          <li>Flat rate of RM 20 per day</li>
          <li>Min 2 hrs of extra working hours</li>
          <li>Must get HOD's approval and submit to HR before end each week on weekly basis</li>
        </ol>
      </div>
    <?php else: ?>
      <div class="memo-heading">MEMO</div>
      <div class="memo-company">JCY HDD TECHNOLOGY SDN. BHD.</div>
      <div class="memo-department">(<?= h($staff['department'] ?: 'DEPARTMENT NAME') ?>)</div>

      <table class="memo-meta">
        <tr><th>To</th><td>Human Resources Department / Payroll department</td></tr>
        <tr><th>Attn</th><td>Ms. Ena / Ms. Lai</td></tr>
        <tr><th>From</th><td><?= h($staff['name']) ?></td></tr>
        <tr><th>Cc</th><td>Mr Nordin / Mr Jeffrey Yeoh</td></tr>
        <tr><th>Date</th><td><?= h($period['label']) ?></td></tr>
        <tr><th>Subject</th><td><strong>CLAIM OVERTIME 08:00 PM to 10:00 PM</strong></td></tr>
      </table>

      <p>With reference to above, please be informed that below workers will be work on 08:00 pm to 10:00 pm.</p>
      <p>Kindly make sure a necessary arrangement for this:</p>

      <table class="claim-table memo-table">
        <thead><tr><th>Date</th><th>Works Day</th><th>Remarks</th><th>OT Hour</th><th>Approval By</th></tr></thead>
        <tbody>
        <?php if (!$categoryRequests): ?>
          <tr><td colspan="5" class="claim-empty">No approved Monday–Saturday OT records for this period.</td></tr>
        <?php else: ?>
          <?php foreach ($categoryRequests as $r): ?>
            <tr>
              <td><?= h(date('d/m/Y', strtotime($r['ot_date']))) ?></td>
              <td><?= h(weekday_name($r['ot_date'])) ?></td>
              <td><?= nl2br(h($r['reason'])) ?></td>
              <td><?= h(number_format((float)$r['total_hours'],2)) ?></td>
              <td></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>

      <p>Thank you</p>
      <p>Regards.</p>
      <div class="requested-by">
        <strong>Requested by:</strong>
        <div class="signature-line"></div>
        <strong>Mr. CK Teh</strong><br>
        General Manager
      </div>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>

<?php if ($sundayRequests): ?>
<section class="claim-document sunday-memo">
  <div class="claim-document-inner">
    <div class="memo-heading">MEMO</div>
    <div class="memo-company">JCY HDD TECHNOLOGY SDN. BHD.</div>
    <div class="memo-department">(<?= h($staff['department'] ?: 'DEPARTMENT NAME') ?>)</div>

    <table class="memo-meta">
      <tr><th>To</th><td>Human Resources Department / Payroll department</td></tr>
      <tr><th>Attn</th><td>Ms. Ena / Ms. Lai</td></tr>
      <tr><th>From</th><td><?= h($staff['name']) ?></td></tr>
      <tr><th>Cc</th><td>Mr Nordin / Mr Jeffrey Yeoh</td></tr>
      <tr><th>Date</th><td><?= h($period['label']) ?></td></tr>
      <tr><th>Subject</th><td><strong>CLAIM OVERTIME 08:00 PM to 10:00 PM</strong></td></tr>
    </table>

    <p>With reference to above, please be informed that below workers will be work on 08:00 pm to 10:00 pm.</p>
    <p>Kindly make sure a necessary arrangement for this:</p>

    <table class="claim-table memo-table">
      <thead><tr><th>Date</th><th>Works Day</th><th>Remarks</th><th>OT Hour</th><th>Approval By</th></tr></thead>
      <tbody>
      <?php foreach ($sundayRequests as $r): ?>
        <tr>
          <td><?= h(date('d/m/Y', strtotime($r['ot_date']))) ?></td>
          <td>Sunday</td>
          <td><?= nl2br(h($r['reason'])) ?></td>
          <td><?= h(number_format((float)$r['total_hours'],2)) ?></td>
          <td></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>

    <p>Thank you</p>
    <p>Regards.</p>
    <div class="requested-by">
      <strong>Requested by:</strong>
      <div class="signature-line"></div>
      <strong>Mr. CK Teh</strong><br>
      General Manager
    </div>
  </div>
</section>
<?php endif; ?>

<?php if (!$requests): ?>
  <div class="empty-state no-print">There are no approved OT records for this claim period.</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
