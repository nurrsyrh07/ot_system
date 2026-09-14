<?php

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_role('approver');

$error = null;
$successPassword = null;
$selectedStaff = null;

$search = trim((string)($_GET['q'] ?? $_POST['q'] ?? ''));
$selectedId = (int)($_GET['staff_id'] ?? $_POST['staff_id'] ?? 0);

/*
|--------------------------------------------------------------------------
| Search staff
|--------------------------------------------------------------------------
*/
$matches = [];

if ($search !== '') {

    $stmt = $pdo->prepare(
        'SELECT
            id,
            staff_no,
            name,
            department,
            email,
            email_type,
            category,
            is_active
         FROM users
         WHERE role = "staff"
           AND is_active = 1
           AND (
                staff_no = :exact
                OR name LIKE :name
           )
         ORDER BY name ASC
         LIMIT 20'
    );

    $stmt->execute([
        ':exact' => $search,
        ':name'  => '%' . $search . '%',
    ]);

    $matches = $stmt->fetchAll();
}

/*
|--------------------------------------------------------------------------
| Issue temporary password
|--------------------------------------------------------------------------
*/
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['issue_temporary_password'])
) {

    /*
    |--------------------------------------------------------------------------
    | CSRF
    |--------------------------------------------------------------------------
    */
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {

        $error = 'Your session expired. Please try again.';

    /*
    |--------------------------------------------------------------------------
    | Require in-person badge verification
    |--------------------------------------------------------------------------
    */
    } elseif (empty($_POST['badge_verified'])) {

        $error = 'Please confirm that you verified the staff member\'s badge in person.';

    } else {

        $staffId = (int)($_POST['staff_id'] ?? 0);

        /*
        |--------------------------------------------------------------------------
        | Load staff account
        |--------------------------------------------------------------------------
        */
        $stmt = $pdo->prepare(
            'SELECT
                id,
                staff_no,
                name,
                department,
                email,
                email_type,
                category
             FROM users
             WHERE id = :id
               AND role = "staff"
               AND is_active = 1
             LIMIT 1'
        );

        $stmt->execute([
            ':id' => $staffId,
        ]);

        $selectedStaff = $stmt->fetch();

        if (!$selectedStaff) {

            $error = 'Staff account not found or inactive.';

        } else {

            try {

                /*
                |--------------------------------------------------------------------------
                | Generate secure temporary password
                |--------------------------------------------------------------------------
                */
                $temporaryPassword = '';

                $alphabet =
                    'ABCDEFGHJKLMNPQRSTUVWXYZ' .
                    'abcdefghijkmnopqrstuvwxyz' .
                    '23456789';

                $max = strlen($alphabet) - 1;

                for ($i = 0; $i < 12; $i++) {
                    $temporaryPassword .= $alphabet[random_int(0, $max)];
                }

                /*
                |--------------------------------------------------------------------------
                | Hash temporary password
                |--------------------------------------------------------------------------
                */
                $hash = password_hash(
                    $temporaryPassword,
                    PASSWORD_DEFAULT
                );

                if ($hash === false) {
                    throw new RuntimeException(
                        'Unable to generate password hash.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Temporary password expires after 24 hours
                |--------------------------------------------------------------------------
                */
                $expiresAt = date(
                    'Y-m-d H:i:s',
                    time() + (24 * 60 * 60)
                );

                /*
                |--------------------------------------------------------------------------
                | Start transaction
                |--------------------------------------------------------------------------
                */
                $pdo->beginTransaction();

                /*
                |--------------------------------------------------------------------------
                | Update password
                |--------------------------------------------------------------------------
                */
                $stmt = $pdo->prepare(
                    'UPDATE users
                     SET
                        password_hash = :password_hash,
                        must_change_password = 1,
                        temporary_password_expires_at = :expires_at
                     WHERE id = :id
                       AND role = "staff"
                       AND is_active = 1
                     LIMIT 1'
                );

                $stmt->execute([
                    ':password_hash' => $hash,
                    ':expires_at'    => $expiresAt,
                    ':id'            => $staffId,
                ]);

                /*
                |--------------------------------------------------------------------------
                | Make sure user was actually updated
                |--------------------------------------------------------------------------
                */
                if ($stmt->rowCount() !== 1) {
                    throw new RuntimeException(
                        'The staff password was not updated.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Invalidate existing email password-reset tokens
                |--------------------------------------------------------------------------
                */
                $stmt = $pdo->prepare(
                    'UPDATE password_resets
                     SET used_at = NOW()
                     WHERE user_id = :user_id
                       AND used_at IS NULL'
                );

                $stmt->execute([
                    ':user_id' => $staffId,
                ]);

                /*
                |--------------------------------------------------------------------------
                | Audit manual password reset
                |--------------------------------------------------------------------------
                */
                $stmt = $pdo->prepare(
                    'INSERT INTO password_reset_audit
                    (
                        user_id,
                        approver_id,
                        method,
                        created_at
                    )
                    VALUES
                    (
                        :user_id,
                        :approver_id,
                        "manual",
                        NOW()
                    )'
                );

                $stmt->execute([
                    ':user_id'     => $staffId,
                    ':approver_id' => current_user_id(),
                ]);

                /*
                |--------------------------------------------------------------------------
                | Commit
                |--------------------------------------------------------------------------
                */
                $pdo->commit();

                /*
                |--------------------------------------------------------------------------
                | Show temporary password ONCE
                |--------------------------------------------------------------------------
                */
                $successPassword = $temporaryPassword;

                /*
                |--------------------------------------------------------------------------
                | Keep selected staff visible
                |--------------------------------------------------------------------------
                */
                $selectedStaff = $selectedStaff;

            } catch (Throwable $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                /*
                |--------------------------------------------------------------------------
                | IMPORTANT:
                | Log exact database error to Apache/PHP error log.
                |--------------------------------------------------------------------------
                */
                error_log(
                    'OT system: manual password reset failed: ' .
                    $e->getMessage()
                );

                /*
                |--------------------------------------------------------------------------
                | For local development, show the real error.
                |
                | Remove this detail after testing production.
                |--------------------------------------------------------------------------
                */
                $error =
                    'The temporary password could not be issued. ' .
                    'Database error: ' .
                    $e->getMessage();
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| Load selected staff for confirmation screen
|--------------------------------------------------------------------------
*/
$selectedForConfirm = null;

if ($selectedId > 0 && $successPassword === null) {

    $stmt = $pdo->prepare(
        'SELECT
            id,
            staff_no,
            name,
            department,
            email,
            email_type,
            category
         FROM users
         WHERE id = :id
           AND role = "staff"
           AND is_active = 1
         LIMIT 1'
    );

    $stmt->execute([
        ':id' => $selectedId,
    ]);

    $selectedForConfirm = $stmt->fetch();

    if (!$selectedForConfirm && !$error) {
        $error = 'Staff account not found or inactive.';
    }
}

$page_title = 'Manual Password Reset';

include __DIR__ . '/includes/header.php';
?>

<div class="page-heading">
    <div>
        <div class="eyebrow">HR / SUPERVISOR</div>

        <h1>Manual Password Reset</h1>

        <p class="subtitle">
            Issue a one-time temporary password after verifying
            the staff member in person.
        </p>
    </div>
</div>

<?php if ($error): ?>

    <div class="form-error">
        <?= h($error) ?>
    </div>

<?php endif; ?>


<?php if ($successPassword !== null && $selectedStaff): ?>

    <div class="manual-reset-result">

        <div class="manual-reset-result-icon">
            ✓
        </div>

        <div>

            <h2>Temporary password issued</h2>

            <p>
                Give this password directly to
                <strong><?= h($selectedStaff['name']) ?></strong>
                (<?= h($selectedStaff['staff_no']) ?>).
            </p>

            <div class="temporary-password">
                <?= h($successPassword) ?>
            </div>

            <p class="manual-reset-warning">
                This password expires in 24 hours and must be changed
                immediately after login.
                It will not be shown again after you leave or refresh this page.
            </p>

        </div>

    </div>

<?php endif; ?>


<div class="manual-reset-card">

    <h2>Find staff account</h2>

    <p class="subtitle">
        Search by exact staff number or staff name.
    </p>

    <form method="get" class="manual-reset-search">

        <input
            type="text"
            name="q"
            value="<?= h($search) ?>"
            placeholder="e.g. S002 or Halim"
            autofocus
        >

        <button type="submit">
            Search
        </button>

    </form>


    <?php if ($search !== '' && !$matches): ?>

        <p class="empty-state">
            No active staff account matched your search.
        </p>

    <?php elseif ($matches): ?>

        <div class="manual-reset-list">

            <?php foreach ($matches as $staff): ?>

                <div class="manual-reset-row">

                    <div>

                        <strong>
                            <?= h($staff['name']) ?>
                        </strong>

                        <span>
                            <?= h($staff['staff_no']) ?>
                            ·
                            <?= h(
                                $staff['department']
                                ?: 'No department'
                            ) ?>
                        </span>

                        <small>

                            <?php if (!empty($staff['email'])): ?>

                                <?= ($staff['email_type'] ?? '') === 'personal'
                                    ? 'Personal email'
                                    : 'Company email' ?>

                            <?php else: ?>

                                No email on file

                            <?php endif; ?>

                        </small>

                    </div>

                    <a
                        class="btn-secondary"
                        href="manual_reset_password.php?q=<?= urlencode($staff['staff_no']) ?>&staff_id=<?= (int)$staff['id'] ?>"
                    >
                        Select
                    </a>

                </div>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</div>


<?php if ($selectedForConfirm && $successPassword === null): ?>

    <div class="manual-reset-card manual-reset-confirm">

        <h2>
            Verify staff member
        </h2>


        <div class="detail-grid">

            <dt>Name</dt>

            <dd>
                <?= h($selectedForConfirm['name']) ?>
            </dd>


            <dt>Staff No.</dt>

            <dd>
                <?= h($selectedForConfirm['staff_no']) ?>
            </dd>


            <dt>Department</dt>

            <dd>
                <?= h(
                    $selectedForConfirm['department']
                    ?: '—'
                ) ?>
            </dd>


            <dt>Email</dt>

            <dd>

                <?php if (!empty($selectedForConfirm['email'])): ?>

                    <?= h($selectedForConfirm['email']) ?>

                <?php else: ?>

                    No email on file

                <?php endif; ?>

            </dd>

        </div>


        <div class="manual-reset-security-note">

            <strong>
                In-person verification required
            </strong>

            <p>
                Check the staff member's company badge or identity
                in person before issuing a temporary password.
                Do not issue a password based only on a staff number,
                name, phone call, message, or email request.
            </p>

        </div>


        <form method="post">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= h(csrf_token()) ?>"
            >

            <input
                type="hidden"
                name="staff_id"
                value="<?= (int)$selectedForConfirm['id'] ?>"
            >

            <input
                type="hidden"
                name="q"
                value="<?= h($search) ?>"
            >


            <label class="checkbox-field">

                <input
                    type="checkbox"
                    name="badge_verified"
                    value="1"
                    required
                >

                <span>

                    <strong>
                        I have verified this staff member's badge in person.
                    </strong>

                    <small>
                        I understand that I am responsible for confirming
                        the employee's identity.
                    </small>

                </span>

            </label>


            <button
                type="submit"
                name="issue_temporary_password"
                value="1"
            >
                Generate Temporary Password
            </button>

        </form>

    </div>

<?php endif; ?>


<?php include __DIR__ . '/includes/footer.php'; ?>