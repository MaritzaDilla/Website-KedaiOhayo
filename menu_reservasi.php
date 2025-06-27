<?php
session_start();
require_once 'koneksi.php';

// Deteksi apakah ini request normal (bukan AJAX)
$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// Reset hanya jika bukan AJAX dan halaman baru dimuat (refresh atau buka baru)
if (!$is_ajax && !isset($_POST['action'])) {
    // Kembalikan stok dari keranjang jika ada
    if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
        foreach ($_SESSION['cart'] as $item) {
            $product_id = $item['product_id'];
            $quantity = $item['quantity'];
            $conn->query("UPDATE products SET stok = stok + $quantity WHERE id = $product_id");
        }
        $_SESSION['cart'] = []; // Kosongkan keranjang
    }
}

// Initialize cart and original stock if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $item = $_POST['item'] ?? '';
    $price = $_POST['price'] ?? 0;
    $image = $_POST['image'] ?? '';
    $product_id = $_POST['product_id'] ?? 0;
    $response = ['success' => false];

    switch ($action) {
        case 'add':
            // Check stock before adding
            $check_stock = $conn->prepare("SELECT stok FROM products WHERE id = ?");
            $check_stock->bind_param("i", $product_id);
            $check_stock->execute();
            $stock_result = $check_stock->get_result();
            $stock_data = $stock_result->fetch_assoc();
            
            if ($stock_data && $stock_data['stok'] > 0) {
                if (isset($_SESSION['cart'][$item])) {
                    $_SESSION['cart'][$item]['quantity'] += 1;
                } else {
                    $_SESSION['cart'][$item] = [
                        'price' => $price,
                        'quantity' => 1,
                        'image' => $image,
                        'product_id' => $product_id
                    ];
                }
                
                // Update stock in database
                $conn->query("UPDATE products SET stok = stok - 1 WHERE id = $product_id");
                
                $response = [
                    'success' => true,
                    'cart' => $_SESSION['cart'],
                    'new_stock' => $stock_data['stok'] - 1
                ];
            } else {
                $response['message'] = 'Stok tidak mencukupi';
            }
            break;
            
        case 'increase':
            if (isset($_SESSION['cart'][$item])) {
                $product_id = $_SESSION['cart'][$item]['product_id'];
                // Check stock
                $check_stock = $conn->prepare("SELECT stok FROM products WHERE id = ?");
                $check_stock->bind_param("i", $product_id);
                $check_stock->execute();
                $stock_result = $check_stock->get_result();
                $stock_data = $stock_result->fetch_assoc();
                
                if ($stock_data && $stock_data['stok'] > 0) {
                    $_SESSION['cart'][$item]['quantity'] += 1;
                    // Update stock
                    $conn->query("UPDATE products SET stok = stok - 1 WHERE id = $product_id");
                    
                    $response = [
                        'success' => true,
                        'cart' => $_SESSION['cart'],
                        'new_stock' => $stock_data['stok'] - 1
                    ];
                } else {
                    $response['message'] = 'Stok tidak mencukupi';
                }
            }
            break;
            
        case 'decrease':
            if (isset($_SESSION['cart'][$item]) && $_SESSION['cart'][$item]['quantity'] > 0) {
                $product_id = $_SESSION['cart'][$item]['product_id'];
                $_SESSION['cart'][$item]['quantity'] -= 1;
                
                // Update stock
                $conn->query("UPDATE products SET stok = stok + 1 WHERE id = $product_id");
                
                // Get updated stock
                $check_stock = $conn->prepare("SELECT stok FROM products WHERE id = ?");
                $check_stock->bind_param("i", $product_id);
                $check_stock->execute();
                $new_stock = $check_stock->get_result()->fetch_assoc()['stok'];
                
                // Remove item if quantity is 0
                if ($_SESSION['cart'][$item]['quantity'] <= 0) {
                    unset($_SESSION['cart'][$item]);
                }
                
                $response = [
                    'success' => true,
                    'cart' => $_SESSION['cart'],
                    'new_stock' => $new_stock
                ];
            }
            break;
            
        case 'remove':
            if (isset($_SESSION['cart'][$item])) {
                $quantity = $_SESSION['cart'][$item]['quantity'];
                $product_id = $_SESSION['cart'][$item]['product_id'];
                // Return all stock
                $conn->query("UPDATE products SET stok = stok + $quantity WHERE id = $product_id");
                unset($_SESSION['cart'][$item]);
                
                $response = [
                    'success' => true,
                    'cart' => $_SESSION['cart']
                ];
            }
            break;
            
        case 'get':
            $response = [
                'success' => true,
                'cart' => $_SESSION['cart']
            ];
            break;
            
        case 'get_stock':
            $product_id = $_POST['product_id'];
            $result = $conn->query("SELECT stok FROM products WHERE id = $product_id");
            $stock = $result->fetch_assoc()['stok'];
            $response = [
                'success' => true,
                'stock' => $stock
            ];
            break;
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Get all products with category
$sql = "SELECT id, nama_produk, harga, stok, gambar, kategori FROM products";
$result = $conn->query($sql);
$menus = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $menus[] = $row;
    }
}

