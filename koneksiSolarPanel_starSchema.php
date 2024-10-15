<?php
// Example database connection using PDO
$host = '10.38.11.3';
$dbname = 'star_schemas_solarpanel';
$username = 'root';
$password = 'ida6422690';

try {
    // Create PDO instance
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    // Set error mode to exception for better error reporting
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>

