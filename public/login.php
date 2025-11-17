<?php
// =========================================================
// PUBLIC/LOGIN.PHP (AUTHENTICATION ENTRY POINT) - FINAL VERSION
// =========================================================

// Include Konfigurasi dan Kelas yang Dibutuhkan
require_once __DIR__ . '/../config/config.php';
// Tidak perlu Controller atau Service di sini, hanya Auth dan Utility
// Asumsi Audit Log dan Sanitize sudah didefinisikan di config.php (untuk proyek ini, kita definisikan di sini juga)

function sanitize_input($data) {
    return htmlspecialchars(stripslashes(trim($data)));
}

function audit_log($pdo, $action, $target_table, $target_id = null) {
    $userId = $_SESSION['user_id'] ?? null;
    $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, target_table, target_id) VALUES (?, ?, ?, ?)");
    $stmt->execute([$userId, $action, $target_table, $target_id]);
}

// Cek apakah sudah login, jika ya, redirect ke index.php
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if (isset($_POST['login'])) {
    $username = sanitize_input($_POST['username']);
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['name'] = $user['name'];
        audit_log($pdo, "User logged in", 'users', $user['id']);
        
        // Redirect ke index.php setelah login sukses
        header('Location: index.php?page=' . ($user['role'] === 'admin' ? 'admin_dashboard' : 'pos'));
        exit;
    } else {
        $error = "Username atau password salah.";
        audit_log($pdo, "Failed login attempt for: " . $username, 'users');
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Login</title>
    <script src="<?php echo TAILWIND_CDN; ?>"></script>
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <script>
        window.onload = function() { 
            AOS.init(); 
            // Focus on username field on load
            const usernameField = document.getElementById('username');
            if (usernameField) usernameField.focus();
        };
    </script>
</head>
<body class="antialiased text-gray-800">
    <div class="min-h-screen flex items-center justify-center bg-sky-50 py-12 px-4">
        <div class="max-w-md w-full space-y-8 bg-white p-10 rounded-xl shadow-2xl" data-aos="fade-up" data-aos-duration="1000">
            <div class="text-center">
                <img src="assets/img/logo.png" onerror="this.onerror=null;this.src='https://placehold.co/80x80/0288D1/FFFFFF?text=L';" alt="Logo" class="mx-auto h-20 w-20 rounded-full mb-4">
                <h2 class="mt-2 text-3xl font-extrabold text-gray-900">
                    Selamat Datang di <?php echo APP_NAME; ?>
                </h2>
                <p class="mt-2 text-sm text-gray-600">
                    Silakan login untuk melanjutkan.
                </p>
            </div>
            <?php if ($error): ?><div class="bg-red-100 border border-red-400 text-red-700 p-3 rounded"><?php echo $error; ?></div><?php endif; ?>
            <form class="mt-8 space-y-6" method="POST" action="login.php">
                <input type="hidden" name="login" value="1">
                <div class="rounded-md shadow-sm -space-y-px">
                    <div>
                        <label for="username" class="sr-only">Username</label>
                        <input id="username" name="username" type="text" autocomplete="username" required class="appearance-none rounded-none relative block w-full px-3 py-3 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-t-lg focus:outline-none focus:ring-sky-500 focus:border-sky-500 sm:text-sm" placeholder="Username Kasir">
                    </div>
                    <div>
                        <label for="password" class="sr-only">Password</label>
                        <input id="password" name="password" type="password" autocomplete="current-password" required class="appearance-none rounded-none relative block w-full px-3 py-3 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-b-lg focus:outline-none focus:ring-sky-500 focus:border-sky-500 sm:text-sm" placeholder="Password">
                    </div>
                </div>

                <div>
                    <button type="submit" class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-lg font-medium rounded-lg text-white bg-sky-700 hover:bg-sky-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-500 transition duration-150">
                        <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                            <svg class="h-5 w-5 text-sky-300 group-hover:text-sky-100" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2V7a3 3 0 00-6 0v2h6z" clip-rule="evenodd" />
                            </svg>
                        </span>
                        Masuk
                    </button>
                </div>
                <p class="mt-2 text-center text-xs text-gray-500">
                    Default Admin: <code class="font-mono">admin</code> / <code class="font-mono">admin123</code> | Default Kasir: <code class="font-mono">kasir</code> / <code class="font-mono">kasir123</code>
                </p>
            </form>
        </div>
    </div>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
</body>
</html>