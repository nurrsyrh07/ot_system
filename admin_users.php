<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/mail.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_role('admin');

$error = null;
$notice = null;
$tempPassword = null; // shown once after a reset, never stored in plain text

/*
 * All three actions (create user, toggle active, reset password) post to
 * this same page with an `action` field, matching the pattern already
 * used in request_detail.php (single-file POST handling, CSRF-checked).
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Your session expired. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'create_user') {
            $staff_no   = trim($_POST['staff_no'] ?? '');
            $name       = trim($_POST['name'] ?? '');
            $department = trim($_POST['department'] ?? '');
            $email      = trim($_POST['email'] ?? '');
            $password   = (string)($_POST['password'] ?? '');
            $role       = $_POST['role'] ?? '';
            $stage      = $_POST['approval_stage'] ?? '';

            if ($staff_no === '' || $name === '' || $email === '' || $password === '') {
                $error = 'Please fill in all required fields.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Please enter a valid email address.';
            } elseif (!in_array($role, ['staff', 'approver', 'admin'], true)) {
                $error = 'Please choose a valid role.';
            } elseif ($role === 'approver' && !in_array($stage, ['1', '2'], true)) {
                $error = 'Please choose Department Manager or General Manager for an approver account.';
            } elseif (strlen($password) < 6) {
                $error = 'Password must be at least 6 characters.';
            } else {
                $check = $pdo->prepare('SELECT id FROM users WHERE staff_no = :staff_no OR email = :email');
                $check->execute([':staff_no' => $staff_no, ':email' => $email]);

                if ($check->fetch()) {
                    $error = 'That staff number or email is already registered.';
                } else {
                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    $approvalStage = $role === 'approver' ? (int)$stage : null;

                    // Staff accounts go through the same category-declaration
                    // flow as self-registration; approver/admin don't need one.
                    $levelToken = $role === 'staff' ? bin2hex(random_bytes(32)) : null;

                    try {
                        $ins = $pdo->prepare(
                            'INSERT INTO users
                                (staff_no, name, department, email, password_hash, role, approval_stage, category, level_token, is_active)
                             VALUES
                                (:staff_no, :name, :department, :email, :hash, :role, :stage, NULL, :token, 1)'
                        );
                        $ins->execute([
                            ':staff_no'   => $staff_no,
                            ':name'       => $name,
                            ':department' => $department !== '' ? $department : null,
                            ':email'      => $email,
                            ':hash'       => $hash,
                            ':role'       => $role,
                            ':stage'      => $approvalStage,
                            ':token'      => $levelToken,
                        ]);

                        if ($role === 'staff') {
                            $newId = (int)$pdo->lastInsertId();
                            try {
                                notify_hr_new_staff($newId);
                            } catch (Throwable $mailError) {
                                error_log('OT system: HR notification failed — ' . $mailError->getMessage());
                            }
                        }

                        flash_set('Account created for ' . $name . '.', 'success');
                        redirect('admin_users.php');
                    } catch (PDOException $e) {
                        // Most likely cause: the live users.role column doesn't
                        // actually allow this value yet — see the schema-drift
                        // note at the bottom of this file.
                        error_log('OT system: create user failed — ' . $e->getMessage());
                        $error = 'Could not create the account. The database may not yet allow the "' . $role . '" role — check with whoever maintains schema.sql.';
                    }
                }
            }
        } elseif ($action === 'toggle_active') {
            $id = (int)($_POST['id'] ?? 0);

            if ($id === current_user_id()) {
                $error = 'You cannot deactivate your own account.';
            } else {
                $stmt = $pdo->prepare('UPDATE users SET is_active = NOT is_active WHERE id = :id');
                $stmt->execute([':id' => $id]);
                flash_set('Account status updated.', 'success');
                redirect('admin_users.php');
            }
        } elseif ($action === 'reset_password') {
            $id = (int)($_POST['id'] ?? 0);

            $newPassword = bin2hex(random_bytes(4)); // 8 hex chars, easy to read aloud/type
            $hash = password_hash($newPassword, PASSWORD_BCRYPT);

            $stmt = $pdo->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
            $stmt->execute([':hash' => $hash, ':id' => $id]);

            $nameStmt = $pdo->prepare('SELECT name FROM users WHERE id = :id');
            $nameStmt->execute([':id' => $id]);
            $resetName = $nameStmt->fetchColumn();

            $tempPassword = $newPassword;
            $notice = 'Password reset for ' . h($resetName) . '. Give them this temporary password — it is shown once and not saved anywhere: ';
        }
    }
}

$users = $pdo->query(
    'SELECT id, staff_no, name, department, email, role, approval_stage, category, is_active, created_at
     FROM users
     ORDER BY role ASC, name ASC'
)->fetchAll();

$page_title = 'Manage Users';
include __DIR__ . '/includes/header.php';
?>

<div class="dash-header">
  <div>
    <h1>Manage Users</h1>
    <p class="subtitle">Create and manage staff, approver, and admin accounts.</p>
  </div>
</div>

<?php if ($error): ?>
  <div class="form-error"><?= h($error) ?></div>
<?php endif; ?>

<?php if ($tempPassword): ?>
  <div class="flash flash-success">
    <?= $notice ?><strong style="font-family: monospace; font-size: 1rem;"><?= h($tempPassword) ?></strong>
  </div>
<?php elseif ($notice): ?>
  <div class="flash flash-success"><?= h($notice) ?></div>
<?php endif; ?>

<?php $__flash = flash_get(); if ($__flash): ?>
  <div class="flash flash-<?= h($__flash['type']) ?>"><?= h($__flash['msg']) ?></div>
<?php endif; ?>

<div class="form-panel" style="max-width: 720px; margin-bottom: 2rem;">
  <h2>Create new account</h2>

  <form method="post" novalidate id="create-user-form">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="action" value="create_user">

    <div class="form-row">
      <div>
        <label for="staff_no">Staff no.</label>
        <input type="text" id="staff_no" name="staff_no" required value="<?= h($_POST['staff_no'] ?? '') ?>">
      </div>
      <div>
        <label for="name">Full name</label>
        <input type="text" id="name" name="name" required value="<?= h($_POST['name'] ?? '') ?>">
      </div>
    </div>

    <div class="form-row">
      <div>
        <label for="department">Department</label>
        <input type="text" id="department" name="department" value="<?= h($_POST['department'] ?? '') ?>">
      </div>
      <div>
        <label for="email">Email</label>
        <input type="email" id="email" name="email" required value="<?= h($_POST['email'] ?? '') ?>">
      </div>
    </div>

    <div class="form-row">
      <div>
        <label for="password">Temporary password</label>
        <input type="text" id="password" name="password" required>
      </div>
      <div>
        <label for="role">Role</label>
        <select id="role" name="role" required onchange="document.getElementById('stage-field').style.display = this.value === 'approver' ? 'block' : 'none';">
          <option value="">Select a role</option>
          <option value="staff">Staff</option>
          <option value="approver">Approver</option>
          <option value="admin">Admin</option>
        </select>
      </div>
    </div>

    <div id="stage-field" style="display:none;">
      <label for="approval_stage">Approval stage</label>
      <select id="approval_stage" name="approval_stage">
        <option value="">Select a stage</option>
        <option value="1">Department Manager</option>
        <option value="2">General Manager</option>
      </select>
    </div>

    <button type="submit">Create account</button>
  </form>
</div>

<div class="request-panel">
  <div class="request-panel-head">
    <h2>All accounts</h2>
  </div>

  <div class="table-wrap">
  <table class="data-table">
    <thead>
      <tr>
        <th>Name</th>
        <th>Staff No</th>
        <th>Role</th>
        <th>Department</th>
        <th>Category</th>
        <th>Status</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($users as $u): ?>
        <tr>
          <td><?= h($u['name']) ?></td>
          <td><?= h($u['staff_no']) ?></td>
          <td>
            <?= h(ucfirst($u['role'])) ?>
            <?php if ($u['role'] === 'approver' && $u['approval_stage']): ?>
              (Stage <?= (int)$u['approval_stage'] ?>)
            <?php endif; ?>
          </td>
          <td><?= h($u['department'] ?: '—') ?></td>
          <td><?= $u['category'] ? h($u['category']) : '—' ?></td>
          <td>
            <span class="badge <?= $u['is_active'] ? 'badge-approved' : 'badge-rejected' ?>">
              <?= $u['is_active'] ? 'Active' : 'Inactive' ?>
            </span>
          </td>
          <td style="max-width:150px;">
            <form method="post" style="margin-bottom:0.35rem;" onsubmit="return confirm('Reset password for <?= h($u['name']) ?>?');">
              <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
              <input type="hidden" name="action" value="reset_password">
              <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
              <button type="submit" class="btn-secondary" style="width:100%; padding: 0.3rem 0.6rem; font-size: 0.78rem;">Reset password</button>
            </form>
            <?php if ((int)$u['id'] !== current_user_id()): ?>
              <form method="post" onsubmit="return confirm('<?= $u['is_active'] ? 'Deactivate' : 'Activate' ?> <?= h($u['name']) ?>?');">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="action" value="toggle_active">
                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                <button type="submit" class="btn-secondary" style="width:100%; padding: 0.3rem 0.6rem; font-size: 0.78rem;">
                  <?= $u['is_active'] ? 'Deactivate' : 'Activate' ?>
                </button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>