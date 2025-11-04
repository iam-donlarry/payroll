<?php
// generate_hash.php
$password = "admin123";  // Change this to your desired password
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

echo "Plain password: " . $password . "<br>";
echo "Hashed password: " . $hashed_password . "<br>";
echo "<br>SQL Query to run in phpMyAdmin:<br>";
echo "UPDATE users SET password_hash = '" . $hashed_password . "' WHERE username = 'admin';";
?>