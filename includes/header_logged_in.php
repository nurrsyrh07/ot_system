<?php

$__current_page = basename($_SERVER['PHP_SELF']);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        <?= isset($page_title)
            ? h($page_title) . ' — '
            : ''
        ?>
        JCY Overtime Management System
    </title>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        <?= isset($page_title)
            ? h($page_title) . ' — '
            : ''
        ?>
        JCY Overtime Management System
    </title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700&family=Inter:wght@400;500;600&display=swap"
        rel="stylesheet"
    >

    <link rel="stylesheet" href="css/style.css">
</head>

<body>

<div class="app-shell">

    <!-- =========================================================
         SIDEBAR
         ========================================================= -->

    <aside class="sidebar">

        <?php include __DIR__ . '/logo.php'; ?>

        <nav class="sidebar-nav">

            <!-- Dashboard -->
            <a
                href="dashboard.php"
                class="<?= $__current_page === 'dashboard.php' ? 'active' : '' ?>"
            >
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <rect
                        x="3"
                        y="3"
                        width="7"
                        height="7"
                        rx="1.5"
                        stroke="currentColor"
                        stroke-width="1.8"
                    />

                    <rect
                        x="14"
                        y="3"
                        width="7"
                        height="7"
                        rx="1.5"
                        stroke="currentColor"
                        stroke-width="1.8"
                    />

                    <rect
                        x="3"
                        y="14"
                        width="7"
                        height="7"
                        rx="1.5"
                        stroke="currentColor"
                        stroke-width="1.8"
                    />

                    <rect
                        x="14"
                        y="14"
                        width="7"
                        height="7"
                        rx="1.5"
                        stroke="currentColor"
                        stroke-width="1.8"
                    />
                </svg>

                <span>Dashboard</span>
            </a>


            <!-- =================================================
                 STAFF ONLY: SUBMIT OT REQUEST
                 ================================================= -->

            <?php if (current_role() === 'staff'): ?>

                <a
                    href="submit_ot.php"
                    class="<?= $__current_page === 'submit_ot.php' ? 'active' : '' ?>"
                >
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">

                        <path
                            d="M7 3h7l4 4v14H7z"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linejoin="round"
                        />

                        <path
                            d="M14 3v4h4"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linejoin="round"
                        />

                        <path
                            d="M9.5 13h5M9.5 16h5"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                        />

                    </svg>

                    <span>Request</span>
                </a>

            <?php endif; ?>


            <!-- =================================================
                 APPROVER: DASHBOARD ONLY
                 ================================================= -->

            <?php if (current_role() === 'approver'): ?>

                <a
                    href="dashboard.php"
                    class="<?= $__current_page === 'dashboard.php' ? 'active' : '' ?>"
                >
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">

                        <path
                            d="M9 11l3 3 6-7"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        />

                        <path
                            d="M5 4h14a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1z"
                            stroke="currentColor"
                            stroke-width="1.8"
                        />

                    </svg>

                    <span>Approvals</span>
                </a>

            <?php endif; ?>

        </nav>


        <!-- =====================================================
             LOGOUT
             ===================================================== -->

        <a
            href="logout.php"
            class="sidebar-logout"
        >

            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">

                <path
                    d="M9 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h3"
                    stroke="currentColor"
                    stroke-width="1.8"
                    stroke-linecap="round"
                />

                <path
                    d="M13 16l4-4-4-4M17 12H9"
                    stroke="currentColor"
                    stroke-width="1.8"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                />

            </svg>

            <span>Log Out</span>

        </a>

    </aside>


    <!-- =========================================================
         MAIN CONTENT
         ========================================================= -->

    <div class="main">

        <!-- =====================================================
             TOP BAR
             ===================================================== -->

        <header class="topbar">

            <!-- Search -->
            <div class="search-box">

                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    aria-hidden="true"
                >

                    <circle
                        cx="11"
                        cy="11"
                        r="7"
                        stroke="currentColor"
                        stroke-width="1.8"
                    />

                    <path
                        d="m20 20-3.5-3.5"
                        stroke="currentColor"
                        stroke-width="1.8"
                        stroke-linecap="round"
                    />

                </svg>

                <input
                    type="text"
                    id="dash-search"
                    placeholder="Search requests"
                    autocomplete="off"
                >

            </div>


            <!-- User -->
            <div class="topbar-right">

                <div class="user-chip">

                    <!-- Avatar -->
                    <div class="avatar">

                        <?= h(
                            strtoupper(
                                substr(
                                    $_SESSION['name'] ?? '?',
                                    0,
                                    1
                                )
                            )
                        ) ?>

                    </div>


                    <!-- User information -->
                    <div class="user-info">

                        <span class="user-name">
                            <?= h($_SESSION['name'] ?? '') ?>
                        </span>

                        <span class="user-sub">

                            <?php if (!empty($_SESSION['staff_no'])): ?>

                                <?= h($_SESSION['staff_no']) ?>

                            <?php endif; ?>


                            <?php if (current_role() === 'approver'): ?>

                                <?php if (!empty($_SESSION['staff_no'])): ?>
                                    ·
                                <?php endif; ?>

                                <?php
                                $stage = current_approval_stage();

                                if ($stage === 1) {
                                    echo 'Stage 1 Approver';
                                } elseif ($stage === 2) {
                                    echo 'Stage 2 Approver';
                                } else {
                                    echo 'Approver';
                                }
                                ?>

                            <?php else: ?>

                                <?php if (!empty($_SESSION['staff_no'])): ?>
                                    ·
                                <?php endif; ?>

                                Staff

                            <?php endif; ?>

                        </span>

                    </div>

                </div>

            </div>

        </header>


        <!-- =====================================================
             PAGE CONTENT
             ===================================================== -->

        <main class="content">

            <?php
            $__flash = flash_get();
            ?>

            <?php if ($__flash): ?>

                <div
                    class="flash flash-<?= h($__flash['type']) ?>"
                    role="alert"
                >
                    <?= h($__flash['msg']) ?>
                </div>

            <?php endif; ?>