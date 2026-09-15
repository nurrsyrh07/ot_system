<?php

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/mail.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();

// --------------------------------------------------
// Get selected month
// --------------------------------------------------

$year = isset($_GET['y']) ? (int)$_GET['y'] : (int)date('Y');
$month = isset($_GET['m']) ? (int)$_GET['m'] : (int)date('n');

// Staff can only ever see their own report — ignore whatever staff_id is
// in the URL for them and use their own id instead. Only admin/approver
// may look up another staff member's report.
if (current_role() === 'staff') {
    $staffId = current_user_id();
} else {
    $staffId = isset($_GET['staff_id']) ? (int)$_GET['staff_id'] : 0;
}

if ($month < 1 || $month > 12) {
    $month = (int)date('n');
}

if ($year < 2000 || $year > 2100) {
    $year = (int)date('Y');
}

if ($staffId <= 0) {
    die('Please select a staff member.');
}

// --------------------------------------------------
// Month information
// --------------------------------------------------

$firstOfMonth = mktime(0, 0, 0, $month, 1, $year);

$monthStart = date('Y-m-01', $firstOfMonth);
$monthEnd   = date('Y-m-t', $firstOfMonth);

$monthName = date('F Y', $firstOfMonth);

// --------------------------------------------------
// Get staff
// --------------------------------------------------

$staffStmt = $pdo->prepare(
    'SELECT id, name, staff_no, department
     FROM users
     WHERE id = :id
       AND role = "staff"
     LIMIT 1'
);

$staffStmt->execute([
    ':id' => $staffId
]);

$staff = $staffStmt->fetch();

if (!$staff) {
    die('Staff member not found.');
}

$stage2Approver = find_approver_by_stage($pdo, 2, $staff['department']);
$stage2ApproverName = $stage2Approver ? $stage2Approver['name'] : 'Approver not configured';

// --------------------------------------------------
// Get approved OT for selected month
// --------------------------------------------------

$stmt = $pdo->prepare(
    'SELECT
        r.ot_date,
        r.start_time,
        r.end_time,
        r.total_hours,
        r.reason
     FROM ot_requests r
     WHERE r.staff_id = :staff_id
       AND r.status = "approved"
       AND r.ot_date BETWEEN :start AND :end
     ORDER BY r.ot_date ASC, r.start_time ASC'
);

$stmt->execute([
    ':staff_id' => $staffId,
    ':start'    => $monthStart,
    ':end'      => $monthEnd
]);

$rows = $stmt->fetchAll();

// --------------------------------------------------
// Calculate monthly total
// --------------------------------------------------

$monthlyTotal = 0;

foreach ($rows as $row) {
    $monthlyTotal += (float)$row['total_hours'];
}

$monthlyTotalDisplay = rtrim(
    rtrim(number_format($monthlyTotal, 2, '.', ''), '0'),
    '.'
);

// --------------------------------------------------
// Page
// --------------------------------------------------

$page_title = 'Monthly OT Report';

include __DIR__ . '/includes/header.php';

?>

<div class="report-page">

  <div class="report-actions no-print">

    <a href="calendar.php?y=<?= $year ?>&m=<?= $month ?>"
       class="btn-secondary">
      ← Back to Calendar
    </a>

    <button type="button"
            class="btn-primary"
            onclick="window.print()">
      Print / Save as PDF
    </button>

    <a href="monthly_report_excel.php?y=<?= $year ?>&m=<?= $month ?>&staff_id=<?= $staffId ?>"
       class="btn-secondary">
      Export Excel
    </a>

  </div>


  <!-- ========================= -->
  <!-- PRINTABLE REPORT -->
  <!-- ========================= -->

  <div class="monthly-report-print">

    <div class="print-header">

      <img src="assets/JCY_logo.png" alt="JCY HDD">

      <div>
        <h2>Monthly Overtime Report</h2>

        <div class="print-meta">
          <?= h($monthName) ?>
        </div>
      </div>

    </div>


    <!-- Staff Information -->

    <table class="memo-fields">

      <tr>
        <td class="memo-field-label">Staff Name</td>
        <td>:</td>
        <td><?= h($staff['name']) ?></td>
      </tr>

      <tr>
        <td class="memo-field-label">Staff No</td>
        <td>:</td>
        <td><?= h($staff['staff_no']) ?></td>
      </tr>

      <tr>
        <td class="memo-field-label">Month</td>
        <td>:</td>
        <td><?= h($monthName) ?></td>
      </tr>

    </table>


    <!-- OT Table -->

    <table class="ot-summary-table">

      <thead>

        <tr>
          <th>No.</th>
          <th>Date</th>
          <th>Works Day</th>
          <th>OT Time</th>
          <th>Remarks</th>
          <th>OT Hour</th>
        </tr>

      </thead>

      <tbody>

        <?php if ($rows): ?>

          <?php foreach ($rows as $index => $row): ?>

            <tr>

              <td style="text-align:center;">
                <?= $index + 1 ?>
              </td>

              <td>
                <?= h(date('d/M/y', strtotime($row['ot_date']))) ?>
              </td>

              <td>
                <?= h(date('l', strtotime($row['ot_date']))) ?>
              </td>

              <td>
                <?= h(substr($row['start_time'], 0, 5)) ?>
                -
                <?= h(substr($row['end_time'], 0, 5)) ?>
              </td>

              <td>
                <?= nl2br(h($row['reason'])) ?>
              </td>

              <td style="text-align:center;">
                <?= h($row['total_hours']) ?>
              </td>

            </tr>

          <?php endforeach; ?>

        <?php else: ?>

          <tr>
            <td colspan="6" style="text-align:center;">
              No approved overtime records for this month.
            </td>
          </tr>

        <?php endif; ?>


        <!-- TOTAL -->

        <tr class="monthly-total-row">

          <td colspan="5" style="text-align:right;">
            <strong>TOTAL OT HOURS</strong>
          </td>

          <td style="text-align:center;">
            <strong><?= h($monthlyTotalDisplay) ?></strong>
          </td>

        </tr>

      </tbody>

    </table>


    <p class="print-closing">
      Thank you.<br>
      Regards,
    </p>


    <div class="print-final-signature">

      <div>Requested By:</div>

      <div style="margin-top: 40px;">
        .......................
      </div>

      <div class="print-sign-name">
        <?= h($stage2ApproverName) ?>
      </div>

      <div>
        General Manager
      </div>

    </div>

  </div>

</div>


<?php include __DIR__ . '/includes/footer.php'; ?>