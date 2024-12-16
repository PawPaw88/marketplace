<?php
session_start();
include('../../config/db.php');

if (!isset($_SESSION['user'])) {
    header('Location: ../../views/auth/login.php');
    exit();
}

$userId = new MongoDB\BSON\ObjectId($_SESSION['user']->_id);

$m = connectDB();

// Cek saldo user saat ini
$filter = ['user_id' => $userId];
$query = new MongoDB\Driver\Query($filter);
$cursor = $m->executeQuery('marketplace.Saldo', $query);
$saldo = current($cursor->toArray());

$currentBalance = isset($saldo->balance) ? $saldo->balance : 0;

$canClaim = true;
$remainingTime = 0;

if ($saldo && isset($saldo->last_claimed)) {
    $lastClaimTime = $saldo->last_claimed->toDateTime();
    $now = new DateTime();
    $diff = $now->getTimestamp() - $lastClaimTime->getTimestamp();
    
    if ($diff < 60) { 
        $canClaim = false;
        $remainingTime = 60 - $diff;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Klaim Saldo - MyFurniture</title>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/style/main.css">
    <link rel="stylesheet" href="../../assets/style/claim.css">
</head>
<body>
    <main>
        <div class="claim-container">
            <a href="topup.php" class="back-button">
                <i class="ri-arrow-left-line"></i>
                Kembali
            </a>
            
            <div class="claim-card">
                <i class="ri-gift-2-line gift-icon"></i>
                <h1>Klaim Saldo Gratis!</h1>
                <p class="claim-description">
                    Selamat datang di MyFurniture! Klaim saldo gratis Rp 5.000.000 untuk memulai berbelanja.
                </p>
                
                <?php if ($canClaim): ?>
                    <button id="claimButton" class="claim-button">
                        <i class="ri-gift-line"></i>
                        Klaim Sekarang
                    </button>
                <?php else: ?>
                    <div class="countdown-timer">
                        Tunggu <span id="countdown"></span> untuk klaim berikutnya
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <div class="success-popup">Selamat! Kamu berhasil mengklaim saldo Rp 5.000.000 🎉</div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const claimButton = document.getElementById('claimButton');
            const popup = document.querySelector('.success-popup');
            const countdown = document.getElementById('countdown');
            
            <?php if (!$canClaim): ?>
            let remainingSeconds = <?php echo $remainingTime; ?>;
            
            function updateCountdown() {
                const hours = Math.floor(remainingSeconds / 3600);
                const minutes = Math.floor((remainingSeconds % 3600) / 60);
                const seconds = remainingSeconds % 60;
                
                countdown.textContent = `${hours}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
                
                if (remainingSeconds > 0) {
                    remainingSeconds--;
                    setTimeout(updateCountdown, 1000);
                } else {
                    window.location.reload();
                }
            }
            
            updateCountdown();
            <?php endif; ?>

            if (claimButton) {
                claimButton.addEventListener('click', function() {
                    fetch('../../controllers/payment/claim_balance.php', {
                        method: 'POST'
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            popup.classList.add('show');
                            setTimeout(() => {
                                popup.classList.remove('show');
                                window.location.href = 'topup.php';
                            }, 3000);
                        }
                    });
                });
            }
        });
    </script>
</body>
</html>