// Get takeaway details from session
$takeaway_details = $_SESSION['takeaway_details'] ?? [];
$takeaway_details = array_merge([
    'nama' => '',
    'nomor_hp' => '',

], $takeaway_details);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pemesanan Menu - Kedai Ohayo</title>
    <link rel="stylesheet" href="css/menutakeaway.css?v=1.1">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .store-info {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 10px 15px;
            margin-top: 10px;
            border-left: 4px solid #e63946;
        }
        
        .store-info p {
            margin: 5px 0;
            font-size: 14px;
            color: #333;
        }
        
        .store-info i {
            margin-right: 8px;
            color: #e63946;
        }
    </style>
</head>
<body>
<div id="notification" class="notification"></div>
    <div class="page-header">
        <h1>Pemesanan Menu</h1>
        <p>Kedai Ohayo - Pesan makanan favoritmu untuk makan di tempat</p>
        <div class="store-info">
            <p><i class="fas fa-clock"></i> Jam Operasional: 11.00-21.00</p>
            <p><i class="fas fa-calendar-times"></i> Kedai Ohayo Tutup Setiap hari Senin  </p>
        </div>
    </div>
    
    <div class="container">
        <section class="menu-section">
            <h2>Daftar Menu</h2>
            
            <div class="search-filter-container">
                <div class="search-container">
                    <input type="text" class="search-input" placeholder="Cari menu..." oninput="searchMenu(this.value)">
                </div>
                
                <div class="category-filter">
                    <button class="category-btn active" onclick="filterCategory('all')">Semua</button>
                    <button class="category-btn" onclick="filterCategory('Makanan')">Makanan</button>
                    <button class="category-btn" onclick="filterCategory('Minuman')">Minuman</button>
                </div>
            </div>
            
            <div class="menu-container">
                <?php foreach ($menus as $menu): 
                    $gambar_path = $menu['gambar'];
                    if (!str_starts_with($gambar_path, 'img/') && !str_starts_with($gambar_path, 'uploads/')) {
                        $gambar_path = 'uploads/' . $gambar_path;
                    }
                    
                    $gambar_exists = file_exists($gambar_path);
                    if (!$gambar_exists) {
                        $gambar_path = 'img/default-product.jpg';
                    }
                ?>
                <div class="menu-item" data-category="<?= htmlspecialchars($menu['kategori']) ?>" 
                     data-name="<?= htmlspecialchars(strtolower($menu['nama_produk'])) ?>">
                    <img src="<?= htmlspecialchars($gambar_path) ?>" alt="<?= htmlspecialchars($menu['nama_produk']) ?>">
                    <h3><?= htmlspecialchars($menu['nama_produk']) ?></h3>
                    <p class="price">Rp<?= number_format($menu['harga'], 0, ',', '.') ?></p>
                    <p class="stock-display" data-product-id="<?= $menu['id'] ?>">
                        Stok: <?= htmlspecialchars($menu['stok']) ?>
                    </p>
                    <button class="add-to-cart" 
                            data-product-id="<?= $menu['id'] ?>" 
                            <?= ($menu['stok'] <= 0 ? 'disabled' : '') ?> 
                            onclick="addToCart('<?= htmlspecialchars($menu['nama_produk']) ?>', 
                                             <?= $menu['harga'] ?>, 
                                             '<?= htmlspecialchars($gambar_path) ?>', 
                                             <?= $menu['id'] ?>)">
                        <?= ($menu['stok'] <= 0 ? 'Stok Habis' : '➕ Tambah ke Keranjang') ?>
                    </button>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
    </div>
    
    <!-- Cart Toggle Button -->
    <div class="cart-toggle" onclick="toggleCart()">
        <svg width="28" height="28" viewBox="0 0 24 24">
            <path d="M7 18c-1.1 0-1.99.9-1.99 2S5.9 22 7 22s2-.9 2-2-.9-2-2-2zM1 2v2h2l3.6 7.59-1.35 2.45c-.16.28-.25.61-.25.96 0 1.1.9 2 2 2h12v-2H7.42c-.14 0-.25-.11-.25-.25l.03-.12.9-1.63h7.45c.75 0 1.41-.41 1.75-1.03l3.58-6.49c.08-.14.12-.31.12-.48 0-.55-.45-1-1-1H5.21l-.94-2H1zm16 16c-1.1 0-1.99.9-1.99 2s.89 2 1.99 2 2-.9 2-2-.9-2-2-2z"/>
        </svg>
        <span class="cart-count">0</span>
    </div>
    
    <!-- Cart Overlay -->
    <div id="overlay" class="modal-overlay" onclick="closeCartOnOverlay(event)"></div>
    
    <!-- Cart Sidebar -->
    <div class="cart" id="cart">
        <div class="cart-header">
            <h3>🛒 Pesanan Kamu</h3>
        </div>
        
        <div class="cart-body">
            <div class="detail-reservasi">
                <div class="detail-title">📅 Detail Reservasi</div>
                <div class="detail-info">
                    <?php if (isset($_SESSION['reservation_details'])): ?>
                        <div class="reservation-data">
                            <div class="data-item">
                                <span class="data-label">Nama Pemesan:</span>
                                <span class="data-value"><?= htmlspecialchars($_SESSION['reservation_details']['nama']) ?></span>
                            </div>
                            <div class="data-item">
                                <span class="data-label">Nomor HP:</span>
                                <span class="data-value"><?= htmlspecialchars($_SESSION['reservation_details']['nomor_hp']) ?></span>
                            </div>
                            <div class="data-item">
                                <span class="data-label">Tanggal Pesanan:</span>
                                <span class="data-value"><?= htmlspecialchars($_SESSION['reservation_details']['tanggal']) ?></span>
                            </div>
                            <div class="data-item">
                                <span class="data-label">Jam Pesanan:</span>
                                <span class="data-value"><?= htmlspecialchars($_SESSION['reservation_details']['waktu']) ?></span>
                            </div>
                            <div class="data-item">
                                <span class="data-label">Jumlah Orang:</span>
                                <span class="data-value"><?= htmlspecialchars($_SESSION['reservation_details']['jumlah_orang']) ?></span>
                            </div>
                        </div>
                        <input type="hidden" id="nama" value="<?= htmlspecialchars($_SESSION['reservation_details']['nama']) ?>">
                        <input type="hidden" id="nomor_hp" value="<?= htmlspecialchars($_SESSION['reservation_details']['nomor_hp']) ?>">
                        <input type="hidden" id="tanggal_pesanan" value="<?= htmlspecialchars($_SESSION['reservation_details']['tanggal']) ?>">
                        <input type="hidden" id="jam_pesanan" value="<?= htmlspecialchars($_SESSION['reservation_details']['waktu']) ?>">
                    <?php else: ?>
                        <div class="alert-message">
                            <p>Data reservasi tidak ditemukan. Silakan melakukan reservasi terlebih dahulu.</p>
                            <a href="form_reservasi.php" class="btn-reservasi">Buat Reservasi</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="empty-cart" id="empty-cart">
                <div class="empty-cart-icon">🛒</div>
                <div>Keranjang masih kosong</div>
                <small>Pilih menu favoritmu!</small>
            </div>
            
            <ul class="cart-items" id="cart-items"></ul>
            
            <div class="cart-summary" id="cart-summary" style="display: none;">
                <div class="summary-row">
                    <span>Total Items:</span>
                    <span id="total-items">0</span>
                </div>
                <div class="summary-row">
                    <span>Subtotal:</span>
                    <span id="subtotal">Rp 0</span>
                </div>
                <div class="summary-row">
                    <span>Tax (10%):</span>
                    <span id="tax">Rp 0</span>
                </div>
                <div class="summary-row total">
                    <span>Total:</span>
                    <span id="grand-total">Rp 0</span>
                </div>
                <div class="summary-row">
                <span>Bayar Sekarang (50%):</span>
                <span id="bayar-sekarang">Rp 0</span>
            </div>
            <small style="display: block; margin-top: 8px; color: #888;">
                💡 Sisa pembayaran dapat dibayarkan langsung di Kedai Ohayo saat pengambilan.
            </small>
            </div>
        </div>
        
        <div class="cart-form">
            <div class="form-section">
                <div class="form-section-title">💳 Metode Pembayaran</div>
                <div class="radio-group">
                    <label class="radio-option">
                        <input type="radio" name="payment" value="Transfer" checked>
                        <span>Transfer Bank</span>
                    </label>
                </div>
                <div class="bank-info">
                    <p style="margin: 0; color: #333; font-size: 14px;">📝 Silahkan Transfer ke:</p>
                    <p style="margin: 5px 0; font-weight: 600; color: var(--secondary-color);">Bank BCA: 123-4567-890</p>
                    <p style="margin: 0; color: #333; font-size: 14px;">An. Kedai Ohayo</p>
                </div>
                <div class="form-group">
                    <label style="display: block; margin-bottom: 8px; color: #555;">📎 Upload Bukti Transfer:</label>
                    <input type="file" id="payment-proof-input" accept="image/*" 
                           style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"
                           onchange="previewPaymentProof(this)">
                    <div id="preview-payment" style="margin-top: 10px; display: none;">
                        <img id="payment-preview" style="max-width: 100%; max-height: 200px; border-radius: 4px;">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <div class="form-section-title">🍽️ Tipe Pesanan</div>
                <div class="radio-group">
                    <label class="radio-option">
                        <input type="radio" name="orderType" value="DIne-In" checked>
                        <span>Dine In (Makan di Tempat)</span>
                    </label>
                </div>
            </div>
            
            <div class="form-group">
                <label>📝 Catatan Tambahan:</label>
                <textarea id="notes" rows="3" placeholder="Contoh: Pedas level 2, tanpa bawang..."></textarea>
            </div>
            
            <button class="checkout-btn" onclick="processOrder()">
                Proses Pesanan
            </button>
        </div>
    </div>

    <script>
    // Global cart state
    // Global cart state
