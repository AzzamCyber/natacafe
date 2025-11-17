<?php
// =========================================================
// SERVICE: PrintService - FINAL VERSION
// =========================================================

class PrintService {
    
    /**
     * Menghasilkan markup HTML print-friendly untuk struk.
     * @param array $order Data pesanan dari tabel orders.
     * @param array $items Detail item pesanan dari tabel order_items.
     * @return string HTML markup.
     */
    public static function generateReceiptHtml(array $order, array $items): string {
        
        $subtotal = 0;
        $items_html = '';
        foreach ($items as $item) {
            $item_total = $item['price_per_item'] * $item['quantity'];
            $subtotal += $item_total;
            $items_html .= sprintf(
                '<div class="flex justify-between text-xs"><span class="w-2/3">%dx %s</span><span class="w-1/3 text-right">Rp %s</span></div>',
                $item['quantity'],
                htmlspecialchars($item['product_name']),
                number_format($item_total, 0, ',', '.')
            );
            if (!empty($item['item_notes'])) {
                $items_html .= sprintf(
                    '<p class="text-[10px] italic">  - Catatan: %s</p>',
                    htmlspecialchars($item['item_notes'])
                );
            }
        }

        // --- Ekstraksi variabel untuk interpolasi HEREDOC yang aman ---
        $total_amount_formatted = number_format($order['total_amount'], 0, ',', '.');
        $subtotal_formatted = number_format($subtotal, 0, ',', '.');
        $order_number = htmlspecialchars($order['order_number']);
        // Menggunakan ternary untuk kompatibilitas PHP 5.6+
        $cashier_name = htmlspecialchars(isset($order['cashier_name']) ? $order['cashier_name'] : 'N/A');
        $order_time_formatted = date('d-m-Y H:i:s', strtotime($order['created_at']));
        $customer_name_safe = htmlspecialchars($order['customer_name']);
        $payment_method_safe = htmlspecialchars($order['payment_method']);
        
        // Menggunakan Konstanta dari config/struck.php
        $store_name = RECEIPT_STORE_NAME;
        $footer_note = RECEIPT_FOOTER_NOTE;
        $dev_credit = RECEIPT_DEVELOPER_CREDIT;
        // --------------------------------------------------------
        
        // Markup yang di-inject ke DOM
        $html = <<<HTML
            <div id="receipt-print" style="visibility: hidden;">
                <div class="text-center mb-2">
                    <h1 class="font-bold text-lg">{$store_name}</h1>
                    <p class="text-xs">Struk Pembayaran</p>
                </div>
                <hr class="border-t border-dashed border-black mb-1">
                <p class="text-xs">No. Order: {$order_number}</p>
                <p class="text-xs">Kasir: {$cashier_name}</p>
                <p class="text-xs">Waktu: {$order_time_formatted}</p>
                <p class="text-xs">Pelanggan: {$customer_name_safe}</p>
                <hr class="border-t border-dashed border-black my-1">

                {$items_html}

                <hr class="border-t border-dashed border-black my-1">
                <div class="flex justify-between font-bold text-xs">
                    <span>Subtotal</span>
                    <span>Rp {$subtotal_formatted}</span>
                </div>
                <div class="flex justify-between font-extrabold text-sm mt-1">
                    <span>TOTAL</span>
                    <span>Rp {$total_amount_formatted}</span>
                </div>

                <p class="text-xs mt-2">Metode Bayar: <span class="font-semibold">{$payment_method_safe}</span></p>

                <div class="text-center mt-4 text-xs">
                    <p>{$footer_note}</p>
                    <p class="mt-1 font-semibold">{$dev_credit}</p>
                </div>
            </div>
        HTML;
        
        return $html;
    }
}