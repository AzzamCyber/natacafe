<?php
// =========================================================
// PUBLIC/CHECKOUT_SUCCESS.PHP - FINAL VERSION
// Variabel global seperti $pdo, $csrf_token, $user_role tersedia.
// =========================================================

if ($page !== 'checkout_success') exit; // Safety check

// Memastikan config struck dimuat
require_once dirname(__DIR__) . '/config/struck.php'; 


$order_id = intval($_GET['order_id'] ?? 0);
if ($order_id === 0) {
    header('Location: index.php?page=pos');
    exit;
}

// Ambil detail order
$stmt_order = $pdo->prepare("
    SELECT o.*, u.name as cashier_name
    FROM orders o
    LEFT JOIN users u ON o.cashier_id = u.id
    WHERE o.id = ?
");
$stmt_order->execute([$order_id]);
$order = $stmt_order->fetch();

// Ambil detail item
$stmt_items = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
$stmt_items->execute([$order_id]);
$items = $stmt_items->fetchAll();

if (!$order || empty($items)) {
    header('Location: index.php?page=pos&error=Pesanan tidak ditemukan.');
    exit;
}

// Generate HTML struk
$receipt_html = PrintService::generateReceiptHtml($order, $items);
echo $receipt_html; // Inject receipt HTML markup to the DOM

$is_admin = $user_role === 'admin';
?>
<div class="flex flex-col items-center justify-center min-h-[80vh] bg-white p-8 rounded-xl shadow-2xl" data-aos="zoom-in">
    <svg class="w-20 h-20 text-emerald-500 mb-4 animate-bounce" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
    <h1 class="text-4xl font-extrabold text-emerald-600 mb-2">PESANAN BERHASIL!</h1>
    <p class="text-xl text-gray-700 mb-6">Nomor Pesanan: <span class="font-mono text-sky-700 font-bold"><?php echo $order['order_number']; ?></span></p>

    <p class="text-lg text-gray-600 mb-8">Struk akan dicetak secara otomatis. Anda akan dialihkan kembali ke halaman POS dalam <span id="cooldown" class="font-bold text-red-500">10</span> detik.</p>

    <button onclick="printReceipt(<?php echo $order_id; ?>)" class="bg-sky-700 text-white font-bold py-3 px-6 rounded-lg hover:bg-sky-600 transition duration-150 shadow-xl text-lg btn-pos">
        Cetak Ulang Struk
    </button>
    <?php if ($is_admin): ?>
        <a href="index.php?page=admin_orders" class="mt-4 text-sm text-gray-600 hover:text-gray-800">Lihat Daftar Pesanan</a>
    <?php endif; ?>
</div>

<script>
    // Memanggil fungsi window.print()
    function printReceipt(orderId) { 
        // Mengubah visibility style pada elemen yang diinject oleh PrintService agar terlihat saat print
        const receiptEl = document.getElementById('receipt-print');
        if (receiptEl) {
            receiptEl.style.visibility = 'visible';
            window.print(); 
        } else {
            console.error('Receipt element not found for printing.');
        }
    }

    // Cooldown dan Redirect
    let countdown_val=10; 
    const e=document.getElementById('cooldown'); 
    
    function u(){ 
        countdown_val--; 
        if(e){ e.textContent=countdown_val; } 
        if(countdown_val<=0){ 
            window.location.href='index.php?page=pos'; 
        } else { 
            setTimeout(u,1000); 
        } 
    }

    // Panggil print otomatis dan mulai countdown setelah 0.5 detik
    setTimeout(() => { 
        printReceipt(<?php echo $order_id; ?>); 
        u(); 
    }, 500);
</script>