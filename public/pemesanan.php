<?php
// =========================================================
// PUBLIC/PEMESANAN.PHP (POS VIEW) - VANILLA JS PURE
// Variabel global seperti $pdo, $csrf_token, $user_role tersedia.
// =========================================================

if ($page !== 'pos' && $page !== 'pemesanan') exit; // Safety check

// Mengambil data produk (FIX: Pastikan image_path diambil)
$products_raw = $pdo->query("SELECT p.*, c.name as category_name, p.image_path FROM products p JOIN categories c ON p.category_id = c.id WHERE p.is_available = 1 ORDER BY c.name, p.name ASC")->fetchAll();
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

// Konversi tipe data untuk konsistensi di JS/Client Side
$products = array_map(function($p) {
    $p['id'] = (int) $p['id'];
    $p['category_id'] = (int) $p['category_id'];
    $p['price'] = (float) $p['price'];
    $p['image'] = $p['image_path'] ?? 'placeholder.png'; // Fallback image
    return $p;
}, $products_raw);

$products_json = json_encode($products);
$categories_json = json_encode($categories);
?>

<div class="h-full flex flex-col lg:flex-row space-y-4 lg:space-y-0 lg:space-x-4">
    <div class="lg:w-3/5 bg-white p-4 rounded-xl shadow-lg overflow-y-auto h-[90vh] lg:h-auto" data-aos="fade-right">
        <h1 class="text-2xl font-bold mb-4 text-sky-700">Pilih Menu</h1>
        
        <div id="category-tabs" class="flex space-x-2 overflow-x-auto pb-2 border-b mb-4">
            <button data-category-id="all" class="category-btn btn-pos bg-sky-700 text-white shadow-md">Semua</button>
            <?php foreach ($categories as $cat): ?>
                <button data-category-id="<?php echo $cat['id']; ?>" class="category-btn btn-pos bg-gray-200 text-gray-700 hover:bg-sky-100">
                    <?php echo sanitize_input($cat['name']); ?>
                </button>
            <?php endforeach; ?>
        </div>
        
        <div id="product-grid" class="mt-4 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
            </div>

        <div id="notes-modal" class="fixed inset-0 bg-gray-600 bg-opacity-75 flex items-center justify-center z-50 hidden">
            <div class="bg-white p-6 rounded-xl shadow-2xl w-full max-w-sm" data-aos="zoom-in" id="modal-content">
                <h3 class="text-xl font-bold mb-4 text-sky-700" id="modal-title">Tambahkan Varian/Catatan</h3>
                <textarea id="item-notes-input" class="w-full px-3 py-2 border rounded-lg focus:ring-sky-500 focus:border-sky-500 mb-4 text-lg" rows="3" placeholder="Contoh: Less sugar, extra ice, ukuran besar..."></textarea>
                
                <div class="flex justify-end space-x-3">
                    <button id="modal-cancel" class="text-gray-600 hover:text-gray-800 font-semibold py-2 px-4 rounded-lg btn-pos">Batal</button>
                    <button id="modal-add-to-cart" class="bg-emerald-500 text-white font-bold py-2 px-4 rounded-lg hover:bg-emerald-600 transition duration-150 btn-pos">Tambah ke Keranjang</button>
                </div>
            </div>
        </div>
    </div>

    <div class="lg:w-2/5 bg-white p-4 rounded-xl shadow-lg flex flex-col h-[90vh] lg:h-auto" data-aos="fade-left">
        <h1 class="text-2xl font-bold mb-4 text-sky-700">Keranjang</h1>
        
        <div id="cart-container" class="flex-grow flex flex-col">
            <div id="cart-items-container" class="flex-grow overflow-y-auto space-y-3 mb-4 max-h-96">
                <div id="cart-empty-message" class="text-center text-gray-500 py-10">Keranjang kosong. Pilih menu di sebelah kiri.</div>
            </div>

            <div class="p-3 border-t border-sky-100">
                <div class="flex justify-between items-center mb-4">
                    <span class="text-lg font-semibold text-gray-700">Total Akhir:</span>
                    <span class="text-xl font-bold text-sky-700">Rp <span id="cart-total-display">0</span></span>
                </div>

                <form id="checkout-form" method="POST" action="index.php?page=checkout_process">
                    <input type="hidden" name="cart_data_json" id="cart-data-json">
                    <input type="hidden" name="total_amount" id="cart-total-amount">

                    <div class="mb-4">
                        <label class="block text-sm font-semibold text-gray-700 mb-1" for="customer_name">Atas Nama (Opsional)</label>
                        <input class="w-full px-4 py-3 border rounded-lg focus:ring-sky-500 focus:border-sky-500 text-lg" type="text" id="customer_name" name="customer_name" placeholder="Nama Pelanggan">
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-semibold text-gray-700 mb-1" for="payment_method">Metode Pembayaran</label>
                        <select class="w-full px-4 py-3 border rounded-lg focus:ring-sky-500 focus:border-sky-500 text-lg" id="payment_method" name="payment_method" required>
                            <option value="Tunai">Tunai</option>
                            <option value="Non-Tunai">Non-Tunai (Transfer/Kartu)</option>
                            <option value="Bayar Nanti">Bayar Nanti (Ambil/Meja)</option>
                        </select>
                    </div>
                    
                    <button type="submit" id="checkout-button" disabled class="w-full text-white font-extrabold btn-pos py-4 rounded-xl transition duration-150 shadow-xl disabled:bg-gray-400 disabled:shadow-none text-xl bg-gray-400">
                        CHECKOUT - Rp 0
                    </button>
                </form>
            </div>
            <button id="clear-cart-button" class="w-full text-sm text-red-500 mt-2 hover:text-red-700">Kosongkan Keranjang</button>
        </div>
    </div>
