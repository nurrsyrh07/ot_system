<?php
/**
 * config/mail.php
 *
 * Notification emails for the OT approval chain.
 *
 * -------------------------------------------------------------------------
 *   Sends via PHP's mail() straight to the internal SMTP relay at
 *   10.0.0.3:25 — the same relay QPM Auto's PHP jobs already use
 *   successfully via php.ini's [mail function] section. No Outlook COM,
 *   no sendmail.exe.
 *   ini_set() below points mail() at that relay at runtime, so this works
 *   even if the php.ini this web app's Apache actually loads isn't the
 *   same php.ini QPM Auto's jobs run under (worth double-checking if you
 *   still see connection errors — Windows/XAMPP boxes sometimes have more
 *   than one php.ini in play).
 *   Sends go straight to whichever approver find_approver_by_stage() looks
 *   up (real recipients, no test redirect) — make sure the `users` table
 *   has the correct email set for approval_stage 1 and 2 before testing.
 * -------------------------------------------------------------------------
 */

// Same internal relay QPM Auto's PHP jobs already send through — no
// Outlook needed.
const SMTP_RELAY_HOST = '10.0.0.3';
const SMTP_RELAY_PORT = 25;

const MAIL_FROM_ADDRESS = 'mis@jcyinternational.com';
// If the relay rejects MAIL_FROM_ADDRESS as sender, send_ot_mail() retries
// once with this address — it's the one php.ini already sends
// successfully from.
const MAIL_FROM_FALLBACK = 'programmer1@jcyinternational.com';
const MAIL_FROM_NAME    = 'OT Requests';

/**
 * Send via PHP's mail(), pointed at the internal relay. Retries once with
 * MAIL_FROM_FALLBACK as sender if the relay rejects the primary from-address.
 */
function send_via_native_mail(string $toEmail, string $toName, string $subject, string $body): bool
{
    // Windows PHP's mail() reads SMTP/smtp_port from php.ini at call time,
    // and these ini_set() calls override that per-request — so this works
    // even if this app's php.ini isn't already pointed at the relay.
    ini_set('SMTP', SMTP_RELAY_HOST);
    ini_set('smtp_port', (string)SMTP_RELAY_PORT);

    $send = function (string $fromAddress) use ($toEmail, $subject, $body) {
        $headers  = "From: " . MAIL_FROM_NAME . " <" . $fromAddress . ">\r\n";
        $headers .= "Reply-To: " . $fromAddress . "\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        return mail($toEmail, $subject, $body, $headers);
    };

    $ok = $send(MAIL_FROM_ADDRESS);
    if (!$ok) {
        error_log("OT system: relay rejected From: " . MAIL_FROM_ADDRESS . " — retrying with fallback sender " . MAIL_FROM_FALLBACK);
        $ok = $send(MAIL_FROM_FALLBACK);
    }
    if (!$ok) {
        error_log("OT system: failed to send mail to {$toEmail} — subject: {$subject}");
    }
    return $ok;
}

/**
 * Single entry point every notification goes through.
 */
function send_ot_mail(string $toEmail, string $toName, string $subject, string $body): bool
{
    return send_via_native_mail($toEmail, $toName, $subject, $body);
}

function format_request_summary(array $request): string
{
    return sprintf(
        "Request #%d\nStaff: %s (%s)\nDate: %s\nTime: %s - %s\nHours: %s\nReason: %s",
        $request['id'],
        $request['staff_name'],
        $request['staff_no'],
        $request['ot_date'],
        substr($request['start_time'], 0, 5),
        substr($request['end_time'], 0, 5),
        $request['total_hours'],
        $request['reason']
    );
}

/** Fetch a request plus the submitting staff member's name/staff_no. */
function get_request_with_staff(PDO $pdo, int $requestId): ?array
{
    $stmt = $pdo->prepare(
        "SELECT r.*, u.name AS staff_name, u.staff_no
         FROM ot_requests r
         JOIN users u ON u.id = r.staff_id
         WHERE r.id = ?"
    );
    $stmt->execute([$requestId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/** Currently active approver assigned to a given stage (1 or 2), if any. */
function find_approver_by_stage(PDO $pdo, int $stage): ?array
{
    $stmt = $pdo->prepare(
        "SELECT * FROM users
         WHERE role = 'approver' AND approval_stage = ? AND is_active = 1
         LIMIT 1"
    );
    $stmt->execute([$stage]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Notify the stage-1 approver (En Salim) that a new request needs review.
 * Called by submit_ot.php right after a staff member's request is inserted.
 */
function notify_stage1_approver(int $request_id): void
{
    global $pdo;

    $request = get_request_with_staff($pdo, $request_id);
    if (!$request) {
        error_log("OT system: notify_stage1_approver — request {$request_id} not found");
        return;
    }

    $approver = find_approver_by_stage($pdo, 1);
    if (!$approver) {
        error_log("OT system: notify_stage1_approver — no active stage-1 approver configured");
        return;
    }

    $subject = "New OT request awaiting your approval — #{$request['id']}";
    $body = "A new overtime request needs your review:\n\n"
          . format_request_summary($request)
          . "\n\nPlease log in to the OT system to approve or reject this request.";

    send_ot_mail($approver['email'], $approver['name'], $subject, $body);
}

/**
 * Notify the stage-2 approver (CK Teh) once the stage-1 approver has
 * approved a request. Called by request_detail.php right after the status
 * flips to pending_stage2.
 */
function notify_stage2_approver(int $request_id): void
{
    global $pdo;

    $request = get_request_with_staff($pdo, $request_id);
    if (!$request) {
        error_log("OT system: notify_stage2_approver — request {$request_id} not found");
        return;
    }

    $approver = find_approver_by_stage($pdo, 2);
    if (!$approver) {
        error_log("OT system: notify_stage2_approver — no active stage-2 approver configured");
        return;
    }

    $subject = "OT request approved at stage 1, needs final approval — #{$request['id']}";
    $body = "The following overtime request has been approved by the stage-1 "
          . "approver and now needs your final approval:\n\n"
          . format_request_summary($request)
          . "\n\nPlease log in to the OT system to approve or reject this request.";

    send_ot_mail($approver['email'], $approver['name'], $subject, $body);
}

/*
 * ---------------------------------------------------------------------
 * PHPMailer alternative — only needed if the relay ever starts requiring
 * SMTP auth (10.0.0.3:25 currently doesn't, matching QPM Auto's setup).
 * Install via: composer require phpmailer/phpmailer
 * Then replace send_via_native_mail() above with:
 * ---------------------------------------------------------------------
 *
 * require '/path/to/vendor/autoload.php';
 * use PHPMailer\PHPMailer\PHPMailer;
 * use PHPMailer\PHPMailer\Exception;
 *
 * function send_via_native_mail(string $toEmail, string $toName, string $subject, string $body): bool
 * {
 *     $mail = new PHPMailer(true);
 *     try {
 *         $mail->isSMTP();
 *         $mail->Host       = SMTP_RELAY_HOST;
 *         $mail->SMTPAuth   = true;
 *         $mail->Username   = 'your-username';
 *         $mail->Password   = 'your-password';
 *         $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
 *         $mail->Port       = SMTP_RELAY_PORT;
 *
 *         $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
 *         $mail->addAddress($toEmail, $toName);
 *         $mail->Subject = $subject;
 *         $mail->Body    = $body;
 *
 *         $mail->send();
 *         return true;
 *     } catch (Exception $e) {
 *         error_log("OT system: PHPMailer error — {$mail->ErrorInfo}");
 *         return false;
 *     }
 * }
 */
