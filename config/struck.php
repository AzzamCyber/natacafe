<?php
// =========================================================
// KONFIGURASI STRUK CETAK (RECEIPT)
// =========================================================

define('RECEIPT_STORE_NAME', 'NATAKENSHI CAFE');
define('RECEIPT_FOOTER_NOTE', 'TERIMA KASIH ATAS KUNJUNGAN ANDA');
define('RECEIPT_DEVELOPER_CREDIT', 'Made By Natakenshi Developer');

// Pengaturan Pajak/Diskon (Opsional, diaktifkan jika diperlukan)
define('RECEIPT_TAX_RATE', 0.0); // Contoh: 0.10 untuk 10%
define('RECEIPT_DISPLAY_TAX', false); 

// Catatan: Pastikan RECEIPT_TAX_RATE digunakan dalam perhitungan total akhir
// di OrderController.php (yang saat ini belum diimplementasikan di sini untuk kesederhanaan).

?>