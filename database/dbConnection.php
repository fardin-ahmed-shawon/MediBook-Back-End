<?php
$servername = "localhost";

//--------------------------------------------------------------------
// For local development ---------------------------------------------
$site_link = "";
$username = "root";
$password = "";
$database_name = "";

// For production -----------------------------------------------------
// $site_link = "";
// $username = "";
// $password = "";
// $database_name = "";

//---------------------------------------------------------------------

// Create connection
$conn = new mysqli($servername, $username, $password, $database_name);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

?>