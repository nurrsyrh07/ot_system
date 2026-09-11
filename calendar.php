<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();

$year  = isset($_GET['y']) ? (int)$_GET['y'] : (int)date('Y');
$month = isset($_GET['m']) ? (int)$_GET['m'] : (int)date('n');

if ($month < 1) { $month = 12; $year--; }
if ($month > 12) { $month = 1; $year++; }

$firstOfMonth  = mktime(0, 0, 0, $month, 1, $year);
$daysInMonth   = (int)date('t', $firstOfMonth);
$startWeekday  = (int)date('w', $firstOfMonth); // 0 = Sunday
$monthStart    = date('Y-m-01', $firstOfMonth);
$monthEnd      = date('Y-m-t', $firstOfMonth);
$today         = date('Y-m-d');

$calendarSql =
    'SELECT r.ot_date, r.total_hours, u.name AS staff_name, u.staff_no, r.id, r.staff_id
     FROM ot_requests r
     JOIN users u ON u.id = r.staff_id
     WHERE r.status = "approved" AND r.ot_date BETWEEN :start AND :end';

$params = [':start' => $monthStart, ':end' => $monthEnd];
if (current_role() === 'staff') {
    $calendarSql .= ' AND r.staff_id = :staff_id';
    $params[':staff_id'] = current_user_id();
}
$calendarSql .= ' ORDER BY r.ot_date ASC, u.name ASC';
$stmt = $pdo->prepare($calendarSql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$byDay = [];
foreach ($rows as $r) {
    $d = (int)date('j', strtotime($r['ot_date']));
    $byDay[$d][] = $r;
}

$prevMonth = $month - 1; $prevYear = $year;
if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }
$nextMonth = $month + 1; $nextYear = $year;
if ($nextMonth > 12) { $nextMonth = 1; $nextYear++; }

$page_title = 'OT Calendar';
include __DIR__ . '/includes/header.php';
?>

<div class="dash-header">
  <div>
    <h1>OT Calendar</h1>
    <p class="subtitle">Approved overtime across the company — <?= h(date('F Y', $firstOfMonth)) ?>.</p>
  </div>
  <div style="display:flex; gap:0.5rem;">
    <a href="calendar.php?y=<?= $prevYear ?>&m=<?= $prevMonth ?>" class="btn-secondary">‹ Prev</a>
    <a href="calendar.php" class="btn-secondary">Today</a>
    <a href="calendar.php?y=<?= $nextYear ?>&m=<?= $nextMonth ?>" class="btn-secondary">Next ›</a>
  </div>
</div>

<?php
$claimMonth = sprintf('%04d-%02d', $year, $month);
?>
<div class="calendar-actions no-print">
  <?php if (current_role() === 'staff'): ?>
    <div>
      <strong>Monthly claim period:</strong> <?= h(claim_period($claimMonth)['label']) ?>
    </div>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
      <a href="claim_form.php?month=<?= h($claimMonth) ?>" class="btn-secondary">Preview Monthly Form</a>
      <a href="claim_form.php?month=<?= h($claimMonth) ?>" class="btn-primary">Print Monthly Form</a>
    </div>
  <?php else: ?>
    <div>
      <strong>Monthly claim period:</strong> <?= h(claim_period($claimMonth)['label']) ?>
    </div>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
      <a href="claim_form.php?month=<?= h($claimMonth) ?>" class="btn-primary">View Staff Forms</a>
    </div>
  <?php endif; ?>
</div>

<div class="calendar-wrap">
  <div class="calendar-weekdays">
    <span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span>
  </div>
  <div class="calendar-grid">
    <?php for ($i = 0; $i < $startWeekday; $i++): ?>
      <div class="calendar-cell calendar-cell-empty"></div>
    <?php endfor; ?>

    <?php for ($d = 1; $d <= $daysInMonth; $d++):
      $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $d);
      $isToday = $dateStr === $today;
      $entries = $byDay[$d] ?? [];
      $shown   = array_slice($entries, 0, 3);
      $more    = count($entries) - count($shown);
    ?>
      <div class="calendar-cell <?= $isToday ? 'calendar-cell-today' : '' ?>">
        <div class="calendar-day-num"><?= $d ?></div>
        <?php if ($shown): ?>
          <div class="calendar-entries">
            <?php foreach ($shown as $entry): ?>
              <div class="calendar-entry" title="<?= h($entry['staff_name']) ?> (<?= h($entry['staff_no']) ?>) — <?= h($entry['total_hours']) ?> hrs">
                <?= h($entry['staff_name']) ?>
              </div>
            <?php endforeach; ?>
            <?php if ($more > 0): ?>
              <div class="calendar-more">+<?= $more ?> more</div>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endfor; ?>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>