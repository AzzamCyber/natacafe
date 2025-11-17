<?php
// =========================================================
// SERVICE: UploadService
// Menangani validasi dan penyimpanan file gambar produk.
// =========================================================

class UploadService {
    
    /**
     * Memproses upload file, memvalidasi, dan me-rename file secara acak.
     * @param array $fileArray $_FILES['image']
     * @return string|false Nama file yang di-rename atau false jika gagal.
     */
    public static function handleProductImageUpload(array $fileArray): string|false {
        if ($fileArray['error'] !== UPLOAD_ERR_OK) {
            return false;
        }

        // 1. Validasi MIME Type
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($fileArray['tmp_name']);
        $allowedMime = ['image/jpeg', 'image/png', 'image/webp'];

        if (!in_array($mimeType, $allowedMime)) {
            return false; // MIME type tidak diizinkan
        }

        // 2. Validasi Ukuran
        if ($fileArray['size'] > IMAGE_UPLOAD_MAX_SIZE) {
            return false; // Ukuran melebihi batas
        }

        // 3. Rename File Acak (32 char)
        $extension = pathinfo($fileArray['name'], PATHINFO_EXTENSION);
        // Random 32 characters filename
        $fileName = bin2hex(random_bytes(16)) . '.' . strtolower($extension);
        $destination = PRODUCT_IMAGE_DIR . $fileName;

        // 4. Pindahkan file
        if (move_uploaded_file($fileArray['tmp_name'], $destination)) {
            // Catatan: Jika perlu thumbnail, lakukan resize di sini.
            return $fileName;
        }

        return false;
    }
}