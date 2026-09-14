<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();

$year  = isset($_GET['y']) ? (int)$_GET['y'] : (int)date('Y');
$month = isset($_GET['m']) ? (int)$_GET['m'] : (int)date('n');

if ($month < 1) {
    $month = 12;
    $year--;
}

if ($month > 12) {
    $month = 1;
    $year++;
}

$firstOfMonth = mktime(0, 0, 0, $month, 1, $year);
$daysInMonth  = (int)date('t', $firstOfMonth);
$startWeekday = (int)date('w', $firstOfMonth);

$monthStart = date('Y-m-01', $firstOfMonth);
$monthEnd   = date('Y-m-t', $firstOfMonth);
$today      = date('Y-m-d');


/*
 * Calendar data.
 * Staff only see their own approved OT.
 * Approvers see all approved OT.
 */
$calendarSql =
    'SELECT
        r.ot_date,
        r.total_hours,
        u.name AS staff_name,
        u.staff_no,
        r.id,
        r.staff_id
     FROM ot_requests r
     JOIN users u ON u.id = r.staff_id
     WHERE r.status = "approved"
       AND r.ot_date BETWEEN :start AND :end';

$params = [
    ':start' => $monthStart,
    ':end'   => $monthEnd
];

if (current_role() === 'staff') {

    $calendarSql .= ' AND r.staff_id = :staff_id';

    $params[':staff_id'] = current_user_id();
}

$calendarSql .= ' ORDER BY r.ot_date ASC, u.name ASC';

$stmt = $pdo->prepare($calendarSql);
$stmt->execute($params);

$rows = $stmt->fetchAll();


/*
 * Staff list for approvers.
 */
$staffList = [];

if (current_role() === 'approver') {

    $staffStmt = $pdo->query(
        'SELECT id, name, staff_no
         FROM users
         WHERE role = "staff"
           AND is_active = 1
         ORDER BY name ASC'
    );

    $staffList = $staffStmt->fetchAll();
}


/*
 * Group OT requests by calendar day.
 */
$byDay = [];

foreach ($rows as $r) {

    $d = (int)date('j', strtotime($r['ot_date']));

    $byDay[$d][] = $r;
}


/*
 * Previous / next month.
 */
$prevMonth = $month - 1;
$prevYear  = $year;

if ($prevMonth < 1) {
    $prevMonth = 12;
    $prevYear--;
}

$nextMonth = $month + 1;
$nextYear  = $year;

if ($nextMonth > 12) {
    $nextMonth = 1;
    $nextYear++;
}


$claimMonth = sprintf(
    '%04d-%02d',
    $year,
    $month
);

$claimPeriod = claim_period($claimMonth);
$approvedCount = count($rows);
$staffWithOtCount = count(array_unique(array_map(static function ($row) { return (int)$row['staff_id']; }, $rows)));

$page_title = 'OT Calendar';

include __DIR__ . '/includes/header.php';
?>


<div class="page-heading no-print">
    <div class="page-heading-copy">
        <div class="eyebrow">OVERTIME TRACKING</div>
        <h1>OT Calendar</h1>
        <p class="subtitle">
            Approved overtime across the company — <?= h(date('F Y', $firstOfMonth)) ?>.
        </p>
    </div>

    <div class="calendar-nav no-print">
        <a href="calendar.php?y=<?= $prevYear ?>&m=<?= $prevMonth ?>" class="btn-secondary">‹ Prev</a>
        <a href="calendar.php" class="btn-secondary">Today</a>
        <a href="calendar.php?y=<?= $nextYear ?>&m=<?= $nextMonth ?>" class="btn-secondary">Next ›</a>
    </div>
</div>

