<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'Penjual') {
    header('Location: ../../index.php');
    exit();
}

include '../../config/db.php';
$m = connectDB();
if (!$m) {
    die("Koneksi database gagal");
}

$user = $_SESSION['user'];

// Memastikan properti avatar selalu ada
if (!isset($user->avatar)) {
    $user->avatar = '1.jpg'; // Nilai default
}

$message = '';
$status = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $alamat = trim($_POST['alamat']);
    $no_telepon = trim($_POST['no_telepon']);
    $avatar = isset($_POST['avatar']) ? $_POST['avatar'] : $user->avatar;

    if (empty($username) || empty($alamat) || empty($no_telepon)) {
        $message = "Semua field harus diisi.";
        $status = "error";
    } elseif (!isset($user->_id) || !$user->_id instanceof MongoDB\BSON\ObjectId) {
        $message = "ID pengguna tidak valid.";
        $status = "error";
    } else {
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
            $message = "Tidak ada perubahan yang perlu disimpan.";
            $status = "info";
        } else {
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
                $result = $m->executeBulkWrite('marketplace.Penjual', $bulk);
                if ($result->getModifiedCount()) {
                    $message = "Profil berhasil diperbarui.";
                    $status = "success";
                    // Update session data
                    $_SESSION['user']->username = $username;
                    $_SESSION['user']->alamat = $alamat;
                    $_SESSION['user']->no_telepon = $no_telepon;
                    $_SESSION['user']->avatar = $avatar;
                    $user = $_SESSION['user']; // Update local $user variable
                } else {
                    $message = "Tidak ada perubahan yang disimpan.";
                    $status = "info";
                }
            } catch (MongoDB\Driver\Exception\BulkWriteException $e) {
                $message = "Gagal menyimpan perubahan: " . $e->getMessage();
                $status = "error";
            } catch (Exception $e) {
                $message = "Terjadi kesalahan: " . $e->getMessage();
                $status = "error";
            }
        }
    }
}

// Tambahkan perhitungan kelengkapan profil setelah $user = $_SESSION['user'];
$totalFields = 3; 
$completedFields = 0;
if (!empty($user->username) && $user->username !== 'user' . substr($user->username, -4)) $completedFields++;
if (!empty($user->alamat)) $completedFields++;
if (!empty($user->no_telepon)) $completedFields++;

$completionPercentage = ($completedFields / $totalFields) * 100;

