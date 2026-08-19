<?php
/**
 * Shared PDO Database Connection Helper
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../core/Logger.php';

$pdoInstance = null;

function createPdoConnection() {
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_TIMEOUT            => 5,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET SESSION wait_timeout=28800, interactive_timeout=28800",
    ];
    return new PDO($dsn, DB_USER, DB_PASS, $options);
}

function getDatabase() {
    global $pdoInstance;

    // If we have a cached instance, verify the connection is still alive
    if ($pdoInstance !== null) {
        try {
            $pdoInstance->query('SELECT 1');
        } catch (PDOException $e) {
            // Connection was lost (e.g. MySQL "server has gone away" / error 2006)
            $pdoInstance = null;
        }
    }

    if ($pdoInstance === null) {
        try {
            $pdoInstance = createPdoConnection();
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }

    return $pdoInstance;
}

function closeConnection() {
    global $pdoInstance;
    $pdoInstance = null;
}
