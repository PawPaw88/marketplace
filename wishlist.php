<?php
session_start();
include('config/db.php');

if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit();
}

$m = connectDB();
$userId = new MongoDB\BSON\ObjectId($_SESSION['user']->_id);

$isLoggedIn = isset($_SESSION['user']);

$pipeline = [
    ['$match' => ['user_id' => $userId]],
    ['$lookup' => [
        'from' => 'Produk',
        'localField' => 'product_id',
        'foreignField' => '_id',
        'as' => 'product'
    ]],
    ['$unwind' => '$product'],
    ['$lookup' => [
        'from' => 'Penjual',
        'localField' => 'product.penjual_id',
        'foreignField' => '_id',
        'as' => 'seller'
    ]],
    ['$unwind' => ['path' => '$seller', 'preserveNullAndEmptyArrays' => true]],
    ['$sort' => ['created_at' => -1]]
];

$command = new MongoDB\Driver\Command([
    'aggregate' => 'Wishlist',
    'pipeline' => $pipeline,
    'cursor' => new stdClass,
]);

$cursor = $m->executeCommand('marketplace', $command);
$wishlists = $cursor->toArray();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wishlist Saya - MyFurniture</title>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/style/main.css">
    <link rel="stylesheet" href="assets/style/order.css">
</head>
<body>
    <?php if ($isLoggedIn): ?>
    <nav class="mobile-nav">
        <a href="index.php" class="mobile-nav-item"><i class="ri-home-line"></i></a>
        <a href="order.php" class="mobile-nav-item"><i class="ri-file-list-3-line"></i></a>
        <a href="wishlist.php" class="mobile-nav-item active"><i class="ri-heart-line"></i></a>
        <a href="user.php" class="mobile-nav-item"><i class="ri-user-line"></i></a>
    </nav>
    <?php endif; ?>

    <main>
        <div class="orders-container">
            <a href="index.php" class="back-button">
                <i class="ri-arrow-left-line"></i>
                Kembali
            </a>
            <h1>Wishlist Saya</h1>
            
            <?php if (empty($wishlists)): ?>
                <div class="empty-state">
                    <i class="ri-heart-line"></i>
                    <p>Wishlist masih kosong</p>
                </div>
            <?php else: ?>
                <div class="orders-list">
                    <?php foreach ($wishlists as $wishlist): ?>
                        <div class="order-card">
                            <div class="order-content">
                                <button class="remove-wishlist" data-id="<?php echo $wishlist->_id; ?>">
                                    <i class="ri-heart-fill"></i>
                                </button>
                                <img src="assets/img/products/<?php echo htmlspecialchars($wishlist->product->gambar[0]); ?>" 
                                     alt="<?php echo htmlspecialchars($wishlist->product->nama_produk); ?>" 
                                     class="product-image">
                                <div class="order-details">
                                    <h3><?php echo htmlspecialchars($wishlist->product->nama_produk); ?></h3>
                                    <p class="price">Rp <?php echo number_format($wishlist->product->harga, 0, ',', '.'); ?></p>
                                    <p class="seller-name-wishlist">Penjual: <?php echo htmlspecialchars($wishlist->seller->username ?? 'Tidak tersedia'); ?></p>
                                    <p class="product-description"><?php echo htmlspecialchars($wishlist->product->deskripsi); ?></p>
                                    <p class="order-date">
                                        Ditambahkan pada: <?php echo $wishlist->created_at->toDateTime()->format('d M Y H:i'); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const removeButtons = document.querySelectorAll('.remove-wishlist');
        
        function showNotification(message, type = 'success') {
            const existingNotification = document.querySelector('.notification-popup');
            if (existingNotification) {
                existingNotification.remove();
            }

            const notification = document.createElement('div');
            notification.className = `notification-popup ${type}`;
            
            const icon = type === 'success' ? 'ri-check-line' : 'ri-error-warning-line';
            
            notification.innerHTML = `
                <i class="${icon}"></i>
                <span>${message}</span>
            `;

            document.body.appendChild(notification);

            setTimeout(() => {
                notification.classList.add('show');
            }, 100);

            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => {
                    notification.remove();
                }, 300);
            }, 3000);
        }
        
        removeButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const wishlistId = this.dataset.id;
                
                fetch('index.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=toggleWishlist&wishlistId=${wishlistId}&removeFromWishlist=true`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Hapus card wishlist dari tampilan
                        this.closest('.order-card').remove();
                        
                        // Tampilkan notifikasi berhasil
                        showNotification('Produk berhasil dihapus dari wishlist', 'success');
                        
                        // Cek apakah wishlist kosong
                        if (document.querySelectorAll('.order-card').length === 0) {
                            const ordersContainer = document.querySelector('.orders-list');
                            ordersContainer.innerHTML = `
                                <div class="empty-state">
                                    <i class="ri-heart-line"></i>
                                    <p>Wishlist masih kosong</p>
                                </div>
                            `;
                        }
                    } else {
                        showNotification(data.message || 'Gagal menghapus produk dari wishlist', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Terjadi kesalahan saat menghapus dari wishlist', 'error');
                });
            });
        });
    });
    </script>
</body>
</html> 