<?php
session_start();
include('../config/db.php');

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'Tidak ada sesi user']);
    exit();
}

$m = connectDB();
$userId = new MongoDB\BSON\ObjectId($_SESSION['user']->_id);

// Data yang akan diupdate
$updateData = [
    'username' => $_POST['username'],
    'no_telepon' => $_POST['no_telepon'],
    'alamat' => $_POST['alamat']
];

// Jika password diisi, tambahkan ke update data
if (!empty($_POST['password'])) {
    $updateData['password'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
}

// Cek di kedua koleksi (Pembeli dan Penjual)
$collections = ['Pembeli', 'Penjual'];
$updated = false;

foreach ($collections as $collection) {
    $bulk = new MongoDB\Driver\BulkWrite;
    $bulk->update(
        ['_id' => $userId],
        ['$set' => $updateData]
    );

    try {
        $result = $m->executeBulkWrite("marketplace.$collection", $bulk);
        if ($result->getModifiedCount() > 0) {
            $updated = true;
            $_SESSION['role'] = $collection;
            
            // Update session data
            $_SESSION['user'] = (object) array_merge(
                (array) $_SESSION['user'],
                $updateData
            );
            
            break;
        }
    } catch (Exception $e) {
        continue;
    }
}

if ($updated) {
    echo json_encode(['success' => true, 'message' => 'Profil berhasil diperbarui']);
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal memperbarui profil']);
}
?> 