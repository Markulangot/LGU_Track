<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) && basename($_SERVER['PHP_SELF']) !== 'login.php') {
    header('Location: login.php');
    exit;
}
if (isset($_SESSION['role']) && $_SESSION['role'] === 'user' && basename($_SERVER['PHP_SELF']) !== 'login.php' && basename($_SERVER['PHP_SELF']) !== 'logout.php') {
    header('Location: user/index.php');
    exit;
}

require_once 'includes/db.php';
require_once 'includes/functions.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title><?php echo isset($page_title) ? $page_title . " - SB e-Legis" : "SB e-Legis"; ?></title>

    <script src="assets/js/tailwind.min.js?plugins=forms,container-queries"></script>
    <link href="assets/css/fonts.css" rel="stylesheet" />

    <style>
        .icon-sm {
            width: 16px;
            height: 16px;
            object-fit: contain;
        }

        .icon-md {
            width: 20px;
            height: 20px;
            object-fit: contain;
        }

        .icon-lg {
            width: 24px;
            height: 24px;
            object-fit: contain;
        }

        .icon-invert {
            filter: brightness(0) invert(1);
        }

        .icon-red {
            filter: invert(12%) sepia(98%) saturate(3000%) hue-rotate(349deg) brightness(85%) contrast(105%);
        }

        .icon-navy {
            filter: invert(12%) sepia(98%) saturate(3000%) hue-rotate(349deg) brightness(85%) contrast(105%);
        }

        /* crimson alias */

        /* Tag Color Themes */
        .tc-blue {
            background-color: #dbe1ff;
            color: #003ea8;
            border: 1px solid #b4c5ff;
        }

        .tc-green {
            background-color: #d3f2d0;
            color: #1a4a17;
            border: 1px solid #a0d9a0;
        }

        .tc-amber {
            background-color: #fef3c7;
            color: #78350f;
            border: 1px solid #fcd34d;
        }

        .tc-red {
            background-color: #ffdad6;
            color: #93000a;
            border: 1px solid #ffb4ab;
        }

        .tc-purple {
            background-color: #ede9fe;
            color: #4c1d95;
            border: 1px solid #c4b5fd;
        }

        .tc-teal {
            background-color: #ccfbf1;
            color: #065f46;
            border: 1px solid #6ee7b7;
        }

        .tc-orange {
            background-color: #ffedd5;
            color: #7c2d12;
            border: 1px solid #fed7aa;
        }

        .tc-slate {
            background-color: #eceef0;
            color: #45464d;
            border: 1px solid #c6c6cd;
        }

        /* Status Badge Styles */
        .status-active {
            background-color: #7f0000;
            color: #ffffff;
        }

        .status-under-review {
            background-color: #ffdad6;
            color: #93000a;
        }

        .status-review {
            background-color: #ffdad6;
            color: #93000a;
        }

        .status-draft {
            background-color: #e0e3e5;
            color: #45464d;
        }

        .status-amended {
            background-color: #fef3c7;
            color: #78350f;
        }

        .status-repealed {
            background-color: #eceef0;
            color: #45464d;
        }

        /* Keyword Highlight */
        .highlight {
            background-color: #fef3c7;
            color: #92400e;
            padding: 0 2px;
            border-radius: 0;
            font-weight: 600;
        }

        /* Sorting Icons */
        .sort-header {
            cursor: pointer;
            user-select: none;
            position: relative;
        }

        .sort-header:hover {
            background-color: #fdf2f2;
        }

        .sort-icon {
            display: inline-block;
            width: 12px;
            height: 12px;
            margin-left: 4px;
            opacity: 0.3;
            transition: all 0.2s;
        }

        .sort-header.active .sort-icon {
            opacity: 1;
            color: #8B0000;
        }

        .sort-asc .sort-icon {
            transform: rotate(180deg);
        }

        /* Success Modal Animation */
        @keyframes scaleIn {
            from {
                transform: scale(0.85);
                opacity: 0;
            }

            to {
                transform: scale(1);
                opacity: 1;
            }
        }

        @keyframes checkPop {
            from {
                transform: scale(0);
                opacity: 0;
            }

            to {
                transform: scale(1);
                opacity: 1;
            }
        }

        #successModal .modal-card {
            animation: scaleIn 0.3s cubic-bezier(0.34, 1.56, 0.64, 1) both;
        }

        #successModal .check-icon {
            animation: checkPop 0.4s 0.15s cubic-bezier(0.34, 1.56, 0.64, 1) both;
        }
    </style>
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    "colors": {
                        "surface": "#ffffff",
                        "background": "#fdf8f8",
                        "primary-container": "#8B0000",
                        "on-primary-container": "#e8a0a0",
                        "secondary-container": "#fddede",
                        "on-surface": "#191c1e",
                        "on-surface-variant": "#45464d",
                        "outline": "#76777d",
                        "outline-variant": "#e0c8c8",
                        "error": "#ba1a1a",
                        "error-container": "#ffdad6",
                        "primary": "#8B0000",
                        "navy": "#8B0000"
                    },
                    "borderRadius": {
                        "none": "0px",
                        "sm": "0px",
                        "DEFAULT": "0px",
                        "md": "0px",
                        "lg": "0px",
                        "xl": "0px",
                        "2xl": "0px",
                        "3xl": "0px",
                        "full": "0px"
                    },
                    "spacing": {
                        "xs": "4px",
                        "sm": "12px",
                        "md": "24px",
                        "lg": "40px",
                        "xl": "64px",
                        "gutter": "24px"
                    },
                    "fontFamily": {
                        "ui": ["Public Sans", "sans-serif"],
                        "doc": ["Newsreader", "serif"]
                    }
                }
            }
        }
    </script>
