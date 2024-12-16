<?php
session_start();
include('../config/db.php');

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$m = connectDB();
$userId = new MongoDB\BSON\ObjectId($_SESSION['user']->_id);

try {
    // Tambahkan logging untuk debug
    error_log("Memulai proses pembayaran untuk user: " . $userId);
    
    // 1. Ambil data keranjang dan hitung total harga terlebih dahulu
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
    
    // Hitung total harga
    $totalHarga = 0;
    foreach ($cartItems as $item) {
        $totalHarga += $item->product->harga * $item->quantity;
    }
    
    error_log("Total harga: " . $totalHarga);
    
    // Inisialisasi session transaksi
    $session = $m->startSession();
    $session->startTransaction();
    
    // 3. Cek saldo
    $filter = ['user_id' => $userId];
    $query = new MongoDB\Driver\Query($filter);
    $cursor = $m->executeQuery('marketplace.Saldo', $query);
    $saldo = current($cursor->toArray());
    
    if (!$saldo || $saldo->balance < $totalHarga) {
        throw new Exception('Saldo tidak mencukupi');
    }
    
    // 4. Kurangi saldo pembeli
    $bulk = new MongoDB\Driver\BulkWrite;
    $bulk->update(
        ['user_id' => $userId],
        ['$inc' => ['balance' => -$totalHarga]]
    );
    $m->executeBulkWrite('marketplace.Saldo', $bulk);
    
    // 5. Buat pesanan untuk setiap item
    foreach ($cartItems as $item) {
        $order = [
            'pembeli_id' => $userId,
            'penjual_id' => $item->product->penjual_id,
            'product_id' => $item->product_id,
            'quantity' => $item->quantity,
            'total_harga' => $item->product->harga * $item->quantity,
            'status' => 'Pending',
            'created_at' => new MongoDB\BSON\UTCDateTime(),
            'alamat_pengiriman' => $_SESSION['user']->alamat
        ];
        
        $bulk = new MongoDB\Driver\BulkWrite;
        $bulk->insert($order);
        $m->executeBulkWrite('marketplace.Pesanan', $bulk);
        
        // 6. Update stok produk
        $bulk = new MongoDB\Driver\BulkWrite;
        $bulk->update(
            ['_id' => $item->product_id],
            ['$inc' => ['stok' => -$item->quantity]]
        );
        $m->executeBulkWrite('marketplace.Produk', $bulk);
        
        // 7. Tambah saldo penjual
        $bulk = new MongoDB\Driver\BulkWrite;
        $bulk->update(
            ['user_id' => $item->product->penjual_id],
            ['$inc' => ['balance' => ($item->product->harga * $item->quantity)]],
            ['upsert' => true]
        );
        $m->executeBulkWrite('marketplace.Saldo', $bulk);
    }
    
    // 8. Hapus keranjang
    $bulk = new MongoDB\Driver\BulkWrite;
    $bulk->delete(['user_id' => $userId]);
    $m->executeBulkWrite('marketplace.Keranjang', $bulk);
    
    // Commit transaksi
    $session->commitTransaction();
    $session->endSession();
    
    error_log("Pembayaran berhasil untuk user: " . $userId);
    echo json_encode(['success' => true, 'message' => 'Pembayaran berhasil']);
    
} catch (Exception $e) {
    error_log("Error pembayaran: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    if (isset($session)) {
        $session->abortTransaction();
        $session->endSession();
    }
    
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan: ' . $e->getMessage()]);
}
?> 