<section class="claim-period-card no-print">
    <div class="claim-period-main">
        <div class="claim-period-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none"><rect x="3" y="4" width="18" height="17" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="M3 9h18M8 2v4M16 2v4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
        </div>
        <div>
            <div class="eyebrow">MONTHLY CLAIM</div>
            <h2><?= h($claimPeriod['month_label']) ?></h2>
            <p><?= h($claimPeriod['label']) ?></p>
        </div>
    </div>

    <div class="claim-period-stats">
        <div class="claim-stat">
            <strong><?= $approvedCount ?></strong>
            <span>Approved OT record<?= $approvedCount === 1 ? '' : 's' ?></span>
        </div>
        <?php if (current_role() === 'approver'): ?>
            <div class="claim-stat">
                <strong><?= $staffWithOtCount ?></strong>
                <span>Staff with OT</span>
            </div>
        <?php endif; ?>
    </div>

    <div class="claim-period-actions">
        <?php if (current_role() === 'staff'): ?>
            <a href="claim_form.php?month=<?= h($claimMonth) ?>" class="btn-secondary">
                Preview Forms
            </a>
            <a href="claim_form.php?month=<?= h($claimMonth) ?>&print=1" class="btn-primary">
                Print Forms
            </a>
        <?php else: ?>
            <form method="get" action="claim_form.php" class="calendar-form-actions">
                <input type="hidden" name="month" value="<?= h($claimMonth) ?>">
                <label class="sr-only" for="calendar-staff">Select staff</label>
                <select id="calendar-staff" name="staff_id" required>
                    <option value="">Select staff</option>
                    <?php foreach ($staffList as $staff): ?>
                        <option value="<?= (int)$staff['id'] ?>">
                            <?= h($staff['name']) ?> (<?= h($staff['staff_no']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn-secondary">Preview Form</button>
            </form>
        <?php endif; ?>
    </div>
</section>

<section class="calendar-section">
    <div class="calendar-section-header no-print">
        <div>
            <h2><?= h(date('F Y', $firstOfMonth)) ?></h2>
            <p>Approved overtime records for this month.</p>
        </div>
        <div class="calendar-legend">
            <span><i></i> Approved OT</span>
            <span><b></b> Today</span>
        </div>
    </div>

    <div class="calendar-wrap">
        <div class="calendar-weekdays">
            <span>Sun</span>
            <span>Mon</span>
            <span>Tue</span>
            <span>Wed</span>
            <span>Thu</span>
            <span>Fri</span>
            <span>Sat</span>
        </div>

        <div class="calendar-grid">
            <?php for ($i = 0; $i < $startWeekday; $i++): ?>
                <div class="calendar-cell calendar-cell-empty"></div>
            <?php endfor; ?>

            <?php for ($d = 1; $d <= $daysInMonth; $d++): ?>
                <?php
                $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $d);
                $isToday = $dateStr === $today;
                $entries = $byDay[$d] ?? [];
                $shown = array_slice($entries, 0, 3);
                $more = count($entries) - count($shown);
                ?>

                <div class="calendar-cell <?= $isToday ? 'calendar-cell-today' : '' ?>">
                    <div class="calendar-day-top">
                        <span class="calendar-day-num"><?= $d ?></span>
                        <?php if ($isToday): ?><span class="today-label">Today</span><?php endif; ?>
                    </div>

                    <?php if ($shown): ?>
                        <div class="calendar-entries">
                            <?php foreach ($shown as $entry): ?>
                                <div class="calendar-entry"
                                     title="<?= h($entry['staff_name']) ?> (<?= h($entry['staff_no']) ?>) — <?= h($entry['total_hours']) ?> hrs">
                                    <span class="calendar-entry-dot"></span>
                                    <span class="calendar-entry-name"><?= h($entry['staff_name']) ?></span>
                                    <span class="calendar-entry-hours"><?= h(number_format((float)$entry['total_hours'], 2)) ?>h</span>
                                </div>
                            <?php endforeach; ?>

                            <?php if ($more > 0): ?>
                                <div class="calendar-more">+<?= $more ?> more</div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="calendar-empty-day">—</div>
                    <?php endif; ?>
                </div>
            <?php endfor; ?>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>