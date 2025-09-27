<?php
require_once __DIR__ . '/includes/auth.php';
$servername = "localhost";
$username = "kritsanai";
$password = "kritsanai1234";
$dbname = "bigproject";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Set charset to utf8
$conn->set_charset("utf8");

// Check connection
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}
?>