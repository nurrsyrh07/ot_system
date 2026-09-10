<?php
require_once __DIR__ . '/config/db.php';
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
 * Only the owner can withdraw their own request, and only while it's still
 * pending_stage1 — i.e. before En Salim has looked at it. Once it's moved to
 * pending_stage2 or beyond, it's already part of someone else's decision
 * and shouldn't quietly disappear from under them.
 */
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

    if ($request['status'] !== 'pending_stage1') {
        $pdo->rollBack();
        flash_set('This request can no longer be withdrawn — it has already moved to the next approval stage.', 'info');
        redirect('request_detail.php?id=' . $id);
    }

    $update = $pdo->prepare('UPDATE ot_requests SET status = "cancelled" WHERE id = :id');
    $update->execute([':id' => $id]);

    $pdo->commit();
    flash_set('Request withdrawn.', 'success');
    redirect('dashboard.php');

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('OT system: withdraw failed — ' . $e->getMessage());
    flash_set('Something went wrong. Please try again.', 'info');
    redirect('dashboard.php');
}