```php
<?php
/**
 * request_detail.php
 *
 * Displays one OT request and its approval history.
 *
 * Permissions:
 *   - Staff: can view their own requests only.
 *   - Admin: can view any request, read-only.
 *   - Approver:
 *       Stage 1 + pending_stage1 -> can Approve / Reject
 *       Stage 2 + pending_stage2 -> can Approve / Reject
 *       Otherwise -> read-only.
 *
 * Database columns used here are based on Person A's version:
 *   users.name
 *   users.staff_no
 *   ot_approvals.decision
 *   ot_approvals.acted_at
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/mail.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    die('Missing or invalid request id.');
}


/*
 * ============================================================
 * LOAD REQUEST
 * ============================================================
 */

$stmt = $pdo->prepare(
    'SELECT
        r.*,
        u.name AS staff_name,
        u.staff_no
     FROM ot_requests r
     JOIN users u
       ON u.id = r.staff_id
     WHERE r.id = :id'
);

$stmt->execute([
    ':id' => $id
]);

$request = $stmt->fetch();

if (!$request) {
    http_response_code(404);

    $page_title = 'Not found';

    include __DIR__ . '/includes/header.php';

    echo '<p class="empty-state">Request not found.</p>';

    include __DIR__ . '/includes/footer.php';

    exit;
}


/*
 * ============================================================
 * ACCESS CONTROL
 * ============================================================
 */

$role   = current_role();
$userId = current_user_id();

$isOwner = (
    $role === 'staff'
    && (int)$request['staff_id'] === $userId
);

$isAdmin = ($role === 'admin');

$isApprover = ($role === 'approver');

/*
 * Staff can only access their own request.
 *
 * Admins and approvers can access any request.
 */
if (!$isOwner && !$isAdmin && !$isApprover) {
    http_response_code(403);
    die('You do not have access to this request.');
}


/*
 * ============================================================
 * DETERMINE APPROVER STAGE
 * ============================================================
 */

$approverStage = null;
$isMyTurn = false;

if ($isApprover) {

    /*
     * current_approval_stage() should return:
     *
     *   1 = Stage 1 approver
     *   2 = Stage 2 approver
     */
    $approverStage = (int)current_approval_stage();

    /*
     * Stage 1 approver can act only when request is
     * pending_stage1.
     */
    if (
        $approverStage === 1
        && $request['status'] === 'pending_stage1'
    ) {
        $isMyTurn = true;
    }

    /*
     * Stage 2 approver can act only when request is
     * pending_stage2.
     */
    if (
        $approverStage === 2
        && $request['status'] === 'pending_stage2'
    ) {
        $isMyTurn = true;
    }
}


/*
 * ============================================================
 * PROCESS APPROVE / REJECT
 * ============================================================
 */

$errors = [];
$notice = null;

if (
    $isMyTurn
    && $_SERVER['REQUEST_METHOD'] === 'POST'
) {

    $action  = $_POST['action'] ?? '';
    $comment = trim($_POST['comment'] ?? '');

    /*
     * Validate the submitted action.
     */
    if (!in_array($action, ['approve', 'reject'], true)) {

        $errors[] = 'Invalid approval action.';

    } else {

        try {

            /*
             * Start database transaction.
             */
            $pdo->beginTransaction();


            /*
             * Lock this request.
             *
             * This is important because two requests could otherwise
             * approve/reject the same request simultaneously.
             */
            $lockStmt = $pdo->prepare(
                'SELECT status
                 FROM ot_requests
                 WHERE id = :id
                 FOR UPDATE'
            );

            $lockStmt->execute([
                ':id' => $id
            ]);

            $currentStatus = $lockStmt->fetchColumn();

            if ($currentStatus === false) {
                throw new RuntimeException(
                    'Request no longer exists.'
                );
            }


            /*
             * Check that the request is still waiting
             * for this approver's stage.
             */
            if ($approverStage === 1) {

                $expectedStatus = 'pending_stage1';

            } elseif ($approverStage === 2) {

                $expectedStatus = 'pending_stage2';

            } else {

                throw new RuntimeException(
                    'Invalid approval stage.'
                );
            }


            if ($currentStatus !== $expectedStatus) {

                throw new RuntimeException(
                    'This request has already moved past this approval stage.'
                );
            }


            /*
             * Determine the new request status.
             */
            if ($action === 'approve') {

                if ($approverStage === 1) {

                    /*
                     * Stage 1 approved.
                     *
                     * Send the request to Stage 2.
                     */
                    $newStatus = 'pending_stage2';

                } else {

                    /*
                     * Stage 2 approved.
                     *
                     * Final approval.
                     */
                    $newStatus = 'approved';
                }

                $decision = 'approved';

            } else {

                /*
                 * Rejection at either stage immediately
                 * closes the request.
                 */
                $newStatus = 'rejected';
                $decision = 'rejected';
            }


            /*
             * Update request status.
             */
            $updateStmt = $pdo->prepare(
                'UPDATE ot_requests
                 SET status = :status
                 WHERE id = :id'
            );

            $updateStmt->execute([
                ':status' => $newStatus,
                ':id'     => $id
            ]);


            /*
             * Record the approval decision.
             *
             * IMPORTANT:
             * Person A uses "decision", not "action".
             *
             * Person A also uses "acted_at" for the timestamp.
             */
            $logStmt = $pdo->prepare(
                'INSERT INTO ot_approvals
                    (
                        request_id,
                        approver_id,
                        stage,
                        decision,
                        comment,
                        acted_at
                    )
                 VALUES
                    (
                        :request_id,
                        :approver_id,
                        :stage,
                        :decision,
                        :comment,
                        NOW()
                    )'
            );

            $logStmt->execute([
                ':request_id' => $id,
                ':approver_id' => $userId,
                ':stage'      => $approverStage,
                ':decision'   => $decision,
                ':comment'    => ($comment !== '')
                    ? $comment
                    : null
            ]);


            /*
             * Commit database changes first.
             */
            $pdo->commit();


            /*
             * If Stage 1 approved the request, notify
             * the Stage 2 approver.
             *
             * The notification is deliberately outside the
             * transaction. A mail failure should not undo
             * the successful approval.
             */
            if (
                $action === 'approve'
                && $approverStage === 1
            ) {

                try {

                    notify_stage2_approver(
                        $pdo,
                        $id
                    );

                } catch (Throwable $mailError) {

                    /*
                     * Email failure should not make the
                     * approval fail.
                     */
                    error_log(
                        'OT system: Stage 2 notification failed — '
                        . $mailError->getMessage()
                    );
                }
            }


            /*
             * Success message.
             */
            if ($action === 'approve') {

                if ($approverStage === 1) {

                    $notice =
                        'Request approved and sent to Stage 2.';

                } else {

                    $notice =
                        'Request approved successfully.';
                }

            } else {

                $notice =
                    'Request rejected successfully.';
            }


            /*
             * Reload the request so that the new status
             * is immediately displayed.
             */
            $stmt->execute([
                ':id' => $id
            ]);

            $request = $stmt->fetch();


            /*
             * It is no longer this approver's turn.
             */
            $isMyTurn = false;


        } catch (Throwable $e) {

            /*
             * Roll back only if a transaction is still active.
             */
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log(
                'OT system: approval action failed — '
                . $e->getMessage()
            );

            $errors[] =
                $e->getMessage()
                ?: 'Something went wrong. Please refresh and try again.';
        }
    }
}


/*
 * ============================================================
 * APPROVAL HISTORY
 * ============================================================
 */

$histStmt = $pdo->prepare(
    'SELECT
        a.*,
        u.name AS approver_name
     FROM ot_approvals a
     JOIN users u
       ON u.id = a.approver_id
     WHERE a.request_id = :id
     ORDER BY a.acted_at ASC'
);

$histStmt->execute([
    ':id' => $id
]);

$history = $histStmt->fetchAll();


/*
 * ============================================================
 * PAGE HEADER
 * ============================================================
 */

$page_title = 'Request #' . $id;

include __DIR__ . '/includes/header.php';
?>


<h1>OT request #<?= (int)$request['id'] ?></h1>


<?php if ($errors): ?>

    <?php foreach ($errors as $error): ?>

        <div class="alert alert-error">
            <?= h($error) ?>
        </div>

    <?php endforeach; ?>

<?php endif; ?>


<?php if ($notice): ?>

    <div class="alert alert-success">
        <?= h($notice) ?>
    </div>

<?php endif; ?>


<!-- =========================================================
     REQUEST DETAILS
     ========================================================= -->

<dl class="detail-grid">

    <dt>Staff</dt>
    <dd>
        <?= h($request['staff_name']) ?>

        <?php if (!empty($request['staff_no'])): ?>
            (<?= h($request['staff_no']) ?>)
        <?php endif; ?>
    </dd>


    <dt>Date</dt>
    <dd>
        <?= h(
            date(
                'd M Y',
                strtotime($request['ot_date'])
            )
        ) ?>
    </dd>


    <dt>Time</dt>
    <dd>
        <?= h(substr($request['start_time'], 0, 5)) ?>
        –
        <?= h(substr($request['end_time'], 0, 5)) ?>
    </dd>


    <dt>Total hours</dt>
    <dd>
        <?= h($request['total_hours']) ?>
    </dd>


    <dt>Reason</dt>
    <dd>
        <?= nl2br(h($request['reason'])) ?>
    </dd>


    <dt>Status</dt>
    <dd>
        <span class="badge badge-<?= h($request['status']) ?>">
            <?= h(status_label($request['status'])) ?>
        </span>
    </dd>

</dl>


<!-- =========================================================
     APPROVAL FORM
     ========================================================= -->

<?php if ($isMyTurn): ?>

    <h2>Your approval</h2>

    <form
        method="post"
        class="approval-form"
    >

        <label for="comment">
            Comment (optional)
        </label>

        <textarea
            name="comment"
            id="comment"
            rows="4"
            maxlength="1000"
            placeholder="Add a comment if needed..."
        ></textarea>


        <div class="approval-buttons">

            <button
                type="submit"
                name="action"
                value="approve"
                class="btn btn-approve"
            >
                Approve
            </button>


            <button
                type="submit"
                name="action"
                value="reject"
                class="btn btn-reject"
            >
                Reject
            </button>

        </div>

    </form>


<?php else: ?>

    <p class="read-only-note">

        <?php if (
            in_array(
                $request['status'],
                ['approved', 'rejected'],
                true
            )
        ): ?>

            This request has been closed.

        <?php elseif ($request['status'] === 'pending_stage1'): ?>

            This request is currently waiting for
            Stage 1 approval.

        <?php elseif ($request['status'] === 'pending_stage2'): ?>

            This request is currently waiting for
            Stage 2 approval.

        <?php else: ?>

            This request is view-only for you —
            it is not currently awaiting your action.

        <?php endif; ?>

    </p>

<?php endif; ?>


<!-- =========================================================
     APPROVAL HISTORY
     ========================================================= -->

<h2>Approval history</h2>


<?php if (!$history): ?>

    <p class="empty-state">
        No decisions have been recorded yet.
    </p>


<?php else: ?>

    <ul class="history-list">

        <?php foreach ($history as $entry): ?>

            <li>

                <strong>
                    <?= h($entry['approver_name']) ?>
                </strong>

                (stage <?= (int)$entry['stage'] ?>) —

                <span
                    class="decision-<?= h($entry['decision']) ?>"
                >
                    <?= h(ucfirst($entry['decision'])) ?>
                </span>

                on

                <?= h(
                    date(
                        'd M Y H:i',
                        strtotime($entry['acted_at'])
                    )
                ) ?>


                <?php if (!empty($entry['comment'])): ?>

                    <br>

                    <em>
                        <?= h($entry['comment']) ?>
                    </em>

                <?php endif; ?>

            </li>

        <?php endforeach; ?>

    </ul>

<?php endif; ?>
