<?php

// Database configuration
$PG_host = getenv('PG_HOST');
$PG_port = getenv('PG_PORT') ?: 5432;
$PG_dbname = getenv('PG_DATABASE');
$PG_user = getenv('PG_USER');
$PG_password = getenv('PG_PASSWORD');

$safeMode = getenv('SAFE_MODE') ?: false;

// Create a PDO connection to the PostgreSQL database
try {
    $dsn = "pgsql:host=$PG_host;port=$PG_port;dbname=$PG_dbname;user=$PG_user;password=$PG_password";
    $pdo = new PDO($dsn);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