let currentCart = {};

// Initialize the page
document.addEventListener('DOMContentLoaded', function() {
    // Show custom time field if "custom" is selected
    // const selected = document.querySelector('input[name="waktu_pengambilan"]:checked');
    // if (selected && selected.value === 'custom') {
    //     toggleCustomTime(true);
    // }
    
    // // Initialize cart
    // updateCart();
    
    // // Initialize category filter
    // filterCategory('all');
    
    // // Add event listener for custom time radio
    // document.querySelectorAll('input[name="waktu_pengambilan"]').forEach(radio => {
    //     if (radio.value !== 'custom') {
    //         radio.addEventListener('click', () => toggleCustomTime(false));
    //     }
    // });
    
    // Validasi tanggal dan jam
    const tanggalInput = document.getElementById('tanggal_pesanan');
    const jamInput = document.getElementById('jam_pesanan');
    
    // Set tanggal minimum ke hari ini
    const today = new Date();
    const yyyy = today.getFullYear();
    const mm = String(today.getMonth() + 1).padStart(2, '0');
    const dd = String(today.getDate()).padStart(2, '0');
    const todayStr = `${yyyy}-${mm}-${dd}`;
    tanggalInput.min = todayStr;
    
    // Fungsi untuk memeriksa apakah tanggal adalah hari Senin
    function isMonday(dateStr) {
        const date = new Date(dateStr);
        return date.getDay() === 1; // 0 = Minggu, 1 = Senin, ..., 6 = Sabtu
    }
    
    // Validasi saat tanggal berubah
    tanggalInput.addEventListener('change', function() {
        const selectedDate = this.value;
        
        if (isMonday(selectedDate)) {
            showNotification('Maaf, kedai tutup setiap hari Senin. Silakan pilih tanggal lain.', true);
            this.value = todayStr;
            return;
        }
        
        if (isIdulFitri(selectedDate)) {
            showNotification('Maaf, kedai tutup pada hari raya Idul Fitri. Silakan pilih tanggal lain.', true);
            this.value = todayStr;
            return;
        }
    });
    
    // Validasi jam operasional
    jamInput.addEventListener('change', function() {
        const selectedTime = this.value;
        const hour = parseInt(selectedTime.split(':')[0]);
        const minute = parseInt(selectedTime.split(':')[1]);
        
        if (hour < 11 || (hour === 21 && minute > 0) || hour > 21) {
            showNotification('Maaf, jam operasional kedai adalah 11.00 - 21.00', true);
            this.value = '11:00';
        }
    });
});

