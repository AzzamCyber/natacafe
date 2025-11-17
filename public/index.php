<?php
// =========================================================
// PUBLIC/INDEX.PHP (APPLICATION CORE / POS / ADMIN) - FINAL VERSION
// =========================================================

// Include Konfigurasi dan Kelas yang Dibutuhkan
require_once __DIR__ . '/../config/config.php';
require_once dirname(__DIR__) . '/config/struck.php'; 
require_once dirname(__DIR__) . '/src/Services/UploadService.php';
require_once dirname(__DIR__) . '/src/Services/PrintService.php';
require_once dirname(__DIR__) . '/src/Controllers/ProductController.php';

// Global Vars
$pdo = get_pdo_connection();
$productController = new ProductController($pdo); 
$page = $_GET['page'] ?? 'pos';
$is_logged_in = isset($_SESSION['user_id']);
$user_role = $_SESSION['role'] ?? null;
$error = '';
$success_message = $_GET['success'] ?? '';
$error_message = $_GET['error'] ?? '';

// =========================================================
// A. CORE UTILITY & SECURITY
// =========================================================

function sanitize_input($data) {
    return htmlspecialchars(stripslashes(trim($data)));
}

function audit_log($pdo, $action, $target_table, $target_id = null) {
    $userId = $_SESSION['user_id'] ?? null;
    $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, target_table, target_id) VALUES (?, ?, ?, ?)");
    $stmt->execute([$userId, $action, $target_table, $target_id]);
}

function require_role($required_role) {
    global $user_role;
    if ($user_role !== $required_role && $user_role !== 'admin') { 
        header('Location: index.php?page=pos&error=Unauthorized Access');
        exit;
    }
}

// =========================================================
// B. AUTHENTICATION & GLOBAL ACTION
// =========================================================

if (isset($_GET['action']) && $_GET['action'] == 'logout') {
    audit_log($pdo, "User logged out", 'users');
    session_destroy();
    header('Location: login.php');
    exit;
}

// Jika BELUM login, paksa redirect ke login.php
if (!$is_logged_in) {
    header('Location: login.php');
    exit;
}

// =========================================================
// C. CONTROLLER PROCESSING (POST Requests)
// =========================================================

if ($is_logged_in) {
    // --- Product & Category Actions ---
    if (isset($_POST['product_action']) || isset($_POST['add_category'])) {
        require_role('admin');
        
        $result = ['success' => false, 'message' => ''];
        
        if (isset($_POST['product_action'])) {
            $result = $productController->handleProductAction($_POST['product_action'], $_POST, $_FILES);
        } elseif (isset($_POST['add_category'])) {
            $result = $productController->handleCategoryAction($_POST);
        }
        
        if ($result['success']) {
            header('Location: index.php?page=admin_products&success=' . urlencode($result['message']));
        } else {
            $redirect_page = (isset($_POST['product_action']) && $_POST['product_action'] === 'edit' && isset($_POST['product_id'])) 
                             ? 'admin_products&id=' . $_POST['product_id'] 
                             : 'admin_products';
            header('Location: index.php?page=' . $redirect_page . '&error=' . urlencode($result['message']));
        }
        exit;
    }

    // --- Order Checkout Action ---
    if ($page === 'checkout_process' && isset($_POST['cart_data_json'])) {
        $cart = json_decode($_POST['cart_data_json'] ?? '[]', true);
        $total_amount = floatval($_POST['total_amount']);
        $customer_name = sanitize_input($_POST['customer_name'] ?? 'Anonim');
        $payment_method = sanitize_input($_POST['payment_method'] ?? 'Tunai');
        $order_notes = sanitize_input($_POST['order_notes'] ?? '');

        if (empty($cart) || $total_amount <= 0) {
            header('Location: index.php?page=pos&error=Keranjang kosong.');
            exit;
        }

        try {
            $pdo->beginTransaction();
            $order_number = 'ORD-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            $sql_order = "INSERT INTO orders (order_number, cashier_id, customer_name, total_amount, payment_method, order_status, order_notes) VALUES (?, ?, ?, ?, ?, 'Completed', ?)";
            $stmt_order = $pdo->prepare($sql_order);
            $stmt_order->execute([$order_number, $_SESSION['user_id'] ?? null, $customer_name, $total_amount, $payment_method, $order_notes]);
            $order_id = $pdo->lastInsertId();

            $sql_item = "INSERT INTO order_items (order_id, product_id, product_name, price_per_item, quantity, item_notes) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt_item = $pdo->prepare($sql_item);

            foreach ($cart as $item) {
                $stmt_item->execute([$order_id, $item['id'], $item['name'], $item['price'], $item['qty'], $item['notes'] ?? null]);
            }

            $pdo->commit();
            audit_log($pdo, "New order placed: " . $order_number, 'orders', $order_id);

            header("Location: index.php?page=checkout_success&order_id=" . $order_id);
            exit;

        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Checkout error: " . $e->getMessage());
            header('Location: index.php?page=pos&error=' . urlencode('Gagal memproses pesanan. Error: ' . $e->getMessage()));
            exit;
        }
    }
    
    // --- Order Status Update Action (Admin) ---
    if (isset($_POST['update_status'])) {
        require_role('admin');
        $order_id = intval($_POST['order_id']);
        $action = sanitize_input($_POST['action']);
        
        $status_map = [
            'mark_paid' => 'Completed',
            'cancel_order' => 'Canceled',
            'refund_order' => 'Refunded'
        ];
        
        $new_status = $status_map[$action] ?? null;

        if ($order_id > 0 && $new_status) {
            try {
                $stmt = $pdo->prepare("UPDATE orders SET order_status = ? WHERE id = ?");
                $stmt->execute([$new_status, $order_id]);
                audit_log($pdo, "Order status updated to " . $new_status, 'orders', $order_id);
                $success_message = "Status Pesanan #{$order_id} berhasil diperbarui menjadi {$new_status}.";
            } catch (PDOException $e) {
                $error_message = "Gagal memperbarui status: " . $e->getMessage();
            }
        }
        $page = 'admin_orders'; 
    }
}

