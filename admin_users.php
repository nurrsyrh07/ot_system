<?php
/**
 * admin_users.php
 *
 * Admin page to create and enable/disable staff, approver, and admin accounts.
 *
 * Database columns used:
 *   users.id
 *   users.staff_no
 *   users.name
 *   users.email
 *   users.password_hash
 *   users.role
 *   users.approval_stage
 *   users.is_active
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_role('admin');

$user = current_user();

$errors = [];
$notice = null;

/*
|--------------------------------------------------------------------------
| Create User
|--------------------------------------------------------------------------
*/
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['form'] ?? '') === 'create_user'
) {
    // CSRF protection
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please refresh the page and try again.';
    }

    $staffNo = trim($_POST['staff_no'] ?? '');
    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role    = $_POST['role'] ?? '';
    $stage   = $_POST['approval_stage'] ?? '';

    /*
     * Validate required fields
     */
    if ($staffNo === '') {
        $errors[] = 'Staff number is required.';
    }

    if ($name === '') {
        $errors[] = 'Name is required.';
    }

    if ($email === '') {
        $errors[] = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if ($password === '') {
        $errors[] = 'Password is required.';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }

    /*
     * Validate role
     */
    if (!in_array($role, ['staff', 'approver', 'admin'], true)) {
        $errors[] = 'Invalid role selected.';
    }

    /*
     * Approvers must have stage 1 or stage 2.
     * Staff/admin accounts do not need a stage.
     */
    if (
        $role === 'approver'
        && !in_array($stage, ['1', '2'], true)
    ) {
        $errors[] = 'Approver accounts must be assigned stage 1 or stage 2.';
    }

    /*
     * Check duplicate staff number
     */
    if (empty($errors)) {
        $check = $pdo->prepare(
            'SELECT id
             FROM users
             WHERE staff_no = :staff_no
             LIMIT 1'
        );

        $check->execute([
            ':staff_no' => $staffNo,
        ]);

        if ($check->fetch()) {
            $errors[] = 'That staff number is already registered.';
        }
    }

    /*
     * Check duplicate email
     */
    if (empty($errors)) {
        $check = $pdo->prepare(
            'SELECT id
             FROM users
             WHERE email = :email
             LIMIT 1'
        );

        $check->execute([
            ':email' => $email,
        ]);

        if ($check->fetch()) {
            $errors[] = 'That email address is already registered.';
        }
    }

    /*
     * Prevent multiple active approvers for the same stage.
     *
     * This matches the current notification logic, which finds one
     * active approver for each stage.
     */
    if (
        $role === 'approver'
        && empty($errors)
    ) {
        $check = $pdo->prepare(
            "SELECT id, name
             FROM users
             WHERE role = 'approver'
               AND approval_stage = :stage
               AND is_active = 1
             ORDER BY id ASC
             LIMIT 1"
        );

        $check->execute([
            ':stage' => (int) $stage,
        ]);

        $existing = $check->fetch();

        if ($existing) {
            $errors[] =
                "Stage {$stage} already has an active approver: "
                . $existing['name']
                . '. Disable that account first before creating another approver for this stage.';
        }
    }

    /*
     * Create account
     */
    if (empty($errors)) {
        /*
         * IMPORTANT:
         * Never store the plain-text password.
         *
         * password_hash() creates a secure one-way password hash.
         */
        $passwordHash = password_hash(
            $password,
            PASSWORD_DEFAULT
        );

        if ($passwordHash === false) {
            $errors[] = 'Unable to securely create the password.';
        } else {
            $approvalStage = null;

            if ($role === 'approver') {
                $approvalStage = (int) $stage;
            }

            try {
                $stmt = $pdo->prepare(
                    'INSERT INTO users
                    (
                        staff_no,
                        name,
                        email,
                        password_hash,
                        role,
                        approval_stage,
                        is_active
                    )
                    VALUES
                    (
                        :staff_no,
                        :name,
                        :email,
                        :password_hash,
                        :role,
                        :approval_stage,
                        1
                    )'
                );

                $stmt->execute([
                    ':staff_no'      => $staffNo,
                    ':name'          => $name,
                    ':email'         => $email,
                    ':password_hash' => $passwordHash,
                    ':role'          => $role,
                    ':approval_stage'=> $approvalStage,
                ]);

                $notice = "Account created successfully for {$name}.";

            } catch (PDOException $e) {
                /*
                 * Do not expose database details to users.
                 */
                error_log(
                    'OT system: failed to create user — '
                    . $e->getMessage()
                );

                $errors[] = 'Unable to create the account. Please check the details and try again.';
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| Enable / Disable User
|--------------------------------------------------------------------------
*/
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['form'] ?? '') === 'toggle_status'
) {
    /*
     * CSRF protection
     */
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please refresh the page and try again.';
    } else {
        $targetId = (int) ($_POST['user_id'] ?? 0);
        $newStatus = $_POST['new_status'] ?? '';

        if ($targetId <= 0) {
            $errors[] = 'Invalid user account.';
        } elseif (!in_array($newStatus, ['active', 'disabled'], true)) {
            $errors[] = 'Invalid account status.';
        } elseif (
            $targetId === (int) $user['id']
            && $newStatus === 'disabled'
        ) {
            /*
             * Prevent admin from disabling their own account.
             */
            $errors[] = "You can't disable your own account.";
        } else {
            /*
             * If enabling an approver, check whether another active
             * approver already occupies the same stage.
             */
            if ($newStatus === 'active') {
                $check = $pdo->prepare(
                    'SELECT id, name, role, approval_stage
                     FROM users
                     WHERE id = :id
                     LIMIT 1'
                );

                $check->execute([
                    ':id' => $targetId,
                ]);

                $targetUser = $check->fetch();

                if (!$targetUser) {
                    $errors[] = 'User account not found.';
                } elseif (
                    $targetUser['role'] === 'approver'
                    && $targetUser['approval_stage'] !== null
                ) {
                    $check = $pdo->prepare(
                        "SELECT id, name
                         FROM users
                         WHERE role = 'approver'
                           AND approval_stage = :stage
                           AND is_active = 1
                           AND id <> :id
                         ORDER BY id ASC
                         LIMIT 1"
                    );

                    $check->execute([
                        ':stage' => (int) $targetUser['approval_stage'],
                        ':id'    => $targetId,
                    ]);

                    $existing = $check->fetch();

                    if ($existing) {
                        $errors[] =
                            'Cannot enable this approver because stage '
                            . (int) $targetUser['approval_stage']
                            . ' is already assigned to active approver '
                            . $existing['name']
                            . '.';
                    }
                }
            }

            /*
             * Update account status
             */
            if (empty($errors)) {
                $upd = $pdo->prepare(
                    'UPDATE users
                     SET is_active = :is_active
                     WHERE id = :id'
                );

                $upd->execute([
                    ':is_active' => $newStatus === 'active' ? 1 : 0,
                    ':id'        => $targetId,
                ]);

                $notice = 'User account status updated successfully.';
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| Get All Users
|--------------------------------------------------------------------------
*/
$allUsers = $pdo->query(
    'SELECT
        id,
        staff_no,
        name,
        email,
        role,
        approval_stage,
        is_active,
        created_at
     FROM users
     ORDER BY
        role,
        approval_stage,
        name'
)->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<h1>Manage Users</h1>

<?php foreach ($errors as $e): ?>
    <div class="alert alert-error">
        <?= h($e) ?>
    </div>
<?php endforeach; ?>

<?php if ($notice): ?>
    <div class="alert alert-success">
        <?= h($notice) ?>
    </div>
<?php endif; ?>


<!-- ================================================================
     CREATE ACCOUNT
================================================================ -->
<h2>Create Account</h2>

<form method="post" class="user-form">

    <input
        type="hidden"
        name="form"
        value="create_user"
    >

    <input
        type="hidden"
        name="csrf_token"
        value="<?= h(csrf_token()) ?>"
    >

    <label>
        Staff Number
        <input
            type="text"
            name="staff_no"
            maxlength="20"
            required
        >
    </label>

    <label>
        Name
        <input
            type="text"
            name="name"
            maxlength="100"
            required
        >
    </label>

    <label>
        Email
        <input
            type="email"
            name="email"
            maxlength="150"
            required
        >
    </label>

    <label>
        Temporary Password
        <input
            type="password"
            name="password"
            minlength="6"
            required
        >
    </label>

    <label>
        Role
        <select
            name="role"
            id="role-select"
            required
        >
            <option value="staff">Staff</option>
            <option value="approver">Approver</option>
            <option value="admin">Admin</option>
        </select>
    </label>

    <label
        id="stage-field"
        style="display:none"
    >
        Approver Stage

        <select name="approval_stage">
            <option value="1">
                Stage 1 (e.g. En Salim)
            </option>

            <option value="2">
                Stage 2 (e.g. CK Teh)
            </option>
        </select>
    </label>

    <button
        type="submit"
        class="btn"
    >
        Create Account
    </button>

</form>


<script>
const roleSelect = document.getElementById('role-select');
const stageField = document.getElementById('stage-field');

function syncStageField() {
    if (roleSelect.value === 'approver') {
        stageField.style.display = '';
    } else {
        stageField.style.display = 'none';
    }
}

roleSelect.addEventListener('change', syncStageField);

syncStageField();
</script>


<!-- ================================================================
     EXISTING ACCOUNTS
================================================================ -->
<h2>Existing Accounts</h2>

<table class="user-list">

    <thead>
        <tr>
            <th>Staff No.</th>
            <th>Name</th>
            <th>Email</th>
            <th>Role</th>
            <th>Stage</th>
            <th>Status</th>
            <th>Action</th>
        </tr>
    </thead>

    <tbody>

    <?php foreach ($allUsers as $u): ?>

        <tr>

            <td>
                <?= h($u['staff_no'] ?? '—') ?>
            </td>

            <td>
                <?= h($u['name']) ?>
            </td>

            <td>
                <?= h($u['email']) ?>
            </td>

            <td>
                <?= h($u['role']) ?>
            </td>

            <td>
                <?php if ($u['approval_stage'] !== null): ?>
                    Stage <?= (int) $u['approval_stage'] ?>
                <?php else: ?>
                    —
                <?php endif; ?>
            </td>

            <td>
                <?php if ((int) $u['is_active'] === 1): ?>
                    Active
                <?php else: ?>
                    Disabled
                <?php endif; ?>
            </td>

            <td>

                <?php if ((int) $u['id'] !== (int) $user['id']): ?>

                    <form
                        method="post"
                        style="display:inline"
                    >

                        <input
                            type="hidden"
                            name="form"
                            value="toggle_status"
                        >

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= h(csrf_token()) ?>"
                        >

                        <input
                            type="hidden"
                            name="user_id"
                            value="<?= (int) $u['id'] ?>"
                        >

                        <?php if ((int) $u['is_active'] === 1): ?>

                            <input
                                type="hidden"
                                name="new_status"
                                value="disabled"
                            >

                            <button
                                type="submit"
                                class="btn btn-small btn-reject"
                                onclick="return confirm('Disable this user account?');"
                            >
                                Disable
                            </button>

                        <?php else: ?>

                            <input
                                type="hidden"
                                name="new_status"
                                value="active"
                            >

                            <button
                                type="submit"
                                class="btn btn-small btn-approve"
                                onclick="return confirm('Enable this user account?');"
                            >
                                Enable
                            </button>

                        <?php endif; ?>

                    </form>

                <?php else: ?>

                    <span>Current account</span>

                <?php endif; ?>

            </td>

        </tr>

    <?php endforeach; ?>

    </tbody>

</table>