</head>

<body class="bg-background text-on-surface font-ui h-screen flex overflow-hidden">
    <?php include 'sidebar.php'; ?>

    <main class="flex-1 flex flex-col h-screen overflow-hidden">
        <!-- TopNavBar -->
        <header class="bg-white text-on-surface h-[60px] sticky top-0 z-50 border-b border-outline-variant w-full">
            <div class="flex items-center justify-between px-6 h-full max-w-[1440px] mx-auto">
                <div class="flex items-center gap-4">
                    <span class="text-lg font-bold text-navy tracking-tight">SB e-Legis</span>
                    <div class="h-4 w-[1px] bg-outline-variant mx-2"></div>
                    <span
                        class="text-sm font-semibold text-on-surface-variant"><?php echo isset($page_title) ? $page_title : "Overview"; ?></span>
                </div>

                <div class="flex items-center gap-6">
                    <!-- Global Search -->
                    <form action="database.php" method="GET" class="relative">
                        <img src="assets/icons/glass.png"
                            class="absolute left-3 top-1/2 -translate-y-1/2 icon-sm opacity-50" alt="Search">
                        <input name="search"
                            class="pl-10 pr-4 py-1.5 border border-outline-variant rounded-DEFAULT bg-background text-on-surface focus:outline-none focus:border-navy w-[220px] text-sm"
                            placeholder="Search..." type="text" />
                    </form>

                    <div class="flex items-center gap-2">
                        <button
                            class="p-2 text-on-surface-variant hover:bg-background rounded-full transition-colors relative">
                            <img src="assets/icons/notification.png" class="icon-md" alt="Notifications">
                            <span
                                class="absolute top-1 right-1 w-2 h-2 bg-error rounded-full border-2 border-white"></span>
                        </button>
                        <a href="settings.php"
                            class="p-2 text-on-surface-variant hover:bg-background rounded-full transition-colors">
                            <img src="assets/icons/settings.png" class="icon-md" alt="Settings">
                        </a>
                    </div>

                    <div class="flex items-center gap-3 pl-4 border-l border-outline-variant relative">
                        <div class="text-right hidden sm:block">
                            <div class="text-xs font-bold text-on-surface"><?php echo htmlspecialchars($_SESSION['full_name']); ?></div>
                            <div class="text-[10px] text-on-surface-variant uppercase tracking-wider"><?php echo htmlspecialchars($_SESSION['role']); ?></div>
                        </div>

                        <button onclick="toggleUserMenu(event)" class="w-8 h-8 rounded-full bg-navy text-white flex items-center justify-center text-xs font-bold hover:bg-opacity-90 transition-all">
                            <?php 
                            $initials = "";
                            $names = explode(" ", $_SESSION['full_name']);
                            foreach ($names as $n) $initials .= $n[0];
                            echo htmlspecialchars(substr($initials, 0, 2));
                            ?>
                        </button>
<!-- Dropdown Menu -->
<div id="userMenu" class="hidden absolute top-full right-0 mt-2 w-48 bg-white border border-outline-variant shadow-xl rounded-lg py-1 z-[100]">
    <a href="settings.php" class="block px-4 py-2 text-xs font-bold text-navy hover:bg-surface-container transition-all">Account Settings</a>
    <button onclick="openLogoutConfirm()" class="w-full text-left block px-4 py-2 text-xs font-bold text-red-600 hover:bg-red-50 transition-all">Logout</button>
</div>
</div>
</div>
</div>
</div>
</header>

<!-- Logout Confirm Modal -->
<div id="logoutConfirmModal" class="fixed inset-0 z-[600] hidden items-center justify-center px-4" style="background:rgba(0,0,0,0.45); backdrop-filter:blur(3px);">
<div class="bg-white shadow-2xl w-full max-w-[320px] p-6">
<h3 class="text-sm font-bold text-navy mb-2">Confirm Logout</h3>
<p class="text-[12px] text-on-surface-variant mb-6">Are you sure you want to end your session?</p>
<div class="flex items-center gap-3 justify-end">
<button onclick="closeLogoutConfirm()" class="px-3 py-1.5 text-xs font-bold text-on-surface-variant hover:text-navy transition-all">No</button>
<a href="logout.php" class="px-4 py-1.5 text-xs font-bold text-white bg-navy transition-all shadow-md hover:opacity-90">Yes, Logout</a>
</div>
</div>
</div>

<script>
function toggleUserMenu(e) {
e.stopPropagation();
const menu = document.getElementById('userMenu');
menu.classList.toggle('hidden');
}
function openLogoutConfirm() {
document.getElementById('userMenu').classList.add('hidden');
document.getElementById('logoutConfirmModal').classList.remove('hidden');
document.getElementById('logoutConfirmModal').classList.add('flex');
}
function closeLogoutConfirm() {
document.getElementById('logoutConfirmModal').classList.add('hidden');
document.getElementById('logoutConfirmModal').classList.remove('flex');
}
window.addEventListener('click', (e) => {
const menu = document.getElementById('userMenu');
if (!menu.classList.contains('hidden')) menu.classList.add('hidden');
});
</script>


        <!-- Scrollable Content -->
        <div class="flex-1 overflow-y-auto bg-background">
            <div class="max-w-[1440px] mx-auto p-6 lg:p-10 space-y-6">