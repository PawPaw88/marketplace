<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'Penjual') {
    header('Location: ../../index.php');
    exit();
}

include '../../config/db.php';
$m = connectDB();
$user = $_SESSION['user'];

// Fungsi untuk mendapatkan tanggal awal berdasarkan periode
function getStartDate($days) {
    $date = new DateTime();
    $date->sub(new DateInterval("P{$days}D"));
    return new MongoDB\BSON\UTCDateTime($date->getTimestamp() * 1000);
}

// Ambil data pesanan dan pendapatan
$startDate = getStartDate(30); // default 30 hari

$orderPipeline = [
    ['$match' => [
        'penjual_id' => new MongoDB\BSON\ObjectId($user->_id),
        'created_at' => ['$gte' => $startDate]
    ]],
    ['$group' => [
        '_id' => null,
        'total_pendapatan' => ['$sum' => '$total_harga'],
        'total_pesanan' => ['$sum' => 1],
        'unique_customers' => ['$addToSet' => '$pembeli_id']
    ]]
];

$command = new MongoDB\Driver\Command([
    'aggregate' => 'Pesanan',
    'pipeline' => $orderPipeline,
    'cursor' => new stdClass,
]);

$cursor = $m->executeCommand('marketplace', $command);
$stats = current($cursor->toArray());

// Ambil data tren penjualan harian
$trendPipeline = [
    ['$match' => [
        'penjual_id' => new MongoDB\BSON\ObjectId($user->_id),
        'created_at' => ['$gte' => $startDate]
    ]],
    ['$group' => [
        '_id' => ['$dateToString' => ['format' => '%Y-%m-%d', 'date' => '$created_at']],
        'pendapatan' => ['$sum' => '$total_harga'],
        'pesanan' => ['$sum' => 1]
    ]],
    ['$sort' => ['_id' => 1]]
];

$command = new MongoDB\Driver\Command([
    'aggregate' => 'Pesanan',
    'pipeline' => $trendPipeline,
    'cursor' => new stdClass,
]);

$cursor = $m->executeCommand('marketplace', $command);
$trendData = $cursor->toArray();

// Ambil produk terlaris
$topProductsPipeline = [
    ['$match' => [
        'penjual_id' => new MongoDB\BSON\ObjectId($user->_id),
        'created_at' => ['$gte' => $startDate]
    ]],
    ['$group' => [
        '_id' => '$product_id',
        'total_terjual' => ['$sum' => '$quantity']
    ]],
    ['$lookup' => [
        'from' => 'Produk',
        'localField' => '_id',
        'foreignField' => '_id',
        'as' => 'product'
    ]],
    ['$unwind' => '$product'],
    ['$sort' => ['total_terjual' => -1]],
    ['$limit' => 5]
];

$command = new MongoDB\Driver\Command([
    'aggregate' => 'Pesanan',
    'pipeline' => $topProductsPipeline,
    'cursor' => new stdClass,
]);

$cursor = $m->executeCommand('marketplace', $command);
$topProducts = $cursor->toArray();

// Ambil kategori terpopuler
$categoryPipeline = [
    ['$match' => [
        'penjual_id' => new MongoDB\BSON\ObjectId($user->_id),
        'created_at' => ['$gte' => $startDate]
    ]],
    ['$lookup' => [
        'from' => 'Produk',
        'localField' => 'product_id',
        'foreignField' => '_id',
        'as' => 'product'
    ]],
    ['$unwind' => '$product'],
    ['$group' => [
        '_id' => '$product.kategori',
        'total' => ['$sum' => 1]
    ]],
    ['$sort' => ['total' => -1]]
];

$command = new MongoDB\Driver\Command([
    'aggregate' => 'Pesanan',
    'pipeline' => $categoryPipeline,
    'cursor' => new stdClass,
]);

$cursor = $m->executeCommand('marketplace', $command);
$categories = $cursor->toArray();

// Konversi data untuk JavaScript
$jsData = [
    'trend' => array_map(function($item) {
        return [
            'date' => $item->_id,
            'pendapatan' => $item->pendapatan,
            'pesanan' => $item->pesanan
        ];
    }, $trendData),
    'categories' => array_map(function($item) {
        return [
            'label' => $item->_id,
            'value' => $item->total
        ];
    }, $categories)
];

