<?php
session_start();
include('config/db.php');

if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit();
}

$m = connectDB();
$userId = new MongoDB\BSON\ObjectId($_SESSION['user']->_id);
$isLoggedIn = isset($_SESSION['user']);

// Tambahkan di bagian atas file setelah koneksi database
if (isset($_GET['seller_id'])) {
    $sellerId = new MongoDB\BSON\ObjectId($_GET['seller_id']);
    
    // Tambahkan pengecekan product_id
    $productData = null;
    if (isset($_GET['product_id'])) {
        try {
            $productId = new MongoDB\BSON\ObjectId($_GET['product_id']);
            
            // Ambil data produk
            $filter = ['_id' => $productId];
            $query = new MongoDB\Driver\Query($filter);
            $cursor = $m->executeQuery('marketplace.Produk', $query);
            $product = current($cursor->toArray());
            
            if ($product) {
                $productData = [
                    'id' => (string)$product->_id,
                    'nama' => $product->nama_produk,
                    'harga' => $product->harga,
                    'gambar' => $product->gambar[0]
                ];
            }
        } catch (Exception $e) {
            error_log("Error fetching product data: " . $e->getMessage());
        }
    }
    
    // Ambil data penjual
    $filter = ['_id' => $sellerId];
    $query = new MongoDB\Driver\Query($filter);
    $cursor = $m->executeQuery('marketplace.Penjual', $query);
    $seller = current($cursor->toArray());
    
    if (!$seller) {
        header('Location: index.php');
        exit();
    }
    
    // Cek apakah sudah ada chat dengan penjual ini
    $filter = [
        '$or' => [
            [
                'pengirim_id' => $userId,
                'penerima_id' => $sellerId
            ],
            [
                'pengirim_id' => $sellerId,
                'penerima_id' => $userId
            ]
        ]
    ];
    
    $query = new MongoDB\Driver\Query($filter);
    $cursor = $m->executeQuery('marketplace.Chat', $query);
    $existingChat = current($cursor->toArray());
    
    // Jika belum ada chat, buat chat baru
    if (!$existingChat) {
        $bulk = new MongoDB\Driver\BulkWrite;
        $chat = [
            'pengirim_id' => $userId,
            'penerima_id' => $sellerId,
            'created_at' => new MongoDB\BSON\UTCDateTime(),
            'dibaca' => false
        ];
        
        // Tambahkan data produk jika ada
        if ($productData) {
            $chat['product_data'] = $productData;
        }
        
        $bulk->insert($chat);
        $m->executeBulkWrite('marketplace.Chat', $bulk);
    }
    
    // Tambahkan script untuk otomatis membuka chat dengan penjual dan refresh chat
    echo "<script>
        window.onload = function() {
            const sellerChatItem = document.querySelector(`.chat-item[data-user-id=\"$sellerId\"]`);
            if (sellerChatItem) {
                sellerChatItem.click();
                sellerChatItem.classList.add('active');
                
                // Mulai interval refresh untuk chat yang aktif
                startChatRefresh(\"$sellerId\");
            }
        }
    </script>";
}

// Mengambil daftar chat
$pipeline = [
    ['$match' => [
        '$or' => [
            ['pengirim_id' => $userId],
            ['penerima_id' => $userId]
        ]
    ]],
    ['$lookup' => [
        'from' => 'Penjual',
        'localField' => 'penerima_id',
        'foreignField' => '_id',
        'as' => 'penjual'
    ]],
    ['$lookup' => [
        'from' => 'Users',
        'localField' => 'pengirim_id',
        'foreignField' => '_id',
        'as' => 'pengirim'
    ]],
    ['$sort' => ['created_at' => -1]],
    ['$group' => [
        '_id' => [
            '$cond' => [
                'if' => ['$eq' => ['$pengirim_id', $userId]],
                'then' => '$penerima_id',
                'else' => '$pengirim_id'
            ]
        ],
        'last_message' => ['$first' => '$pesan'],
        'last_message_time' => ['$first' => '$created_at'],
        'penjual' => ['$first' => '$penjual'],
        'pengirim' => ['$first' => '$pengirim']
    ]]
];

$command = new MongoDB\Driver\Command([
    'aggregate' => 'Chat',
    'pipeline' => $pipeline,
    'cursor' => new stdClass,
]);

$cursor = $m->executeCommand('marketplace', $command);
$chats = $cursor->toArray();

// Handle pengiriman pesan baru via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'sendMessage') {
        try {
            $penerima_id = new MongoDB\BSON\ObjectId($_POST['penerima_id']);
            $pesan = htmlspecialchars($_POST['pesan']);
            
            $chat = [
                'pengirim_id' => $userId,
                'penerima_id' => $penerima_id,
                'pesan' => $pesan,
                'created_at' => new MongoDB\BSON\UTCDateTime(),
                'dibaca' => false
            ];
            
            // Tambahkan data produk jika ada
            if (isset($_POST['product_data'])) {
                $chat['product_data'] = json_decode($_POST['product_data'], true);
            }
            
            $bulk = new MongoDB\Driver\BulkWrite;
            $bulk->insert($chat);
            $m->executeBulkWrite('marketplace.Chat', $bulk);
            
            echo json_encode(['success' => true]);
            exit;
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat - MyFurniture</title>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/style/main.css">
    <link rel="stylesheet" href="assets/style/chat.css">
</head>
<body>
    <?php if ($isLoggedIn): ?>
    <nav class="mobile-nav">
        <a href="index.php" class="mobile-nav-item"><i class="ri-home-line"></i></a>
        <a href="order.php" class="mobile-nav-item"><i class="ri-file-list-3-line"></i></a>
        <a href="wishlist.php" class="mobile-nav-item"><i class="ri-heart-line"></i></a>
        <a href="user.php" class="mobile-nav-item"><i class="ri-user-line"></i></a>
    </nav>
    <?php endif; ?>

    <main class="chat-container">
        <a href="index.php" class="back-button">
            <i class="ri-arrow-left-line"></i>
            Kembali
        </a>
        
        <div class="chat-wrapper">
            <div class="chat-list">
                <div class="chat-list-header">
                    <h2>Pesan</h2>
                    <div class="chat-search">
                        <i class="ri-search-line"></i>
                        <input type="text" placeholder="Cari chat...">
                    </div>
                </div>

                <div class="chat-list-content">
                    <?php if (empty($chats)): ?>
                        <div class="empty-state">
                            <i class="ri-message-3-line"></i>
                            <p>Belum ada pesan</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($chats as $chat): 
                            $otherUserId = $chat->_id;
                            $otherUser = null;
                            
                            if (!empty($chat->penjual)) {
                                $otherUser = $chat->penjual[0];
                            } elseif (!empty($chat->pengirim)) {
                                $otherUser = $chat->pengirim[0];
                            }
                            
                            if ($otherUser): 
                        ?>
                            <div class="chat-item" data-user-id="<?php echo $otherUserId; ?>">
                                <img src="assets/img/avatar/<?php echo htmlspecialchars($otherUser->avatar ?? '1.jpg'); ?>" 
                                     alt="<?php echo htmlspecialchars($otherUser->username ?? 'User'); ?>" 
                                     class="chat-avatar">
                                <div class="chat-info">
                                    <div class="chat-header">
                                        <h3><?php echo htmlspecialchars($otherUser->username ?? 'User'); ?></h3>
                                        <span class="chat-time">
                                            <?php 
                                                $time = $chat->last_message_time->toDateTime();
                                                echo $time->format('H:i');
                                            ?>
                                        </span>
                                    </div>
                                    <p class="chat-preview"><?php echo htmlspecialchars($chat->last_message); ?></p>
                                </div>
                            </div>
                        <?php 
                            endif;
                        endforeach; 
                        ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="chat-content">
                <div class="chat-placeholder">
                    <i class="ri-message-3-line"></i>
                    <p>Pilih chat untuk mulai berkomunikasi</p>
                </div>

                <div class="chat-messages" style="display: none;">
                    <div class="chat-header">
                        <div class="chat-user-info">
                            <img src="" alt="" class="chat-user-avatar">
                            <h3 class="chat-user-name"></h3>
                        </div>
                    </div>

                    <div class="messages-container"></div>

                    <div class="chat-input">
                        <div class="chat-input-wrapper">
                            <div id="productPreview" style="display: none;" class="product-preview">
                                <!-- Product preview akan ditambahkan melalui JavaScript -->
                            </div>
                            <input type="text" placeholder="Ketik pesan...">
                        </div>
                        <button class="send-message">
                            <i class="ri-send-plane-fill"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
    const currentUserId = '<?php echo $_SESSION['user']->_id; ?>';
    
    let refreshInterval;

    function startChatRefresh(userId) {
        // Hentikan interval sebelumnya jika ada
        if (refreshInterval) {
            clearInterval(refreshInterval);
        }
        
        // Mulai interval baru
        refreshInterval = setInterval(() => refreshMessages(userId), 3000);
    }

    document.querySelectorAll('.chat-item').forEach(item => {
        item.addEventListener('click', async () => {
            // Hapus kelas active dari semua chat item
            document.querySelectorAll('.chat-item').forEach(i => i.classList.remove('active'));
            item.classList.add('active');
            
            const userId = item.dataset.userId;
            
            // Mulai refresh interval untuk chat yang baru dipilih
            startChatRefresh(userId);
            
            // Load pesan
            await refreshMessages(userId);
        });
    });

    async function refreshMessages(userId) {
        try {
            const response = await fetch(`api/messages.php?user_id=${userId}`);
            const messages = await response.json();
            
            document.querySelector('.chat-placeholder').style.display = 'none';
            document.querySelector('.chat-messages').style.display = 'flex';
            
            // Update tampilan pesan
            const messagesContainer = document.querySelector('.messages-container');
            messagesContainer.innerHTML = messages.map(msg => `
                <div class="message ${msg.pengirim_id === currentUserId ? 'sent' : 'received'}">
                    <div class="message-content">${msg.pesan}</div>
                    ${msg.product_data ? `
                        <div class="message-product">
                            <img src="assets/img/products/${msg.product_data.gambar}" 
                                 alt="${msg.product_data.nama}"
                                 onerror="this.src='assets/img/products/default.jpg'">
                            <div class="message-product-info">
                                <div class="message-product-name">${msg.product_data.nama}</div>
                                <div class="message-product-price">Rp ${msg.product_data.harga.toLocaleString('id-ID')}</div>
                            </div>
                        </div>
                    ` : ''}
                    <div class="message-time">
                        ${new Date(msg.created_at.$date).toLocaleTimeString()}
                    </div>
                </div>
            `).join('');
            
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        } catch (error) {
            console.error('Error refreshing messages:', error);
        }
    }

    // Bersihkan interval saat pengguna meninggalkan halaman
    window.addEventListener('beforeunload', () => {
        if (refreshInterval) {
            clearInterval(refreshInterval);
        }
    });
    </script>
</body>
</html> 