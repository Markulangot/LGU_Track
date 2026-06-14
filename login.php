<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        check_rate_limit($pdo, 'login', 100, 15);
    } catch (Exception $e) {
        $error = $e->getMessage();
    }

    if (!$error) {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username && $password) {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];

                // Log activity
                $log_stmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, description) VALUES (?, 'login', ?)");
                $log_stmt->execute([$user['id'], "User {$user['username']} logged in"]);

                if ($user['role'] === 'user') {
                    header('Location: user/index.php');
                } else {
                    header('Location: index.php');
                }
                exit;
            } else {
                $error = 'Invalid username or password';
            }
        } else {
            $error = 'Please enter both username and password';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>SB e-Legis</title>
    <script src="assets/js/tailwind.min.js"></script>
    <link href="assets/css/fonts.css" rel="stylesheet" />
    <style>
        .login-card {
            background: white;
            box-shadow: 0 20px 60px rgba(15, 23, 42, 0.1);
            border: 1px solid #e0e3e5;
        }

        .icon-invert {
            filter: brightness(0) invert(1);
        }
    </style>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        navy: '#131b2e',
                        red_primary: '#B91C1C',
                        background: '#f7f9fb',
                        outline: '#e0e3e5',
                        muted: '#76777d'
                    }
                }
            }
        }
    </script>
</head>

<body class="bg-background font-sans min-h-screen flex items-center justify-center p-6">

    <div class="w-full max-w-[900px] flex login-card rounded-lg overflow-hidden min-h-[500px]">
        <!-- Left Column: Branding -->
        <div class="flex-1 bg-white p-12 flex flex-col items-center justify-center text-center border-r border-outline">
            <div class="mb-10">
                <img src="assets/icons/UpdateLogo-Clean.png" class="w-48 h-48 object-contain" alt="LGU Mambajao Seal">
            </div>
            <h5 class="text-[32px] font-bold text-red_primary tracking-tight mb-2">SB Mambajao Legislative</h5>
            <p class="text-muted text-sm max-w-[280px] leading-relaxed mb-12">
                The unified municipal legislative document tracking & archiving system.
            </p>
            <div class="mt-auto">
                <p class="text-[10px] font-bold text-muted uppercase tracking-widest">
                    LGU MAMBAJAO - SANGGUNIANG BAYAN
                </p>
            </div>
        </div>

        <!-- Right Column: Login Form -->
        <div class="flex-1 bg-white p-16 flex flex-col justify-center">
            <div class="mb-10">
                <h2 class="text-2xl font-bold text-red_primary mb-2">Welcome back</h2>
                <p class="text-muted text-sm">Please sign in to your staff account.</p>
            </div>

            <?php if ($error): ?>
                <div class="bg-red-50 text-red-600 text-xs p-3 rounded mb-6 border border-red-100 font-medium">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST" class="space-y-6">
                <div>
                    <label
                        class="block text-[10px] font-bold uppercase tracking-widest text-muted mb-2">Username</label>
                    <input type="text" name="username" required
                        class="w-full px-4 py-3 bg-[#f8f9fa] border border-transparent rounded focus:outline-none focus:bg-white focus:border-red_primary transition-all text-sm"
                        placeholder="Enter your username">
                </div>

                <div>
                    <label
                        class="block text-[10px] font-bold uppercase tracking-widest text-muted mb-2">Password</label>
                    <input type="password" name="password" required
                        class="w-full px-4 py-3 bg-[#f8f9fa] border border-transparent rounded focus:outline-none focus:bg-white focus:border-red_primary transition-all text-sm"
                        placeholder="Enter your password">
                </div>

                <div class="pt-2">
                    <button type="submit"
                        class="w-full bg-red_primary text-white font-bold py-3.5 rounded hover:bg-opacity-95 transition-all shadow-lg flex items-center justify-center gap-2 text-sm">
                        Sign In
                        <img src="assets/icons/checked.png" class="w-4 h-4 icon-invert" alt="Check">
                    </button>
                </div>
            </form>
        </div>
    </div>

</body>

</html>