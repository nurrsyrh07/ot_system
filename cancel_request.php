<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/mail.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_role('staff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('dashboard.php');
}

if (!verify_csrf($_POST['csrf_token'] ?? '')) {
    flash_set('Your session expired. Please try again.', 'info');
    redirect('dashboard.php');
}

$id = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    redirect('dashboard.php');
}

/*
 * Only the owner can cancel their own request. Cancellation is allowed at
 * any point the request is still "live" — pending stage 1, pending stage 2,
 * or already approved — but not once it's already rejected or cancelled,
 * since those are already closed.
 */
$cancellableStatuses = ['pending_stage1', 'pending_stage2', 'approved'];

$pdo->beginTransaction();

try {
    $lock = $pdo->prepare(
        'SELECT staff_id, status FROM ot_requests WHERE id = :id FOR UPDATE'
    );
    $lock->execute([':id' => $id]);
    $request = $lock->fetch();

    if (!$request || (int)$request['staff_id'] !== current_user_id()) {
        $pdo->rollBack();
        http_response_code(403);
        die('You do not have access to this request.');
    }

    if (!in_array($request['status'], $cancellableStatuses, true)) {
        $pdo->rollBack();
        flash_set('This request can no longer be cancelled.', 'info');
        redirect('request_detail.php?id=' . $id);
    }

    $update = $pdo->prepare('UPDATE ot_requests SET status = "cancelled" WHERE id = :id');
    $update->execute([':id' => $id]);

    $pdo->commit();

    // Let both approvers know, regardless of how far the request had
    // gotten — they should hear about it even if they'd already approved
    // their stage. A mail failure here doesn't undo the cancellation.
    try {
        notify_cancellation($id);
    } catch (Throwable $mailError) {
        error_log('OT system: cancellation notification failed — ' . $mailError->getMessage());
    }

    flash_set('Request cancelled.', 'success');
    redirect('dashboard.php');

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('OT system: cancel failed — ' . $e->getMessage());
    flash_set('Something went wrong. Please try again.', 'info');
    redirect('dashboard.php');
}