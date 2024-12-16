<?php
session_start();
include('../config/db.php');

header('Content-Type: application/json');

// Cek autentikasi
if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu']);
    exit;
}

// Cek method dan parameter
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method tidak valid']);
    exit;
}

if (!isset($_POST['itemId'])) {
    echo json_encode(['success' => false, 'message' => 'ID item tidak ditemukan']);
    exit;
}

try {
    $m = connectDB();
    $itemId = new MongoDB\BSON\ObjectId($_POST['itemId']);
    $userId = new MongoDB\BSON\ObjectId($_SESSION['user']->_id);
    
    // Tambahkan pengecekan user_id untuk keamanan
    $bulk = new MongoDB\Driver\BulkWrite;
    $bulk->delete([
        '_id' => $itemId,
        'user_id' => $userId // Pastikan item milik user yang sedang login
    ]);
    
    $result = $m->executeBulkWrite('marketplace.Keranjang', $bulk);
    
    if ($result->getDeletedCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Produk berhasil dihapus dari keranjang']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Item tidak ditemukan atau Anda tidak memiliki akses']);
    }
} catch (MongoDB\Driver\Exception\Exception $e) {
    error_log("MongoDB Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan pada database']);
} catch (Exception $e) {
    error_log("General Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan sistem']);
}
?> 