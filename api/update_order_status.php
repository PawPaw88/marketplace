<?php
session_start();
include('../config/db.php');

if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'Penjual') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (!isset($_POST['orderId']) || !isset($_POST['status'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

try {
    $m = connectDB();
    $orderId = new MongoDB\BSON\ObjectId($_POST['orderId']);
    $newStatus = $_POST['status'];
    
    $bulk = new MongoDB\Driver\BulkWrite;
    $bulk->update(
        ['_id' => $orderId],
        ['$set' => [
            'status' => $newStatus,
            'updated_at' => new MongoDB\BSON\UTCDateTime()
        ]]
    );
    
    $result = $m->executeBulkWrite('marketplace.Pesanan', $bulk);
    
    if ($result->getModifiedCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Status pesanan berhasil diperbarui']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal memperbarui status pesanan']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan: ' . $e->getMessage()]);
}
?>