<?php
// =========================================================
// PUBLIC/DAFTARPESANAN.PHP - FINAL VERSION (FIXED PRINT & COLUMNS)
// Variabel global seperti $pdo, $csrf_token, $user_role tersedia.
// =========================================================

if ($page !== 'daftarpesanan' && $page !== 'admin_orders') exit; // Safety check
require_role('admin');

$stmt_orders = $pdo->query("SELECT o.*, u.name as cashier_name FROM orders o LEFT JOIN users u ON o.cashier_id = u.id ORDER BY o.created_at DESC");
$orders = $stmt_orders->fetchAll();
?>
<h1 class="text-3xl font-bold mb-6 text-gray-700">Daftar Pesanan & Status</h1>

<div class="bg-white p-6 rounded-xl shadow-lg" data-aos="fade-up">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order No</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Waktu</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Pemesan</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bayar</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($orders as $order): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-base font-medium text-gray-900"><?php echo $order['order_number']; ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-base text-gray-500"><?php echo date('d/M H:i', strtotime($order['created_at'])); ?></td>
                        
                        <td class="px-6 py-4 whitespace-nowrap text-base text-gray-700 font-semibold"><?php echo sanitize_input($order['customer_name']); ?></td>
                        
                        <td class="px-6 py-4 whitespace-nowrap text-base font-bold text-sky-700">Rp <?php echo number_format($order['total_amount'], 0, ',', '.'); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-base text-gray-500"><?php echo $order['payment_method']; ?></td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-3 py-1 inline-flex text-base leading-5 font-semibold rounded-full <?php
                                echo $order['order_status'] === 'Completed' ? 'bg-green-100 text-green-800' : 
                                     ($order['order_status'] === 'Pending' ? 'bg-yellow-100 text-yellow-800' : 
                                     ($order['order_status'] === 'Canceled' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800'));
                            ?>">
                                <?php echo $order['order_status']; ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                            
                            <button onclick="window.location.href='index.php?page=checkout_success&order_id=<?php echo $order['id']; ?>'" class="text-indigo-600 hover:text-indigo-900 transition duration-150 btn-pos bg-indigo-50 px-3 py-1 rounded-lg text-sm">Cetak</button>
                            
                            <form method="POST" action="index.php?page=admin_orders" class="inline">
                                <input type="hidden" name="update_status" value="1">
                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                <?php if ($order['order_status'] === 'Pending'): ?>
                                    <button type="submit" name="action" value="mark_paid" class="text-green-600 hover:text-green-800 btn-pos bg-green-50 px-3 py-1 rounded-lg text-sm">Bayar</button>
                                <?php endif; ?>
                                <?php if ($order['order_status'] !== 'Canceled'): ?>
                                    <button type="submit" name="action" value="cancel_order" onclick="return confirm('Yakin batalkan pesanan #<?php echo $order['order_number']; ?>?')" class="text-red-600 hover:text-red-800 btn-pos bg-red-50 px-3 py-1 rounded-lg text-sm">Batal</button>
                                <?php endif; ?>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>