<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/mail.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (current_user_id()) {
    redirect('dashboard.php');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ------------------------------------------------------------
    // CSRF validation
    // ------------------------------------------------------------
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Your session expired. Please try again.';
    } else {

        // --------------------------------------------------------
        // Read form values
        // --------------------------------------------------------
        $staff_no = trim((string)($_POST['staff_no'] ?? ''));
        $name = trim((string)($_POST['name'] ?? ''));
        $department = trim((string)($_POST['department'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));

        $no_company_email = isset($_POST['no_company_email']);

        $password = (string)($_POST['password'] ?? '');
        $confirm = (string)($_POST['confirm'] ?? '');

        // Normalize email if one was entered
        if ($email !== '') {
            $email = strtolower($email);
        }

        // --------------------------------------------------------
        // Validation
        // --------------------------------------------------------

        // Required basic fields
        if ($staff_no === '' || $name === '' || $password === '') {

            $error = 'Please fill in all required fields.';

        // Company email required unless exception is selected
        } elseif (!$no_company_email && $email === '') {

            $error = 'Please enter your company email, or check "I don\'t have a company email".';

        // Validate email format when an email was entered
        } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {

            $error = 'Please enter a valid email address.';

        // Company email must use company domain
        } elseif (
            !$no_company_email &&
            $email !== '' &&
            !preg_match('/@jcyinternational\.com$/i', $email)
        ) {

            $error = 'Please use your @jcyinternational.com company email address.';

        // Password confirmation
        } elseif ($password !== $confirm) {

            $error = 'Passwords do not match.';

        // Minimum password length
        } elseif (strlen($password) < 6) {

            $error = 'Password must be at least 6 characters.';

        } else {

            // ----------------------------------------------------
            // Determine email type
            // ----------------------------------------------------
            //
            // Checkbox checked + blank email:
            //   email      = NULL
            //   email_type = NULL
            //
            // Checkbox checked + personal email:
            //   email      = personal email
            //   email_type = personal
            //
            // Checkbox not checked:
            //   email      = company email
            //   email_type = company
            // ----------------------------------------------------

            if ($no_company_email && $email === '') {
                $email = null;
            }

            if ($email === null) {
                $emailType = null;
            } elseif ($no_company_email) {
                $emailType = 'personal';
            } else {
                $emailType = 'company';
            }

            // ----------------------------------------------------
            // Check whether staff number or email already exists
            // ----------------------------------------------------
            //
            // Important:
            // PDO native prepared statements do not allow the same
            // named placeholder to be reused multiple times.
            // Therefore :email_check and :email_value are separate.
            // ----------------------------------------------------

            if ($email === null) {

                // No email: only check staff number
                $check = $pdo->prepare(
                    'SELECT id
                     FROM users
                     WHERE staff_no = :staff_no
                     LIMIT 1'
                );

                $check->execute([
                    ':staff_no' => $staff_no,
                ]);

            } else {

                // Email exists: check staff number OR email
                $check = $pdo->prepare(
                    'SELECT id
                     FROM users
                     WHERE staff_no = :staff_no
                        OR email = :email_value
                     LIMIT 1'
                );

                $check->execute([
                    ':staff_no' => $staff_no,
                    ':email_value' => $email,
                ]);
            }

            if ($check->fetch()) {

                $error = 'That staff number or email is already registered.';

            } else {

                // ------------------------------------------------
                // Create account
                // ------------------------------------------------
                try {

                    $hash = password_hash($password, PASSWORD_BCRYPT);

                    if ($hash === false) {
                        throw new RuntimeException('Unable to create password hash.');
                    }

                    // Token used later for HR/category confirmation
                    $token = bin2hex(random_bytes(32));

                    // ------------------------------------------------
                    // Insert user
                    // ------------------------------------------------
                    $ins = $pdo->prepare(
                        'INSERT INTO users
                        (
                            staff_no,
                            name,
                            department,
                            email,
                            email_type,
                            password_hash,
                            role,
                            category,
                            level_token,
                            is_active
                        )
                        VALUES
                        (
                            :staff_no,
                            :name,
                            :department,
                            :email,
                            :email_type,
                            :hash,
                            "staff",
                            NULL,
                            :token,
                            1
                        )'
                    );

                    $ins->execute([
                        ':staff_no' => $staff_no,
                        ':name' => $name,
                        ':department' => $department !== ''
                            ? $department
                            : null,
                        ':email' => $email,
                        ':email_type' => $emailType,
                        ':hash' => $hash,
                        ':token' => $token,
                    ]);

                    $newUserId = (int)$pdo->lastInsertId();

                    // ------------------------------------------------
                    // Notify HR
                    // ------------------------------------------------
                    //
                    // Email problems should NOT cancel registration.
                    // The account has already been created.
                    // ------------------------------------------------
                    try {

                        notify_hr_new_staff($newUserId);

                    } catch (Throwable $mailError) {

                        error_log(
                            'OT system: HR notification failed - ' .
                            $mailError->getMessage()
                        );
                    }

                    // ------------------------------------------------
                    // Success
                    // ------------------------------------------------
                    flash_set(
                        'Account created. Please wait for HR to confirm your account before logging in.',
                        'success'
                    );

                    redirect('login.php');

                } catch (PDOException $e) {

                    // Log the real database error for troubleshooting
                    error_log(
                        'OT system: registration database error - ' .
                        $e->getMessage()
                    );

                    $error = 'Could not create the account. Please check the details and try again.';

                } catch (Throwable $e) {

                    // Log any other unexpected error
                    error_log(
                        'OT system: registration error - ' .
                        $e->getMessage()
                    );

                    $error = 'Could not create the account. Please check the details and try again.';
                }
            }
        }
    }
}

