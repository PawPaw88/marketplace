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
    <title>Isi Saldo - MyFurniture</title>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/style/main.css">
    <link rel="stylesheet" href="../../assets/style/topup.css">
</head>
<body>
    <main>
        <div class="topup-container">
            <a href="methods.php" class="back-button">
                <i class="ri-arrow-left-line"></i>
                Kembali
            </a>
            <h1>Isi Saldo</h1>
            
            <div class="balance-card">
                <div class="balance-info">
                    <i class="ri-secure-payment-line"></i>
                    <div>
                        <span class="balance-label">Saldo MyFurniture Pay</span>
                        <span class="balance-amount">Rp <?php echo $currentBalance; ?></span>
                    </div>
                </div>
                <a href="claim.php" class="claim-balance-btn">
                    <i class="ri-gift-line"></i>
                    Klaim Saldo
                </a>
            </div>

            <div class="topup-section">
                <h2>Nominal Isi Saldo</h2>
                <div class="amount-options">
                    <button class="amount-btn" data-amount="10000">Rp 10.000</button>
                    <button class="amount-btn" data-amount="20000">Rp 20.000</button>
                    <button class="amount-btn" data-amount="50000">Rp 50.000</button>
                    <button class="amount-btn" data-amount="100000">Rp 100.000</button>
                    <button class="amount-btn" data-amount="200000">Rp 200.000</button>
                    <button class="amount-btn" data-amount="500000">Rp 500.000</button>
                </div>
                
                <div class="custom-amount">
                    <label for="customAmount">Atau masukkan nominal lain</label>
                    <div class="input-group">
                        <span class="currency">Rp</span>
                        <input type="number" id="customAmount" placeholder="0" min="10000" max="1000000">
                    </div>
                    <span class="min-amount">Minimal Rp 10.000</span>
                </div>

                <div class="payment-method-section">
                    <h2>Metode Pembayaran</h2>
                    <div class="payment-methods">
                        <div class="payment-method">
                            <i class="ri-bank-card-line"></i>
                            <span>Kartu Kredit/Debit</span>
                            <i class="ri-arrow-right-s-line"></i>
                        </div>
                        <div class="payment-method">
                            <i class="ri-wallet-line"></i>
                            <span>Dompet Digital</span>
                            <i class="ri-arrow-right-s-line"></i>
                        </div>
                        <div class="payment-method">
                            <i class="ri-bank-line"></i>
                            <span>Transfer Bank</span>
                            <i class="ri-arrow-right-s-line"></i>
                        </div>
                    </div>
                </div>

                <button class="topup-submit" disabled>Isi Saldo</button>
            </div>
        </div>
    </main>

    <div class="coming-soon-popup">Mohon maaf, fitur ini masih dalam tahap pengembangan. Kamu bisa klaim saldo dengan cara klik bagian klaim saldo 😁</div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const amountBtns = document.querySelectorAll('.amount-btn');
            const customInput = document.getElementById('customAmount');
            const submitBtn = document.querySelector('.topup-submit');
            const paymentMethods = document.querySelectorAll('.payment-method');
            const popup = document.querySelector('.coming-soon-popup');

            let selectedAmount = 0;

            function updateSubmitButton() {
                submitBtn.disabled = selectedAmount < 10000;
            }

            amountBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    amountBtns.forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    selectedAmount = parseInt(this.dataset.amount);
                    customInput.value = '';
                    updateSubmitButton();
                });
            });

            customInput.addEventListener('input', function() {
                amountBtns.forEach(btn => btn.classList.remove('active'));
                selectedAmount = parseInt(this.value) || 0;
                updateSubmitButton();
            });

            paymentMethods.forEach(method => {
                method.addEventListener('click', function() {
                    popup.classList.add('show');
                    setTimeout(() => {
                        popup.classList.remove('show');
                    }, 6000);
                });
            });

            submitBtn.addEventListener('click', function() {
                popup.classList.add('show');
                setTimeout(() => {
                    popup.classList.remove('show');
                }, 6000);
            });
        });
    </script>
</body>
</html> 