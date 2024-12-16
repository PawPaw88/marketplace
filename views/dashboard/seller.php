<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'Penjual') {
    header('Location: ../../index.php');
    exit();
}

include '../../config/db.php';
$m = connectDB();
$user = $_SESSION['user'];

$totalFields = 3; 
$completedFields = 0;
if (!empty($user->username) && $user->username !== 'user' . substr($user->username, -4)) $completedFields++;
if (!empty($user->alamat)) $completedFields++;
if (!empty($user->no_telepon)) $completedFields++;

$completionPercentage = ($completedFields / $totalFields) * 100;

$message = '';
$status = '';

$query = new MongoDB\Driver\Query(['penjual_id' => new MongoDB\BSON\ObjectId($user->_id)]);
$cursor = $m->executeQuery("marketplace.Produk", $query);
$totalProduk = iterator_count($cursor);

$pipeline = [
    ['$match' => ['penjual_id' => new MongoDB\BSON\ObjectId($user->_id)]],
    ['$group' => [
        '_id' => null,
        'totalUnit' => ['$sum' => '$stok']
    ]]
];

$command = new MongoDB\Driver\Command([
    'aggregate' => 'Produk',
    'pipeline' => $pipeline,
    'cursor' => new stdClass,
]);

$cursor = $m->executeCommand('marketplace', $command);
$result = $cursor->toArray();
$totalUnit = !empty($result) ? $result[0]->totalUnit : 0;

// Hitung total pesanan dan total pendapatan
$orderStatsPipeline = [
    ['$match' => ['penjual_id' => new MongoDB\BSON\ObjectId($user->_id)]],
    ['$group' => [
        '_id' => null,
        'totalPesanan' => ['$sum' => 1],
        'totalPendapatan' => ['$sum' => '$total_harga'],
        'uniqueCustomers' => ['$addToSet' => '$pembeli_id']
    ]]
];

$command = new MongoDB\Driver\Command([
    'aggregate' => 'Pesanan',
    'pipeline' => $orderStatsPipeline,
    'cursor' => new stdClass,
]);

$cursor = $m->executeCommand('marketplace', $command);
$orderStats = current($cursor->toArray());

// Set nilai default jika tidak ada data
$totalPesanan = $orderStats ? $orderStats->totalPesanan : 0;
$totalPendapatan = $orderStats ? $orderStats->totalPendapatan : 0;
$totalCustomers = $orderStats ? count($orderStats->uniqueCustomers) : 0;

// Siapkan data untuk grafik
$orderStatusData = [
    'Pending' => 0,
    'Diproses' => 0,
    'Dikirim' => 0,
    'Selesai' => 0,
    'Dibatalkan' => 0
];

$orderPipeline = [
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
    'pipeline' => $orderPipeline,
    'cursor' => new stdClass,
]);

$cursor = $m->executeCommand('marketplace', $command);
$orders = $cursor->toArray();

foreach ($orders as $order) {
    $orderStatusData[$order->status]++;
}

// Konversi data untuk JavaScript
$chartData = [
    'labels' => array_keys($orderStatusData),
    'data' => array_values($orderStatusData)
];

// Ambil produk terlaris
$topProductsPipeline = [
    ['$match' => ['penjual_id' => new MongoDB\BSON\ObjectId($user->_id)]],
    ['$group' => [
        '_id' => '$product_id',
        'totalSold' => ['$sum' => '$quantity'],
        'totalRevenue' => ['$sum' => '$total_harga']
    ]],
    ['$lookup' => [
        'from' => 'Produk',
        'localField' => '_id',
        'foreignField' => '_id',
        'as' => 'product'
    ]],
    ['$unwind' => '$product'],
    ['$sort' => ['totalSold' => -1]],
    ['$limit' => 5]
];

$command = new MongoDB\Driver\Command([
    'aggregate' => 'Pesanan',
    'pipeline' => $topProductsPipeline,
    'cursor' => new stdClass,
]);

$cursor = $m->executeCommand('marketplace', $command);
$topProducts = $cursor->toArray();