$page_title = 'Register';

include __DIR__ . '/includes/header.php';
?>

<div class="auth-card">

    <h1>Create your account</h1>

    <p class="subtitle">
        Register with your staff number to get started.
    </p>

    <?php if ($error): ?>
        <p class="form-error"><?= h($error) ?></p>
    <?php endif; ?>

    <form method="post" novalidate>

        <input
            type="hidden"
            name="csrf_token"
            value="<?= h(csrf_token()) ?>"
        >

        <!-- Staff number -->
        <label for="staff_no">Staff no.</label>

        <input
            type="text"
            id="staff_no"
            name="staff_no"
            required
            autofocus
            value="<?= h($_POST['staff_no'] ?? '') ?>"
        >

        <!-- Full name -->
        <label for="name">Full name</label>

        <input
            type="text"
            id="name"
            name="name"
            required
            value="<?= h($_POST['name'] ?? '') ?>"
        >

        <!-- Department -->
        <label for="department">Department</label>

        <input
            type="text"
            id="department"
            name="department"
            value="<?= h($_POST['department'] ?? '') ?>"
        >

        <!-- Email -->
        <div class="form-label-with-info">
            <label for="email">Email</label>

            <button
                type="button"
                class="privacy-info-button"
                aria-label="Privacy information"
                aria-describedby="privacy-tooltip"
            >
                <span aria-hidden="true">⚠</span>
            </button>

            <div
                id="privacy-tooltip"
                class="privacy-tooltip"
                role="tooltip"
            >
                <strong>Privacy &amp; personal email</strong>

                <p>
                    Your personal email is optional. If provided, it may be used only
                    for system account notifications and password recovery.
                </p>

                <p>
                    HR may verify the email before it is activated for these purposes.
                    Your personal email will not be displayed to other staff members.
                </p>
            </div>
        </div>

        <input
            type="email"
            id="email"
            name="email"
            value="<?= h($_POST['email'] ?? '') ?>"
            placeholder="name@jcyinternational.com"
        >

        <!-- No company email checkbox -->
        <label class="checkbox-field">

            <input
                type="checkbox"
                id="no_company_email"
                name="no_company_email"
                value="1"
                <?= isset($_POST['no_company_email']) ? 'checked' : '' ?>
            >

            <span>

                <strong>I don't have a company email</strong>

                <small>
                    Personal email is optional. Leave the email field blank if you do not want to provide one.
                </small>

            </span>

        </label>

        <div class="form-help" id="email-help">
            Company email is required by default.

            If you do not have one, a personal email is optional
            and will require HR verification before it is used.
        </div>

        <!-- Password -->
        <label for="password">Password</label>

        <div class="password-wrap">

            <input
                type="password"
                id="password"
                name="password"
                required
                minlength="6"
            >

            <button
                type="button"
                class="password-toggle"
                data-target="password"
                aria-label="Show password"
            >
                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    aria-hidden="true"
                >
                    <path
                        d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"
                        stroke="currentColor"
                        stroke-width="1.8"
                    />

                    <circle
                        cx="12"
                        cy="12"
                        r="3"
                        stroke="currentColor"
                        stroke-width="1.8"
                    />
                </svg>
            </button>

        </div>

        <!-- Confirm password -->
        <label for="confirm">Confirm password</label>

        <div class="password-wrap">

            <input
                type="password"
                id="confirm"
                name="confirm"
                required
                minlength="6"
            >

            <button
                type="button"
                class="password-toggle"
                data-target="confirm"
                aria-label="Show password"
            >
                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    aria-hidden="true"
                >
                    <path
                        d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"
                        stroke="currentColor"
                        stroke-width="1.8"
                    />

                    <circle
                        cx="12"
                        cy="12"
                        r="3"
                        stroke="currentColor"
                        stroke-width="1.8"
                    />
                </svg>
            </button>

        </div>

        <!-- Submit -->
        <button type="submit">
            Create account
        </button>

    </form>

    <p class="auth-switch">
        Already have an account?
        <a href="login.php">Log in</a>
    </p>

