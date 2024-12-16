<?php
session_start();
include('../../config/db.php');

if (!isset($_SESSION['user'])) {
    header('Location: ../../views/auth/login.php');
    exit();
}

$userId = new MongoDB\BSON\ObjectId($_SESSION['user']->_id);
$m = connectDB();

// Ambil data saldo
$filter = ['user_id' => $userId];
$query = new MongoDB\Driver\Query($filter);
$cursor = $m->executeQuery('marketplace.Saldo', $query);
$saldo = current($cursor->toArray());

$currentBalance = isset($saldo->balance) ? number_format($saldo->balance, 0, ',', '.') : 0;

$isLoggedIn = isset($_SESSION['user']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Metode Pembayaran - MyFurniture</title>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/style/main.css">
    <link rel="stylesheet" href="../../assets/style/methods.css">
</head>
<body>
    <main>
        <div class="payment-methods-container">
        <a href="../../index.php" class="back-button" id="backButton">
            <i class="ri-arrow-left-line"></i>
            Kembali
        </a>
            <h1 class="payment-title">Metode Pembayaran</h1>
            <div class="payment-methods">
                <div class="payment-method default">
                    <div class="payment-method-info">
                        <i class="ri-secure-payment-line"></i>
                        <div class="payment-details">
                            <span>MyFurniture Pay</span>
                            <span class="balance">Saldo: Rp <?php echo $currentBalance; ?></span>
                        </div>
                    </div>
                    <a href="../../views/payment/topup.php" class="topup-button">Isi Saldo</a>
                </div>
                <div class="payment-method">
                    <i class="ri-bank-card-line"></i>
                    <span>Kartu Kredit/Debit</span>
                </div>
                <div class="payment-method">
                    <i class="ri-wallet-line"></i>
                    <span>Dompet Digital</span>
                </div>
                <div class="payment-method">
                    <i class="ri-bank-line"></i>
                    <span>Transfer Bank</span>
                </div>
                <div class="payment-method">
                    <i class="ri-cash-line"></i>
                    <span>Bayar di Tempat</span>
                </div>
            </div>
        </div>
    </main>

    <div class="coming-soon-popup">Mohon maaf, fitur ini masih dalam tahap pengembangan. Silakan gunakan MyFurniture Pay untuk melakukan pembayaran 😁</div>

    <script src="../../assets/js/main.js" defer></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const paymentMethods = document.querySelectorAll('.payment-method:not(.default)');
            const popup = document.querySelector('.coming-soon-popup');

            paymentMethods.forEach(method => {
                method.addEventListener('click', function() {
                    popup.classList.add('show');
                    setTimeout(() => {
                        popup.classList.remove('show');
                    }, 6000);
                });
            });
        });
    </script>
</body>
</html> 