// Filter menu by category
function filterCategory(category) {
    // Update active button
    document.querySelectorAll('.category-btn').forEach(btn => {
        btn.classList.remove('active');
        if ((category === 'all' && btn.textContent === 'Semua') || 
            btn.textContent === category) {
            btn.classList.add('active');
        }
    });
    
    // Get current search query
    const searchQuery = document.querySelector('.search-input').value.toLowerCase();
    
    // Filter menu items
    const menuItems = document.querySelectorAll('.menu-item');
    menuItems.forEach(item => {
        const itemCategory = item.getAttribute('data-category');
        const itemName = item.getAttribute('data-name');
        const matchesCategory = category === 'all' || itemCategory === category;
        const matchesSearch = itemName.includes(searchQuery);
        
        item.style.display = (matchesCategory && matchesSearch) ? 'block' : 'none';
    });
}

// Search menu items
function searchMenu(query) {
    const queryLower = query.toLowerCase();
    const activeCategory = document.querySelector('.category-btn.active').textContent;
    
    document.querySelectorAll('.menu-item').forEach(item => {
        const itemName = item.getAttribute('data-name');
        const itemCategory = item.getAttribute('data-category');
        const matchesSearch = itemName.includes(queryLower);
        const matchesCategory = activeCategory === 'Semua' || itemCategory === activeCategory;
        
        item.style.display = (matchesSearch && matchesCategory) ? 'block' : 'none';
    });
}