// Tambahkan query untuk data grafik penjualan
$salesPipeline = [
    ['$match' => [
        'penjual_id' => new MongoDB\BSON\ObjectId($user->_id),
        'created_at' => [
            '$gte' => new MongoDB\BSON\UTCDateTime(strtotime('-7 days') * 1000)
        ]
    ]],
    ['$group' => [
        '_id' => ['$dateToString' => ['format' => '%Y-%m-%d', 'date' => '$created_at']],
        'total_penjualan' => ['$sum' => '$quantity'],
        'jumlah_pesanan' => ['$sum' => 1]
    ]],
    ['$sort' => ['_id' => 1]]
];

$command = new MongoDB\Driver\Command([
    'aggregate' => 'Pesanan',
    'pipeline' => $salesPipeline,
    'cursor' => new stdClass,
]);

$cursor = $m->executeCommand('marketplace', $command);
$salesData = $cursor->toArray();

// Siapkan data untuk grafik penjualan
$salesChartData = [
    'labels' => [],
    'data' => []
];

// Buat array untuk menyimpan data penjualan per tanggal
$salesByDate = [];
foreach ($salesData as $sale) {
    $salesByDate[$sale->_id] = $sale->total_penjualan;
}

// Isi data untuk 7 hari terakhir dengan pengecekan data yang ada
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $salesChartData['labels'][] = date('d M', strtotime($date));
    $salesChartData['data'][] = isset($salesByDate[$date]) ? $salesByDate[$date] : 0;
}

// Tambahkan query untuk data grafik penjualan bulanan
$salesPipelineMonth = [
    ['$match' => [
        'penjual_id' => new MongoDB\BSON\ObjectId($user->_id),
        'created_at' => [
            '$gte' => new MongoDB\BSON\UTCDateTime(strtotime('-30 days') * 1000)
        ]
    ]],
    ['$group' => [
        '_id' => [
            'week' => ['$week' => '$created_at'],
            'month' => ['$month' => '$created_at']
        ],
        'total_penjualan' => ['$sum' => '$quantity'],
        'jumlah_pesanan' => ['$sum' => 1]
    ]],
    ['$sort' => ['_id.week' => 1]]
];

$command = new MongoDB\Driver\Command([
    'aggregate' => 'Pesanan',
    'pipeline' => $salesPipelineMonth,
    'cursor' => new stdClass,
]);

$cursor = $m->executeCommand('marketplace', $command);
$salesDataMonth = $cursor->toArray();

// Siapkan data untuk grafik penjualan bulanan
$salesChartDataMonth = [
    'labels' => ['Minggu 1', 'Minggu 2', 'Minggu 3', 'Minggu 4'],
    'data' => [0, 0, 0, 0]
];

