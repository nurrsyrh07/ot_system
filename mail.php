<?php

/*
|--------------------------------------------------------------------------
| Mail Configuration
|--------------------------------------------------------------------------
|
| TODO before production:
| - Confirm the SMTP/mail server details with IT/MIS.
| - Confirm the official OT system "From" email address.
| - If SMTP authentication is required, replace mail() with PHPMailer.
|
*/

const MAIL_FROM_ADDRESS = 'ot-system@example.com';
const MAIL_FROM_NAME    = 'OT Application System';


/*
|--------------------------------------------------------------------------
| Send Email
|--------------------------------------------------------------------------
*/

function send_ot_mail(
    string $toEmail,
    string $toName,
    string $subject,
    string $body
): bool {
    $headers = 'From: '
        . MAIL_FROM_NAME
        . ' <'
        . MAIL_FROM_ADDRESS
        . ">\r\n";

    $headers .= 'Reply-To: '
        . MAIL_FROM_ADDRESS
        . "\r\n";

    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    $success = mail(
        $toEmail,
        $subject,
        $body,
        $headers
    );

    if (!$success) {
        error_log(
            "OT system: failed to send email to {$toEmail} — {$subject}"
        );
    }

    return $success;
}


/*
|--------------------------------------------------------------------------
| Get OT Request With Staff Information
|--------------------------------------------------------------------------
*/

function get_request_with_staff(
    PDO $pdo,
    int $requestId
): ?array {
    $stmt = $pdo->prepare(
        'SELECT
            r.*,
            u.name AS staff_name,
            u.staff_no,
            u.email AS staff_email
         FROM ot_requests r
         JOIN users u
           ON u.id = r.staff_id
         WHERE r.id = :id
         LIMIT 1'
    );

    $stmt->execute([
        ':id' => $requestId
    ]);

    $request = $stmt->fetch();

    return $request ?: null;
}


/*
|--------------------------------------------------------------------------
| Format OT Request Summary
|--------------------------------------------------------------------------
*/

function format_request_summary(
    array $request
): string {
    return sprintf(
        "Request #%d\n"
        . "Staff: %s (%s)\n"
        . "Date: %s\n"
        . "Time: %s - %s\n"
        . "Total hours: %s\n"
        . "Reason: %s",
        (int) $request['id'],
        $request['staff_name'],
        $request['staff_no'] ?? '',
        $request['ot_date'],
        $request['start_time'],
        $request['end_time'],
        $request['total_hours'],
        $request['reason']
    );
}


/*
|--------------------------------------------------------------------------
| Find Active Approver By Stage
|--------------------------------------------------------------------------
|
| Stage 1 = En Salim
| Stage 2 = CK Teh
|
| The actual approver is determined from the users table using:
|   role = 'approver'
|   approval_stage = 1 or 2
|   is_active = 1
|
*/

function find_approver_by_stage(
    PDO $pdo,
    int $stage
): ?array {
    if (!in_array($stage, [1, 2], true)) {
        return null;
    }

    $stmt = $pdo->prepare(
        "SELECT
            id,
            name,
            email
         FROM users
         WHERE role = 'approver'
           AND approval_stage = :stage
           AND is_active = 1
         ORDER BY id ASC
         LIMIT 1"
    );

    $stmt->execute([
        ':stage' => $stage
    ]);

    $approver = $stmt->fetch();

    return $approver ?: null;
}


/*
|--------------------------------------------------------------------------
| Notify Stage 1 Approver
|--------------------------------------------------------------------------
|
| Called after a staff member submits a new OT request.
|
*/

function notify_stage1_approver(
    PDO $pdo,
    int $requestId
): bool {
    $request = get_request_with_staff(
        $pdo,
        $requestId
    );

    if (!$request) {
        error_log(
            "OT system: request {$requestId} not found."
        );

        return false;
    }

    $approver = find_approver_by_stage(
        $pdo,
        1
    );

    if (!$approver) {
        error_log(
            'OT system: no active Stage 1 approver found.'
        );

        return false;
    }

    $subject =
        "New OT request awaiting approval — #{$request['id']}";

    $body =
        "A new overtime request needs your approval.\n\n"
        . format_request_summary($request)
        . "\n\n"
        . "Please log in to the OT system to approve or reject this request.";

    return send_ot_mail(
        $approver['email'],
        $approver['name'],
        $subject,
        $body
    );
}


/*
|--------------------------------------------------------------------------
| Notify Stage 2 Approver
|--------------------------------------------------------------------------
|
| Called after Stage 1 approves an OT request.
|
*/

function notify_stage2_approver(
    PDO $pdo,
    int $requestId
): bool {
    $request = get_request_with_staff(
        $pdo,
        $requestId
    );

    if (!$request) {
        error_log(
            "OT system: request {$requestId} not found."
        );

        return false;
    }

    $approver = find_approver_by_stage(
        $pdo,
        2
    );

    if (!$approver) {
        error_log(
            'OT system: no active Stage 2 approver found.'
        );

        return false;
    }

    $subject =
        "OT request needs final approval — #{$request['id']}";

    $body =
        "The following overtime request has passed Stage 1 "
        . "and now needs your final approval.\n\n"
        . format_request_summary($request)
        . "\n\n"
        . "Please log in to the OT system to approve or reject this request.";

    return send_ot_mail(
        $approver['email'],
        $approver['name'],
        $subject,
        $body
    );
}