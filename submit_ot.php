<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/mail.php';

require_role('staff');

$error = null;

$ot_date = $_POST['ot_date'] ?? '';
$start_time = $_POST['start_time'] ?? '';
$end_time = $_POST['end_time'] ?? '';
$reason = trim($_POST['reason'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Your session expired. Please try again.';
    } elseif (
        $ot_date === ''
        || $start_time === ''
        || $end_time === ''
        || $reason === ''
    ) {
        $error = 'Please fill in all fields.';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ot_date)) {
        $error = 'Please enter a valid date.';
    } elseif (!preg_match('/^\d{2}:\d{2}$/', $start_time) || !preg_match('/^\d{2}:\d{2}$/', $end_time)) {
        $error = 'Please enter valid start and end times.';
    } else {
        $dateObject = DateTime::createFromFormat('Y-m-d', $ot_date);

        if (!$dateObject || $dateObject->format('Y-m-d') !== $ot_date) {
            $error = 'Please enter a valid date.';
        } else {
            $total_hours = calculate_hours($start_time, $end_time);

            if ($total_hours <= 0) {
                $error = 'End time must be after start time.';
            } elseif ($total_hours > 24) {
                $error = 'OT duration cannot be more than 24 hours.';
            } else {
                try {
                    $stmt = $pdo->prepare(
                        'INSERT INTO ot_requests
                            (staff_id, ot_date, start_time, end_time, total_hours, reason, status)
                         VALUES
                            (:staff_id, :ot_date, :start_time, :end_time, :total_hours, :reason, "pending_stage1")'
                    );

                    $stmt->execute([
                        ':staff_id' => current_user_id(),
                        ':ot_date' => $ot_date,
                        ':start_time' => $start_time,
                        ':end_time' => $end_time,
                        ':total_hours' => $total_hours,
                        ':reason' => $reason,
                    ]);

                    $request_id = (int)$pdo->lastInsertId();

                    /*
                     * Person B's mail.php uses the PDO argument.
                     * A mail failure is logged but does not invalidate
                     * the successfully-created OT request.
                     */
                    try {
                        notify_stage1_approver($request_id);
                    } catch (Throwable $mailError) {
                        error_log(
                            'OT system: Stage 1 notification failed — '
                            . $mailError->getMessage()
                        );
                    }

                    flash_set(
                        'OT request submitted. It has been sent to En Salim for approval.',
                        'success'
                    );

                    redirect('request_detail.php?id=' . $request_id);

                } catch (PDOException $e) {
                    error_log('OT system: OT request insert failed — ' . $e->getMessage());
                    $error = 'Unable to submit the OT request. Please try again.';
                }
            }
        }
    }
}

$page_title = 'New OT request';
include __DIR__ . '/includes/header.php';
?>

<div class="dash-header">
  <div>
    <h1>New OT request</h1>
    <p class="subtitle">Fill in the details below to submit for approval.</p>
  </div>
  <a href="dashboard.php" class="btn-secondary">Back to dashboard</a>
</div>

<?php if ($error): ?>
  <p class="form-error"><?= h($error) ?></p>
<?php endif; ?>

<form method="post" class="form-panel" novalidate>
  <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">

  <label for="ot_date">Date</label>
  <input
    type="date"
    id="ot_date"
    name="ot_date"
    required
    value="<?= h($ot_date) ?>"
  >

  <div class="form-row">
    <div>
      <label for="start_time">Start time</label>
      <input
        type="time"
        id="start_time"
        name="start_time"
        required
        value="<?= h($start_time) ?>"
      >
    </div>

    <div>
      <label for="end_time">End time</label>
      <input
        type="time"
        id="end_time"
        name="end_time"
        required
        value="<?= h($end_time) ?>"
      >
    </div>
  </div>

  <p class="hint" id="hours_preview"></p>

  <label for="reason">Reason</label>
  <textarea
    id="reason"
    name="reason"
    rows="4"
    required
    maxlength="5000"
  ><?= h($reason) ?></textarea>

  <button type="submit">Submit request</button>
</form>

<script>
function updateHoursPreview() {
    var s = document.getElementById('start_time').value;
    var e = document.getElementById('end_time').value;
    var out = document.getElementById('hours_preview');

    if (!s || !e) {
        out.textContent = '';
        return;
    }

    var sp = s.split(':').map(Number);
    var ep = e.split(':').map(Number);

    var start = sp[0] * 60 + sp[1];
    var end = ep[0] * 60 + ep[1];

    if (end <= start) {
        end += 24 * 60;
    }

    var hours = (end - start) / 60;

    out.textContent = hours.toFixed(2) + ' hours';
}

document.getElementById('start_time').addEventListener('change', updateHoursPreview);
document.getElementById('end_time').addEventListener('change', updateHoursPreview);
updateHoursPreview();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>