// Kelompokkan data per minggu
foreach ($salesDataMonth as $sale) {
    $weekIndex = ($sale->_id->week % 4); // Konversi ke indeks 0-3
    $salesChartDataMonth['data'][$weekIndex] += $sale->total_penjualan;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Seller Dashboard - MyFurniture</title>
    <link rel="stylesheet" href="../../assets/style/seller.css" />
    <link rel="stylesheet" href="../../assets/style/sidebar.css" />
    <script src="https://unpkg.com/feather-icons@4.29.0/dist/feather.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="main-container">
        <nav class="sidebar">
            <div class="logo">MyFurniture.</div>
            <ul class="nav-links">
                <li><a href="seller.php" class="active"><i data-feather="grid"></i> <span>Dashboard</span></a></li>
                <li><a href="products.php"><i data-feather="shopping-bag"></i> <span>Produk</span></a></li>
                <li><a href="analytics.php"><i data-feather="pie-chart"></i> <span>Laporan</span></a></li>
                <li><a href="orders.php"><i data-feather="shopping-cart"></i> <span>Pesanan</span></a></li>
                <li><a href="profile.php"><i data-feather="user"></i> <span>Profil</span></a></li>
            </ul>
        </nav>

        <main class="content">
                <div class="header-content">
                    <div class="greeting">
                        <h1>Halo <?php echo htmlspecialchars(ucwords($user->username)); ?>! 👋</h1>
                        <p>Yuk kita lihat pendapatan kita hari ini!</p>
                    </div>
                    <div class="search-filter-wrapper">
                        <div class="search-bar">
                            <i data-feather="search" class="search-icon"></i>
                            <input type="text" placeholder="Cari furniture..." />
                        </div>
                    </div>
                    <div class="header-actions">
                    <div class="notif-wrapper">
                        <i data-feather="bell" class="notif-icon"></i>
                    </div>
                        <nav class="nav-buttons">
                            <a href="../auth/logout.php">Logout</a>
                        </nav>
                    </div>
                </div>
            

            <?php if ($completionPercentage < 100): ?>
                <div class="alert alert-warning">
                    Silakan lengkapi profil Anda terlebih dahulu untuk dapat mulai berjualan.
                    <a href="profile.php">Lengkapi Profil</a>
                </div>
                <div class="profile-completion">
                    <p>Kelengkapan Profil: <?php echo round($completionPercentage); ?>%</p>
                    <div class="progress-bar">
                        <div class="progress" style="width: <?php echo $completionPercentage; ?>%;"></div>
                    </div>
                </div>

                <div id="profilePopup" class="popup">
                    <div class="popup-content">
                        <h3>Perhatian!</h3>
                        <p>Silakan lengkapi profil Anda terlebih dahulu sebelum mengakses fitur ini.</p>
                        <button onclick="closePopup()" class="btn-close">Mengerti</button>
                    </div>
                </div>

                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        feather.replace({ width: 20, height: 20 });

                        // Hanya nonaktifkan link jika profil belum lengkap
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

                    function closePopup() {
                        document.getElementById('profilePopup').style.display = 'none';
                    }
                </script>
            <?php endif; ?>

            <div id="dashboard" class="dashboard-section">
                <div class="dashboard-stats">
                    <div class="stat-card stat-card-blue">
                        <div class="stat-icon">
                            <i data-feather="box"></i>
                        </div>
                        <h3>Total Stok</h3>
                        <div class="stat-details">
                            <span class="total-product"><i data-feather="package"></i> <?php echo $totalProduk; ?> Produk</span>
                            <span class="total-category"><i data-feather="grid"></i> <?php echo $totalUnit; ?> Unit</span>
                        </div>
                    </div>
                    <div class="stat-card stat-card-orange">
                        <div class="stat-icon">
                            <i data-feather="shopping-cart"></i>
                        </div>
                        <h3>Total Pesanan</h3>
                        <div class="stat-details">
                            <span><i class="total-order" data-feather="package"></i> <?php echo $totalPesanan; ?> Pesanan</span>
                            <span><i class="total-user" data-feather="users"></i> <?php echo $totalCustomers; ?> Pembeli</span>
                        </div>
                    </div>
                    <div class="stat-card stat-card-green">
                        <div class="stat-icon">
                            <i data-feather="dollar-sign"></i>
                        </div>
                        <h3>Pendapatan</h3>
                        <div class="stat-details">
                            <span><i data-feather="arrow-down"></i> Rp <?php echo number_format($totalPendapatan, 0, ',', '.'); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <div id="chart-section" class="chart-section">
                <div class="chart-row">
                    <div class="chart-column main-chart">
                        <h2>Grafik Penjualan</h2>
                        <div class="chart-item">
                            <div class="chart-top-row">
                                <div id="salesLegend"></div>
                                <div class="chart-header">
                                    <select id="salesPeriodSelect">
                                        <option>Minggu ini</option>
                                        <option>Bulan ini</option>
                                    </select>
                                </div>
                            </div>
                            <div class="chart-canvas-container">
                                <canvas id="salesChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="chart-column side-chart">
                        <h2>Status Pesanan</h2>
                        <div class="chart-item">
                            <div class="chart-header">
                                <select id="orderStatusPeriodSelect">
                                    <option>Minggu ini</option>
                                    <option>Bulan ini</option>
                                </select>
                            </div>
                            <div class="chart-canvas-container">
                                <canvas id="orderStatusChart"></canvas>
                            </div>
                            <div id="orderStatusLegend"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="products-section">
                <h2>Produk Terlaris</h2>
                <table class="seller">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>NAMA</th>
                            <th>KATEGORI</th>
                            <th>HARGA</th>
                            <th>TERJUAL</th>
                            <th>STOK SAAT INI</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($topProducts)): ?>
                            <tr>
                                <td colspan="6" class="text-center">Belum ada data penjualan</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($topProducts as $index => $product): ?>
                                <tr>
                                    <td><span class="product-number"><?php echo $index + 1; ?></span></td>
                                    <td><?php echo htmlspecialchars($product->product->nama_produk); ?></td>
                                    <td><?php echo htmlspecialchars($product->product->kategori); ?></td>
                                    <td>Rp <?php echo number_format($product->product->harga, 0, ',', '.'); ?></td>
                                    <td><?php echo $product->totalSold; ?></td>
                                    <td><?php echo $product->product->stok; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            feather.replace({ width: 20, height: 20 });

            const salesData = {
                weekly: {
                    labels: <?php echo json_encode($salesChartData['labels']); ?>,
                    data: <?php echo json_encode($salesChartData['data']); ?>
                },
                monthly: {
                    labels: <?php echo json_encode($salesChartDataMonth['labels']); ?>,
                    data: <?php echo json_encode($salesChartDataMonth['data']); ?>
                }
            };

            const salesChart = new Chart(document.getElementById('salesChart').getContext('2d'), {
                type: 'bar',
                data: {
                    labels: salesData.weekly.labels,
                    datasets: [{
                        label: 'Unit Terjual',
                        data: salesData.weekly.data,
                        backgroundColor: 'rgba(255, 144, 83, 0.8)',
                        borderRadius: 5,
                        borderSkipped: false,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const value = context.raw;
                                    return value > 0 ? `${value} unit` : 'Tidak ada penjualan';
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1,
                                callback: function(value) {
                                    return value > 0 ? `${value} unit` : '0';
                                }
                            },
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    },
                },
            });

            // Tambahkan event listener untuk dropdown periode
            document.getElementById('salesPeriodSelect').addEventListener('change', function() {
                const period = this.value === 'Minggu ini' ? 'weekly' : 'monthly';
                
                salesChart.data.labels = salesData[period].labels;
                salesChart.data.datasets[0].data = salesData[period].data;
                salesChart.update();
            });

            // Data dari PHP
            const orderStatusData = <?php echo json_encode($chartData); ?>;

            const orderStatusConfig = {
                type: 'doughnut',
                data: {
                    labels: orderStatusData.labels,
                    datasets: [{
                        data: orderStatusData.data,
                        backgroundColor: [
                            '#fff3cd', // Pending - kuning
                            '#cce5ff', // Diproses - biru
                            '#e8f4fd', // Dikirim - biru muda
                            '#d1e7dd', // Selesai - hijau
                            '#f8d7da'  // Dibatalkan - merah
                        ],
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    return context.label + ': ' + context.raw.toLocaleString() + ' pesanan';
                                }
                            }
                        }
                    },
                    cutout: '55%',
                    radius: '75%'
                },
            };

            function createCustomLegend(elementId, labels, colors) {
                const legendContainer = document.getElementById(elementId);
                legendContainer.innerHTML = '';
                const ul = document.createElement('ul');
                ul.className = 'custom-legend';

        labels.forEach((label, index) => {
            const li = document.createElement('li');
            li.className = 'legend-item';
            
            const colorBox = document.createElement('span');
            colorBox.className = 'legend-color';
            colorBox.style.backgroundColor = colors[index];
            
            const text = document.createElement('span');
            text.className = 'legend-text';
            text.textContent = label;
            
            const count = document.createElement('span');
            count.className = 'legend-count';
            count.textContent = ` (${orderStatusData.data[index]})`;
            text.appendChild(count);
            
            li.appendChild(colorBox);
            li.appendChild(text);
            ul.appendChild(li);
        });

        legendContainer.appendChild(ul);
    }

    const orderStatusCtx = document.getElementById('orderStatusChart').getContext('2d');
    new Chart(orderStatusCtx, orderStatusConfig);
    createCustomLegend('orderStatusLegend', 
        orderStatusData.labels,
        ['#fff3cd', '#cce5ff', '#e8f4fd', '#d1e7dd', '#f8d7da']
    );
});
</script>
</body>
</html>