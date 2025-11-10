<?php
// Database connection
$host = "localhost";
$user = "root"; // change to your DB user
$pass = ""; // change to your DB password
$dbname = "nigeria_payroll_hr"; // change to your DB name

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Your Paystack secret key
$secretKey = "sk_test_ee360e630f9f6ba2f9776e8759a51b9575e4642f";
$url = "https://api.paystack.co/bank";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $secretKey"
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // just for testing
$response = curl_exec($ch);

if (curl_errno($ch)) {
    die("Curl Error: " . curl_error($ch));
}

curl_close($ch);

// Decode the JSON response
$data = json_decode($response, true);

// Check if data exists
if (isset($data['data']) && is_array($data['data'])) {
    $count = 0;

    foreach ($data['data'] as $bank) {
        $name = $conn->real_escape_string($bank['name']);
        $code = $conn->real_escape_string($bank['code']);

        // Use REPLACE INTO to insert or update existing records
        $sql = "REPLACE INTO banks (bank_name, bank_code) VALUES ('$name', '$code')";
        if ($conn->query($sql)) {
            $count++;
        } else {
            echo "Error inserting $name: " . $conn->error . "<br>";
        }
    }

    echo "✅ Bank list updated successfully ($count banks inserted/updated)";
} else {
    echo "❌ Failed to update bank list.<br><pre>";
    print_r($data);
    echo "</pre>";
}

$conn->close();
?>
