<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'Penjual') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

include '../../config/db.php';
$m = connectDB();
if (!$m) {
    echo json_encode(['status' => 'error', 'message' => 'Koneksi database gagal']);
    exit();
}

$user = $_SESSION['user'];

$response = ['status' => 'error', 'message' => 'Unknown error occurred'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $alamat = trim($_POST['alamat']);
    $no_telepon = trim($_POST['no_telepon']);
    $avatar = isset($_POST['avatar']) ? $_POST['avatar'] : '1.jpg';

    if (empty($username) || empty($alamat) || empty($no_telepon)) {
        echo json_encode(['status' => 'error', 'message' => 'Semua field harus diisi']);
        exit();
    }

    if (!isset($user->_id) || !$user->_id instanceof MongoDB\BSON\ObjectId) {
        echo json_encode(['status' => 'error', 'message' => 'ID pengguna tidak valid']);
        exit();
    }

    $changes = array_diff_assoc([
        'username' => $username,
        'alamat' => $alamat,
        'no_telepon' => $no_telepon,
        'avatar' => $avatar
    ], [
        'username' => $user->username,
        'alamat' => $user->alamat,
        'no_telepon' => $user->no_telepon,
        'avatar' => $user->avatar
    ]);

    if (empty($changes)) {
        echo json_encode(['status' => 'info', 'message' => 'Tidak ada perubahan yang perlu disimpan']);
        exit();
    }

    $bulk = new MongoDB\Driver\BulkWrite;
    $bulk->update(
        ['_id' => new MongoDB\BSON\ObjectId($user->_id)],
        ['$set' => [
            'username' => $username,
            'alamat' => $alamat,
            'no_telepon' => $no_telepon,
            'avatar' => $avatar
        ]]
    );
    
    try {
        $result = $m->executeBulkWrite("marketplace.Penjual", $bulk);

        if ($result->getModifiedCount() > 0) {
            $query = new MongoDB\Driver\Query(['_id' => new MongoDB\BSON\ObjectId($user->_id)]);
            $cursor = $m->executeQuery("marketplace.Penjual", $query);
            $updatedUser = current($cursor->toArray());
            $_SESSION['user'] = $updatedUser;

            $response = [
                'status' => 'success',
                'message' => 'Profil berhasil diperbarui!',
                'user' => [
                    'username' => $updatedUser->username,
                    'email' => $updatedUser->email,
                    'alamat' => $updatedUser->alamat,
                    'no_telepon' => $updatedUser->no_telepon,
                    'avatar' => $updatedUser->avatar
                ]
            ];
        } else {
            $response = ['status' => 'error', 'message' => 'Tidak ada perubahan yang disimpan'];
        }
    } catch (MongoDB\Driver\Exception\BulkWriteException $e) {
        $response = ['status' => 'error', 'message' => 'Gagal menyimpan perubahan: ' . $e->getMessage()];
    } catch (Exception $e) {
        $response = ['status' => 'error', 'message' => 'Terjadi kesalahan: ' . $e->getMessage()];
    }
}

echo json_encode($response);