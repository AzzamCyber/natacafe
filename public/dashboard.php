<?php
// =========================================================
// PUBLIC/DASHBOARD.PHP
// Variabel global seperti $pdo, $csrf_token, $user_role tersedia.
// =========================================================

if ($page !== 'dashboard' && $page !== 'admin_dashboard') exit; // Safety check
require_role('admin');

$today = date('Y-m-d');
$stmt_sales = $pdo->prepare("SELECT SUM(total_amount) as total FROM orders WHERE DATE(created_at) = ? AND order_status = 'Completed'");
$stmt_sales->execute([$today]);
$sales_today = $stmt_sales->fetchColumn() ?? 0;

$stmt_orders = $pdo->prepare("SELECT COUNT(*) as count FROM orders WHERE DATE(created_at) = ? AND order_status = 'Completed'");
$stmt_orders->execute([$today]);
$orders_today = $stmt_orders->fetchColumn() ?? 0;

$stmt_pending = $pdo->query("SELECT COUNT(*) as count FROM orders WHERE order_status = 'Pending'");
$orders_pending = $stmt_pending->fetchColumn() ?? 0;

?>
<h1 class="text-3xl font-bold mb-6 text-gray-700">Dashboard Admin Ringkas</h1>

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="bg-white p-6 rounded-xl shadow-lg border-l-4 border-sky-500" data-aos="fade-up" data-aos-delay="100">
        <p class="text-sm font-medium text-gray-500">Penjualan Hari Ini</p>
        <p class="text-3xl font-extrabold text-sky-700 mt-1">Rp <?php echo number_format($sales_today, 0, ',', '.'); ?></p>
    </div>
    <div class="bg-white p-6 rounded-xl shadow-lg border-l-4 border-emerald-500" data-aos="fade-up" data-aos-delay="200">
        <p class="text-sm font-medium text-gray-500">Jumlah Order Selesai</p>
        <p class="text-3xl font-extrabold text-emerald-700 mt-1"><?php echo $orders_today; ?></p>
    </div>
    <div class="bg-white p-6 rounded-xl shadow-lg border-l-4 border-amber-500" data-aos="fade-up" data-aos-delay="300">
        <p class="text-sm font-medium text-gray-500">Order Pending (Bayar Nanti)</p>
        <p class="text-3xl font-extrabold text-amber-700 mt-1"><?php echo $orders_pending; ?></p>
    </div>
    <a href="index.php?page=export_data" class="bg-white p-6 rounded-xl shadow-lg border-l-4 border-indigo-500 hover:bg-indigo-50 transition duration-150 flex flex-col justify-center" data-aos="fade-up" data-aos-delay="400">
        <p class="text-sm font-medium text-gray-500">Laporan Penjualan</p>
        <button class="mt-1 text-lg font-bold text-indigo-700 flex items-center">
            <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
            Export Data (CSV)
        </button>
    </a>
</div>

<div class="bg-white p-6 rounded-xl shadow-lg" data-aos="fade-up">
    <h2 class="text-xl font-semibold mb-4 text-gray-700">Audit Log Terbaru</h2>
    <?php 
    $stmt_logs = $pdo->query("SELECT l.*, u.username FROM audit_logs l LEFT JOIN users u ON l.user_id = u.id ORDER BY l.timestamp DESC LIMIT 5");
    $logs = $stmt_logs->fetchAll();
    ?>
    <ul class="divide-y divide-gray-100">
        <?php foreach ($logs as $log): ?>
            <li class="py-2 text-sm">
                <span class="font-mono text-xs text-gray-400 mr-3"><?php echo date('H:i:s', strtotime($log['timestamp'])); ?></span>
                <span class="font-semibold text-sky-700"><?php echo $log['username'] ?? 'System'; ?>:</span>
                <?php echo sanitize_input($log['action']); ?>
                <span class="text-xs text-gray-500 ml-2">(<?php echo $log['target_table']; ?>)</span>
            </li>
        <?php endforeach; ?>
    </ul>
</div>