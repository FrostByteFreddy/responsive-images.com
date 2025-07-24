<?php
// The directory where your ZIP files are stored
$resultsDir = 'results/';

// Get the filename from the URL, e.g., download.php?file=my-archive.zip
$fileName = isset($_GET['file']) ? basename($_GET['file']) : '';

// --- SECURITY CHECKS ---
// 1. Ensure a filename was provided
// 2. Ensure the file exists
// 3. Ensure the file is a .zip file
// 4. Ensure the filename doesn't contain ".." to prevent directory traversal
if ($fileName && file_exists($resultsDir . $fileName) && pathinfo($fileName, PATHINFO_EXTENSION) === 'zip' && !str_contains($fileName, '..')) {

    $filePath = $resultsDir . $fileName;

    // Send headers to the browser to trigger a download dialog
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Content-Length: ' . filesize($filePath));
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');

    // Read the file and send its contents to the browser
    readfile($filePath);
    exit;
} else {
    // If checks fail, show a 404 Not Found error
    http_response_code(404);
    echo '<h1>404 Not Found</h1>';
    echo 'The file you requested could not be found.';
}