// =========================================================
// D. EXPORT DATA
// =========================================================

if ($page === 'export_data') {
    require_role('admin');
    header('Location: export_sales.php'); // Redirect ke script export baru
    exit;
}

// =========================================================
// E. TEMPLATE VISUAL (HTML & Tailwind CSS)
// =========================================================
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME . ' - ' . ucfirst(str_replace('_', ' ', $page)); ?></title>
    <script src="<?php echo TAILWIND_CDN; ?>"></script>
    
    <link rel="icon" href="assets/logo.png" type="image/png"> 
    
    <script src="https://cdn.jsdelivr.net/gh/alpinejs/alpine@v3.13.5/dist/cdn.min.js" defer></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap');
        :root { --primary: #0288D1; --light-primary: #4FC3F7; --accent: #E3F2FD; }
        body { font-family: 'Poppins', sans-serif; background-color: #f7f9fb; }
        .menu-link.active { background-color: var(--accent); color: var(--primary); font-weight: 600; }
        [x-cloak] { display: none !important; }

        /* Tablet/POS Specific Styling */
        .product-card { height: 180px; }
        .btn-pos { padding: 1rem 0.5rem; font-size: 1.1rem; }
        .qty-btn { width: 40px; height: 40px; font-size: 1.2rem; }
        
        @media print {
            body {
                visibility: hidden; 
                margin: 0;
                padding: 0;
            }
            #receipt-print { 
                position: absolute; 
                left: 0; 
                top: 0; 
                width: 300px; 
                padding: 10px; 
                font-size: 11px; 
                font-family: monospace; 
                visibility: visible; 
            }
            #receipt-print * {
                visibility: visible;
            }
            .no-print { display: none !important; }
        }
    </style>
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
</head>
<body class="antialiased text-gray-800">

