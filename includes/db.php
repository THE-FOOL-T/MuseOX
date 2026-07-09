<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';

class Database {
    private static $instance = null;

    // Returns an Oracle PDO connection
    public static function getConnection() {
        if (self::$instance === null) {
            try {
                $dsn = "oci:dbname=" . DB_CONN_STR;
                self::$instance = new PDO($dsn, DB_USER, DB_PASS);
                self::$instance->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            } catch (PDOException $e) {
                error_log("Database connection failure context details: " . $e->getMessage());
                die("Critical execution failure: An unresolvable structural fallback exception occurred.");
            }
        }
        return self::$instance;
    }
}