// Tambahkan perhitungan rating setelah koneksi database
$rating = 0;
try {
    $filter = ['id_penjual' => $user->_id];
    $query = new MongoDB\Driver\Query($filter);
    $cursor = $m->executeQuery('marketplace.Ulasan', $query);
    $reviews = $cursor->toArray();
    
    if (count($reviews) > 0) {
        $totalRating = 0;
        foreach ($reviews as $review) {
            $totalRating += $review->rating;
        }
        $rating = number_format($totalRating / count($reviews), 1);
    }
} catch (Exception $e) {
    // Handle error jika diperlukan
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Profil Penjual - MyFurniture</title>
    <link rel="stylesheet" href="../../assets/style/seller.css" />
    <link rel="stylesheet" href="../../assets/style/sidebar.css" />
    <link rel="stylesheet" href="../../assets/style/profile_seller.css" />
    <script src="https://unpkg.com/feather-icons@4.29.0/dist/feather.min.js"></script>
</head>
<body>
    <div class="main-container">
        <nav class="sidebar">
            <div class="logo">MyFurniture.</div>
            <ul class="nav-links">
                <li><a href="seller.php"><i data-feather="grid"></i> <span>Dashboard</span></a></li>
                <li><a href="products.php"><i data-feather="shopping-bag"></i> <span>Produk</span></a></li>
                <li><a href="analytics.php"><i data-feather="pie-chart"></i> <span>Laporan</span></a></li>
                <li><a href="orders.php"><i data-feather="shopping-cart"></i> <span>Pesanan</span></a></li>
                <li><a href="profile.php" class="active"><i data-feather="user"></i> <span>Profil</span></a></li>
            </ul>
        </nav>

        <main class="content">
            <div class="header-content">
                <h2>Profil Saya</h2>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $status; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <div class="profile-container">
                <div class="profile-header">
                    <div class="profile-info">
                        <img src="../../assets/img/avatar/<?php echo htmlspecialchars($user->avatar); ?>" alt="Avatar" class="current-avatar">
                        <div class="profile-details">
                            <div class="profile-name"><?php echo htmlspecialchars($user->username); ?></div>
                            <div class="profile-status">
                                <i data-feather="star" class="star-icon"></i>
                                <?php echo $rating; ?> / 5.0
                            </div>
                        </div>
                    </div>
                    <button type="button" class="edit-button" onclick="toggleEditForm()">
                        <i data-feather="edit-2"></i> Edit Profil
                    </button>
                </div>

                <div class="profile-section">
                    <div class="section-title">Email</div>
                    <div class="section-content"><?php echo htmlspecialchars($user->email); ?></div>
                </div>

                <div class="profile-section">
                    <div class="section-title">Alamat</div>
                    <div class="section-content"><?php echo htmlspecialchars($user->alamat); ?></div>
                </div>

                <div class="profile-section">
                    <div class="section-title">Nomor Telepon</div>
                    <div class="section-content"><?php echo htmlspecialchars($user->no_telepon); ?></div>
                </div>
            </div>

            <form method="POST" action="" class="profile-form" id="editForm" style="display: none;">
                <div class="avatar-section">
                    <img src="../../assets/img/avatar/<?php echo htmlspecialchars($user->avatar); ?>" alt="Avatar" class="current-avatar">
                    <div class="avatar-options">
                        <?php for($i = 1; $i <= 11; $i++): ?>
                            <label class="avatar-option">
                                <input type="radio" name="avatar" value="<?php echo $i; ?>.jpg" <?php echo $user->avatar == $i.'.jpg' ? 'checked' : ''; ?>>
                                <img src="../../assets/img/avatar/<?php echo $i; ?>.jpg" alt="Avatar <?php echo $i; ?>">
                            </label>
                        <?php endfor; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label for="username">Nama Pengguna</label>
                    <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user->username); ?>" required>
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" value="<?php echo htmlspecialchars($user->email); ?>" readonly>
                </div>

                <div class="form-group">
                    <label for="alamat">Alamat</label>
                    <textarea id="alamat" name="alamat" required><?php echo htmlspecialchars($user->alamat); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="no_telepon">Nomor Telepon</label>
                    <input type="tel" id="no_telepon" name="no_telepon" value="<?php echo htmlspecialchars($user->no_telepon); ?>" required>
                </div>

                <div class="form-buttons">
                    <button type="submit" class="btn-update">Perbarui Profil</button>
                    <button type="button" class="btn-cancel" onclick="toggleEditForm()">Batal</button>
                </div>
            </form>
        </main>
    </div>

    <?php if ($completionPercentage < 100): ?>
        <div id="profilePopup" class="popup">
            <div class="popup-content">
                <h3>Perhatian!</h3>
                <p>Silakan lengkapi profil Anda terlebih dahulu sebelum mengakses fitur ini.</p>
                <button onclick="closePopup()" class="btn-close">Mengerti</button>
            </div>
        </div>
    <?php endif; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            feather.replace({ width: 20, height: 20 });

            <?php if ($completionPercentage < 100): ?>
                const sidebarLinks = document.querySelectorAll('.nav-links a:not([href="profile.php"]):not([href="seller.php"])');
                
                sidebarLinks.forEach(link => {
                    link.addEventListener('click', function(e) {
                        e.preventDefault();
                        document.getElementById('profilePopup').style.display = 'flex';
                    });
                    
                    link.style.opacity = '0.5';
                    link.style.cursor = 'not-allowed';
                });
            <?php endif; ?>
        });

        function toggleEditForm() {
            const profileContainer = document.querySelector('.profile-container');
            const editForm = document.getElementById('editForm');
            
            if (editForm.style.display === 'none') {
                profileContainer.style.display = 'none';
                editForm.style.display = 'block';
            } else {
                profileContainer.style.display = 'block';
                editForm.style.display = 'none';
            }
        }

        function closePopup() {
            document.getElementById('profilePopup').style.display = 'none';
        }
    </script>
</body>
</html>