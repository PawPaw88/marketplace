<?php
session_start();
include('../../config/db.php');

$isLoggedIn = isset($_SESSION['user']);
if (!$isLoggedIn) {
    header('Location: ../../views/auth/login.php?form=login');
    exit();
}

$m = connectDB();
$userId = new MongoDB\BSON\ObjectId($_SESSION['user']->_id);

try {
    $cartPipeline = [
        ['$match' => ['user_id' => $userId]],
        ['$lookup' => [
            'from' => 'Produk',
            'localField' => 'product_id',
            'foreignField' => '_id',
            'as' => 'product'
        ]],
        ['$unwind' => '$product'],
        ['$lookup' => [
            'from' => 'Penjual',
            'localField' => 'product.penjual_id',
            'foreignField' => '_id',
            'as' => 'seller'
        ]],
        ['$unwind' => '$seller']
    ];

    $command = new MongoDB\Driver\Command([
        'aggregate' => 'Keranjang',
        'pipeline' => $cartPipeline,
        'cursor' => new stdClass,
    ]);

    $cursor = $m->executeCommand('marketplace', $command);
    $cartItems = $cursor->toArray();
} catch (Exception $e) {
    error_log("Error fetching cart items: " . $e->getMessage());
    $cartItems = [];
}

$totalHarga = 0;
foreach ($cartItems as $item) {
    $subtotal = $item->product->harga * $item->quantity;
    $totalHarga += $subtotal;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proses Pembayaran</title>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/style/main.css">
    <link rel="stylesheet" href="../../assets/style/payment.css">
</head>
<body>

    <div class="payment-container">
    <a href="../../index.php" class="back-button" id="backButton">
            <i class="ri-arrow-left-line"></i>
            Kembali
        </a>
            <h1>Total Keranjang</h1>
        <?php if (empty($cartItems)): ?>
            <p>Keranjang belanja Anda kosong.</p>
        <?php else: ?>
            <div class="cart-items">
                <?php foreach ($cartItems as $item): ?>
                    <div class="cart-item">
                        <img src="../../assets/img/products/<?php echo htmlspecialchars($item->product->gambar[0]); ?>" 
                             alt="<?php echo htmlspecialchars($item->product->nama_produk); ?>">
                        
                        <div class="cart-item-details">
                            <h3><?php echo htmlspecialchars($item->product->nama_produk); ?></h3>
                            <p class="seller-name">Penjual: <?php echo htmlspecialchars($item->seller->username); ?></p>
                        </div>

                        <div class="quantity-controls">
                            <button class="quantity-btn" onclick="updateQuantity('<?php echo $item->_id; ?>', 'decrease')">-</button>
                            <input type="number" 
                                   class="quantity-input" 
                                   value="<?php echo $item->quantity; ?>" 
                                   min="1"
                                   max="<?php echo $item->product->stok; ?>"
                                   onchange="updateQuantity('<?php echo $item->_id; ?>', 'set', this.value)"
                                   data-product-id="<?php echo $item->_id; ?>"
                                   data-max-stock="<?php echo $item->product->stok; ?>">
                            <button class="quantity-btn" onclick="updateQuantity('<?php echo $item->_id; ?>', 'increase')">+</button>
                        </div>

                        <div class="cart-item-price">
                            Rp <?php echo number_format($item->product->harga * $item->quantity, 0, ',', '.'); ?>
                        </div>

                        <button class="delete-cart-item" onclick="deleteCartItem('<?php echo $item->_id; ?>')">
                            <i class="ri-delete-bin-line"></i>
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="total-price">
                <h2>
                    <span>Total Harga :</span>
                    <span>Rp <?php echo number_format($totalHarga, 0, ',', '.'); ?></span>
                </h2>
            </div>

            <!-- Tambahkan bagian metode pembayaran -->
            <div class="payment-methods-section">
                <h2>Pilih Metode Pembayaran</h2>
                <div class="payment-methods">
                    <div class="payment-method default">
                        <div class="payment-method-info">
                            <i class="ri-secure-payment-line"></i>
                            <div class="payment-details">
                                <span>MyFurniture Pay</span>
                                <span class="balance">Saldo: Rp <?php 
                                    $filter = ['user_id' => $userId];
                                    $query = new MongoDB\Driver\Query($filter);
                                    $cursor = $m->executeQuery('marketplace.Saldo', $query);
                                    $saldo = current($cursor->toArray());
                                    echo number_format(isset($saldo->balance) ? $saldo->balance : 0, 0, ',', '.'); 
                                ?></span>
                            </div>
                        </div>
                        <a href="../payment/topup.php" class="topup-button">Isi Saldo</a>
                    </div>
                    <div class="payment-method disabled">
                        <i class="ri-bank-card-line"></i>
                        <span>Kartu Kredit/Debit</span>
                    </div>
                    <div class="payment-method disabled">
                        <i class="ri-wallet-line"></i>
                        <span>Dompet Digital</span>
                    </div>
                </div>
            </div>

            <!-- Sebelum form pembayaran, tambahkan div untuk popup password -->
            <div class="password-confirmation-popup" id="passwordConfirmation" style="display: none;">
                <div class="confirmation-content">
                    <h3>Konfirmasi Password</h3>
                    <p>Masukkan password Anda untuk melanjutkan pembayaran</p>
                    <div class="password-input-group">
                        <input type="password" id="confirmPassword" placeholder="Masukkan password">
                        <span id="passwordError" class="error-message"></span>
                    </div>
                    <div class="confirmation-buttons">
                        <button class="cancel-btn" onclick="closePasswordConfirmation()">Batal</button>
                        <button class="confirm-btn" onclick="validatePassword()">Konfirmasi</button>
                    </div>
                </div>
            </div>

            <!-- Ubah form pembayaran -->
            <form id="paymentForm" onsubmit="return false;">
                <button type="button" onclick="showPasswordConfirmation()" class="confirm-payment-btn">Bayar</button>
            </form>
        <?php endif; ?>
    </div>

    <!-- Tambahkan popup notifikasi metode pembayaran -->
    <div class="payment-method-popup">Mohon maaf, metode pembayaran ini belum tersedia. Silakan gunakan MyFurniture Pay 😊</div>

    <!-- Ubah popup konfirmasi -->
    <div class="confirmation-popup" id="deleteConfirmation" style="display: none;">
        <div class="confirmation-content">
            <h3>Konfirmasi Hapus</h3>
            <p>Apakah Anda yakin ingin menghapus item ini dari keranjang?</p>
            <div class="confirmation-buttons">
                <button class="cancel-btn" onclick="closeDeleteConfirmation()">Batal</button>
                <button class="confirm-btn" onclick="confirmDelete()">Hapus</button>
            </div>
        </div>
    </div>

    <!-- Tambahkan div untuk notifikasi popup -->
    <div class="notification-popup" id="notificationPopup">
        <i class="ri-checkbox-circle-line"></i>
        <span id="notificationMessage"></span>
    </div>

    <!-- Tambahkan setelah notification-popup div -->
    <div class="success-animation-popup" id="successAnimationPopup">
        <div class="success-animation-content">
            <div class="checkmark-circle">
                <svg class="checkmark-circle" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
                    <circle class="checkmark-circle-bg" cx="26" cy="26" r="25" fill="none"/>
                    <path class="checkmark" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8"/>
                </svg>
            </div>
            <h2>Pembayaran Berhasil!</h2>
            <p>Terima kasih atas pembelian Anda</p>
            <button class="continue-btn" onclick="window.location.href='../../index.php'">Lanjutkan Belanja</button>
        </div>
    </div>

    <!-- Tambahkan elemen audio untuk efek suara -->
    <audio id="successSound" preload="auto">
        <source src="../../assets/sounds/success.mp3" type="audio/mpeg">
        <source src="../../assets/sounds/success.wav" type="audio/wav">
        <source src="../../assets/sounds/success.ogg" type="audio/ogg">
    </audio>

    <script>
    let currentCartId = null;

    function showNotification(message, type = "success") {
        const popup = document.getElementById('notificationPopup');
        const messageEl = document.getElementById('notificationMessage');
        
        popup.className = `notification-popup ${type}`;
        messageEl.textContent = message;
        
        popup.classList.add('show');
        
        setTimeout(() => {
            popup.classList.remove('show');
        }, 3000);
    }

    function deleteCartItem(cartId) {
        currentCartId = cartId;
        const popup = document.getElementById('deleteConfirmation');
        popup.style.display = 'flex';
        popup.classList.add('show');
    }

    function closeDeleteConfirmation() {
        const popup = document.getElementById('deleteConfirmation');
        popup.classList.remove('show');
        setTimeout(() => {
            popup.style.display = 'none';
        }, 300);
        currentCartId = null;
    }

    function confirmDelete() {
        if (currentCartId) {
            const formData = new FormData();
            formData.append('itemId', currentCartId);

            fetch('../../api/delete_cart_item.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    showNotification(data.message || 'Gagal menghapus item dari keranjang', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Terjadi kesalahan saat menghapus item', 'error');
            });
        }
        closeDeleteConfirmation();
    }

    document.getElementById('deleteConfirmation').addEventListener('click', function(e) {
        if (e.target === this) {
            closeDeleteConfirmation();
        }
    });

    function updateQuantity(cartId, action, value = null) {
        let input = document.querySelector(`input[data-product-id="${cartId}"]`);
        let currentQty = parseInt(input.value);
        let maxStock = parseInt(input.dataset.maxStock);
        let newQty;

        switch(action) {
            case 'increase':
                newQty = Math.min(maxStock, currentQty + 1);
                break;
            case 'decrease':
                newQty = Math.max(1, currentQty - 1);
                break;
            case 'set':
                newQty = Math.max(1, Math.min(maxStock, parseInt(value) || 1));
                break;
        }

        if (newQty === currentQty) {
            if (newQty === maxStock) {
                showNotification('Jumlah melebihi stok yang tersedia', 'error');
            }
            return;
        }

        if (isNaN(newQty) || newQty < 1) {
            showNotification('Jumlah tidak valid', 'error');
            input.value = currentQty;
            return;
        }

        const formData = new FormData();
        formData.append('itemId', cartId);
        formData.append('quantity', newQty);
        formData.append('action', action);

        fetch('../../api/update_cart_quantity.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                input.value = newQty;
                showNotification(data.message, 'success');
                setTimeout(() => {
                    location.reload();
                }, 1000);
            } else {
                showNotification(data.message || 'Gagal mengupdate kuantitas', 'error');
                input.value = currentQty;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Terjadi kesalahan saat mengupdate kuantitas', 'error');
            input.value = currentQty;
        });
    }

    // Tambahkan script untuk metode pembayaran
    document.addEventListener('DOMContentLoaded', function() {
        const disabledMethods = document.querySelectorAll('.payment-method.disabled');
        const popup = document.querySelector('.payment-method-popup');

        disabledMethods.forEach(method => {
            method.addEventListener('click', function() {
                popup.classList.add('show');
                setTimeout(() => {
                    popup.classList.remove('show');
                }, 3000);
            });
        });
    });

    function showPasswordConfirmation() {
        const popup = document.getElementById('passwordConfirmation');
        popup.style.display = 'flex';
        popup.classList.add('show');
        document.getElementById('confirmPassword').value = '';
        document.getElementById('passwordError').textContent = '';
    }

    function closePasswordConfirmation() {
        const popup = document.getElementById('passwordConfirmation');
        popup.classList.remove('show');
        setTimeout(() => {
            popup.style.display = 'none';
        }, 300);
    }

    function validatePassword() {
        const password = document.getElementById('confirmPassword').value;
        const errorElement = document.getElementById('passwordError');
        
        if (!password) {
            errorElement.textContent = 'Password tidak boleh kosong';
            return;
        }

        const formData = new FormData();
        formData.append('password', password);

        fetch('../../api/validate_password.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Password valid, lanjutkan ke proses pembayaran
                closePasswordConfirmation();
                processPurchase();
            } else {
                errorElement.textContent = data.message || 'Password salah';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            errorElement.textContent = 'Terjadi kesalahan, silakan coba lagi';
        });
    }

    function processPurchase() {
        fetch('../../api/process_payment.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const successPopup = document.getElementById('successAnimationPopup');
                const successSound = document.getElementById('successSound');
                
                successPopup.style.display = 'flex';
                setTimeout(() => {
                    successPopup.classList.add('show');
                    // Mainkan suara dengan penanganan error
                    try {
                        const playPromise = successSound.play();
                        if (playPromise !== undefined) {
                            playPromise.catch(error => {
                                console.error('Error playing sound:', error);
                            });
                        }
                    } catch (error) {
                        console.error('Error playing sound:', error);
                    }
                }, 100);
            } else {
                showNotification(data.message || 'Gagal memproses pembayaran', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Terjadi kesalahan saat memproses pembayaran', 'error');
        })
        .finally(() => {
            closePasswordConfirmation();
        });
    }

    document.getElementById('passwordConfirmation').addEventListener('click', function(e) {
        if (e.target === this) {
            closePasswordConfirmation();
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        // Inisialisasi dan load suara dengan penanganan error
        const successSound = document.getElementById('successSound');
        
        // Tambahkan event listener untuk error
        successSound.addEventListener('error', function(e) {
            console.error('Error loading sound:', e);
        });

        // Coba load suara saat interaksi pertama
        document.body.addEventListener('click', function() {
            try {
                successSound.load();
            } catch (error) {
                console.error('Error loading sound:', error);
            }
        }, { once: true });
    });
    </script>
</body>
</html> 