<?php
session_start();
include('config/db.php');

if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit();
}

$m = connectDB();
$userId = new MongoDB\BSON\ObjectId($_SESSION['user']->_id);

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
    // Jika user tidak ditemukan, redirect ke halaman login
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
    <link rel="stylesheet" href="assets/style/profile.css">
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
        <a href="#" class="back-button" id="backButton">
            <i class="ri-arrow-left-line"></i>
            Kembali
        </a>
            
            <!-- Tampilan profil default -->
            <div class="profile-details" id="profileView">
                <div class="profile-header">
                    <img src="assets/img/avatar/<?php echo htmlspecialchars($user->avatar ?? '1.jpg'); ?>" 
                         alt="Profile" class="profile-avatar">
                    <div class="user-info">
                        <h1><?php echo htmlspecialchars($user->username); ?></h1>
                        <p class="user-email"><?php echo htmlspecialchars($user->email); ?></p>
                    </div>
                    <button type="button" class="edit-icon" onclick="toggleEdit()">
                        <i class="ri-edit-line"></i>
                    </button>
                </div>

                <div class="detail-group">
                    <label>Username</label>
                    <p><?php echo htmlspecialchars($user->username); ?></p>
                </div>
                <div class="detail-group">
                    <label>Email</label>
                    <p><?php echo htmlspecialchars($user->email); ?></p>
                </div>
                <div class="detail-group">
                    <label>Nomor HP</label>
                    <p><?php echo !empty($user->no_telepon) ? htmlspecialchars($user->no_telepon) : '-'; ?></p>
                </div>
                <div class="detail-group">
                    <label>Alamat</label>
                    <p><?php echo !empty($user->alamat) ? htmlspecialchars($user->alamat) : '-'; ?></p>
                </div>
            </div>

            <!-- Form edit profil (hidden by default) -->
            <div class="profile-form" id="profileForm" style="display: none;">
                <form method="POST" id="editProfileForm">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" 
                               value="<?php echo htmlspecialchars($user->username); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" 
                               value="<?php echo htmlspecialchars($user->email); ?>" readonly class="readonly-input">
                    </div>

                    <div class="form-group">
                        <label for="password">Password Baru (kosongkan jika tidak ingin mengubah)</label>
                        <input type="password" id="password" name="password">
                    </div>

                    <div class="form-group">
                        <label for="no_telepon">Nomor HP</label>
                        <input type="tel" id="no_telepon" name="no_telepon" 
                               value="<?php echo htmlspecialchars($user->no_telepon ?? ''); ?>" ">
                    </div>

                    <div class="form-group">
                        <label for="alamat">Alamat</label>
                        <textarea id="alamat" name="alamat" rows="3"><?php echo htmlspecialchars($user->alamat ?? ''); ?></textarea>
                    </div>

                    <div class="button-group">
                        <button type="button" class="cancel-btn" onclick="toggleEdit()">Batal</button>
                        <button type="submit" class="save-btn">Simpan Perubahan</button>
                    </div>
                </form>
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

        document.addEventListener("DOMContentLoaded", function() {
            const backButton = document.getElementById('backButton');
            if (backButton) {
                backButton.addEventListener('click', function(e) {
                    e.preventDefault(); 
                    window.history.back(); 
                });
            }
        });
    </script>
</body>
</html> 