<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();

$role = current_role();
$userId = current_user_id();

$page_title = 'Dashboard';
include __DIR__ . '/includes/header.php';
?>

<?php if ($role === 'staff'): ?>

    <div class="dash-header">
      <div>
        <h1>My OT requests</h1>
        <p class="subtitle">View the status of your overtime requests.</p>
      </div>
      <a href="submit_ot.php" class="btn-primary">New OT request</a>
    </div>

    <?php
    $stmt = $pdo->prepare(
        'SELECT *
         FROM ot_requests
         WHERE staff_id = :staff_id
         ORDER BY created_at DESC'
    );

    $stmt->execute([
        ':staff_id' => $userId
    ]);

    $requests = $stmt->fetchAll();

    $totalRequests = count($requests);
    $pendingRequests = 0;
    $approvedRequests = 0;
    $rejectedRequests = 0;
    $approvedHours = 0.0;

    foreach ($requests as $request) {
        if (in_array($request['status'], ['pending_stage1', 'pending_stage2'], true)) {
            $pendingRequests++;
        } elseif ($request['status'] === 'approved') {
            $approvedRequests++;
            $approvedHours += (float)$request['total_hours'];
        } elseif ($request['status'] === 'rejected') {
            $rejectedRequests++;
        }
    }
    ?>

    <div class="stat-cards">
      <div class="stat-card">
        <div class="stat-card-head">
          <div class="stat-icon stat-icon-blue">
            <svg viewBox="0 0 24 24" fill="none">
              <path d="M4 5h16v14H4z" stroke="currentColor" stroke-width="1.8"/>
              <path d="M8 3v4M16 3v4M4 9h16" stroke="currentColor" stroke-width="1.8"/>
            </svg>
          </div>
          <h3>Total requests</h3>
        </div>
        <span class="stat-num"><?= $totalRequests ?></span>
      </div>

      <div class="stat-card">
        <div class="stat-card-head">
          <div class="stat-icon stat-icon-blue">
            <svg viewBox="0 0 24 24" fill="none">
              <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"/>
              <path d="M12 7v5l3 2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            </svg>
          </div>
          <h3>Pending</h3>
        </div>
        <span class="stat-num"><?= $pendingRequests ?></span>
      </div>

      <div class="stat-card">
        <div class="stat-card-head">
          <div class="stat-icon stat-icon-green">
            <svg viewBox="0 0 24 24" fill="none">
              <path d="m5 12 4 4L19 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </div>
          <h3>Approved hours</h3>
        </div>
        <span class="stat-hours"><?= h(number_format($approvedHours, 2)) ?></span>
        <div class="stat-hours-sub"><?= $approvedRequests ?> approved request<?= $approvedRequests === 1 ? '' : 's' ?></div>
      </div>
    </div>

    <div class="request-panel">
      <div class="request-panel-head">
        <h2>Recent requests</h2>
        <?php if ($totalRequests > 0): ?>
          <span class="user-sub"><?= $totalRequests ?> total</span>
        <?php endif; ?>
      </div>

      <?php if (!$requests): ?>
        <p class="empty-state">
          You haven't submitted any OT requests yet.
          <a href="submit_ot.php">Submit one</a>.
        </p>
      <?php else: ?>
        <div class="table-wrap">
          <table class="data-table">
            <thead>
              <tr>
                <th>Date</th>
                <th>Time</th>
                <th>Hours</th>
                <th>Status</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($requests as $request): ?>
              <tr>
                <td><?= h(date('d M Y', strtotime($request['ot_date']))) ?></td>
                <td><?= h(substr($request['start_time'], 0, 5)) ?>–<?= h(substr($request['end_time'], 0, 5)) ?></td>
                <td><?= h($request['total_hours']) ?></td>
                <td>
                  <span class="badge badge-<?= h($request['status']) ?>">
                    <?= h(status_label($request['status'])) ?>
                  </span>
                </td>
                <td>
                  <a href="request_detail.php?id=<?= (int)$request['id'] ?>">View</a>
                  <?php if (in_array($request['status'], ['pending_stage1', 'pending_stage2', 'approved'], true)): ?>
                    · <a href="#" class="cancel-link" data-id="<?= (int)$request['id'] ?>">Cancel</a>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <form method="post" action="cancel_request.php" id="cancel-form" style="display:none;">
      <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="id" id="cancel-id" value="">
    </form>

    <script>
      document.querySelectorAll('.cancel-link').forEach(function (link) {
        link.addEventListener('click', function (e) {
          e.preventDefault();
          if (confirm('Cancel this OT request? This cannot be undone.')) {
            document.getElementById('cancel-id').value = link.dataset.id;
            document.getElementById('cancel-form').submit();
          }
        });
      });
    </script>

<?php elseif ($role === 'approver'): ?>

    <?php
    $stage = current_approval_stage();
    $statusFilter = null;

    if ($stage === 1) {
        $statusFilter = 'pending_stage1';
    } elseif ($stage === 2) {
        $statusFilter = 'pending_stage2';
    }

    $pending = [];

    if ($statusFilter !== null) {
        $stmt = $pdo->prepare(
            'SELECT
                r.*,
                u.name AS staff_name,
                u.staff_no
             FROM ot_requests r
             JOIN users u ON u.id = r.staff_id
             WHERE r.status = :status
             ORDER BY r.created_at ASC'
        );

        $stmt->execute([
            ':status' => $statusFilter
        ]);

        $pending = $stmt->fetchAll();
    }
    ?>

    <?php if ($stage === null || !in_array($stage, [1, 2], true)): ?>

      <h1>Approver dashboard</h1>
      <div class="form-error">
        Your account does not have a valid approval stage assigned.
        Please contact the system administrator.
      </div>

    <?php else: ?>

      <div class="dash-header">
        <div>
          <h1>Requests awaiting your approval</h1>
          <p class="subtitle"><?= h(approval_stage_label($stage)) ?></p>
        </div>
      </div>

      <div class="request-panel">
        <div class="request-panel-head">
          <h2>Pending requests</h2>
          <span class="user-sub"><?= count($pending) ?> waiting</span>
        </div>

        <?php if (!$pending): ?>
          <p class="empty-state">
            Nothing is waiting for your approval right now.
          </p>
        <?php else: ?>
          <div class="table-wrap">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Staff</th>
                  <th>Staff No.</th>
                  <th>Date</th>
                  <th>Time</th>
                  <th>Hours</th>
                  <th>Submitted</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
              <?php foreach ($pending as $request): ?>
                <tr>
                  <td><?= h($request['staff_name']) ?></td>
                  <td><?= h($request['staff_no']) ?></td>
                  <td><?= h(date('d M Y', strtotime($request['ot_date']))) ?></td>
                  <td><?= h(substr($request['start_time'], 0, 5)) ?>–<?= h(substr($request['end_time'], 0, 5)) ?></td>
                  <td><?= h($request['total_hours']) ?></td>
                  <td><?= h(date('d M Y H:i', strtotime($request['created_at']))) ?></td>
                  <td>
                    <a href="request_detail.php?id=<?= (int)$request['id'] ?>" class="btn btn-small">
                      Review
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>

    <?php endif; ?>

<?php else: ?>

    <h1>Dashboard</h1>
    <div class="form-error">
      Your account has an invalid role. Please contact the system administrator.
    </div>

<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>