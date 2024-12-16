<?php
session_start();
include('config/db.php');

if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit();
}

$m = connectDB();
$userId = new MongoDB\BSON\ObjectId($_SESSION['user']->_id);

// Tambahkan query untuk mengambil saldo
$filter = ['user_id' => $userId];
$query = new MongoDB\Driver\Query($filter);
$cursor = $m->executeQuery('marketplace.Saldo', $query);
$saldo = current($cursor->toArray());

$currentBalance = isset($saldo->balance) ? number_format($saldo->balance, 0, ',', '.') : 0;

// Cek di kedua koleksi (Pembeli dan Penjual)
$user = null;
$collections = ['Pembeli', 'Penjual'];

foreach ($collections as $collection) {
    $filter = ['_id' => $userId];
    $query = new MongoDB\Driver\Query($filter);
    $cursor = $m->executeQuery("marketplace.$collection", $query);
    $result = current($cursor->toArray());
    
    if ($result) {
        $user = $result;
        break;
    }
}

if (!$user) {
    session_destroy();
    header('Location: views/auth/login.php');
    exit();
}

$isLoggedIn = isset($_SESSION['user']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Saya - MyFurniture</title>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/style/main.css">
    <link rel="stylesheet" href="assets/style/user.css">
</head>
<body>
    <div id="popup" class="popup" style="display: none;">
        <div class="popup-content">
            <p id="popup-message"></p>
            <button onclick="closePopup()">OK</button>
        </div>
    </div>

    <?php if ($isLoggedIn): ?>
        <nav class="mobile-nav">
            <a href="index.php" class="mobile-nav-item"><i class="ri-home-line"></i></a>
            <a href="order.php" class="mobile-nav-item"><i class="ri-file-list-3-line"></i></a>
            <a href="wishlist.php" class="mobile-nav-item"><i class="ri-heart-line"></i></a>
            <a href="user.php" class="mobile-nav-item active"><i class="ri-user-line"></i></a>
        </nav>
    <?php endif; ?>

    <main>
        <div class="profile-container">
            <a href="index.php" class="back-button">
                <i class="ri-arrow-left-line"></i>
                Kembali
            </a>
            
            <h1 class="profile-title">Informasi Akun</h1>

            <div class="profile-details" id="profileView">
                <div class="profile-header">
                    <img src="assets/img/avatar/<?php echo htmlspecialchars($user->avatar ?? '1.jpg'); ?>" 
                         alt="Profile" class="profile-avatar">
                    <div class="user-info">
                        <h1><?php echo htmlspecialchars($user->username); ?></h1>
                        <p class="user-email"><?php echo htmlspecialchars($user->email); ?></p>
                    </div>
                </div>
                
                <div class="myfurniture-pay">
                    <div class="pay-info">
                        <i class="ri-secure-payment-line"></i>
                        <div class="pay-details">
                            <span>MyFurniture Pay</span>
                            <span class="balance">Rp <?php echo $currentBalance; ?></span>
                        </div>
                    </div>
                </div>

                <div class="profile-actions">
                    <a href="profile.php" class="profile-action-btn">
                        <i class="ri-user-line"></i>
                        Lihat Profil
                    </a>
                    <a href="views/cart/payment.php" class="profile-action-btn">
                        <i class="ri-shopping-cart-line"></i>
                        Lihat Keranjang
                    </a>
                    <a href="views/payment/methods.php" class="profile-action-btn">
                        <i class="ri-bank-card-line"></i>
                        Metode Pembayaran
                    </a>
                    <a href="views/auth/logout.php" class="profile-action-btn logout">
                        <i class="ri-logout-box-line"></i>
                        Logout
                    </a>
                </div>
            </div>
        </div>
    </main>

    <script>
        function toggleEdit() {
            const profileView = document.getElementById('profileView');
            const profileForm = document.getElementById('profileForm');
            
            if (profileView.style.display === 'none') {
                profileView.style.display = 'block';
                profileForm.style.display = 'none';
            } else {
                profileView.style.display = 'none';
                profileForm.style.display = 'block';
            }
        }

        function showPopup(message, isSuccess = true) {
            const popup = document.getElementById('popup');
            const popupMessage = document.getElementById('popup-message');
            popup.style.display = 'flex';
            popup.className = `popup ${isSuccess ? 'success' : 'error'}`;
            popupMessage.textContent = message;
        }

        function closePopup() {
            document.getElementById('popup').style.display = 'none';
        }

        // Update event listener form submission
        document.getElementById('editProfileForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            try {
                const response = await fetch('api/update_profile.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showPopup('Profil berhasil diperbarui', true);
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    showPopup(data.message || 'Gagal memperbarui profil', false);
                }
            } catch (error) {
                console.error('Error:', error);
                showPopup('Terjadi kesalahan saat memperbarui profil', false);
            }
        });
    </script>
</body>
</html> 