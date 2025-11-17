<?php
// =========================================================
// CONTROLLER: ProductController - FINAL VERSION
// Menangani semua operasi CRUD Produk dan Kategori.
// =========================================================

class ProductController {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Memproses aksi CRUD Product (Add/Edit/Delete).
     * @param string $action 'add', 'edit', atau 'delete'
     * @param array $postData Data dari $_POST
     * @param array $fileData Data dari $_FILES
     * @return array ['success' => bool, 'message' => string]
     */
    public function handleProductAction(string $action, array $postData, array $fileData): array {
        
        // CSRF Token Check (Keamanan)
        if (!isset($postData['csrf_token']) || $postData['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
            return ['success' => false, 'message' => "CSRF Token mismatch. Silakan coba lagi."];
        }

        try {
            if ($action === 'add' || $action === 'edit') {
                $product_id = intval($postData['product_id'] ?? 0);
                $name = sanitize_input($postData['name']);
                $price = floatval($postData['price']);
                $category_id = intval($postData['category_id']);
                $is_available = isset($postData['is_available']) ? 1 : 0;
                $description = sanitize_input($postData['description'] ?? '');
                $image_file = null;
                
                if (isset($fileData['image']) && $fileData['image']['error'] === UPLOAD_ERR_OK) {
                    // Logika upload gambar menggunakan Service Layer
                    $image_file = UploadService::handleProductImageUpload($fileData['image']);
                    if (!$image_file) {
                        return ['success' => false, 'message' => "Gagal upload gambar. Pastikan format JPG/PNG/WEBP dan max 5MB."];
                    }
                }

                if ($action === 'add') {
                    $sql = "INSERT INTO products (category_id, name, description, price, is_available, image_path) VALUES (?, ?, ?, ?, ?, ?)";
                    $stmt = $this->pdo->prepare($sql);
                    $stmt->execute([$category_id, $name, $description, $price, $is_available, $image_file]);
                    audit_log($this->pdo, "Added product: " . $name, 'products', $this->pdo->lastInsertId());
                    return ['success' => true, 'message' => "Produk berhasil ditambahkan."];
                } elseif ($action === 'edit' && $product_id) {
                    // Logika Hapus Gambar Lama saat Update Gambar Baru
                    if ($image_file) {
                        $stmt_old = $this->pdo->prepare("SELECT image_path FROM products WHERE id = ?");
                        $stmt_old->execute([$product_id]);
                        $old_image = $stmt_old->fetchColumn();
                        
                        // FIX PATH: Menggunakan konstanta PRODUCT_IMAGE_DIR untuk penghapusan fisik
                        if ($old_image && defined('PRODUCT_IMAGE_DIR') && file_exists(PRODUCT_IMAGE_DIR . $old_image)) {
                            unlink(PRODUCT_IMAGE_DIR . $old_image);
                        }
                    }
                    
                    $updates = ["category_id = ?", "name = ?", "description = ?", "price = ?", "is_available = ?"];
                    $params = [$category_id, $name, $description, $price, $is_available];
                    
                    // Deskripsi opsional
                    if (isset($postData['description'])) {
                        $updates[] = "description = ?";
                        $params[] = $description;
                    }
                    
                    if ($image_file) {
                        $updates[] = "image_path = ?";
                        $params[] = $image_file;
                    }
                    $sql = "UPDATE products SET " . implode(', ', $updates) . " WHERE id = ?";
                    $params[] = $product_id;
                    $stmt = $this->pdo->prepare($sql);
                    $stmt->execute($params);
                    audit_log($this->pdo, "Updated product: " . $name, 'products', $product_id);
                    return ['success' => true, 'message' => "Produk berhasil diperbarui."];
                }
            } elseif ($action === 'delete') {
                $product_id = intval($postData['product_id']);
                $stmt = $this->pdo->prepare("SELECT image_path FROM products WHERE id = ?");
                $stmt->execute([$product_id]);
                $product_info = $stmt->fetch();
                
                // FIX PATH: Menggunakan konstanta PRODUCT_IMAGE_DIR untuk path absolut penghapusan
                if ($product_info && $product_info['image_path'] && defined('PRODUCT_IMAGE_DIR') && file_exists(PRODUCT_IMAGE_DIR . $product_info['image_path'])) {
                    unlink(PRODUCT_IMAGE_DIR . $product_info['image_path']);
                }
                
                $stmt = $this->pdo->prepare("DELETE FROM products WHERE id = ?");
                $stmt->execute([$product_id]);
                audit_log($this->pdo, "Deleted product ID: " . $product_id, 'products', $product_id);
                return ['success' => true, 'message' => "Produk berhasil dihapus."];
            }

            return ['success' => false, 'message' => "Aksi produk tidak valid."];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => "Kesalahan DB: " . $e->getMessage()];
        }
    }
    
    /**
     * Memproses aksi CRUD Category (Add).
     */
    public function handleCategoryAction(array $postData): array {
        try {
            $name = sanitize_input($postData['name'] ?? '');
            if (empty($name)) return ['success' => false, 'message' => "Nama kategori tidak boleh kosong."];

            $stmt = $this->pdo->prepare("INSERT INTO categories (name) VALUES (?)");
            $stmt->execute([$name]);
            audit_log($this->pdo, "Added new category: " . $name, 'categories', $this->pdo->lastInsertId());
            return ['success' => true, 'message' => "Kategori berhasil ditambahkan."];

        } catch (PDOException $e) {
            return ['success' => false, 'message' => "Gagal menambahkan kategori. Nama mungkin sudah ada."];
        }
    }
    
    /**
     * Mengambil daftar produk lengkap.
     */
    public function getAllProducts(): array {
        $stmt = $this->pdo->query("SELECT p.*, c.name as category_name FROM products p JOIN categories c ON p.category_id = c.id ORDER BY c.name, p.name");
        return $stmt->fetchAll();
    }
}