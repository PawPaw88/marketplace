<?php
session_start();
include('../config/db.php');

header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu']);
    exit;
}

if (!isset($_POST['itemId']) || !isset($_POST['quantity'])) {
    echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
    exit;
}

try {
    $m = connectDB();
    $cartId = new MongoDB\BSON\ObjectId($_POST['itemId']);
    $quantity = (int)$_POST['quantity'];

    if ($quantity < 1) {
        echo json_encode(['success' => false, 'message' => 'Jumlah minimal adalah 1']);
        exit;
    }

    // Cek stok produk
    $cartPipeline = [
        ['$match' => ['_id' => $cartId]],
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
    $cartItem = current($cursor->toArray());

    if (!$cartItem) {
        echo json_encode(['success' => false, 'message' => 'Item tidak ditemukan']);
        exit;
    }

    if ($quantity > $cartItem->product->stok) {
        echo json_encode(['success' => false, 'message' => 'Jumlah melebihi stok yang tersedia']);
        exit;
    }

    $bulk = new MongoDB\Driver\BulkWrite;
    $bulk->update(
        ['_id' => $cartId],
        ['$set' => ['quantity' => $quantity]]
    );

    $result = $m->executeBulkWrite('marketplace.Keranjang', $bulk);

    if ($result->getModifiedCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Jumlah berhasil diperbarui']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal memperbarui jumlah']);
    }

} catch (Exception $e) {
    error_log("Error updating cart quantity: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan saat memperbarui jumlah']);
} 