// Toggle cart visibility
function toggleCart() {
    const cart = document.getElementById('cart');
    const overlay = document.getElementById('overlay');
    
    if (cart.style.display === 'none' || cart.style.display === '') {
        cart.style.display = 'block';
        overlay.style.display = 'block';
        document.body.style.overflow = 'hidden';
    } else {
        cart.style.display = 'none';
        overlay.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
}

// Close cart when clicking on overlay
function closeCartOnOverlay(event) {
    if (event.target === document.getElementById('overlay')) {
        toggleCart();
    }
}

// Toggle custom time input
function toggleCustomTime(show) {
    const customTimeGroup = document.getElementById('custom-time-group');
    if (show) {
        customTimeGroup.style.display = 'block';
        document.getElementById('custom_time').focus();
    } else {
        customTimeGroup.style.display = 'none';
    }
}

// Function to show notification
function showNotification(message, isError = false) {
    const notification = document.getElementById('notification');
    notification.textContent = message;
    notification.className = 'notification' + (isError ? ' error' : '');
    notification.classList.add('show');
    
    // Add icon based on type
    const icon = document.createElement('span');
    icon.innerHTML = isError ? '❌' : '✅';
    notification.prepend(icon);
    
    // Hide after 3 seconds
    setTimeout(() => {
        notification.classList.remove('show');
    }, 3000);
}

// Function to add item to cart with stock management
async function addToCart(itemName, price, image, productId) {
    // Get all buttons and stock displays for this product
    const addButtons = document.querySelectorAll(`.add-to-cart[data-product-id="${productId}"]`);
    const stockDisplays = document.querySelectorAll(`.stock-display[data-product-id="${productId}"]`);
    
    // Show loading state
    addButtons.forEach(button => {
        button.disabled = true;
        button.innerHTML = '<span class="loading-spinner"></span>';
    });

    try {
        // 1. Get current stock from server
        const stockResponse = await getStock(productId);
        const currentStock = stockResponse.stock;
        
        if (currentStock <= 0) {
            throw new Error('Stok habis');
        }
        
        // 2. Optimistic UI update - reduce stock display immediately
        const newStock = currentStock - 1;
        updateStockDisplay(productId, newStock);
        
        // 3. Add to cart
        const data = {
            action: 'add',
            item: itemName,
            price: price,
            image: image,
            product_id: productId
        };
        
        const response = await sendCartRequest(data);
        
        if (!response.success) {
            // If failed, revert stock display
            updateStockDisplay(productId, currentStock);
            throw new Error(response.message || 'Gagal menambahkan ke keranjang');
        }
        
        // 4. Update UI with server response
        currentCart = response.cart;
        renderCart();
        
        // 5. Show success notification
        showNotification(`${itemName} berhasil ditambahkan ke keranjang`);
        
    } catch (error) {
        showNotification(error.message, true);
    } finally {
        // Reset buttons based on current stock
        const currentStockDisplay = document.querySelector(`.stock-display[data-product-id="${productId}"]`);
        const currentStock = parseInt(currentStockDisplay.textContent.replace('Stok: ', ''));
        
        addButtons.forEach(button => {
            button.disabled = currentStock <= 0;
            button.textContent = currentStock <= 0 ? 'Stok Habis' : '➕ Tambah ke Keranjang';
        });
    }
}

// Function to increase quantity in cart
async function increaseQuantity(itemName) {
    const item = currentCart[itemName];
    if (!item) return;

    try {
        // 1. Check stock availability
        const stockResponse = await getStock(item.product_id);
        const currentStock = stockResponse.stock;
        
        if (currentStock <= 0) {
            showNotification('Stok tidak mencukupi', true);
            return;
        }

        // 2. Optimistic UI update
        const newQuantity = item.quantity + 1;
        currentCart[itemName].quantity = newQuantity;
        renderCart();
        updateStockDisplay(item.product_id, currentStock - 1);

        // 3. Send request to server
        const data = {
            action: 'increase',
            item: itemName,
            product_id: item.product_id
        };

        const response = await sendCartRequest(data);
        if (!response.success) {
            // Revert changes if failed
            currentCart[itemName].quantity = item.quantity;
            renderCart();
            updateStockDisplay(item.product_id, currentStock);
            throw new Error(response.message || 'Gagal menambah jumlah item');
        }

        // 4. Update with server response
        currentCart = response.cart;
        updateStockDisplay(item.product_id, response.new_stock);

    } catch (error) {
        showNotification(error.message, true);
    }
}

// Function to decrease quantity in cart
async function decreaseQuantity(itemName) {
    const item = currentCart[itemName];
    if (!item) return;

    try {
        // 1. Get current stock from display (optimistic)
        const currentStock = parseInt(
            document.querySelector(`.stock-display[data-product-id="${item.product_id}"]`)
                .textContent.replace('Stok: ', '')
        );

        // 2. Optimistic UI update
        const newQuantity = item.quantity - 1;
        if (newQuantity <= 0) {
            await removeItem(itemName);
            return;
        }

        currentCart[itemName].quantity = newQuantity;
        renderCart();
        updateStockDisplay(item.product_id, currentStock + 1);

        // 3. Send request to server
        const data = {
            action: 'decrease',
            item: itemName,
            product_id: item.product_id
        };

        const response = await sendCartRequest(data);
        if (!response.success) {
            // Revert changes if failed
            currentCart[itemName].quantity = item.quantity;
            renderCart();
            updateStockDisplay(item.product_id, currentStock);
            throw new Error(response.message || 'Gagal mengurangi jumlah item');
        }

        // 4. Update with server response
        currentCart = response.cart;
        updateStockDisplay(item.product_id, response.new_stock);

    } catch (error) {
        showNotification(error.message, true);
    }
}

// Function to remove item from cart
async function removeItem(itemName) {
    const item = currentCart[itemName];
    if (!item) return;

    try {
        // 1. Get current stock from display (optimistic)
        const currentStock = parseInt(
            document.querySelector(`.stock-display[data-product-id="${item.product_id}"]`)
                .textContent.replace('Stok: ', '')
        );

        // 2. Optimistic UI update
        const removedQuantity = item.quantity;
        delete currentCart[itemName];
        renderCart();
        updateStockDisplay(item.product_id, currentStock + removedQuantity);

        // 3. Send request to server
        const data = {
            action: 'remove',
            item: itemName,
            product_id: item.product_id
        };

        const response = await sendCartRequest(data);
        if (!response.success) {
            // Revert changes if failed
            currentCart[itemName] = item;
            renderCart();
            updateStockDisplay(item.product_id, currentStock);
            throw new Error(response.message || 'Gagal menghapus item');
        }

        // 4. Update with server response
        currentCart = response.cart;
        updateStockDisplay(item.product_id, response.new_stock);
        showNotification(`${itemName} dihapus dari keranjang`);

    } catch (error) {
        showNotification(error.message, true);
    }
}

// Helper function to update stock display with animation
function updateStockDisplay(productId, newStock) {
    const stockDisplays = document.querySelectorAll(`.stock-display[data-product-id="${productId}"]`);
    const addButtons = document.querySelectorAll(`.add-to-cart[data-product-id="${productId}"]`);
    
    stockDisplays.forEach(display => {
        // Animation effect
        display.classList.add('stock-update');
        setTimeout(() => {
            display.textContent = `Stok: ${newStock}`;
            display.classList.remove('stock-update');
        }, 300);
    });
    
    // Update buttons state
    addButtons.forEach(button => {
        button.disabled = newStock <= 0;
        button.textContent = newStock <= 0 ? 'Stok Habis' : '➕ Tambah ke Keranjang';
    });
}

// Get stock information from server
async function getStock(productId) {
    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'get_stock',
                product_id: productId
            })
        });
        
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.message || 'Failed to get stock');
        }
        
        return data;
    } catch (error) {
        console.error('Error:', error);
        throw error;
    }
}

