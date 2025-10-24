<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Attempting to connect to Google API Certs URL...<br>";

// --- IMPORTANT: Make sure this path matches your php.ini setting ---
$ca_bundle_path = 'C:/xampp/php/extras/ssl/cacert.pem'; 

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://www.googleapis.com/oauth2/v3/certs');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

// Explicitly tell cURL where the CA bundle is
if (file_exists($ca_bundle_path)) {
    curl_setopt($ch, CURLOPT_CAINFO, $ca_bundle_path);
    echo "Using CA bundle: " . $ca_bundle_path . "<br>";
} else {
    echo "<b>Error: CA bundle not found at specified path: " . $ca_bundle_path . "</b><br>";
    // You might try commenting out the CAINFO line below if the path fails, 
    // just to see if cURL works without explicit bundle path (less secure)
    // curl_setopt($ch, CURLOPT_CAINFO, $ca_bundle_path); 
}

curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // Enforce verification
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
curl_setopt($ch, CURLOPT_TIMEOUT, 15); // Add a timeout

$result = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error_num = curl_errno($ch);
$curl_error_msg = curl_error($ch);

curl_close($ch);

echo "HTTP Status Code: " . $httpcode . "<br>";

if ($curl_error_num > 0) {
    echo "<b>cURL Error (" . $curl_error_num . "): " . $curl_error_msg . "</b><br>";
} else {
    echo "<b>cURL connection successful!</b><br>";
    echo "Response (first 100 chars): <pre>" . htmlspecialchars(substr($result, 0, 100)) . "...</pre><br>";
}
?>