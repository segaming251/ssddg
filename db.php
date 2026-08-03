<?php
// ═══════════════════════════════════════════════════════
//  WEBRAT v2.0 — Cấu hình Database (Hỗ trợ Đa CSDL)
// ═══════════════════════════════════════════════════════

// Database 1 (Mặc định)
define('DB_HOST', 'localhost');
define('DB_NAME', 'taklcfgs_Hoangcha');
define('DB_USER', 'taklcfgs_KhoaxHoang');
define('DB_PASS', '.kn8?.hZ8=0+DyZB');
define('DB_PORT', '3306');

// Database 2 (Mới bổ sung)
define('DB2_HOST', '103.77.241.140');
define('DB2_NAME', 'ctpzfsyl_Khoaxhoang');
define('DB2_USER', 'ctpzfsyl_KhoaxHoangluu');
define('DB2_PASS', 'T+terFOOwghW.Dht');
define('DB2_PORT', '3306');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    try {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        error_log('[WEBRAT DB1] ' . $e->getMessage());
        die('DATABASE_CONNECTION_FAILED');
    }

    return $pdo;
}

function getDB2(): PDO {
    static $pdo2 = null;
    if ($pdo2 !== null) return $pdo2;

    try {
        $dsn = 'mysql:host=' . DB2_HOST . ';port=' . DB2_PORT . ';dbname=' . DB2_NAME . ';charset=utf8mb4';
        $pdo2 = new PDO($dsn, DB2_USER, DB2_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
        ]);
    } catch (PDOException $e) {
        error_log('[WEBRAT DB2] ' . $e->getMessage());
        die('DATABASE2_CONNECTION_FAILED');
    }

    return $pdo2;
}

