<?php
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

$product_id = isset($_GET['id']) ? $_GET['id'] : null;

if (!$product_id) {
    header('Location: products.php');
    exit();
}

$pipeline = [
    ['$match' => ['_id' => new MongoDB\BSON\ObjectId($product_id)]],
    ['$lookup' => [
        'from' => 'Penjual',
        'localField' => 'penjual_id',
        'foreignField' => '_id',
        'as' => 'penjual'
    ]],
    ['$unwind' => '$penjual']
];

$command = new MongoDB\Driver\Command([
    'aggregate' => 'Produk',
    'pipeline' => $pipeline,
    'cursor' => new stdClass,
]);

$cursor = $m->executeCommand('marketplace', $command);
$result = $cursor->toArray();

if (empty($result)) {
    header('Location: products.php');
    exit();
}

$product = $result[0];

if (!isset($product->gambar)) {
    $product->gambar = [];
} else if (!is_array($product->gambar)) {
    $product->gambar = [$product->gambar];
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nama_produk = trim($_POST['nama_produk']);
    $kategori = trim($_POST['kategori']);
    $harga_raw = $_POST['harga'];
    $harga_cleaned = str_replace(['Rp ', '.'], '', $harga_raw);
    $harga = (int)$harga_cleaned;
    $deskripsi = trim($_POST['deskripsi']);
    $stok = (int)$_POST['stok'];

    if (isset($_POST['removed_images']) && !empty($_POST['removed_images'])) {
        $removed_images = json_decode($_POST['removed_images']);
        if (is_array($removed_images)) {
            foreach ($removed_images as $removed_image) {
                $index = array_search($removed_image, $product->gambar);
                if ($index !== false) {
                    unset($product->gambar[$index]);
                    unlink("../../assets/img/products/" . $removed_image);
                }
            }
            $product->gambar = array_values($product->gambar);
        }
    }

    $uploaded_images = $product->gambar;

    if (!empty($_FILES['gambar_produk']['name'][0])) {
        $target_dir = "../../assets/img/products/";
        foreach($_FILES['gambar_produk']['tmp_name'] as $key => $tmp_name) {
            $file_name = $_FILES['gambar_produk']['name'][$key];
            $file_size = $_FILES['gambar_produk']['size'][$key];
            $file_tmp = $_FILES['gambar_produk']['tmp_name'][$key];
            
            $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $new_filename = uniqid() . '.' . $file_extension;
            $target_file = $target_dir . $new_filename;
            
            $allowed_types = ['jpg', 'jpeg', 'png'];
            if (in_array($file_extension, $allowed_types) && $file_size <= 500000) {
                if (move_uploaded_file($file_tmp, $target_file)) {
                    $uploaded_images[] = $new_filename;
                }
            }
        }
    }

    if (empty($nama_produk) || empty($kategori) || $harga <= 0 || $stok < 0) {
        $message = "Semua field harus diisi dengan benar!";
        $status = 'error';
    } else {
        $bulk = new MongoDB\Driver\BulkWrite;
        $bulk->update(
            ['_id' => new MongoDB\BSON\ObjectId($product_id)],
            ['$set' => [
                'nama_produk' => $nama_produk,
                'kategori' => $kategori,
                'harga' => $harga,
                'deskripsi' => $deskripsi,
                'stok' => $stok,
                'gambar' => $uploaded_images,
                'updated_at' => new MongoDB\BSON\UTCDateTime(new DateTime('now'))
            ]]
        );

        try {
            $result = $m->executeBulkWrite("marketplace.Produk", $bulk);
            if ($result->getModifiedCount() > 0) {
                $message = "Produk berhasil diperbarui!";
                $status = 'success';
            } else {
                $message = "Tidak ada perubahan pada produk.";
                $status = 'info';
            }
        } catch (MongoDB\Driver\Exception\Exception $e) {
            $message = "Error: " . $e->getMessage();
            $status = 'error';
        } if ($status === 'success') {
            $pipeline = [
                ['$match' => ['_id' => new MongoDB\BSON\ObjectId($product_id)]],
                ['$lookup' => [
                    'from' => 'Penjual',
                    'localField' => 'penjual_id',
                    'foreignField' => '_id',
                    'as' => 'penjual'
                ]],
                ['$unwind' => '$penjual']
            ];

            $command = new MongoDB\Driver\Command([
                'aggregate' => 'Produk',
                'pipeline' => $pipeline,
                'cursor' => new stdClass,
            ]);

            $cursor = $m->executeCommand('marketplace', $command);
            $result = $cursor->toArray();
            
            if (!empty($result)) {
                $product = $result[0];
            } else {
                header('Location: products.php');
                exit();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Edit Produk - MyFurniture</title>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet" />
    <link rel="stylesheet" href="../../assets/style/seller.css" />
    <link rel="stylesheet" href="../../assets/style/sidebar.css" />
    <link rel="stylesheet" href="../../assets/style/edit_product.css" />
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
                    <h1>Edit Produk</h1>
                </div>
                <div class="header-actions">
                    <a href="products.php" class="btn btn-secondary"><i data-feather="arrow-left"></i> Kembali</a>
                    <button type="submit" form="edit-product-form" class="btn btn-primary"><i data-feather="save"></i> Simpan Perubahan</button>
                </div>
            </div>
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $status; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            <form id="edit-product-form" method="POST" action="" class="edit-product-form" enctype="multipart/form-data">
                <div class="form-container">
                    <div class="image-upload-section">
                        <div class="form-group">
                            <label for="gambar_produk">Unggah Gambar</label>
                            <div class="upload-container">
                                <input type="file" id="gambar_produk" name="gambar_produk[]" multiple accept="image/jpeg,image/png" style="display: none;">
                                <label for="gambar_produk" class="upload-icon"><i class="ri-upload-2-line"></i></label>
                                <span class="upload-text">Klik atau seret gambar ke sini</span>
                            </div>
                            <div class="image-preview-container">
                                <?php if (isset($product->gambar) && is_array($product->gambar)): ?>
                                    <?php foreach ($product->gambar as $image): ?>
                                        <div class="image-preview">
                                            <img src="../../assets/img/products/<?php echo htmlspecialchars($image); ?>" />
                                            <span class="remove-image" data-name="<?php echo htmlspecialchars($image); ?>">&times;</span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="product-details-section">
                        <div class="form-group">
                            <label for="nama_produk">Nama Produk</label>
                            <input type="text" id="nama_produk" name="nama_produk" value="<?php echo htmlspecialchars($product->nama_produk); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="harga">Harga Produk</label>
                            <input type="text" id="harga" name="harga" value="<?php echo number_format($product->harga, 0, ',', '.'); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="stok">Stok</label>
                            <input type="number" id="stok" name="stok" value="<?php echo $product->stok; ?>" required min="0">
                        </div>
                        <div class="form-group">
                            <label for="deskripsi">Deskripsi</label>
                            <textarea id="deskripsi" name="deskripsi" rows="4"><?php echo htmlspecialchars($product->deskripsi); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label for="kategori">Kategori</label>
                            <select id="kategori" name="kategori" required>
                                <option value="">Pilih Kategori</option>
                                <option value="Meja" <?php echo $product->kategori === 'Meja' ? 'selected' : ''; ?>>Meja</option>
                                <option value="Kursi" <?php echo $product->kategori === 'Kursi' ? 'selected' : ''; ?>>Kursi</option>
                                <option value="Sofa" <?php echo $product->kategori === 'Sofa' ? 'selected' : ''; ?>>Sofa</option>
                                <option value="Lemari" <?php echo $product->kategori === 'Lemari' ? 'selected' : ''; ?>>Lemari</option>
                                <option value="Dekorasi" <?php echo $product->kategori === 'Dekorasi' ? 'selected' : ''; ?>>Dekorasi</option>
                                <option value="Tempat Tidur" <?php echo $product->kategori === 'Tempat Tidur' ? 'selected' : ''; ?>>Tempat Tidur</option>
                                <option value="Ruang Tamu" <?php echo $product->kategori === 'Ruang Tamu' ? 'selected' : ''; ?>>Ruang Tamu</option>
                                <option value="Ruang Makan" <?php echo $product->kategori === 'Ruang Makan' ? 'selected' : ''; ?>>Ruang Makan</option>
                                <option value="Lainnya" <?php echo $product->kategori === 'Lainnya' ? 'selected' : ''; ?>>Lainnya</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Penjual</label>
                            <p><?php echo htmlspecialchars($product->penjual->username); ?></p>
                        </div>
                    </div>
                </div>
                <input type="hidden" name="removed_images" id="removed_images" value="">
            </form>
        </main>
    </div>

    <div id="modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modal-title"></h2>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body">
                <p id="modal-message"></p>
            </div>
            <div class="modal-footer">
                <button id="modal-cancel" class="btn btn-secondary">Batal</button>
                <button id="modal-confirm" class="btn btn-primary">Ya</button>
            </div>
        </div>
    </div>
    <div id="successAnimation" style="display: none;">
        <div class="overlay"></div>
        <div class="success-animation">
            <div class="success-icon">
                <i class="ri-check-line"></i>
            </div>
            <p class="success-message">Produk berhasil disimpan!</p>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-maskmoney/3.0.2/jquery.maskMoney.min.js"></script>
<script>
$(document).ready(function() {
    feather.replace({ width: 20, height: 20 });
    
    var initialFormData = new FormData($('#edit-product-form')[0]);
    var fileList = new DataTransfer();
    var removedImages = [];
    
    function showModal(title, message, onConfirm) {
        $('#modal-title').text(title);
        $('#modal-message').text(message);
        $('#modal').show();

        $('#modal-confirm').off('click').on('click', function() {
            $('#modal').hide();
            if (onConfirm) onConfirm();
        });

        $('#modal-cancel, .close').off('click').on('click', function() {
            $('#modal').hide();
        });
    }
    
    function isFormChanged() {
        var currentFormData = new FormData($('#edit-product-form')[0]);
        var changed = false;
        
        for (var pair of currentFormData.entries()) {
            if (pair[0] !== 'removed_images' && pair[1] !== initialFormData.get(pair[0])) {
                changed = true;
                break;
            }
        }
        
        if (fileList.files.length > 0 || removedImages.length > 0) {
            changed = true;
        }
        
        return changed;
    }
    
    $('.btn-secondary').click(function(e) {
        if (isFormChanged()) {
            e.preventDefault();
            showModal(
                'Konfirmasi Pembatalan',
                'Anda memiliki perubahan yang belum disimpan. Apakah Anda yakin ingin membatalkan perubahan?',
                function() {
                    window.location.href = 'products.php';
                }
            );
        } else {
            window.location.href = 'products.php';
        }
    });
    
    $('.edit-product-form').submit(function(e) {
        e.preventDefault();
        var harga = $('#harga').val();
        
        if ($('#harga').maskMoney('unmasked')[0] <= 0) {
            showModal(
                'Peringatan',
                'Harga harus lebih dari 0!',
                null
            );
            return;
        }
        
        if (isFormChanged()) {
            showModal(
                'Konfirmasi Penyimpanan',
                'Apakah Anda yakin ingin menyimpan perubahan pada produk ini?',
                function() {
                    // Kirim form menggunakan AJAX
                    $.ajax({
                        type: 'POST',
                        url: $(this).attr('action'),
                        data: new FormData($('#edit-product-form')[0]),
                        processData: false,
                        contentType: false,
                        success: function(response) {
                            // Tampilkan animasi sukses
                            $('#successAnimation').fadeIn(300);
                            
                            // Redirect setelah 1.5 detik
                            setTimeout(function() {
                                window.location.href = 'products.php';
                            }, 1500);
                        },
                        error: function() {
                            showModal(
                                'Error',
                                'Terjadi kesalahan saat menyimpan produk.',
                                null
                            );
                        }
                    });
                }
            );
        } else {
            window.location.href = 'products.php';
        }
    });

    $('#harga').maskMoney({
        prefix: 'Rp ',
        thousands: '.',
        decimal: ',',
        precision: 0,
        allowZero: true,
        affixesStay: true
    });
    $('#harga').maskMoney('mask');

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
        $(this).parent('.image-preview').remove();
        
        if (fileName.indexOf('blob:') === -1) {
            removedImages.push(fileName);
            $('#removed_images').val(JSON.stringify(removedImages));
        } else {
            for (var i = 0; i < fileList.items.length; i++) {
                if (fileList.items[i].getAsFile().name === fileName) {
                    fileList.items.remove(i);
                    break;
                }
            }
        }
        updateFileInput();
    });

    function updateFileInput() {
        $('#gambar_produk')[0].files = fileList.files;
    }
});
</script>
</body>
</html>