</div>

<script>
(function () {

    // ------------------------------------------------------------
    // Email field behaviour
    // ------------------------------------------------------------

    var checkbox = document.getElementById('no_company_email');
    var email = document.getElementById('email');
    var help = document.getElementById('email-help');

    function updateEmailState() {

        if (checkbox.checked) {

            email.placeholder = 'Personal email (optional)';

            help.textContent =
                'Personal email is optional. HR will verify it before it is used for account-related purposes.';

        } else {

            email.placeholder = 'name@jcyinternational.com';

            help.textContent =
                'Company email is required unless you select "I don\'t have a company email".';
        }
    }

    checkbox.addEventListener('change', updateEmailState);

    updateEmailState();

        /*
    |--------------------------------------------------------------------------
    | Privacy tooltip
    |--------------------------------------------------------------------------
    */

    var privacyButton = document.querySelector('.privacy-info-button');
    var privacyTooltip = document.getElementById('privacy-tooltip');

    if (privacyButton && privacyTooltip) {

        privacyButton.addEventListener('click', function (event) {

            event.preventDefault();
            event.stopPropagation();

            privacyTooltip.classList.toggle('is-visible');
        });

        document.addEventListener('click', function (event) {

            if (
                !event.target.closest('.form-label-with-info')
            ) {
                privacyTooltip.classList.remove('is-visible');
            }
        });
    }


    // ------------------------------------------------------------
    // Password show/hide buttons
    // ------------------------------------------------------------

    document.querySelectorAll('.password-toggle').forEach(function (btn) {

        btn.addEventListener('click', function () {

            var input = document.getElementById(btn.dataset.target);

            if (input.type === 'password') {

                input.type = 'text';
                btn.setAttribute('aria-label', 'Hide password');

            } else {

                input.type = 'password';
                btn.setAttribute('aria-label', 'Show password');
            }
        });
    });

})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>