// Send cart request to server
async function sendCartRequest(data) {
    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams(data)
        });
        
        return await response.json();
    } catch (error) {
        console.error('Error:', error);
        throw error;
    }
}

// Update cart UI
function updateCart() {
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({action: 'get'})
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            currentCart = data.cart || {};
            renderCart();
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
}

// Render cart items
function renderCart() {
    const cartItemsContainer = document.getElementById('cart-items');
    const emptyCartDiv = document.getElementById('empty-cart');
    const cartSummaryDiv = document.getElementById('cart-summary');
    
    // Clear existing items
    cartItemsContainer.innerHTML = '';
    
    if (Object.keys(currentCart).length === 0) {
        emptyCartDiv.style.display = 'block';
        cartSummaryDiv.style.display = 'none';
        document.querySelector('.cart-count').textContent = '0';
        return;
    }
    
    emptyCartDiv.style.display = 'none';
    cartSummaryDiv.style.display = 'block';
    
    let totalItems = 0;
    let subtotal = 0;
    
    // Add each item to cart
    for (const [itemName, itemData] of Object.entries(currentCart)) {
        totalItems += itemData.quantity;
        const itemTotal = itemData.price * itemData.quantity;
        subtotal += itemTotal;
        
        const li = document.createElement('li');
        li.className = 'cart-item';
        li.innerHTML = `
            <img src="${itemData.image}" alt="${itemName}" class="cart-item-image">
            <div class="cart-item-details">
                <div class="cart-item-name">${itemName}</div>
                <div class="cart-item-price">Rp${itemData.price.toLocaleString('id-ID')} x ${itemData.quantity}</div>
            </div>
            <div class="cart-item-controls">
                <button class="quantity-btn" onclick="decreaseQuantity('${itemName}')">-</button>
                <span class="quantity-display">${itemData.quantity}</span>
                <button class="quantity-btn" onclick="increaseQuantity('${itemName}')">+</button>
                <button class="remove-btn" onclick="removeItem('${itemName}')">×</button>
            </div>
        `;
        cartItemsContainer.appendChild(li);
    }
    
   // Update summary
document.getElementById('total-items').textContent = totalItems;
document.getElementById('subtotal').textContent = `Rp${subtotal.toLocaleString('id-ID')}`;

const tax = subtotal * 0.1; // 10% tax
document.getElementById('tax').textContent = `Rp${tax.toLocaleString('id-ID')}`;

const grandTotal = subtotal + tax;
const bayarSekarang = grandTotal * 0.5;

document.getElementById('grand-total').textContent = `Rp${grandTotal.toLocaleString('id-ID')}`;
document.getElementById('bayar-sekarang').textContent = `Rp${bayarSekarang.toLocaleString('id-ID')}`;

// Update cart count
document.querySelector('.cart-count').textContent = totalItems;
}

