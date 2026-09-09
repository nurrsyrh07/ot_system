<?php

/** Escape for safe HTML output. */
function h($str): string
{
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

/** Hours between two "HH:MM" times, handling shifts that cross midnight. */
function calculate_hours(string $start_time, string $end_time): float
{
    $start = strtotime($start_time);
    $end   = strtotime($end_time);
    if ($start === false || $end === false) {
        return 0.0;
    }
    if ($end <= $start) {
        $end += 86400; // shift crosses midnight
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
    $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
}

function flash_get(): ?array
{
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

/** Human-readable label for an ot_requests.status value. */
function status_label(string $status): string
{
    $map = [
        'pending_stage1' => 'Pending — En Salim',
        'pending_stage2' => 'Pending — CK Teh',
        'approved'       => 'Approved',
        'rejected'       => 'Rejected',
    ];
    return $map[$status] ?? $status;
}
