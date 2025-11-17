-- Skema Database untuk Aplikasi Kasir Cafe/Resto
-- Nama Database: kasir_cafe

-- Tabel Pengguna (Admin/Kasir)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL, -- Disimpan menggunakan bcrypt
    role ENUM('admin', 'cashier') NOT NULL DEFAULT 'cashier',
    name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabel Kategori Produk
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE
);

-- Tabel Produk
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL,
    image_path VARCHAR(255) NULL, -- Path gambar di /assets/product/
    is_available BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT
);

-- Tabel Pesanan (Orders)
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(20) NOT NULL UNIQUE,
    cashier_id INT NULL, -- Kasir yang mencatat (FK ke users.id)
    customer_name VARCHAR(100) NULL,
    total_amount DECIMAL(10, 2) NOT NULL,
    payment_method ENUM('Tunai', 'Non-Tunai', 'Bayar Nanti') NOT NULL,
    order_status ENUM('Pending', 'Completed', 'Canceled', 'Refunded') NOT NULL DEFAULT 'Pending',
    order_notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (cashier_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Tabel Item Pesanan (Detail)
CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NULL,
    product_name VARCHAR(150) NOT NULL,
    price_per_item DECIMAL(10, 2) NOT NULL,
    quantity INT NOT NULL,
    item_notes TEXT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
);

-- Tabel Log Audit (Opsional: untuk mencatat perubahan kritis)
CREATE TABLE audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action TEXT NOT NULL,
    target_table VARCHAR(50) NOT NULL,
    target_id INT NULL,
    ip_address VARCHAR(45) NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Data Awal (Default User & Kategori)
INSERT INTO categories (name) VALUES 
('Minuman Kopi'), 
('Makanan Berat'), 
('Dessert');

-- Default User Admin (Password: admin123)
INSERT INTO users (username, password_hash, role, name) VALUES 
('admin', '$2y$10$w09uD8v1R/FkK4X8iZ7pTus9XvT0bV/YQfH8Tz1w5hA.9QjJ1m2N0', 'admin', 'Super Admin Natakenshi');
-- Default User Kasir (Password: kasir123)
INSERT INTO users (username, password_hash, role, name) VALUES 
('kasir', '$2y$10$tJ/3V1kX6iG9UuT4l2a5QeA.qB2M9zV6Hl5D0xY8oP3w2jR1C4S5', 'cashier', 'Kasir Utama');