</div>

<script>
    // ====================================================================
    // PURE VANILLA JAVASCRIPT POS LOGIC (MENGGANTIKAN ALPINEJS)
    // ====================================================================

    document.addEventListener('DOMContentLoaded', () => {
        const products = <?php echo $products_json; ?>;
        const productGrid = document.getElementById('product-grid');
        const tabsContainer = document.getElementById('category-tabs');
        const cartItemsContainer = document.getElementById('cart-items-container');
        const cartTotalDisplay = document.getElementById('cart-total-display');
        const cartTotalAmountInput = document.getElementById('cart-total-amount');
        const checkoutButton = document.getElementById('checkout-button');
        const clearCartButton = document.getElementById('clear-cart-button');
        const cartDataJsonInput = document.getElementById('cart-data-json');
        const checkoutForm = document.getElementById('checkout-form');

        // Modal Elements
        const modal = document.getElementById('notes-modal');
        const modalTitle = document.getElementById('modal-title');
        const itemNotesInput = document.getElementById('item-notes-input');
        const modalCancel = document.getElementById('modal-cancel');
        const modalAddToCart = document.getElementById('modal-add-to-cart');
        let selectedProduct = null; // State untuk produk yang sedang dipilih di modal

        // Cart State Management
        let cart = [];

        // --- Utility Functions ---
        const formatRupiah = (amount) => {
            return new Intl.NumberFormat('id-ID').format(amount);
        };

        const loadCart = () => {
            const savedCart = localStorage.getItem('kasir_cafe_cart');
            if (savedCart) {
                cart = JSON.parse(savedCart);
            }
            renderCart();
            renderProductGrid('all');
        };

        const saveCart = () => {
            localStorage.setItem('kasir_cafe_cart', JSON.stringify(cart));
        };

        const calculateTotal = () => {
            return cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
        };

        // --- Cart Rendering ---

        const renderCart = () => {
            cartItemsContainer.innerHTML = '';
            const total = calculateTotal();

            if (cart.length === 0) {
                cartItemsContainer.innerHTML = '<div class="text-center text-gray-500 py-10">Keranjang kosong. Pilih menu di sebelah kiri.</div>';
                checkoutButton.disabled = true;
                checkoutButton.classList.add('bg-gray-400');
                checkoutButton.classList.remove('bg-sky-700', 'hover:bg-sky-600');
            } else {
                cart.forEach(item => {
                    const itemElement = document.createElement('div');
                    itemElement.className = 'flex items-center p-2 bg-sky-50 rounded-lg shadow-sm';
                    
                    itemElement.innerHTML = `
                        <img src="assets/product/${item.image}" onerror="this.onerror=null;this.src='https://placehold.co/40x40/E3F2FD/0288D1?text=M';" class="w-10 h-10 object-cover rounded-md mr-3 border" alt="Item Image">
                        <div class="flex-grow">
                            <p class="font-semibold text-gray-800 text-sm">${item.name}</p>
                            <p class="text-xs text-gray-500">Rp ${formatRupiah(item.price)}</p>
                            ${item.notes ? `<p class="text-xs text-red-500 italic">Catatan: ${item.notes}</p>` : ''}
                        </div>
                        <div class="flex items-center space-x-1">
                            <button data-action="decrement" data-id="${item.id}" data-notes="${item.notes}" class="text-sm bg-sky-200 text-sky-800 w-8 h-8 rounded-full hover:bg-sky-300 transition duration-100 qty-btn">-</button>
                            <span class="font-semibold w-6 text-center text-lg">${item.qty}</span>
                            <button data-action="increment" data-id="${item.id}" data-notes="${item.notes}" class="text-sm bg-sky-200 text-sky-800 w-8 h-8 rounded-full hover:bg-sky-300 transition duration-100 qty-btn">+</button>
                            <button data-action="remove" data-id="${item.id}" data-notes="${item.notes}" class="text-red-500 ml-2 hover:text-red-700 w-8 h-8 qty-btn">
                                <svg class="w-5 h-5 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                            </button>
                        </div>
                    `;
                    cartItemsContainer.appendChild(itemElement);
                });
                
                checkoutButton.disabled = false;
                checkoutButton.classList.remove('bg-gray-400');
                checkoutButton.classList.add('bg-sky-700', 'hover:bg-sky-600');
            }

            cartTotalDisplay.textContent = formatRupiah(total);
            checkoutButton.innerHTML = `CHECKOUT - Rp ${formatRupiah(total)}`;
            cartTotalAmountInput.value = total;
            cartDataJsonInput.value = JSON.stringify(cart);

            saveCart();
        };

        // --- Cart Actions (Pure JS) ---

        const updateCartItemQty = (id, notes, change) => {
            const index = cart.findIndex(item => item.id == id && item.notes === notes); // Use == for coercion
            if (index > -1) {
                cart[index].qty += change;
                if (cart[index].qty <= 0) {
                    cart.splice(index, 1);
                }
            }
            renderCart();
        };

        const removeFromCart = (id, notes) => {
            cart = cart.filter(item => !(item.id == id && item.notes === notes));
            renderCart();
        };

        // --- Event Listeners for Cart Buttons ---

        cartItemsContainer.addEventListener('click', (e) => {
            const btn = e.target.closest('button[data-action]');
            if (!btn) return;
            
            const action = btn.dataset.action;
            const id = btn.dataset.id;
            const notes = btn.dataset.notes;

            if (action === 'increment') {
                updateCartItemQty(id, notes, 1);
            } else if (action === 'decrement') {
                updateCartItemQty(id, notes, -1);
            } else if (action === 'remove') {
                removeFromCart(id, notes);
            }
        });

        clearCartButton.addEventListener('click', () => {
            if (confirm('Yakin ingin mengosongkan keranjang?')) {
                cart = [];
                renderCart();
            }
        });

        checkoutForm.addEventListener('submit', (e) => {
             if (cart.length === 0) {
                e.preventDefault();
                console.error('Keranjang belanja kosong!');
                return;
            }
            // Clear cart from storage before navigating, assuming success
            localStorage.removeItem('kasir_cafe_cart');
        });

        // --- Product Grid Rendering and Filtering ---

        const renderProductGrid = (categoryId) => {
            productGrid.innerHTML = '';
            let productsToRender = products;
            
            if (categoryId !== 'all') {
                const targetId = parseInt(categoryId);
                productsToRender = products.filter(p => p.category_id === targetId);
            }

            if (productsToRender.length === 0) {
                productGrid.innerHTML = '<div class="text-center text-gray-500 py-10 w-full col-span-4">Tidak ada produk di kategori ini.</div>';
                return;
            }

            productsToRender.forEach(product => {
                const card = document.createElement('div');
                card.className = 'bg-sky-50 p-3 rounded-xl shadow-md cursor-pointer hover:shadow-lg hover:ring-2 ring-sky-300 transition duration-200 product-card';
                card.setAttribute('data-aos', 'zoom-in');
                card.setAttribute('data-product', JSON.stringify(product));
                
                card.innerHTML = `
                    <img src="assets/product/${product.image_path}" onerror="this.onerror=null;this.src='https://placehold.co/150x100/E3F2FD/0288D1?text=Menu';" class="w-full h-24 object-cover rounded-lg mb-2" alt="Produk Image">
                    <p class="font-semibold text-sm text-gray-800 truncate">${product.name}</p>
                    <p class="text-xs text-sky-600 font-bold">Rp ${formatRupiah(product.price)}</p>
                `;
                
                card.addEventListener('click', () => openModal(product));
                productGrid.appendChild(card);
            });
        };

        // --- Category Tab Event Listeners ---
        
        tabsContainer.addEventListener('click', (e) => {
            const btn = e.target.closest('.category-btn');
            if (!btn) return;
            
            // Hapus kelas aktif dari semua tombol
            document.querySelectorAll('.category-btn').forEach(b => {
                b.classList.remove('bg-sky-700', 'shadow-md');
                b.classList.add('bg-gray-200', 'text-gray-700', 'hover:bg-sky-100');
            });
            
            // Tambahkan kelas aktif ke tombol yang diklik
            btn.classList.add('bg-sky-700', 'shadow-md');
            btn.classList.remove('bg-gray-200', 'text-gray-700', 'hover:bg-sky-100');

            const categoryId = btn.dataset.categoryId;
            renderProductGrid(categoryId);
        });
        
        // --- Modal Logic ---

        const openModal = (product) => {
            selectedProduct = product;
            modalTitle.textContent = `Tambahkan Varian/Catatan untuk ${product.name}`;
            itemNotesInput.value = '';
            modal.classList.remove('hidden');
        };

        const closeModal = () => {
            modal.classList.add('hidden');
            selectedProduct = null;
        };

        modalCancel.addEventListener('click', closeModal);

        modalAddToCart.addEventListener('click', () => {
            if (!selectedProduct) return;

            const notes = itemNotesInput.value.trim();
            const productToAdd = {
                id: selectedProduct.id,
                name: selectedProduct.name,
                price: selectedProduct.price,
                image: selectedProduct.image_path, // Pastikan menggunakan image_path
                notes: notes,
                category_id: selectedProduct.category_id,
                is_available: selectedProduct.is_available 
            };
            
            // Logika menambahkan ke keranjang
            const index = cart.findIndex(item => item.id === productToAdd.id && item.notes === productToAdd.notes);
            if (index > -1) {
                cart[index].qty++;
            } else {
                cart.push({ ...productToAdd, qty: 1 });
            }
            
            renderCart();
            closeModal();
            // Scroll ke bawah keranjang
            cartItemsContainer.scrollTop = cartItemsContainer.scrollHeight;
        });


        // --- Initialization ---
        loadCart();
    });
</script>