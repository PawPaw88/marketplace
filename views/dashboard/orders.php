<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'Penjual') {
    header('Location: ../../index.php');
    exit();
}

include '../../config/db.php';
$m = connectDB();
$user = $_SESSION['user'];

// Pipeline untuk mengambil pesanan
$pipeline = [
    ['$match' => ['penjual_id' => new MongoDB\BSON\ObjectId($user->_id)]],
    ['$lookup' => [
        'from' => 'Pembeli',
        'localField' => 'pembeli_id',
        'foreignField' => '_id',
        'as' => 'pembeli'
    ]],
    ['$unwind' => '$pembeli'],
    ['$lookup' => [
        'from' => 'Produk',
        'localField' => 'product_id',
        'foreignField' => '_id',
        'as' => 'produk'
    ]],
    ['$unwind' => '$produk'],
    ['$sort' => ['created_at' => -1]]
];

$command = new MongoDB\Driver\Command([
    'aggregate' => 'Pesanan',
    'pipeline' => $pipeline,
    'cursor' => new stdClass,
]);

$cursor = $m->executeCommand('marketplace', $command);
$orders = $cursor->toArray();

// Data debug untuk testing jika tidak ada pesanan
if (empty($orders)) {
    $debugOrders = [
        (object)[
            '_id' => new MongoDB\BSON\ObjectId(),
            'pembeli' => (object)[
                'username' => 'Budi Santoso',
            ],
            'alamat_pengiriman' => 'Jl. Mawar No. 123, Jakarta Selatan',
            'produk' => (object)[
                'nama_produk' => 'Kursi Kayu Jati',
                'gambar' => ['kursi-jati-1.jpg'],
            ],
            'jumlah' => 2,
            'total_harga' => 2500000,
            'status' => 'Pending',
            'created_at' => new MongoDB\BSON\UTCDateTime(strtotime('2024-03-20') * 1000),
            'penjual_id' => new MongoDB\BSON\ObjectId($user->_id)
        ],
        (object)[
            '_id' => new MongoDB\BSON\ObjectId(),
            'pembeli' => (object)[
                'username' => 'Siti Rahayu',
            ],
            'alamat_pengiriman' => 'Jl. Melati No. 45, Bandung',
            'produk' => (object)[
                'nama_produk' => 'Meja Makan Set Dengan Kursi ada anck scscsc',
                'gambar' => ['meja-makan-1.jpg'],
            ],
            'jumlah' => 1,
            'total_harga' => 4500000,
            'status' => 'Diproses',
            'created_at' => new MongoDB\BSON\UTCDateTime(strtotime('2024-03-19') * 1000),
            'penjual_id' => new MongoDB\BSON\ObjectId($user->_id)
        ]
    ];
    
    $orders = $debugOrders;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Pesanan - MyFurniture</title>
    <link rel="stylesheet" href="../../assets/style/seller.css" />
    <link rel="stylesheet" href="../../assets/style/sidebar.css" />
    <link rel="stylesheet" href="../../assets/style/orders_seller.css" />
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
                <li><a href="orders.php" class="active"><i data-feather="shopping-cart"></i> <span>Pesanan</span></a></li>
                <li><a href="profile.php"><i data-feather="user"></i> <span>Profil</span></a></li>
            </ul>
        </nav>

        <main class="content">
            <div class="header-content">
                <div class="greeting">
                    <h1>Pesanan</h1>
                    <p>Kelola pesanan pelanggan</p>
                </div>
                <div class="search-filter-wrapper">
                    <div class="search-bar">
                        <i data-feather="search" class="search-icon"></i>
                        <input type="text" id="orderSearch" placeholder="Cari pesanan..." />
                    </div>
                </div>
            </div>

            <div class="filter-buttons">
                <button class="filter-btn active" data-status="all">Semua</button>
                <button class="filter-btn" data-status="pending">Pending</button>
                <button class="filter-btn" data-status="diproses">Diproses</button>
                <button class="filter-btn" data-status="dikirim">Dikirim</button>
                <button class="filter-btn" data-status="selesai">Selesai</button>
                <button class="filter-btn" data-status="dibatalkan">Dibatalkan</button>
            </div>

            <div class="orders-section">
                <table>
                    <thead>
                        <tr>
                            <th>ID Pesanan</th>
                            <th>Pelanggan</th>
                            <th>Produk</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Tanggal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                        <tr class="order-row" data-status="<?php echo strtolower($order->status); ?>">
                            <td data-label="ID Pesanan">#<?php echo substr($order->_id, -8); ?></td>
                            <td data-label="Pelanggan">
                                <div class="customer-info">
                                    <span class="customer-name"><?php echo htmlspecialchars($order->pembeli->username); ?></span>
                                    <span class="customer-address"><?php echo htmlspecialchars($order->alamat_pengiriman); ?></span>
                                </div>
                            </td>
                            <td data-label="Produk">
                                <div class="product-info">
                                    <?php if (!empty($order->produk)): ?>
                                        <div class="product-image" style="background-image: url('../../assets/img/products/<?php echo $order->produk->gambar[0]; ?>')"></div>
                                        <div class="product-details">
                                            <span class="product-name"><?php echo htmlspecialchars($order->produk->nama_produk); ?></span>
                                            <span class="product-quantity"><?php echo $order->quantity; ?>x <?php echo htmlspecialchars($order->produk->harga); ?></span>
                                            <span class="product-detail"><?php echo htmlspecialchars($order->produk->deskripsi); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td data-label="Total">Rp <?php echo number_format($order->total_harga, 0, ',', '.'); ?></td>
                            <td data-label="Status">
                                <select class="status-dropdown" data-order-id="<?php echo $order->_id; ?>">
                                    <option value="Pending" <?php echo $order->status === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="Diproses" <?php echo $order->status === 'Diproses' ? 'selected' : ''; ?>>Diproses</option>
                                    <option value="Dikirim" <?php echo $order->status === 'Dikirim' ? 'selected' : ''; ?>>Dikirim</option>
                                    <option value="Selesai" <?php echo $order->status === 'Selesai' ? 'selected' : ''; ?>>Selesai</option>
                                    <option value="Dibatalkan" <?php echo $order->status === 'Dibatalkan' ? 'selected' : ''; ?>>Dibatalkan</option>
                                </select>
                            </td>
                            <td data-label="Tanggal"><?php echo $order->created_at->toDateTime()->format('d M Y'); ?></td>
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

            // Tambahkan event listener untuk dropdown status
            document.querySelectorAll('.status-dropdown').forEach(dropdown => {
                // Set initial data-status
                dropdown.setAttribute('data-status', dropdown.value.toLowerCase());
                
                dropdown.addEventListener('change', function() {
                    const orderId = this.dataset.orderId;
                    const newStatus = this.value;
                    
                    // Update data-status attribute dan styling
                    this.setAttribute('data-status', newStatus.toLowerCase());
                    
                    const formData = new FormData();
                    formData.append('orderId', orderId);
                    formData.append('status', newStatus);
                    
                    fetch('../../api/update_order_status.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Update tampilan
                            this.closest('.order-row').dataset.status = newStatus.toLowerCase();
                            showNotification(data.message, 'success');
                        } else {
                            showNotification(data.message, 'error');
                            // Kembalikan ke status sebelumnya jika gagal
                            this.value = this.dataset.previousStatus;
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showNotification('Terjadi kesalahan saat mengupdate status', 'error');
                        this.value = this.dataset.previousStatus;
                    });
                    
                    // Simpan status sebelumnya
                    this.dataset.previousStatus = newStatus;
                });
            });

            // Fungsi untuk update status (perlu diimplementasikan)
            function updateOrderStatus(orderId, status) {
                // Implementasi AJAX untuk update status
                console.log('Update order:', orderId, 'to status:', status);
                // TODO: Tambahkan kode AJAX untuk update status ke database
            }

            // Filter pesanan berdasarkan status
            const filterButtons = document.querySelectorAll('.filter-btn');
            const orderRows = document.querySelectorAll('.order-row');

            filterButtons.forEach(button => {
                button.addEventListener('click', () => {
                    const status = button.dataset.status;
                    
                    filterButtons.forEach(btn => btn.classList.remove('active'));
                    button.classList.add('active');

                    orderRows.forEach(row => {
                        if (status === 'all' || row.dataset.status === status) {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    });
                });
            });

            // Pencarian pesanan
            const searchInput = document.getElementById('orderSearch');
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                
                orderRows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(searchTerm) ? '' : 'none';
                });
            });
        });
    </script>
</body>
</html> 