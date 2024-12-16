<?php
    session_start();
    include('config/db.php');

    $isLoggedIn = isset($_SESSION['user']);
    $userRole = $isLoggedIn ? $_SESSION['role'] : null;

    if ($isLoggedIn && $userRole === 'Penjual') {
        $_SESSION['user'] = (object) [
            'username' => $user->username,
            'email' => $user->email,
            'avatar' => $user->avatar ?? '1.jpg'
        ];
        header('Location: views/dashboard/seller.php');
        exit();
    }

    $m = connectDB();

    $pipeline = [
        ['$lookup' => [
            'from' => 'Penjual',
            'localField' => 'penjual_id',
            'foreignField' => '_id',
            'as' => 'penjual'
        ]],
        ['$unwind' => '$penjual'],
        ['$lookup' => [
            'from' => 'Pesanan',
            'localField' => '_id',
            'foreignField' => 'product_id',
            'as' => 'pesanan'
        ]],
        ['$addFields' => [
            'total_terjual' => [
                '$sum' => '$pesanan.quantity'
            ],
            'rating_avg' => [
                '$avg' => '$pesanan.rating'
            ]
        ]]
    ];

    $command = new MongoDB\Driver\Command([
        'aggregate' => 'Produk',
        'pipeline' => $pipeline,
        'cursor' => new stdClass,
    ]);

    $cursor = $m->executeCommand('marketplace', $command);
    $products = $cursor->toArray();

    $categories = array_unique(array_map(function($product) {
        return $product->kategori;
    }, $products));
    sort($categories);

    $cartItems = [];
    if ($isLoggedIn) {
        try {
            $userId = new MongoDB\BSON\ObjectId($_SESSION['user']->_id);
            
            $cartPipeline = [
                ['$match' => ['user_id' => $userId]],
                ['$lookup' => [
                    'from' => 'Produk',
                    'localField' => 'product_id',
                    'foreignField' => '_id',
                    'as' => 'product'
                ]],
                ['$unwind' => '$product']
            ];

            $command = new MongoDB\Driver\Command([
                'aggregate' => 'Keranjang',
                'pipeline' => $cartPipeline,
                'cursor' => new stdClass,
            ]);

            $cursor = $m->executeCommand('marketplace', $command);
            $cartItems = $cursor->toArray();
        } catch (Exception $e) {
            error_log("Error fetching cart items: " . $e->getMessage());
            $cartItems = [];
        }
    }

    $wishlistItems = [];
    if ($isLoggedIn) {
        try {
            $userId = new MongoDB\BSON\ObjectId($_SESSION['user']->_id);
            
            $wishlistPipeline = [
                ['$match' => ['user_id' => $userId]],
                ['$lookup' => [
                    'from' => 'Produk',
                    'localField' => 'product_id',
                    'foreignField' => '_id',
                    'as' => 'product'
                ]],
                ['$unwind' => '$product']
            ];

            $command = new MongoDB\Driver\Command([
                'aggregate' => 'Wishlist',
                'pipeline' => $wishlistPipeline,
                'cursor' => new stdClass,
            ]);

            $cursor = $m->executeCommand('marketplace', $command);
            $wishlistItems = $cursor->toArray();
        } catch (Exception $e) {
            error_log("Error fetching wishlist items: " . $e->getMessage());
            $wishlistItems = [];
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggleWishlist') {
        if (!$isLoggedIn) {
            echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu']);
            exit;
        }
    
        try {
            if (isset($_POST['removeFromWishlist']) && isset($_POST['wishlistId'])) {
                $wishlistId = new MongoDB\BSON\ObjectId($_POST['wishlistId']);
                $bulk = new MongoDB\Driver\BulkWrite;
                $bulk->delete(['_id' => $wishlistId]);
                $m->executeBulkWrite('marketplace.Wishlist', $bulk);
                echo json_encode(['success' => true, 'message' => 'Produk dihapus dari wishlist']);
                exit;
            }

            $productId = new MongoDB\BSON\ObjectId($_POST['productId']);
            $userId = new MongoDB\BSON\ObjectId($_SESSION['user']->_id);
            
            $filter = [
                'user_id' => $userId,
                'product_id' => $productId
            ];
            
            $query = new MongoDB\Driver\Query($filter);
            $cursor = $m->executeQuery('marketplace.Wishlist', $query);
            $existingItem = current($cursor->toArray());
    
            $bulk = new MongoDB\Driver\BulkWrite;
            
            if ($existingItem) {
                $bulk->delete(['_id' => $existingItem->_id]);
                $message = 'Produk dihapus dari wishlist';
                $isInWishlist = false;
            } else {
                $wishlistItem = [
                    'user_id' => $userId,
                    'product_id' => $productId,
                    'created_at' => new MongoDB\BSON\UTCDateTime()
                ];
                $bulk->insert($wishlistItem);
                $message = 'Produk ditambahkan ke wishlist';
                $isInWishlist = true;
            }
            
            $m->executeBulkWrite('marketplace.Wishlist', $bulk);
            echo json_encode(['success' => true, 'message' => $message, 'isInWishlist' => $isInWishlist]);
        } catch (Exception $e) {
            error_log("Error toggling wishlist: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Gagal mengubah wishlist']);
        }
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'addToCart') {
        if (!$isLoggedIn) {
            echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu']);
            exit;
        }

        try {
            $productId = new MongoDB\BSON\ObjectId($_POST['productId']);
            $userId = new MongoDB\BSON\ObjectId($_SESSION['user']->_id);
            $quantity = (int)$_POST['quantity'];
            
            // Cek stok produk
            $productQuery = new MongoDB\Driver\Query(['_id' => $productId]);
            $productCursor = $m->executeQuery('marketplace.Produk', $productQuery);
            $product = current($productCursor->toArray());
            
            if (!$product) {
                echo json_encode(['success' => false, 'message' => 'Produk tidak ditemukan']);
                exit;
            }
            
            // Cek item di keranjang
            $filter = [
                'user_id' => $userId,
                'product_id' => $productId
            ];
            
            $query = new MongoDB\Driver\Query($filter);
            $cursor = $m->executeQuery('marketplace.Keranjang', $query);
            $existingItem = current($cursor->toArray());

            $totalQuantity = $quantity;
            if ($existingItem) {
                $totalQuantity += $existingItem->quantity;
            }

            // Validasi total kuantitas dengan stok
            if ($totalQuantity > $product->stok) {
                echo json_encode([
                    'success' => false, 
                    'message' => 'Jumlah melebihi stok yang tersedia. Stok tersisa: ' . $product->stok
                ]);
                exit;
            }

            $bulk = new MongoDB\Driver\BulkWrite;
            
            if ($existingItem) {
                $bulk->update(
                    ['_id' => $existingItem->_id],
                    ['$set' => ['quantity' => $totalQuantity]]
                );
            } else {
                $cartItem = [
                    'user_id' => $userId,
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'created_at' => new MongoDB\BSON\UTCDateTime()
                ];
                $bulk->insert($cartItem);
            }
            
            $m->executeBulkWrite('marketplace.Keranjang', $bulk);
            echo json_encode(['success' => true, 'message' => 'Produk berhasil ditambahkan ke keranjang']);
        } catch (Exception $e) {
            error_log("Error adding to cart: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Gagal menambahkan ke keranjang']);
        }
        exit;
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MyFurniture</title>
    <script src="https://unpkg.com/feather-icons@4.29.0/dist/feather.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/style/main.css">
</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">
                <div class="logo">MyFurniture.</div>
                <div class="search-filter-wrapper">
                    <div class="search-bar">
                        <i class="ri-search-line search-icon"></i>
                        <input type="text" placeholder="Cari furniture...">
                    </div>
                </div>
                <div class="header-actions">
                    <?php if ($isLoggedIn): ?>
                        <div class="icon-wrapper">
                            <i class="ri-shopping-cart-line cart-icon"></i>
                        </div>
                        <div class="icon-wrapper">
                            <i class="ri-notification-3-line notif-icon"></i>
                        </div>
                        <div class="icon-wrapper">
                            <i class="ri-message-3-line chat-icon"></i>
                        </div>
                        <div class="user-profile">
                            <div class="profile-icon">
                                <img src="assets/img/avatar/<?php echo htmlspecialchars($_SESSION['user']->avatar ?? '1.jpg'); ?>" alt="Profile" class="avatar">
                            </div>
                            <span class="user-name"><?php echo htmlspecialchars($_SESSION['user']->username); ?></span>
                            <div class="profile-dropdown">
                                <div class="profile-info"></div>
                            </div>
                        </div>
                    <?php else: ?>
                        <nav class="nav-buttons">
                            <a href="views/auth/login.php?form=login">Login</a>
                            <a href="views/auth/login.php?form=register">Sign Up</a>
                        </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <div id="cartModal" class="header-modal">
        <div class="header-modal-content">
            <div class="header-modal-title">Keranjang Belanja</div>
            <?php if (empty($cartItems)): ?>
                <div class="empty-state">
                    <i class="ri-shopping-cart-line"></i>
                    <p>Keranjang belanja Anda masih kosong</p>
                </div>
            <?php else: ?>
                <button class="clear-cart-btn" onclick="clearCart()">Hapus Semua</button>
                <?php 
                $totalHarga = 0;
                foreach ($cartItems as $item): 
                    $subtotal = $item->product->harga * $item->quantity;
                    $totalHarga += $subtotal;
                ?>
                    <div class="modal-item">
                        <button class="delete-item-btn" onclick="deleteCartItem('<?php echo $item->_id; ?>')">
                            <i class="ri-delete-bin-line"></i>
                        </button>
                        <img src="assets/img/products/<?php echo htmlspecialchars($item->product->gambar[0]); ?>" 
                             alt="<?php echo htmlspecialchars($item->product->nama_produk); ?>">
                        <div class="modal-item-content">
                            <div class="modal-item-title"><?php echo htmlspecialchars($item->product->nama_produk); ?></div>
                            <div class="modal-item-desc">
                                <?php echo $item->quantity; ?> x Rp <?php echo number_format($item->product->harga, 0, ',', '.'); ?>
                            </div>
                            <div class="modal-item-price">
                                Rp <?php echo number_format($subtotal, 0, ',', '.'); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <div class="modal-total">
                    <span>Total:</span>
                    <span>Rp <?php echo number_format($totalHarga, 0, ',', '.'); ?></span>
                </div>
                <div class="modal-footer">
                    <a href="views/cart/payment.php" class="view-all-btn">Lihat Keranjang</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div id="notifModal" class="header-modal">
        <div class="header-modal-content">
            <div class="header-modal-title">Notifikasi</div>
            <div class="modal-item">
                <img src="assets/img/products/sample.jpg" alt="Product">
                <div class="modal-item-content">
                    <div class="modal-item-title">Pesanan Dikirim</div>
                    <div class="modal-item-desc">Pesanan Anda sedang dalam perjalanan</div>
                </div>
            </div>
            <div class="modal-footer">
                <a href="#" class="view-all-btn">Lihat Semua Notifikasi</a>
            </div>
        </div>
    </div>

    <div id="chatModal" class="header-modal">
        <div class="header-modal-content">
            <div class="header-modal-title">Pesan</div>
            <div class="modal-item">
                <img src="assets/img/avatar/1.jpg" alt="Seller">
                <div class="modal-item-content">
                    <div class="modal-item-title">Toko Furniture ABC</div>
                    <div class="modal-item-desc">Baik kak, pesanan sudah kami proses</div>
                </div>
            </div>
            <div class="modal-footer">
                <a href="chat.php" class="view-all-btn">Lihat Semua Pesan</a>
            </div>
        </div>
    </div>

    <div id="profileModal" class="header-modal">
        <div class="header-modal-content">
            <div class="header-modal-title">Profil Saya</div>
            <div class="profile-info">
                <img src="assets/img/avatar/<?php echo htmlspecialchars($_SESSION['user']->avatar ?? '1.jpg'); ?>" alt="Profile" class="profile-avatar">
                <div class="profile-details">
                    <p><?php echo htmlspecialchars($_SESSION['user']->username); ?></p>
                    <p><?php echo htmlspecialchars($_SESSION['user']->email); ?></p>
                </div>
            </div>
            <div class="profile-actions">
                <a href="profile.php" class="profile-action-btn">
                    <i class="ri-user-line"></i>
                    Lihat Profil
                </a>
                <a href="order.php" class="profile-action-btn">
                    <i class="ri-file-list-3-line"></i>
                    Pesanan Saya
                </a>
                <a href="wishlist.php" class="profile-action-btn">
                    <i class="ri-heart-line"></i>
                    Wishlist Saya
                </a>
                <a href="views/cart/payment.php" class="profile-action-btn">
                    <i class="ri-shopping-cart-line"></i>
                    Lihat Keranjang
                </a>
                <a href="views/payment/methods.php" class="profile-action-btn">
                    <i class="ri-bank-card-line"></i>
                    Metode Pembayaran
                </a>
                <a href="views/auth/logout.php" class="profile-action-btn logout">
                    <i class="ri-logout-box-line"></i>
                    Logout
                </a>
            </div>
        </div>
    </div>

    <?php if ($isLoggedIn): ?>
    <nav class="mobile-nav">
        <a href="index.php" class="mobile-nav-item active"><i class="ri-home-line"></i></a>
        <a href="order.php" class="mobile-nav-item"><i class="ri-file-list-3-line"></i></a>
        <a href="wishlist.php" class="mobile-nav-item"><i class="ri-heart-line"></i></a>
        <a href="user.php" class="mobile-nav-item"><i class="ri-user-line"></i></a>
    </nav>
    <?php endif; ?>

    <main>
        <div class="hero">
            <section class="hero-slider">
                <div class="hero-slide active">
                    <div class="hero-content">
                        <div class="hero-text">
                            <h1>Pasti Promo Pasti Ori</h1>
                            <p>Temukan produk berkualitas dari toko resmi</p>
                        </div>
                        <div class="hero-image">
                            <img src="assets/img/1.png" alt="Promo 1">
                        </div>
                    </div>
                </div>
                <div class="hero-slide">
                    <div class="hero-content">
                        <div class="hero-text">
                            <h1>Diskon Akhir Tahun</h1>
                            <p>Hanya hari ini, jangan lewatkan!</p>
                        </div>
                        <div class="hero-image">
                            <img src="assets/img/2.png" alt="Promo 2">
                        </div>
                    </div>
                </div>
                <div class="hero-slide">
                    <div class="hero-content">
                        <div class="hero-text">
                            <h1>Belanja Lebih Hemat</h1>
                            <p>Koleksi furniture terbaru dengan harga spesial</p>
                        </div>
                        <div class="hero-image">
                            <img src="assets/img/3.png" alt="Promo 3">
                        </div>
                    </div>
                </div>
                <div class="indicator">
                    <span class="active"></span>
                    <span></span>
                    <span></span>
                </div>
            </section>
        </div>

        <div class="products-section">
            <div class="category-container">
                <div class="category-buttons">
                    <button class="category-btn active" data-category="all">Semua</button>
                    <?php foreach ($categories as $category): ?>
                        <?php if ($category !== 'Lainnya'): ?>
                            <button class="category-btn" data-category="<?php echo htmlspecialchars($category); ?>">
                                <?php echo htmlspecialchars($category); ?>
                            </button>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <?php if (in_array('Lainnya', $categories)): ?>
                        <button class="category-btn" data-category="Lainnya">Lainnya</button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="product-grid">
                <?php foreach ($products as $product): ?>
                    <div class="product-card" 
                         data-category="<?php echo htmlspecialchars($product->kategori); ?>" 
                         data-product='<?php echo htmlspecialchars(json_encode($product, JSON_HEX_APOS | JSON_HEX_QUOT)); ?>'>
                        <?php if (!empty($product->gambar)): ?>
                            <img src="assets/img/products/<?php echo htmlspecialchars($product->gambar[0]); ?>" alt="<?php echo htmlspecialchars($product->nama_produk); ?>" class="product-image">
                        <?php else: ?>
                            <div class="no-image">No Image</div>
                        <?php endif; ?>
                        <h3><?php echo htmlspecialchars($product->nama_produk); ?></h3>
                        <p class="product-price">Rp <?php echo number_format($product->harga, 0, ',', '.'); ?></p>
                        <p class="product-seller"><?php echo htmlspecialchars($product->penjual->username); ?></p>
                        <div class="product-info">
                            <span class="product-rating">
                                <i class="ri-star-fill"></i> 
                                <?php echo number_format($product->rating_avg ?? 0, 1); ?>
                            </span>
                            <span class="product-sold">
                                <?php echo number_format($product->total_terjual ?? 0); ?> Terjual
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div id="productModal" class="modal">
                <div class="modal-content">
                    <button class="back-button"><i class="ri-arrow-left-line"></i> Kembali</button>
                    <div class="product-main-info">
                        <h2 id="modalProductName"></h2>
                        <div class="modal-product-info">
                            <div class="modal-rating">
                                <i class="ri-star-fill"></i>
                                <span>4.5</span>
                                <span>(50 ulasan)</span>
                            </div>
                            <div class="modal-actions">
                                <button class="modal-action-btn">
                                    <i class="ri-heart-line"></i>
                                    Wishlist
                                </button>
                            </div>
                        </div>
                        <div class="modal-image-container">
                            <img id="modalProductImage" src="" alt="Product Image">
                        </div>
                    </div>
                    <div class="modal-tabs">
                        <div class="modal-tab active" data-tab="details">Informasi Produk</div>
                        <div class="modal-tab" data-tab="reviews">Ulasan</div>
                    </div>
                    <div class="modal-tab-content active" id="details">
                        <div class="seller-info">
                            <img id="modalSellerAvatar" src="" alt="Seller Avatar" class="seller-avatar">
                            <div class="seller-details">
                                <p id="modalProductSeller" class="seller-name"></p>
                                <p id="modalSellerAddress" class="seller-address"></p>
                            </div>
                        </div>
                        <p id="modalProductPrice"></p>
                        <p id="modalProductDescription"></p>
                        <div class="modal-buttons">
                            <button id="modalAddToCart">Tambah ke Keranjang</button>
                            <?php if ($isLoggedIn): ?>
                                <a href="chat.php?seller_id=<?php echo $product->penjual->_id; ?>" id="modalChatSeller" class="chat-seller-btn">Chat Penjual</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="modal-tab-content" id="reviews">
                        <div class="review-item">
                            <div class="review-header">
                                <img src="assets/img/avatar/1.jpg" alt="Reviewer" class="reviewer-avatar">
                                <div class="reviewer-info">
                                    <div class="reviewer-name">John Doe</div>
                                    <div class="review-date">20 Mar 2024</div>
                                </div>
                            </div>
                            <div class="review-rating">
                                <i class="ri-star-fill"></i>
                                <i class="ri-star-fill"></i>
                                <i class="ri-star-fill"></i>
                                <i class="ri-star-fill"></i>
                                <i class="ri-star-fill"></i>
                            </div>
                            <p class="review-text">Produk sangat bagus dan sesuai dengan deskripsi. Pengiriman cepat dan packing aman.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div id="quantityModal" class="quantity-modal">
                <div class="quantity-modal-content">
                    <div class="quantity-input-container">
                        <label for="productQuantity">Jumlah:</label>
                        <input type="number" id="productQuantity" class="quantity-input" value="1" min="1">
                    </div>
                    <div class="quantity-modal-buttons">
                        <button class="cancel-add">Batal</button>
                        <button class="confirm-add">Konfirmasi</button>
                    </div>
                </div>
            </div>
        </div>
    </main>
    <script src="assets/js/utils.js" defer></script>
    <script src="assets/js/notifications.js" defer></script>
    <script src="assets/js/cart.js" defer></script>
    <script src="assets/js/productDetails.js" defer></script>
    <script src="assets/js/main.js" defer></script>
    <script>
    const isLoggedIn = <?php echo $isLoggedIn ? 'true' : 'false'; ?>;
    const wishlistItems = <?php echo json_encode(array_map(function($item) {
        return $item->product->_id;
    }, $wishlistItems)); ?>;
    </script>
</body>
</html>