// Fungsi untuk menghitung persentase perubahan
function calculatePercentageChange($current, $previous) {
    if ($previous == 0) return 0;
    return round((($current - $previous) / $previous) * 100);
}

// Ambil data periode sebelumnya (30 hari sebelum startDate)
$previousStartDate = getStartDate(60); // 60 hari yang lalu
$previousEndDate = $startDate; // Akhir periode sebelumnya adalah awal periode saat ini

// Pipeline untuk periode sebelumnya
$previousPipeline = [
    ['$match' => [
        'penjual_id' => new MongoDB\BSON\ObjectId($user->_id),
        'created_at' => [
            '$gte' => $previousStartDate,
            '$lt' => $previousEndDate
        ]
    ]],
    ['$group' => [
        '_id' => null,
        'total_pendapatan' => ['$sum' => '$total_harga'],
        'total_pesanan' => ['$sum' => 1],
        'unique_customers' => ['$addToSet' => '$pembeli_id']
    ]]
];

$command = new MongoDB\Driver\Command([
    'aggregate' => 'Pesanan',
    'pipeline' => $previousPipeline,
    'cursor' => new stdClass,
]);

$cursor = $m->executeCommand('marketplace', $command);
$previousStats = current($cursor->toArray());

// Hitung persentase perubahan
$revenueChange = calculatePercentageChange(
    $stats ? $stats->total_pendapatan : 0,
    $previousStats ? $previousStats->total_pendapatan : 0
);

$ordersChange = calculatePercentageChange(
    $stats ? $stats->total_pesanan : 0,
    $previousStats ? $previousStats->total_pesanan : 0
);

