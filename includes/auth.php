<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** Redirect to login.php if nobody is logged in. */
function require_login(): void
{
    if (empty($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Redirect / deny unless the logged-in user has one of the given roles.
 * @param string|array $roles
 */
function require_role($roles): void
{
    require_login();
    $roles = is_array($roles) ? $roles : [$roles];
    if (!in_array($_SESSION['role'] ?? null, $roles, true)) {
        http_response_code(403);
        die('You do not have access to this page.');
    }
}

function current_user_id(): ?int
{
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

function current_role(): ?string
{
    return $_SESSION['role'] ?? null;
}

/** 1 (En Salim), 2 (CK Teh), or null for non-approvers. */
function current_approval_stage(): ?int
{
    return isset($_SESSION['approval_stage']) && $_SESSION['approval_stage'] !== null
        ? (int)$_SESSION['approval_stage']
        : null;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf($token): bool
{
    return !empty($_SESSION['csrf_token']) && is_string($token)
        && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Attempt to log a user in by staff_no + password.
 * On success, populates the session and returns true.
 */
function attempt_login(PDO $pdo, string $staff_no, string $password): bool
{
    $stmt = $pdo->prepare(
        'SELECT * FROM users WHERE staff_no = :staff_no AND is_active = 1 LIMIT 1'
    );
    $stmt->execute([':staff_no' => $staff_no]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return false;
    }

    // Staff accounts must have an HR-confirmed level before they can log in.
    // Approvers do not use the staff level, so this check applies only to role=staff.
    if ($user['role'] === 'staff' && $user['level'] === null) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['user_id']        = (int)$user['id'];
    $_SESSION['staff_no']       = $user['staff_no'];
    $_SESSION['name']           = $user['name'];
    $_SESSION['role']           = $user['role'];
    $_SESSION['approval_stage'] = $user['approval_stage'] !== null ? (int)$user['approval_stage'] : null;
    return true;
}