// Preview payment proof image
function previewPaymentProof(input) {
    const previewDiv = document.getElementById('preview-payment');
    const previewImg = document.getElementById('payment-preview');
    
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            previewImg.src = e.target.result;
            previewDiv.style.display = 'block';
        }
        
        reader.readAsDataURL(input.files[0]);
    } else {
        previewDiv.style.display = 'none';
    }
}

// Process order
function processOrder() {
    const nama = document.getElementById('nama').value.trim();
    const nomorHp = document.getElementById('nomor_hp').value.trim();
    const tanggalPesanan = document.getElementById('tanggal_pesanan').value;
    const jamPesanan = document.getElementById('jam_pesanan').value;
    // const waktuPengambilan = document.querySelector('input[name="waktu_pengambilan"]').value;
    const paymentMethod = document.querySelector('input[name="payment"]:checked').value;
    const orderType = document.querySelector('input[name="orderType"]:checked').value;
    const notes = document.getElementById('notes').value.trim();
    const paymentProofInput = document.getElementById('payment-proof-input');
    
    // Validasi bukti pembayaran untuk metode transfer
    if (paymentMethod === 'Transfer' && (!paymentProofInput.files || !paymentProofInput.files[0])) {
        paymentProofInput.style.borderColor = '#ff6b6b';
        paymentProofInput.style.backgroundColor = '#fff0f0';
        showNotification('Mohon upload bukti transfer', true);
        return;
    }
    
    // Validasi keranjang
    if (Object.keys(currentCart).length === 0) {
        showNotification('Keranjang masih kosong', true);
        return;
    }
    
    // Calculate totals
    let subtotal = 0;
    for (const [itemName, itemData] of Object.entries(currentCart)) {
        subtotal += itemData.price * itemData.quantity;
    }
    const grandTotal = subtotal + tax;
const bayarSekarang = grandTotal * 0.5;

const orderData = {
    customer: {
        nama: nama,
        nomor_hp: nomorHp,
        tanggal: tanggalPesanan,
        jam: jamPesanan,
        // waktu_pengambilan: waktuPengambilan,
        payment_method: paymentMethod,
        order_type: orderType,
        notes: notes
    },
    cart: currentCart,
    totals: {
        subtotal: subtotal,
        tax: tax,
        grandTotal: grandTotal,
        bayar_sekarang: bayarSekarang
    }
};

    
    // Tambahkan bukti pembayaran jika ada
    if (paymentProofInput.files && paymentProofInput.files[0]) {
        // Simpan base64 dari gambar bukti pembayaran
        const reader = new FileReader();
        reader.onload = function(e) {
            orderData.payment_proof = e.target.result;
            sendOrderData(orderData);
        };
        reader.readAsDataURL(paymentProofInput.files[0]);
    } else {
        sendOrderData(orderData);
    }
}