$customersChange = calculatePercentageChange(
    $stats ? count($stats->unique_customers) : 0,
    $previousStats ? count($previousStats->unique_customers) : 0
);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Analitik - MyFurniture</title>
    <link rel="stylesheet" href="../../assets/style/seller.css" />
    <link rel="stylesheet" href="../../assets/style/sidebar.css" />
    <link rel="stylesheet" href="../../assets/style/analytics.css" />
    <script src="https://unpkg.com/feather-icons@4.29.0/dist/feather.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="main-container">
        <nav class="sidebar">
            <div class="logo">MyFurniture.</div>
            <ul class="nav-links">
                <li><a href="seller.php"><i data-feather="grid"></i> <span>Dashboard</span></a></li>
                <li><a href="products.php"><i data-feather="shopping-bag"></i> <span>Produk</span></a></li>
                <li><a href="analytics.php" class="active"><i data-feather="pie-chart"></i> <span>Laporan</span></a></li>
                <li><a href="orders.php"><i data-feather="shopping-cart"></i> <span>Pesanan</span></a></li>
                <li><a href="profile.php"><i data-feather="user"></i> <span>Profil</span></a></li>
            </ul>
        </nav>

        <main class="content">
            <div class="header-content">
                <div class="greeting">
                    <h1>Analitik Toko</h1>
                    <p>Pantau performa toko Anda</p>
                </div>
                <div class="date-filter">
                    <select id="periodFilter">
                        <option value="7">7 Hari Terakhir</option>
                        <option value="30">30 Hari Terakhir</option>
                        <option value="90">90 Hari Terakhir</option>
                    </select>
                </div>
            </div>

            <div class="analytics-grid">
                <div class="analytics-card revenue">
                    <div class="card-header">
                        <h3>Total Pendapatan</h3>
                        <i data-feather="dollar-sign"></i>
                    </div>
                    <div class="card-value">Rp <?php echo number_format($stats ? $stats->total_pendapatan : 0, 0, ',', '.'); ?></div>
                    <div class="card-trend <?php echo $revenueChange >= 0 ? 'positive' : 'negative'; ?>">
                        <i data-feather="<?php echo $revenueChange >= 0 ? 'trending-up' : 'trending-down'; ?>"></i>
                        <span><?php echo abs($revenueChange); ?>% dari periode sebelumnya</span>
                    </div>
                </div>

                <div class="analytics-card orders">
                    <div class="card-header">
                        <h3>Total Pesanan</h3>
                        <i data-feather="shopping-cart"></i>
                    </div>
                    <div class="card-value"><?php echo $stats ? $stats->total_pesanan : 0; ?></div>
                    <div class="card-trend <?php echo $ordersChange >= 0 ? 'positive' : 'negative'; ?>">
                        <i data-feather="<?php echo $ordersChange >= 0 ? 'trending-up' : 'trending-down'; ?>"></i>
                        <span><?php echo abs($ordersChange); ?>% dari periode sebelumnya</span>
                    </div>
                </div>

                <div class="analytics-card customers">
                    <div class="card-header">
                        <h3>Pelanggan Baru</h3>
                        <i data-feather="users"></i>
                    </div>
                    <div class="card-value"><?php echo $stats ? count($stats->unique_customers) : 0; ?></div>
                    <div class="card-trend <?php echo $customersChange >= 0 ? 'positive' : 'negative'; ?>">
                        <i data-feather="<?php echo $customersChange >= 0 ? 'trending-up' : 'trending-down'; ?>"></i>
                        <span><?php echo abs($customersChange); ?>% dari periode sebelumnya</span>
                    </div>
                </div>
            </div>

            <div class="chart-container">
                <div class="chart-card">
                    <div class="chart-header">
                        <h3>Tren Penjualan</h3>
                        <div class="chart-actions">
                            <select id="salesMetric">
                                <option value="revenue">Pendapatan</option>
                                <option value="orders">Jumlah Pesanan</option>
                            </select>
                        </div>
                    </div>
                    <canvas id="salesTrendChart"></canvas>
                </div>
            </div>

            <div class="analytics-details">
                <div class="detail-card">
                    <h3>Produk Terlaris</h3>
                    <div class="product-list">
                        <?php if (empty($topProducts)): ?>
                            <div class="product-item">
                                <span class="product-rank">-</span>
                                <div class="product-info">
                                    <span class="product-name">Belum ada data</span>
                                    <span class="product-sales">0 terjual</span>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($topProducts as $index => $product): ?>
                                <div class="product-item">
                                    <span class="product-rank"><?php echo $index + 1; ?></span>
                                    <div class="product-info">
                                        <span class="product-name"><?php echo htmlspecialchars($product->product->nama_produk); ?></span>
                                        <span class="product-sales"><?php echo $product->total_terjual; ?> terjual</span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="detail-card">
                    <h3>Kategori Terpopuler</h3>
                    <div class="category-chart-container">
                        <canvas id="categoryChart"></canvas>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            feather.replace({ width: 20, height: 20 });

            const trendData = <?php echo json_encode($jsData['trend']); ?>;
            const categoryData = <?php echo json_encode($jsData['categories']); ?>;
            
            // Update chart tren penjualan
            const salesTrendChart = new Chart(document.getElementById('salesTrendChart').getContext('2d'), {
                type: 'line',
                data: {
                    labels: trendData.map(item => item.date),
                    datasets: [{
                        label: 'Pendapatan',
                        data: trendData.map(item => item.pendapatan),
                        borderColor: '#3c5850',
                        tension: 0.4,
                        fill: false
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: value => 'Rp ' + value.toLocaleString('id-ID')
                            }
                        }
                    }
                }
            });

            // Update chart kategori
            const categoryChart = new Chart(document.getElementById('categoryChart').getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: categoryData.map(item => item.label),
                    datasets: [{
                        data: categoryData.map(item => item.value),
                        backgroundColor: [
                            '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF'
                        ],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom' }
                    }
                }
            });

            // Event listener untuk perubahan metrik
            document.getElementById('salesMetric').addEventListener('change', function() {
                const metric = this.value;
                salesTrendChart.data.datasets[0] = {
                    label: metric === 'revenue' ? 'Pendapatan' : 'Jumlah Pesanan',
                    data: trendData.map(item => metric === 'revenue' ? item.pendapatan : item.pesanan),
                    borderColor: '#3c5850',
                    tension: 0.4,
                    fill: false
                };
                salesTrendChart.update();
            });

            // Event listener untuk filter periode
            document.getElementById('periodFilter').addEventListener('change', function() {
                // Implementasi logika filter di sini
                console.log('Period changed:', this.value);
            });
        });
    </script>
</body>
</html> 