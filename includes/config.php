<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'ipl_auction');

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database if not exists
$sql = "CREATE DATABASE IF NOT EXISTS " . DB_NAME;
if ($conn->query($sql) === TRUE) {
    $conn->select_db(DB_NAME);
} else {
    die("Error creating database: " . $conn->error);
}

// Create Tables
$tables = [
    "users" => "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        role ENUM('admin', 'team') NOT NULL,
        team_name VARCHAR(100),
        budget DECIMAL(15,2) DEFAULT 100000000.00,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    "players" => "CREATE TABLE IF NOT EXISTS players (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        category ENUM('Batsman', 'Bowler', 'All-rounder', 'Wicketkeeper') NOT NULL,
        base_price DECIMAL(15,2) NOT NULL,
        current_price DECIMAL(15,2) DEFAULT NULL,
        team_id INT DEFAULT NULL,
        status ENUM('available', 'sold', 'unsold', 'bidding') DEFAULT 'available',
        image_url VARCHAR(255) DEFAULT NULL,
        FOREIGN KEY (team_id) REFERENCES users(id) ON DELETE SET NULL
    )",
    "bids" => "CREATE TABLE IF NOT EXISTS bids (
        id INT AUTO_INCREMENT PRIMARY KEY,
        player_id INT NOT NULL,
        user_id INT NOT NULL,
        bid_amount DECIMAL(15,2) NOT NULL,
        bid_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )",
    "settings" => "CREATE TABLE IF NOT EXISTS settings (
        id INT PRIMARY KEY,
        auction_status ENUM('not_started', 'active', 'paused', 'ended') DEFAULT 'not_started',
        active_player_id INT DEFAULT NULL,
        FOREIGN KEY (active_player_id) REFERENCES players(id) ON DELETE SET NULL
    )"
];

foreach ($tables as $name => $sql) {
    if (!$conn->query($sql)) {
        die("Error creating table $name: " . $conn->error);
    }
}

// Insert default settings if not exists
$conn->query("INSERT IGNORE INTO settings (id, auction_status) VALUES (1, 'not_started')");

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>
