<?php
session_start();
include('../../config/db.php');

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu']);
    exit();
}

try {
    $m = connectDB();
    
    $userId = new MongoDB\BSON\ObjectId($_SESSION['user']->_id);
    
    // Cek apakah user sudah memiliki saldo
    $filter = ['user_id' => $userId];
    $query = new MongoDB\Driver\Query($filter);
    $cursor = $m->executeQuery('marketplace.Saldo', $query);
    $existingSaldo = current($cursor->toArray());
    
    $bulk = new MongoDB\Driver\BulkWrite;
    
    if ($existingSaldo) {
        // Update saldo yang sudah ada
        $newBalance = $existingSaldo->balance + 5000000;
        $bulk->update(
            ['_id' => $existingSaldo->_id],
            ['$set' => [
                'balance' => $newBalance,
                'last_claimed' => new MongoDB\BSON\UTCDateTime()
            ]]
        );
    } else {
        // Buat saldo baru
        $saldoData = [
            'user_id' => $userId,
            'balance' => 5000000,
            'created_at' => new MongoDB\BSON\UTCDateTime(),
            'last_claimed' => new MongoDB\BSON\UTCDateTime()
        ];
        $bulk->insert($saldoData);
    }
    
    $result = $m->executeBulkWrite('marketplace.Saldo', $bulk);
    
    if ($result->getModifiedCount() > 0 || $result->getInsertedCount() > 0) {
        // Tambahkan riwayat klaim
        $claimHistory = [
            'user_id' => $userId,
            'amount' => 5000000,
            'claimed_at' => new MongoDB\BSON\UTCDateTime()
        ];
        
        $bulk = new MongoDB\Driver\BulkWrite;
        $bulk->insert($claimHistory);
        $m->executeBulkWrite('marketplace.ClaimHistory', $bulk);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Selamat! Anda berhasil mengklaim Rp 5.000.000'
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Gagal mengklaim saldo'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Terjadi kesalahan: ' . $e->getMessage()
    ]);
}
?>