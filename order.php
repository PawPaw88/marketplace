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
    ['$match' => ['pembeli_id' => $userId]],
    ['$lookup' => [
        'from' => 'Produk',
        'localField' => 'product_id',
        'foreignField' => '_id',
        'as' => 'product'
    ]],
    ['$unwind' => '$product'],
    ['$lookup' => [
        'from' => 'Penjual',
        'localField' => 'penjual_id',
        'foreignField' => '_id',
        'as' => 'seller'
    ]],
    ['$unwind' => '$seller'],
    ['$sort' => ['created_at' => -1]]
];

$command = new MongoDB\Driver\Command([
    'aggregate' => 'Pesanan',
    'pipeline' => $pipeline,
    'cursor' => new stdClass,
]);

$cursor = $m->executeCommand('marketplace', $command);
$orders = $cursor->toArray();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesanan Saya - MyFurniture</title>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/style/main.css">
    <link rel="stylesheet" href="assets/style/order.css">
</head>
<body>

    <?php if ($isLoggedIn): ?>
    <nav class="mobile-nav">
        <a href="index.php" class="mobile-nav-item"><i class="ri-home-line"></i></a>
        <a href="order.php" class="mobile-nav-item active"><i class="ri-file-list-3-line"></i></a>
        <a href="wishlist.php" class="mobile-nav-item"><i class="ri-heart-line"></i></a>
        <a href="user.php" class="mobile-nav-item"><i class="ri-user-line"></i></a>
    </nav>
    <?php endif; ?>

    <main>
        <div class="orders-container">
            <a href="index.php" class="back-button">
                <i class="ri-arrow-left-line"></i>
                Kembali
            </a>
            <h1>Pesanan Saya</h1>
            
            <?php if (empty($orders)): ?>
                <div class="empty-state">
                    <i class="ri-shopping-bag-line"></i>
                    <p>Belum ada pesanan</p>
                    <a href="index.php" class="shop-now-btn">Belanja Sekarang</a>
                </div>
            <?php else: ?>
                <div class="orders-list">
                    <?php foreach ($orders as $order): ?>
                        <div class="order-card">
                            <div class="order-header">
                                <div class="seller-info">
                                    <img src="assets/img/avatar/<?php echo htmlspecialchars($order->seller->avatar ?? '1.jpg'); ?>" alt="Seller">
                                    <span><?php echo htmlspecialchars($order->seller->username); ?></span>
                                </div>
                                <div class="order-status <?php echo strtolower($order->status); ?>">
                                    <?php echo htmlspecialchars($order->status); ?>
                                </div>
                            </div>
                            
                            <div class="order-content">
                                <img src="assets/img/products/<?php echo htmlspecialchars($order->product->gambar[0]); ?>" 
                                     alt="<?php echo htmlspecialchars($order->product->nama_produk); ?>" 
                                     class="product-image">
                                <div class="order-details">
                                    <h3><?php echo htmlspecialchars($order->product->nama_produk); ?></h3>
                                    <p class="quantity"><?php echo $order->quantity; ?> x Rp <?php echo number_format($order->product->harga, 0, ',', '.'); ?></p>
                                    <p class="total">Total: Rp <?php echo number_format($order->total_harga, 0, ',', '.'); ?></p>
                                    <p class="seller-address"><?php echo htmlspecialchars($order->seller->alamat); ?></p>

                                    <!-- Tambahkan shipping timeline -->
                                    <div class="shipping-timeline">
                                        <div class="timeline-item <?php echo in_array($order->status, ['Pending', 'Diproses', 'Dikirim', 'Selesai']) ? 'active' : ''; ?>">
                                            <div class="timeline-point"></div>
                                            <span>Pending</span>
                                        </div>
                                        <div class="timeline-item <?php echo in_array($order->status, ['Diproses', 'Dikirim', 'Selesai']) ? 'active' : ''; ?>">
                                            <div class="timeline-point"></div>
                                            <span>Diproses</span>
                                        </div>
                                        <div class="timeline-item <?php echo in_array($order->status, ['Dikirim', 'Selesai']) ? 'active' : ''; ?>">
                                            <div class="timeline-point"></div>
                                            <span>Dikirim</span>
                                        </div>
                                        <div class="timeline-item <?php echo $order->status === 'Selesai' ? 'active' : ''; ?>">
                                            <div class="timeline-point"></div>
                                            <span>Selesai</span>
                                        </div>
                                    </div>

                                </div>
                            </div>
                            
                            <div class="order-footer">
                                <div class="order-footer-content">
                                    <p class="order-date">
                                        Dipesan pada: <?php echo $order->created_at->toDateTime()->format('d M Y H:i'); ?>
                                    </p>
                                    <?php if ($order->status === 'Selesai'): ?>
                                        <button class="review-btn" data-order-id="<?php echo $order->_id; ?>">
                                            Beri Ulasan
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>