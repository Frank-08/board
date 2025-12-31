<?php
/**
 * QR Code Generator Endpoint
 * Generates QR codes locally using PHP GD library
 */

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';

// Require authentication
requireAuth();

// Get the data to encode
$data = $_GET['data'] ?? '';

if (empty($data)) {
    http_response_code(400);
    die('Data parameter is required');
}

// Check if GD library is available
if (!function_exists('imagecreatetruecolor')) {
    http_response_code(503);
    die('GD library is required for QR code generation. Please install php-gd extension.');
}

// Include QR code library
$phpqrcodePath = __DIR__ . '/../libs/phpqrcode.php';
if (!file_exists($phpqrcodePath)) {
    http_response_code(503);
    header('Content-Type: text/html');
    echo '<!DOCTYPE html><html><head><title>QR Code Library Required</title></head><body>';
    echo '<h1>QR Code Library Not Found</h1>';
    echo '<p>Please download phpqrcode.php and place it in the libs/ directory.</p>';
    echo '<p><strong>Quick setup:</strong></p>';
    echo '<pre>cd ' . htmlspecialchars(__DIR__ . '/../libs') . '
wget https://raw.githubusercontent.com/t0k4rt/phpqrcode/master/qrlib.php -O phpqrcode.php</pre>';
    echo '<p>Or download from: <a href="https://sourceforge.net/projects/phpqrcode/">phpqrcode on SourceForge</a></p>';
    echo '</body></html>';
    exit;
}

require_once $phpqrcodePath;

// Generate QR code
// Error correction level: QR_ECLEVEL_L, QR_ECLEVEL_M, QR_ECLEVEL_Q, QR_ECLEVEL_H
$errorCorrectionLevel = QR_ECLEVEL_M;
// Pixel size (size of each module in pixels)
$matrixPointSize = 10;
// Margin (number of modules)
$margin = 2;

// Output QR code directly as PNG image
QRcode::png($data, false, $errorCorrectionLevel, $matrixPointSize, $margin);