<?php
echo '<script>window.onload = function() { AOS.init(); };</script>';
?>

    <div class="flex h-screen bg-gray-50">
        <div class="w-64 bg-white shadow-xl p-4 hidden lg:block flex-shrink-0">
            <h2 class="text-xl font-bold text-sky-700 mb-6"><?php echo APP_NAME; ?></h2>
            <p class="text-sm text-gray-500 mb-4">Hello, <?php echo $_SESSION['name']; ?></p>
            <nav class="space-y-2">
                <a href="index.php?page=pemesanan" class="menu-link block py-2 px-3 rounded-lg transition duration-200 hover:bg-sky-100/50 hover:text-sky-700 <?php echo $page === 'pos' || $page === 'pemesanan' ? 'active' : 'text-gray-600 hover:bg-gray-100'; ?>">Pemesanan (POS)</a>
                <?php if ($user_role === 'admin'): ?>
                    <a href="index.php?page=dashboard" class="menu-link block py-2 px-3 rounded-lg transition duration-200 hover:bg-sky-100/50 hover:text-sky-700 <?php echo $page === 'dashboard' ? 'active' : 'text-gray-600 hover:bg-gray-100'; ?>">Dashboard</a>
                    <a href="index.php?page=manageproduct" class="menu-link block py-2 px-3 rounded-lg transition duration-200 hover:bg-sky-100/50 hover:text-sky-700 <?php echo $page === 'manageproduct' || str_starts_with($page, 'admin_products') ? 'active' : 'text-gray-600 hover:bg-gray-100'; ?>">Manajemen Produk</a>
                    <a href="index.php?page=daftarpesanan" class="menu-link block py-2 px-3 rounded-lg transition duration-200 hover:bg-sky-100/50 hover:text-sky-700 <?php echo $page === 'daftarpesanan' || $page === 'admin_orders' ? 'active' : 'text-gray-600 hover:bg-gray-100'; ?>">Daftar Pesanan</a>
                <?php endif; ?>
                <a href="index.php?action=logout" class="block py-2 px-3 rounded-lg text-red-500 hover:bg-red-50 mt-4">Logout</a>
            </nav>
        </div>

        <main class="flex-1 overflow-x-hidden overflow-y-auto p-4 sm:p-8 flex flex-col"> 
            
            <?php if ($error_message): ?><div class="bg-red-100 border border-red-400 text-red-700 p-3 rounded mb-4" data-aos="fade-down"><?php echo sanitize_input($error_message); ?></div><?php endif; ?>
            <?php if ($success_message): ?><div class="bg-sky-100 border border-sky-400 text-sky-700 p-3 rounded mb-4" data-aos="fade-down"><?php echo sanitize_input($success_message); ?></div><?php endif; ?>

            <div class="flex-grow">
            <?php 
            // =========================================================
            // E. ROUTING KE FILE TERPISAH
            // =========================================================
            $target_file = '';
            
            switch ($page) {
                case 'pos':
                case 'pemesanan':
                    $target_file = 'pemesanan.php';
                    break;
                case 'dashboard':
                case 'admin_dashboard':
                    $target_file = 'dashboard.php';
                    break;
                case 'manageproduct':
                case 'admin_products':
                case 'admin_products_edit':
                case 'admin_categories':
                    $target_file = 'manageproduct.php';
                    break;
                case 'daftarpesanan':
                case 'admin_orders':
                    $target_file = 'daftarpesanan.php';
                    break;
                case 'checkout_success':
                    $target_file = 'checkout_success.php';
                    break;
                default:
                    // Fallback jika tidak ada page yang cocok
                    echo "<h1 class='text-3xl font-bold'>Halaman Tidak Ditemukan</h1><p class='mt-4'>Silakan navigasi menggunakan menu samping.</p>";
                    exit; // Hentikan eksekusi setelah menampilkan error
            }
            
            // Sertakan file yang sesuai.
            if (!empty($target_file) && file_exists(__DIR__ . '/' . $target_file)) {
                include __DIR__ . '/' . $target_file;
            } else {
                echo "<h1 class='text-3xl font-bold'>Error</h1><p class='mt-4'>File template {$target_file} tidak ditemukan.</p>";
            }
            ?>
            </div>
        </main>
        <footer class="p-3 text-center text-xs text-gray-500 border-t bg-white absolute bottom-0 w-full lg:pl-64 no-print">
            &copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?> | Dibuat oleh Natakenshi Developer
        </footer>
    </div>

<script>
    // ====================================================
    // L. LOCK SCREEN OTOMATIS (60 DETIK IDLE)
    // ====================================================
    let idleTime = 0;
    const maxIdleTime = 300000; // 60 detik dalam milidetik

    // Fungsi untuk memicu logout
    function triggerLogout() {
        // Hanya logout jika halaman bukan checkout_success (karena checkout punya cooldown sendiri)
        const currentPage = new URLSearchParams(window.location.search).get('page');
        if (currentPage !== 'checkout_success') {
            console.log("Auto-locking POS due to inactivity.");
            window.location.href = 'index.php?action=logout';
        }
    }

    // Reset waktu idle saat ada aktivitas user
    function resetIdleTimer() {
        idleTime = 0;
    }

    // Cek waktu idle setiap detik
    setInterval(() => {
        idleTime += 1000;
        if (idleTime >= maxIdleTime) {
            triggerLogout();
        }
    }, 1000); // Cek setiap 1 detik

    // Event listeners untuk mereset timer
    document.addEventListener('mousemove', resetIdleTimer, false);
    document.addEventListener('keypress', resetIdleTimer, false);
    document.addEventListener('click', resetIdleTimer, false);
    document.addEventListener('scroll', resetIdleTimer, false);
</script>
</body>
</html>