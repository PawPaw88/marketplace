<?php
include('../../config/db.php');
session_start();

if (isset($_SESSION['user']) && isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'Pembeli') {
        header("Location: ../../index.php");
    } else {
        header("Location: ../dashboard/seller.php");
    }
    exit();
}

$showRegisterForm = isset($_GET['form']) && $_GET['form'] === 'register';

$message = '';
$status = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action']) && $_POST['action'] == 'register') {
        $email = $_POST['email'];
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $role = $_POST['role'];

        if ($password !== $confirm_password) {
            $message = "Password dan konfirmasi password tidak cocok!";
            $status = 'error';
        } else {
            $m = connectDB();
            $collections = ['Penjual', 'Pembeli'];
            $email_exists = false;
            foreach ($collections as $collection) {
                $query = new MongoDB\Driver\Query(['email' => $email]);
                $cursor = $m->executeQuery("marketplace.$collection", $query);
                if ($cursor->isDead() == false) {
                    foreach ($cursor as $user) {
                        $email_exists = true;
                        break 2;
                    }
                }
            }

            if ($email_exists) {
                $message = "Email sudah terdaftar!";
                $status = 'error';
            } else {
                $collection = $role === 'penjual' ? 'Penjual' : 'Pembeli';
                $default_username = 'user' . rand(1000, 9999);
                $bulk = new MongoDB\Driver\BulkWrite;
                $bulk->insert([
                    'email' => $email,
                    'password' => password_hash($password, PASSWORD_DEFAULT),
                    'role' => $role,
                    'username' => $default_username,
                    'alamat' => '',
                    'no_telepon' => ''
                ]);
                
                $m->executeBulkWrite("marketplace.$collection", $bulk);
                $message = "Registrasi berhasil! Silakan login.";
                $status = 'success';
            }
        }
    } else {
        $email = $_POST['email'];
        $password = $_POST['password'];
        
        $m = connectDB();
        $collections = ['Penjual', 'Pembeli'];
        $login_success = false;

        foreach ($collections as $collection) {
            $query = new MongoDB\Driver\Query(['email' => $email]);
            $cursor = $m->executeQuery("marketplace.$collection", $query);
            
            foreach ($cursor as $user) {
                if (password_verify($password, $user->password)) {
                    $_SESSION['user'] = $user;
                    $_SESSION['role'] = $collection;
                    $login_success = true;
                    break 2;
                }
            }
        }

        if ($login_success) {
            $_SESSION['login_message'] = "Login berhasil! Mengalihkan...";
            $_SESSION['login_status'] = 'success';
            $_SESSION['redirect_url'] = $collection === 'Pembeli' ? '../../index.php' : '../dashboard/seller.php';
        } else {
            $message = "Email atau password salah!";
            $status = 'error';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login / Register</title>
    <link rel="stylesheet" href="../../assets/style/login.css">
</head>
<body>
    <div id="notification" class="notification">
        <div class="notification-content">
            <p id="notification-message"></p>
        </div>
    </div>
    <div class="main-container">
        <div class="login-container">
            <div class="login-title">
                <h2>MyFurniture.</h2>
                <h3 id="form-title">Selamat Datang Kembali!</h3>
                <p id="form-subtitle">Silakan login untuk melanjutkan:</p>
            </div>
            <form method="POST" class="login-form" id="auth-form" autocomplete="off">
                <input type="hidden" name="action" id="form-action" value="login">
                <div class="input-group">
                    <input type="email" name="email" required class="login-input" placeholder="">
                    <label for="email" class="input-label">Email</label>
                </div>
                <div class="input-group">
                    <input type="password" name="password" required class="login-input" placeholder="">
                    <label for="password" class="input-label">Password</label>
                </div>
                <div class="input-group" id="confirm-password-group" style="display:none;">
                    <input type="password" name="confirm_password" class="login-input" placeholder="">
                    <label for="confirm_password" class="input-label">Konfirmasi Password</label>
                </div>
                <div class="input-group" id="role-group" style="display:none;">
                    <select name="role" class="login-input">
                        <option value="penjual">Penjual</option>
                        <option value="pembeli">Pembeli</option>
                    </select>
                    <label for="role" class="input-label">Role</label>
                </div>
                <button type="submit" class="login-button" id="submit-btn">Login</button>
            </form>
            <p class="login-register-text">
                <span id="toggle-text">Belum punya akun?</span> 
                <a href="#" id="toggle-form" class="login-register-link">Daftar disini</a>
            </p>
        </div>
        <div class="image-container">
            <div class="tagline">
                <h2 id="tagline-text"></h2>
            </div>
            <img id="login-image" src="../../assets/img/login.png" alt="login image">
            <img id="register-image" src="../../assets/img/reg.png" alt="register image" style="display: none;">
        </div>
    </div>
    <script src="../../assets/js/login.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const loginImage = document.getElementById('login-image');
        const registerImage = document.getElementById('register-image');
        const form = document.getElementById('auth-form');
        const formTitle = document.getElementById('form-title');
        const formSubtitle = document.getElementById('form-subtitle');
        const submitBtn = document.getElementById('submit-btn');
        const toggleText = document.getElementById('toggle-text');
        const toggleForm = document.getElementById('toggle-form');
        const formAction = document.getElementById('form-action');
        const confirmPasswordGroup = document.getElementById('confirm-password-group');
        const roleGroup = document.getElementById('role-group');

        function showNotification(message, type, redirect = null) {
            const notification = document.getElementById('notification');
            const notificationMessage = document.getElementById('notification-message');
            
            notificationMessage.textContent = message;
            notification.className = 'notification ' + type;
            notification.style.display = 'block';
            setTimeout(() => {
                notification.classList.add('show');
                document.body.classList.add('notification-shown');
            }, 10);
            
            if (redirect) {
                setTimeout(() => {
                    window.location.href = redirect;
                }, 2000); // Menunggu 2 detik sebelum redirect
            } else {
                setTimeout(() => {
                    notification.classList.remove('show');
                    document.body.classList.remove('notification-shown');
                    setTimeout(() => {
                        notification.style.display = 'none';
                    }, 500);
                }, 3000);
            }
        }

        function showRegisterForm() {
            form.classList.remove('login-form');
            form.classList.add('register-form');
            formTitle.textContent = 'Buat Akun Baru';
            formSubtitle.textContent = 'Silakan isi data untuk mendaftar:';
            submitBtn.textContent = 'Daftar';
            toggleText.textContent = 'Sudah punya akun?';
            toggleForm.textContent = 'Login disini';
            formAction.value = 'register';
            confirmPasswordGroup.style.display = 'block';
            roleGroup.style.display = 'block';
            loginImage.style.display = 'none';
            registerImage.style.display = 'block';
            registerImage.classList.add('enlarged', 'fade-in');
            loginImage.classList.remove('fade-in');
        }

        function showLoginForm() {
            form.classList.remove('register-form');
            form.classList.add('login-form');
            formTitle.textContent = 'Selamat Datang Kembali!';
            formSubtitle.textContent = 'Silakan login untuk melanjutkan:';
            submitBtn.textContent = 'Login';
            toggleText.textContent = 'Belum punya akun?';
            toggleForm.textContent = 'Daftar disini';
            formAction.value = 'login';
            confirmPasswordGroup.style.display = 'none';
            roleGroup.style.display = 'none';
            registerImage.style.display = 'none';
            loginImage.style.display = 'block';
            loginImage.classList.add('fade-in');
            loginImage.classList.remove('enlarged');
            registerImage.classList.remove('enlarged', 'fade-in');
        }

        if (<?php echo $showRegisterForm ? 'true' : 'false'; ?>) {
            showRegisterForm();
        } else {
            loginImage.classList.add('fade-in');
        }

        toggleForm.addEventListener('click', function(e) {
            e.preventDefault();
            if (form.classList.contains('login-form')) {
                showRegisterForm();
            } else {
                showLoginForm();
            }
        });

        <?php
        if (isset($_SESSION['login_message']) && isset($_SESSION['login_status'])) {
            $redirect = isset($_SESSION['redirect_url']) ? $_SESSION['redirect_url'] : null;
            echo "showNotification('" . $_SESSION['login_message'] . "', '" . $_SESSION['login_status'] . "', '$redirect');";
            unset($_SESSION['login_message']);
            unset($_SESSION['login_status']);
            unset($_SESSION['redirect_url']);
        } elseif ($message && $status) {
            echo "showNotification('$message', '$status');";
            if ($status === 'error') {
                echo "showLoginForm();";
            }
        }
        ?>
    });
    </script>
</body>
</html>