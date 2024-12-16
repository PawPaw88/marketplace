<?php
function connectDB() {
    $host = 'localhost';  
    $port = '27017';      
    $dbname = 'marketplace';  

    try {
        $m = new MongoDB\Driver\Manager("mongodb://{$host}:{$port}/{$dbname}");
        return $m;
    } catch (MongoDB\Driver\Exception\Exception $e) {
        die("Failed to connect to database: " . $e->getMessage());
    }
}