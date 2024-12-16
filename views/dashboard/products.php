<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'Penjual') {
    header('Location: ../../index.php');
    exit();
}

include '../../config/db.php';
$m = connectDB();
$user = $_SESSION['user'];

$pipeline = [
    ['$match' => ['penjual_id' => new MongoDB\BSON\ObjectId($user->_id)]],
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
$products = $cursor->toArray();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Produk Saya - MyFurniture</title>
    <link rel="stylesheet" href="../../assets/style/seller.css" />
    <link rel="stylesheet" href="../../assets/style/sidebar.css" />
    <link rel="stylesheet" href="../../assets/style/products.css" />
    <script src="https://unpkg.com/feather-icons@4.29.0/dist/feather.min.js"></script>
</head>
<body>
    <div class="main-container">
        <nav class="sidebar">
            <div class="logo">MyFurniture.</div>
            <ul class="nav-links">
                <li><a href="seller.php"><i data-feather="grid"></i> <span>Dashboard</span></a></li>
                <li><a href="products.php" class="active"><i data-feather="shopping-bag"></i> <span>Produk</span></a></li>
                <li><a href="analytics.php"><i data-feather="pie-chart"></i> <span>Laporan</span></a></li>
                <li><a href="orders.php"><i data-feather="shopping-cart"></i> <span>Pesanan</span></a></li>
                <li><a href="profile.php"><i data-feather="user"></i> <span>Profil</span></a></li>
            </ul>
        </nav>

        <main class="content">
            <div class="header-content">
                <div class="greeting">
                    <h1>Produk Saya</h1>
                    <p>Kelola produk Anda di sini</p>
                </div>
                <div class="search-filter-wrapper">
                    <div class="search-bar">
                        <i data-feather="search" class="search-icon"></i>
                        <input type="text" placeholder="Cari produk..." />
                    </div>
                </div>
                <div class="header-actions">
                    <a href="add_product.php" class="add-product-btn"><i data-feather="plus"></i></a>
                </div>
            </div>

            <div class="products-section">
                <table>
                    <thead>
                        <tr>
                            <th>Nama Produk</th>
                            <th>Kategori</th>
                            <th>Harga</th>
                            <th>Stok</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $product): 
                            $isIncomplete = empty($product->gambar) || empty($product->nama_produk) || empty($product->kategori) || !isset($product->harga) || !isset($product->stok);
                            $created_at = isset($product->created_at) ? $product->created_at->toDateTime() : null;
                        ?>
                        <tr class="<?php echo $isIncomplete ? 'incomplete-field' : ''; ?>">
                            <td class="product-name-cell">
                                <?php if (!empty($product->gambar)): ?>
                                    <div class="product-image-preview" style="background-image: url('../../assets/img/products/<?php echo htmlspecialchars($product->gambar[0]); ?>')"></div>
                                <?php else: ?>
                                    <div class="tooltip product-image-warning">
                                        <i data-feather="alert-triangle" class="warning-icon"></i>
                                        <span class="tooltiptext">Gambar produk belum ditambahkan</span>
                                    </div>
                                <?php endif; ?>
                                <div class="product-info">
                                    <div class="product-name">
                                        <?php if (!empty($product->nama_produk)): ?>
                                            <?php echo htmlspecialchars($product->nama_produk); ?>
                                        <?php else: ?>
                                            <div class="tooltip">
                                                <i data-feather="alert-triangle" class="warning-icon"></i>
                                                <span class="tooltiptext">Nama produk belum diisi</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="product-description">
                                        <?php echo !empty($product->deskripsi) ? htmlspecialchars($product->deskripsi) : 'Tidak ada deskripsi'; ?>
                                    </div>
                                    <div class="product-meta">
                                        Dibuat: <?php echo $created_at ? $created_at->format('d M Y') : '-'; ?>
                                    </div>
                                    <div class="product-actions">
                                        <button class="action-btn edit" onclick="window.location.href='edit_product.php?id=<?php echo $product->_id; ?>'">
                                            <i data-feather="edit-2"></i>
                                        </button>
                                        <button class="action-btn delete" data-id="<?php echo $product->_id; ?>">
                                            <i data-feather="trash-2"></i>
                                        </button>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php if (!empty($product->kategori)): ?>
                                    <?php echo htmlspecialchars($product->kategori); ?>
                                <?php else: ?>
                                    <div class="tooltip">
                                        <i data-feather="alert-triangle" class="warning-icon"></i>
                                        <span class="tooltiptext">Kategori belum dipilih</span>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (isset($product->harga)): ?>
                                    Rp <?php echo number_format($product->harga, 0, ',', '.'); ?>
                                <?php else: ?>
                                    <div class="tooltip">
                                        <i data-feather="alert-triangle" class="warning-icon"></i>
                                        <span class="tooltiptext">Harga belum diisi</span>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (isset($product->stok)): ?>
                                    <?php echo htmlspecialchars($product->stok); ?>
                                <?php else: ?>
                                    <div class="tooltip">
                                        <i data-feather="alert-triangle" class="warning-icon"></i>
                                        <span class="tooltiptext">Stok belum diisi</span>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            feather.replace({ width: 20, height: 20 });

            document.querySelectorAll('.edit-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const productId = this.getAttribute('data-id');
                    console.log('Edit product:', productId);
                });
            });

            document.querySelectorAll('.delete-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const productId = this.getAttribute('data-id');
                    if (confirm('Apakah Anda yakin ingin menghapus produk ini?')) {
                        console.log('Delete product:', productId);
                    }
                });
            });

            document.querySelector('.add-product-btn').addEventListener('click', function() {
                console.log('Add new product');
            });
        });
    </script>
</body>
</html>