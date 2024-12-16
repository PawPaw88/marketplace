<?php
session_start();
include('../config/db.php');

$isLoggedIn = isset($_SESSION['user']);
$cartItems = [];

if ($isLoggedIn) {
    try {
        $m = connectDB();
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
?>

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
            <img src="/marketplace/assets/img/products/<?php echo htmlspecialchars($item->product->gambar[0]); ?>" 
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
        <a href="/marketplace/views/cart/payment.php" class="view-all-btn">Lihat Keranjang</a>
    </div>
<?php endif; ?> 