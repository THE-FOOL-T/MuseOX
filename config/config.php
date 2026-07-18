<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_lifetime' => 86400,
        'cookie_secure'   => false,
        'cookie_httponly' => true,
        'cookie_samesite' => 'Strict'
    ]);
}

// Oracle Database Configurations
define('DB_USER', 'museOX');           
define('DB_PASS', '2022');    
define('DB_CONN_STR', 'localhost/XE'); 

define('BASE_URL', 'http://localhost/MuseoX/');

// Error configuration settings for secure execution controls
error_reporting(E_ALL);
ini_set('display_errors', '1');