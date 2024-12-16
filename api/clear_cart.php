<?php
session_start();
include('../config/db.php');

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $m = connectDB();
    $userId = new MongoDB\BSON\ObjectId($_SESSION['user']->_id);
    
    $bulk = new MongoDB\Driver\BulkWrite;
    $bulk->delete(['user_id' => $userId]);
    
    $result = $m->executeBulkWrite('marketplace.Keranjang', $bulk);
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?> 