// Fungsi untuk mengirim data pesanan
function sendOrderData(orderData) {
    // Disable button during processing
    const button = document.querySelector('.checkout-btn');
    button.disabled = true;
    button.innerHTML = '<span class="loading-spinner"></span> Memproses...';
    
    // Send order data
    fetch('proses_reservasi.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(orderData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Pesanan berhasil diproses!');
            // Clear cart and redirect or refresh
            window.location.href = 'nota_reservasi.php?order_id=' + data.order_id;
        } else {
            showNotification(data.message || 'Terjadi kesalahan saat memproses pesanan', true);
            button.disabled = false;
            button.textContent = 'Proses Pesanan';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Terjadi kesalahan saat memproses pesanan', true);
        button.disabled = false;
        button.textContent = 'Proses Pesanan';
    });
}

// Close cart when pressing Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        const cart = document.getElementById('cart');
        if (cart.style.display === 'block') {
            toggleCart();
        }
    }
});

// Format phone number input
document.getElementById('nomor_hp').addEventListener('input', function(e) {
    // Hapus karakter non-angka
    this.value = this.value.replace(/[^0-9]/g, '');
    
    // Tambahkan validasi visual
    const phoneRegex = /^(\+62|62|0)8[1-9][0-9]{6,10}$/;
    if (this.value && !phoneRegex.test(this.value)) {
        this.style.borderColor = '#ff6b6b';
        this.style.backgroundColor = '#fff0f0';
    } else {
        this.style.borderColor = '';
        this.style.backgroundColor = '';
    }
});
</script>
</body>
</html>