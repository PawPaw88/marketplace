<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'Penjual') {
    header('Location: ../../index.php');
    exit();
}

include '../../config/db.php';
$m = connectDB();
$user = $_SESSION['user'];

$message = '';
$status = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    error_log("POST data: " . print_r($_POST, true));
    error_log("FILES data: " . print_r($_FILES, true));

    $nama_produk = trim($_POST['nama_produk']);
    $kategori = trim($_POST['kategori']);
    $harga_raw = $_POST['harga'];
    $harga_cleaned = str_replace(['Rp ', '.'], '', $harga_raw);
    $harga = (int)$harga_cleaned;
    $deskripsi = trim($_POST['deskripsi']);
    $stok = (int)$_POST['stok'];

    $uploaded_images = [];
    $target_dir = "../../assets/img/products/";

    if (!is_dir($target_dir) || !is_writable($target_dir)) {
        error_log("Directory issue: " . $target_dir . " does not exist or is not writable");
        $message = "Error with upload directory. Please contact administrator.";
        $status = 'error';
    } else {
        foreach($_FILES['gambar_produk']['tmp_name'] as $key => $tmp_name) {
            $file_name = $_FILES['gambar_produk']['name'][$key];
            $file_size = $_FILES['gambar_produk']['size'][$key];
            $file_tmp = $_FILES['gambar_produk']['tmp_name'][$key];
            $file_type = $_FILES['gambar_produk']['type'][$key];
            
            $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $new_filename = uniqid() . '.' . $file_extension;
            $target_file = $target_dir . $new_filename;
            
            $allowed_types = ['jpg', 'jpeg', 'png'];
            if (in_array($file_extension, $allowed_types) && $file_size <= 5000000) {
                if (move_uploaded_file($file_tmp, $target_file)) {
                    $uploaded_images[] = $new_filename;
                    error_log("File berhasil diunggah: " . $new_filename);
                } else {
                    error_log("Gagal mengunggah file: " . $file_name);
                }
            } else {
                error_log("File tidak valid: " . $file_name);
            }
        }
    }

    if (empty($nama_produk) || empty($kategori) || $harga <= 0 || $stok < 0 || empty($uploaded_images)) {
        $message = "Semua field harus diisi dengan benar dan setidaknya satu gambar harus diunggah!";
        $status = 'error';
        error_log("Validation failed: nama_produk=" . $nama_produk . ", kategori=" . $kategori . ", harga=" . $harga . ", stok=" . $stok . ", images=" . implode(", ", $uploaded_images));
    } else {
        $bulk = new MongoDB\Driver\BulkWrite;
        $doc = [
            '_id' => new MongoDB\BSON\ObjectId(), 
            'nama_produk' => $nama_produk,
            'kategori' => $kategori,
            'harga' => $harga,
            'deskripsi' => $deskripsi,
            'stok' => $stok,
            'gambar' => $uploaded_images,
            'penjual_id' => new MongoDB\BSON\ObjectId($user->_id),
            'created_at' => new MongoDB\BSON\UTCDateTime(new DateTime('now')),
            'updated_at' => new MongoDB\BSON\UTCDateTime(new DateTime('now'))
        ];
        $bulk->insert($doc);
    
        try {
            $result = $m->executeBulkWrite("marketplace.Produk", $bulk);
            if ($result->getInsertedCount() > 0) {
                $message = "Produk berhasil ditambahkan!";
                $status = 'success';
            } else {
                $message = "Gagal menambahkan produk.";
                $status = 'error';
                error_log("Gagal menambahkan produk. Tidak ada dokumen yang dimasukkan.");
            }
        } catch (MongoDB\Driver\Exception\Exception $e) {
            $message = "Error: " . $e->getMessage();
            $status = 'error';
            error_log("MongoDB Error: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Tambah Produk - MyFurniture</title>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet" />
    <link rel="stylesheet" href="../../assets/style/seller.css" />
    <link rel="stylesheet" href="../../assets/style/sidebar.css" />
    <link rel="stylesheet" href="../../assets/style/add_product.css" />
    <script src="https://unpkg.com/feather-icons@4.29.0/dist/feather.min.js"></script>
</head>
<body>
<div class="main-container">
        <nav class="sidebar">
            <div class="logo">MyFurniture.</div>
            <ul class="nav-links">
                <li><a href="seller.php"><i data-feather="grid"></i> <span>Dashboard</span></a></li>
                <li><a href="products.php" class="active"><i data-feather="shopping-bag"></i> <span>Produk</span></a></li>
                <li><a href="#analytics"><i data-feather="pie-chart"></i> <span>Laporan</span></a></li>
                <li><a href="#orders"><i data-feather="shopping-cart"></i> <span>Pesanan</span></a></li>
                <li><a href="profile.php"><i data-feather="user"></i> <span>Profil</span></a></li>
            </ul>
        </nav>

        <main class="content">
            <div class="header-content">
                <div class="greeting">
                    <h1>Tambah Produk</h1>
                </div>
                <div class="header-actions">
                    <a href="products.php" class="btn btn-secondary"><i data-feather="arrow-left"></i> Kembali</a>
                    <button type="submit" form="add-product-form" class="btn btn-primary"><i data-feather="plus"></i> Tambah Produk</button>
                </div>
            </div>
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $status; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            <form id="add-product-form" method="POST" action="" class="add-product-form" enctype="multipart/form-data">
                <div class="form-container">
                    <div class="image-upload-section">
                        <div class="form-group">
                            <label for="gambar_produk">Unggah Gambar</label>
                            <div class="upload-container">
                                <input type="file" id="gambar_produk" name="gambar_produk[]" multiple accept="image/jpeg,image/png" required style="display: none;">
                                <label for="gambar_produk" class="upload-icon"><i class="ri-upload-2-line"></i></label>
                                <span class="upload-text">Klik atau seret gambar ke sini</span>
                            </div>
                            <div class="image-preview-container"></div>
                        </div>
                    </div>
                    <div class="product-details-section">
                        <div class="form-group">
                            <label for="nama_produk">Nama Produk</label>
                            <input type="text" id="nama_produk" name="nama_produk" placeholder="Masukkan nama produk" required>
                        </div>
                        <div class="form-group">
                            <label for="harga">Harga Produk</label>
                            <input type="text" id="harga" name="harga" placeholder="Rp 0" required>
                        </div>
                        <div class="form-group">
                            <label for="stok">Stok</label>
                            <input type="number" id="stok" name="stok" placeholder="Masukkan jumlah stok" required min="0">
                        </div>
                        <div class="form-group">
                            <label for="deskripsi">Deskripsi</label>
                            <textarea id="deskripsi" name="deskripsi" rows="4" placeholder="Masukkan deskripsi produk"></textarea>
                        </div>
                        <div class="form-group">
                            <label for="kategori">Kategori</label>
                            <select id="kategori" name="kategori" required>
                                <option value="">Pilih Kategori</option>
                                <option value="Meja">Meja</option>
                                <option value="Kursi">Kursi</option>
                                <option value="Sofa">Sofa</option>
                                <option value="Lemari">Lemari</option>
                                <option value="Dekorasi">Dekorasi</option>
                                <option value="Tempat Tidur">Tempat Tidur</option>
                                <option value="Ruang Tamu">Ruang Tamu</option>
                                <option value="Ruang Makan">Ruang Makan</option>
                                <option value="Lainnya">Lainnya</option>
                            </select>
                        </div>
                    </div>
                </div>
            </form>
        </main>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-maskmoney/3.0.2/jquery.maskMoney.min.js"></script>
    <script>
    $(document).ready(function() {
        feather.replace({ width: 20, height: 20 });
        
        $('#harga').maskMoney({
            prefix: 'Rp ',
            thousands: '.',
            decimal: ',',
            precision: 0,
            allowZero: true,
            affixesStay: true
        });
        $('#harga').maskMoney('mask');

        $('.add-product-form').submit(function(e) {
            e.preventDefault(); 
            var harga = $('#harga').val();
            if ($('#harga').maskMoney('unmasked')[0] <= 0) {
                alert('Harga harus lebih dari 0!');
            } else {
                this.submit();
            }
        });

        var fileList = new DataTransfer();

        $('#gambar_produk').on('change', function(e) {
            var files = e.target.files;
            for (var i = 0; i < files.length; i++) {
                var file = files[i];
                fileList.items.add(file);
                previewImage(file);
            }
            updateFileInput();
        });

        function previewImage(file) {
            var reader = new FileReader();
            reader.onload = function(e) {
                var div = $('<div class="image-preview"></div>');
                div.append('<img src="' + e.target.result + '" />');
                div.append('<span class="remove-image" data-name="' + file.name + '">&times;</span>');
                $('.image-preview-container').append(div);
            };
            reader.readAsDataURL(file);
        }

        $(document).on('click', '.remove-image', function() {
            var fileName = $(this).data('name');
            for (var i = 0; i < fileList.items.length; i++) {
                if (fileList.items[i].getAsFile().name === fileName) {
                    fileList.items.remove(i);
                    break;
                }
            }
            $(this).parent('.image-preview').remove();
            updateFileInput();
        });

        function updateFileInput() {
            $('#gambar_produk')[0].files = fileList.files;
        }
    });
    </script>
</body>
</html>