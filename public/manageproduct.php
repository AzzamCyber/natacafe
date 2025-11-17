<?php
// =========================================================
// PUBLIC/MANAGEPRODUCT.PHP - FINAL VERSION (Fixed Path Display)
// Variabel global seperti $pdo, $csrf_token, $user_role tersedia.
// =========================================================

if (!in_array($page, ['manageproduct', 'admin_products', 'admin_products_edit', 'admin_categories'])) exit; // Safety check
require_role('admin');

$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
// FIX: Pastikan image_path diambil dari DB
$products = $pdo->query("SELECT p.*, c.name as category_name, p.image_path FROM products p JOIN categories c ON p.category_id = c.id ORDER BY p.created_at DESC")->fetchAll();
$edit_product = null;

if ($page === 'admin_products_edit' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([intval($_GET['id'])]);
    $edit_product = $stmt->fetch();
    if (!$edit_product) {
        header('Location: index.php?page=admin_products&error=Produk tidak ditemukan.');
        exit;
    }
}

// LOGIKA HAPUS KATEGORI (Dipindahkan ke Controller di versi final)
if (isset($_POST['delete_category'])) {
    // Logic ini seharusnya sudah ada di index.php
    $category_id = intval($_POST['category_id']);
    try {
        $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->execute([$category_id]);
        audit_log($pdo, "Deleted category ID: " . $category_id, 'categories', $category_id);
        header('Location: index.php?page=admin_products&success=' . urlencode('Kategori berhasil dihapus.'));
        exit;
    } catch (PDOException $e) {
        header('Location: index.php?page=admin_products&error=' . urlencode('Gagal menghapus kategori. Pastikan tidak ada produk yang menggunakannya.'));
        exit;
    }
}
?>
<h1 class="text-3xl font-bold mb-6 text-gray-700">Manajemen Produk & Kategori</h1>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    
    <div class="lg:col-span-2 bg-white p-6 rounded-xl shadow-lg" data-aos="fade-right">
        <h2 class="text-2xl font-bold mb-4 text-sky-700"><?php echo $edit_product ? 'Edit Produk' : 'Tambah Produk Baru'; ?></h2>
        <form method="POST" action="index.php?page=admin_products" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="product_action" value="<?php echo $edit_product ? 'edit' : 'add'; ?>">
            <?php if ($edit_product): ?><input type="hidden" name="product_id" value="<?php echo $edit_product['id']; ?>"><?php endif; ?>
            
            <div class="mb-4">
                <label class="block text-gray-700 font-semibold mb-2" for="name">Nama Produk</label>
                <input class="w-full px-4 py-2 border rounded-lg focus:ring-sky-500 focus:border-sky-500 text-lg" type="text" id="name" name="name" required value="<?php echo $edit_product ? sanitize_input($edit_product['name']) : ''; ?>">
            </div>

            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-gray-700 font-semibold mb-2" for="category_id">Kategori</label>
                    <select class="w-full px-4 py-2 border rounded-lg focus:ring-sky-500 focus:border-sky-500 text-lg" id="category_id" name="category_id" required>
                        <option value="">Pilih Kategori</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo $edit_product && $edit_product['category_id'] == $cat['id'] ? 'selected' : ''; ?>>
                                <?php echo sanitize_input($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <a href="#kategori-add-form" class="text-xs text-sky-600 hover:underline mt-1 block">Tambah Kategori Baru</a>
                </div>
                <div>
                    <label class="block text-gray-700 font-semibold mb-2" for="price">Harga (Rp)</label>
                    <input class="w-full px-4 py-2 border rounded-lg focus:ring-sky-500 focus:border-sky-500 text-lg" type="number" step="100" id="price" name="price" required value="<?php echo $edit_product ? $edit_product['price'] : ''; ?>">
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-gray-700 font-semibold mb-2" for="description">Deskripsi (Opsional)</label>
                <textarea class="w-full px-4 py-2 border rounded-lg focus:ring-sky-500 focus:border-sky-500 text-lg" id="description" name="description"><?php echo $edit_product ? sanitize_input($edit_product['description']) : ''; ?></textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6 items-center">
                <div>
                    <label class="block text-gray-700 font-semibold mb-2" for="image">Gambar Produk (JPG/PNG/WEBP, Max 5MB)</label>
                    <input class="w-full p-2 border rounded-lg" type="file" id="image" name="image">
                    <?php if ($edit_product && $edit_product['image_path']): ?>
                        <p class="text-sm text-gray-500 mt-1">Gambar saat ini:</p>
                        <img src="assets/product/<?php echo $edit_product['image_path']; ?>" onerror="this.onerror=null;this.src='https://placehold.co/50x50/E3F2FD/0288D1?text=M';" class="w-16 h-16 object-cover rounded-md mt-1 border" alt="Produk Image">
                    <?php endif; ?>
                </div>
                <div class="flex items-center">
                    <input class="h-6 w-6 text-sky-600 border-gray-300 rounded focus:ring-sky-500" type="checkbox" id="is_available" name="is_available" <?php echo $edit_product === null || $edit_product['is_available'] ? 'checked' : ''; ?>>
                    <label class="ml-3 text-gray-700 font-semibold text-lg" for="is_available">Tersedia untuk dijual</label>
                </div>
            </div>

            <button type="submit" class="w-full bg-sky-700 text-white font-bold py-3 px-4 rounded-lg hover:bg-sky-600 transition duration-150 shadow-lg text-lg btn-pos">
                <?php echo $edit_product ? 'Simpan Perubahan' : 'Tambah Produk'; ?>
            </button>
            <?php if ($edit_product): ?>
                <a href="index.php?page=admin_products" class="w-full block text-center mt-3 text-sm text-gray-600 hover:text-gray-800">Batalkan Edit</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="lg:col-span-1 space-y-6">
        
        <div id="kategori-add-form" class="bg-white p-6 rounded-xl shadow-lg" data-aos="fade-up">
            <h3 class="text-xl font-bold mb-3 text-emerald-700">⚙️ Kelola Kategori</h3>
            
            <form method="POST" action="index.php?page=admin_products" class="flex mb-4">
                <input type="hidden" name="add_category" value="1">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="text" name="name" placeholder="Nama Kategori Baru" class="flex-grow px-3 py-2 border rounded-l-lg text-lg" required>
                <button type="submit" class="bg-emerald-500 text-white px-4 py-2 rounded-r-lg hover:bg-emerald-600 text-lg">Tambah</button>
            </form>
            
            <h4 class="font-semibold text-gray-700 mt-4">Daftar Kategori (<?php echo count($categories); ?>)</h4>
            <ul class="divide-y divide-gray-100 max-h-48 overflow-y-auto mt-2">
                <?php foreach ($categories as $cat): ?>
                    <li class="py-2 text-base text-gray-600 flex justify-between items-center">
                        <span><?php echo sanitize_input($cat['name']); ?></span>
                        <form method="POST" action="index.php?page=admin_products" onsubmit="return confirm('Yakin hapus kategori ini? Pastikan tidak ada produk yang menggunakannya!')" class="inline">
                            <input type="hidden" name="delete_category" value="1">
                            <input type="hidden" name="category_id" value="<?php echo $cat['id']; ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <button type="submit" class="text-red-500 hover:text-red-700 text-sm">Hapus</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="bg-white p-6 rounded-xl shadow-lg" data-aos="fade-up" data-aos-delay="100">
            <h3 class="text-xl font-bold mb-3 text-sky-700">📦 Daftar Produk</h3>
            <ul class="divide-y divide-gray-100 max-h-96 overflow-y-auto">
                <?php foreach ($products as $product): ?>
                    <li class="py-2 flex justify-between items-center hover:bg-sky-50/50 transition duration-100 px-2 rounded-lg">
                        <div class="flex items-center">
                            <img src="assets/product/<?php echo $product['image_path']; ?>" onerror="this.onerror=null;this.src='https://placehold.co/50x50/E3F2FD/0288D1?text=M';" class="w-10 h-10 object-cover rounded-md mr-3 border" alt="Produk Image">
                            <div>
                                <p class="font-semibold text-base"><?php echo sanitize_input($product['name']); ?></p>
                                <p class="text-sm text-gray-500"><?php echo sanitize_input($product['category_name']); ?> - Rp <?php echo number_format($product['price'], 0, ',', '.'); ?></p>
                            </div>
                        </div>
                        <div class="flex space-x-2">
                            <a href="index.php?page=admin_products_edit&id=<?php echo $product['id']; ?>" class="text-sky-600 hover:text-sky-800 text-base">Edit</a>
                            <form method="POST" action="index.php?page=admin_products" onsubmit="return confirm('Yakin ingin menghapus produk ini?')" class="inline">
                                <input type="hidden" name="product_action" value="delete">
                                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                <button type="submit" class="text-red-600 hover:text-red-800 text-base">Hapus</button>
                            </form>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>