```php
<?php
/**
 * dashboard.php
 *
 * Dashboard behaviour:
 *
 * STAFF
 *   - Shows only their own OT requests.
 *
 * APPROVER
 *   - Stage 1 approver sees requests with status:
 *       pending_stage1
 *   - Stage 2 approver sees requests with status:
 *       pending_stage2
 *   - Each request links to request_detail.php where
 *     Approve / Reject controls are handled.
 *
 * ADMIN
 *   - Can access user management.
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();

$role = current_role();
$userId = current_user_id();

$page_title = 'Dashboard';

include __DIR__ . '/includes/header.php';
?>


<?php
/*
 * ============================================================
 * STAFF DASHBOARD
 * ============================================================
 */
?>

<?php if ($role === 'staff'): ?>

    <?php
    /*
     * Get this staff member's OT requests only.
     */
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
    ?>

    <h1>My OT requests</h1>

    <?php if (!$requests): ?>

        <p class="empty-state">
            You haven't submitted any OT requests yet.
            <a href="submit_ot.php">Submit one</a>.
        </p>

    <?php else: ?>

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

                    <td>
                        <?= h(
                            date(
                                'd M Y',
                                strtotime($request['ot_date'])
                            )
                        ) ?>
                    </td>

                    <td>
                        <?= h(substr($request['start_time'], 0, 5)) ?>
                        –
                        <?= h(substr($request['end_time'], 0, 5)) ?>
                    </td>

                    <td>
                        <?= h($request['total_hours']) ?>
                    </td>

                    <td>
                        <span class="badge badge-<?= h($request['status']) ?>">
                            <?= h(status_label($request['status'])) ?>
                        </span>
                    </td>

                    <td>
                        <a href="request_detail.php?id=<?= (int)$request['id'] ?>">
                            View
                        </a>
                    </td>

                </tr>

            <?php endforeach; ?>

            </tbody>

        </table>

    <?php endif; ?>


<?php
/*
 * ============================================================
 * APPROVER DASHBOARD
 * ============================================================
 */
?>

<?php elseif ($role === 'approver'): ?>

    <?php
    /*
     * Determine which approval stage belongs to this user.
     *
     * current_approval_stage() should return:
     *
     *   1 = Stage 1 approver
     *   2 = Stage 2 approver
     */
    $stage = (int) current_approval_stage();

    /*
     * Only stages 1 and 2 are valid.
     */
    if (!in_array($stage, [1, 2], true)) {
        $stage = 0;
    }

    /*
     * Determine which requests this approver should see.
     */
    if ($stage === 1) {
        $statusFilter = 'pending_stage1';
    } elseif ($stage === 2) {
        $statusFilter = 'pending_stage2';
    } else {
        $statusFilter = null;
    }

    $pending = [];

    if ($statusFilter !== null) {

        /*
         * Get requests waiting for this approver's stage.
         *
         * IMPORTANT:
         * This uses Person A's database column:
         *     users.name
         *
         * Not Person B's:
         *     users.full_name
         */
        $stmt = $pdo->prepare(
            'SELECT r.*,
                    u.name AS staff_name,
                    u.staff_no
             FROM ot_requests r
             JOIN users u
                  ON u.id = r.staff_id
             WHERE r.status = :status
             ORDER BY r.created_at ASC'
        );

        $stmt->execute([
            ':status' => $statusFilter
        ]);

        $pending = $stmt->fetchAll();
    }
    ?>


    <?php if ($stage === 0): ?>

        <h1>Approver dashboard</h1>

        <div class="alert alert-error">
            Your account does not have a valid approval stage assigned.
            Please contact an administrator.
        </div>

    <?php else: ?>

        <h1>Requests awaiting your approval</h1>

        <p>
            You are the
            <strong>Stage <?= $stage ?></strong>
            approver.
        </p>


        <?php if (!$pending): ?>

            <p class="empty-state">
                Nothing is waiting for your approval right now.
            </p>

        <?php else: ?>

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

                        <td>
                            <?= h($request['staff_name']) ?>
                        </td>

                        <td>
                            <?= h($request['staff_no']) ?>
                        </td>

                        <td>
                            <?= h(
                                date(
                                    'd M Y',
                                    strtotime($request['ot_date'])
                                )
                            ) ?>
                        </td>

                        <td>
                            <?= h(substr($request['start_time'], 0, 5)) ?>
                            –
                            <?= h(substr($request['end_time'], 0, 5)) ?>
                        </td>

                        <td>
                            <?= h($request['total_hours']) ?>
                        </td>

                        <td>
                            <?= h(
                                date(
                                    'd M Y H:i',
                                    strtotime($request['created_at'])
                                )
                            ) ?>
                        </td>

                        <td>
                            <a
                                href="request_detail.php?id=<?= (int)$request['id'] ?>"
                                class="btn btn-small"
                            >
                                Review
                            </a>
                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        <?php endif; ?>

    <?php endif; ?>


<?php
/*
 * ============================================================
 * ADMIN DASHBOARD
 * ============================================================
 */
?>

<?php elseif ($role === 'admin'): ?>

    <h1>Admin</h1>

    <p>
        <a href="admin_users.php" class="btn">
            Manage users
        </a>
    </p>


<?php
/*
 * ============================================================
 * UNKNOWN ROLE
 * ============================================================
 */
?>

<?php else: ?>

    <h1>Dashboard</h1>

    <div class="alert alert-error">
        Your account does not have a valid role assigned.
        Please contact an administrator.
    </div>

<?php endif; ?>
