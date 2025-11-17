<?php
// =========================================================
// KONFIGURASI GLOBAL APLIKASI KASIR CAFE/RESTO
// =========================================================

// Pengaturan Database
define('DB_HOST', 'localhost');
define('DB_NAME', 'natacafev2');
define('DB_USER', 'root');
define('DB_PASS', '');

// Pengaturan Path
define('ROOT_PATH', dirname(__DIR__));
define('ASSETS_PATH', ROOT_PATH . '/public');
define('PRODUCT_IMAGE_DIR', ASSETS_PATH . '/assets/product/');
define('IMAGE_UPLOAD_MAX_SIZE', 5 * 1024 * 1024); // 5 MB

// Pengaturan Aplikasi
define('APP_NAME', 'Nata Cafe');
define('TAILWIND_CDN', 'https://cdn.tailwindcss.com');

// Start PHP Session
session_start();

// Buat folder asset jika belum ada
if (!is_dir(PRODUCT_IMAGE_DIR)) {
    mkdir(PRODUCT_IMAGE_DIR, 0777, true); 
}

// Fungsi Koneksi DB Global (PDO)
function get_pdo_connection() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $e) {
            die("Koneksi database gagal: " . $e->getMessage());
        }
    }
    return $pdo;
}

// Global PDO Connection: Panggil fungsi di sini sehingga $pdo terdefinisi
$pdo = get_pdo_connection();

// CSRF Token Generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
?>