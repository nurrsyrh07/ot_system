<?php

/** Escape a value for safe HTML output. */
function h($str): string
{
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

/**
 * Calculate hours between HH:MM times.
 * If the end time is earlier than the start time, the shift is treated
 * as crossing midnight.
 */
function calculate_hours(string $start_time, string $end_time): float
{
    $start = strtotime($start_time);
    $end = strtotime($end_time);

    if ($start === false || $end === false) {
        return 0.0;
    }

    if ($end <= $start) {
        $end += 86400;
    }

    return round(($end - $start) / 3600, 2);
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function flash_set(string $msg, string $type = 'info'): void
{
    $_SESSION['flash'] = [
        'msg' => $msg,
        'type' => $type
    ];
}

function flash_get(): ?array
{
    if (empty($_SESSION['flash'])) {
        return null;
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);

    return $flash;
}

/** Human-readable label for ot_requests.status. */
function status_label(string $status): string
{
    $map = [
        'pending_stage1' => 'Pending — En Salim',
        'pending_stage2' => 'Pending — CK Teh',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'cancelled' => 'Cancelled',
    ];

    return $map[$status] ?? $status;
}

/** Return the label for an approval stage. */
function approval_stage_label(?int $stage): string
{
    return match ($stage) {
        1 => 'Stage 1 — En Salim',
        2 => 'Stage 2 — CK Teh',
        default => 'Staff',
    };
}
function approval_status_label(int $status): string
{
    return match ($status) {
        1 => 'Pending',
        2 => 'Approved',
        3 => 'Rejected',
        default => 'Unknown',
    };
}

/**
 * Absolute base URL of the app (scheme + host + path to the app root, no
 * trailing slash) — used to build links in emails, since those need a full
 * URL rather than a relative one. Works out the app's root folder from
 * wherever the currently-running script actually lives.
 */
function base_url(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $root   = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    return $scheme . '://' . $host . $root;
}
/** Human-readable label for the two HR-assigned staff categories. */
function category_label(?string $category): string
{
    return match ($category) {
        'below_ae' => 'Below Assistant Engineer',
        'ae_above' => 'Assistant Engineer and Above',
        default => 'Not confirmed',
    };
}

/**
 * Return the 21st-to-20th claim period represented by a YYYY-MM month.
 * The month means the month containing the 20th (the end of the cycle).
 */
function claim_period(string $yearMonth): array
{
    if (!preg_match('/^\d{4}-\d{2}$/', $yearMonth)) {
        $yearMonth = date('Y-m');
    }

    [$year, $month] = array_map('intval', explode('-', $yearMonth));
    if ($month < 1 || $month > 12) {
        $year = (int)date('Y');
        $month = (int)date('n');
    }

    $end = new DateTimeImmutable(sprintf('%04d-%02d-20', $year, $month));
    $start = $end->modify('-1 month')->modify('+1 day');

    return [
        'start' => $start->format('Y-m-d'),
        'end' => $end->format('Y-m-d'),
        'label' => $start->format('d/m/Y') . ' to ' . $end->format('d/m/Y'),
        'month_label' => $end->format('F Y'),
    ];
}

/** Current claim month: before the 21st, use this month's 20th; from 21st onward use next month's 20th. */
function current_claim_month(): string
{
    $today = new DateTimeImmutable('today');
    $end = ((int)$today->format('d') >= 21) ? $today->modify('+1 month') : $today;
    return $end->format('Y-m');
}

function weekday_name(string $date): string
{
    return date('l', strtotime($date));
}
