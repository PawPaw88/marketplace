# Marketplace Furniture

Selamat datang di proyek Marketplace Furniture! Ini adalah aplikasi web untuk jual beli furniture yang menggunakan PHP dan MongoDB.

## Persyaratan Sistem

- PHP 7.4 atau lebih baru
- MongoDB 4.4 atau lebih baru
- Web Server (misalnya Apache)
- Ekstensi MongoDB untuk PHP
- MongoDB Database Tools

## Langkah-langkah Membuka Proyek

1. Clone atau unduh repositori ini ke folder web server Anda.

2. Pastikan MongoDB terinstal dan berjalan di sistem Anda.

3. Buka Command Prompt atau Terminal, navigasikan ke folder proyek anda

4. Impor database yang sudah disediakan menggunakan perintah:

(pastikan untuk mendownload MongoDB Database Tools terlebih dahulu dan menambahkan folder bin nya ke path)

```

mongorestore --db marketplace database_dump/marketplace

```

5. Buka file `config/db.php` dan pastikan konfigurasi sesuai:

```php
<?php
function connectDB() {
    try {
        $m = new MongoDB\Driver\Manager("mongodb://localhost:27017/marketplace");
        return $m;
    } catch (MongoDB\Driver\Exception\Exception $e) {
        die("Failed to connect to database: " . $e->getMessage());
    }
}
?>
```

6. Jalankan web server Anda (misalnya Apache melalui XAMPP).

7. Buka browser dan akses:
   ```
   http://localhost/marketplace
   ```
   Sesuaikan URL jika menggunakan konfigurasi yang berbeda.

## Akun Demo:
Penjual:
 - bahtiar@gmail.com - bahtiar123
 - seller@gmail.com - seller123
 - tes@gmail.com - tes123
 - new@gmail.com - new123

Pembeli:
 - buyer@gmail.com - buyer123
 - asep@gmail.com - asep123

