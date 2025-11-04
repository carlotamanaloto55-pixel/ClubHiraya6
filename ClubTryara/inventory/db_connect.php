<?php
$servername = "localhost";
$username = "root";      // or your phpMyAdmin username
$password = "";          // or your phpMyAdmin password
$dbname = "clubhiraya"; // change this to your actual DB name

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
