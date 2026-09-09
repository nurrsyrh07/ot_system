<?php
/**
 * STUB — owned by Person B.
 *
 * This file exists so submit_ot.php and request_detail.php have something
 * to call while both halves of the system are being built in parallel.
 * Replace the bodies below with real mail() / PHPMailer sending logic.
 * Keep the function names and single ($request_id) argument the same so
 * the callers on the Person A side don't need to change.
 */

function notify_stage1_approver(int $request_id): void
{
    // TODO(Person B): look up the request + staff member, then email En Salim
    // that a new OT request (#$request_id) is waiting on their approval.
    error_log("[stub] notify_stage1_approver called for request #{$request_id}");
}

function notify_stage2_approver(int $request_id): void
{
    // TODO(Person B): look up the request + staff member, then email CK Teh
    // that En Salim approved OT request #$request_id and it now needs the
    // final sign-off.
    error_log("[stub] notify_stage2_approver called for request #{$request_id}");
}
