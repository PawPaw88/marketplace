<?php
session_start();
include('../config/db.php');

header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'Sesi tidak valid']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Metode tidak valid']);
    exit();
}

$password = $_POST['password'] ?? '';

if (empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Password tidak boleh kosong']);
    exit();
}

try {
    $m = connectDB();
    $userId = new MongoDB\BSON\ObjectId($_SESSION['user']->_id);
    $collection = $_SESSION['role'];
    
    $filter = ['_id' => $userId];
    $query = new MongoDB\Driver\Query($filter);
    $cursor = $m->executeQuery("marketplace.$collection", $query);
    $user = current($cursor->toArray());

    if ($user && password_verify($password, $user->password)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Password salah']);
    }
} catch (Exception $e) {
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan sistem']);
}
?> 