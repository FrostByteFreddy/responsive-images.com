<?php

// Function to send a JSON error response and exit
function send_json_error($message, $code = 400) {
    header('Content-Type: application/json');
    http_response_code($code);
    echo json_encode(['status' => 'error', 'message' => $message]);
    exit();
}

// 1. Initial Setup & Validation
// --------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_error('Invalid request method.');
}

if (!isset($_FILES['source_image']) || $_FILES['source_image']['error'] !== UPLOAD_ERR_OK) {
    send_json_error('File upload error or no file uploaded.');
}

if ($_FILES['source_image']['size'] > 15 * 1024 * 1024) { // 15MB limit
    send_json_error('File size exceeds the 15MB limit.');
}

// 2. Handle Secure File Upload
// --------------------------------------------------------------------------
$upload_dir = 'uploads' . DIRECTORY_SEPARATOR;
$results_dir = 'results' . DIRECTORY_SEPARATOR;
if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
if (!is_dir($results_dir)) mkdir($results_dir, 0755, true);

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime_type = $finfo->file($_FILES['source_image']['tmp_name']);
$allowed_mime_types = ['image/jpeg', 'image/png', 'image/webp'];

if (!in_array($mime_type, $allowed_mime_types)) {
    send_json_error('Invalid file type. Please upload a JPG, PNG, or WebP image.');
}

list($width, $height) = getimagesize($_FILES['source_image']['tmp_name']);
if ($width > 8000 || $height > 8000) {
    send_json_error('Image dimensions are too large (max 8000x8000 pixels).');
}

$original_filename = $_FILES['source_image']['name'];
$original_basename = pathinfo($original_filename, PATHINFO_FILENAME);
$original_extension = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));

$temp_source_filename = uniqid('source_', true) . '.' . $original_extension;
$source_image_path = $upload_dir . $temp_source_filename;

if (!move_uploaded_file($_FILES['source_image']['tmp_name'], $source_image_path)) {
    send_json_error('Failed to move uploaded file.');
}

// 3. Receive and Parse Input Data
// --------------------------------------------------------------------------
$json_config = json_decode($_POST['json_config'] ?? '', true);
if (json_last_error() !== JSON_ERROR_NONE || !isset($json_config['outputs'])) {
    send_json_error('Invalid JSON configuration.');
}

// **CORRECTED:** Get and sanitize the base path from the form submission
$base_path = $_POST['base_path'] ?? '';
$base_path = preg_replace('/[^a-zA-Z0-9\/_.-]/', '', $base_path);
if (!empty($base_path)) {
    $base_path = rtrim($base_path, '/') . '/';
}

$focal_x_percent = floatval(rtrim($_POST['focalPointX'] ?? '50', '%')) / 100;
$focal_y_percent = floatval(rtrim($_POST['focalPointY'] ?? '50', '%')) / 100;
$outputs = $json_config['outputs'];

// 4. Prepare Output Directory
// --------------------------------------------------------------------------
$date_str = date('Y-m-d');
$unique_id = bin2hex(random_bytes(4));
$base_output_name = 'responsive-images-' . $date_str . '-' . $unique_id;

$final_output_path = $results_dir . $base_output_name;
if (!mkdir($final_output_path, 0755, true)) {
    send_json_error('Failed to create output directory.');
}

// 5. Core Image Processing Loop (with GD)
// --------------------------------------------------------------------------
$source_image_resource = null;
switch ($mime_type) {
    case 'image/jpeg': $source_image_resource = imagecreatefromjpeg($source_image_path); break;
    case 'image/png': $source_image_resource = imagecreatefrompng($source_image_path); break;
    case 'image/webp': $source_image_resource = imagecreatefromwebp($source_image_path); break;
}

if (!$source_image_resource) {
    send_json_error('Failed to load image resource.');
}

$original_width = imagesx($source_image_resource);
$original_height = imagesy($source_image_resource);
$original_aspect = $original_width / $original_height;
$generated_files = [];

foreach ($outputs as $output) {
    $target_width = isset($output['width']) ? intval($output['width']) : 0;
    $target_height = isset($output['height']) ? intval($output['height']) : 0;
    if ($target_width <= 0 || $target_height <= 0) continue;

    $allowed_formats = ['jpeg', 'jpg', 'png', 'webp'];
    $format = isset($output['format']) ? strtolower($output['format']) : '';
    if (!in_array($format, $allowed_formats)) continue;
    
    $target_aspect = $target_width / $target_height;
    $new_width = ($original_aspect > $target_aspect) ? intval($target_height * $original_aspect) : $target_width;
    $new_height = ($original_aspect > $target_aspect) ? $target_height : intval($target_width / $original_aspect);

    $resized_img = imagecreatetruecolor($new_width, $new_height);
    imagecopyresampled($resized_img, $source_image_resource, 0, 0, 0, 0, $new_width, $new_height, $original_width, $original_height);

    $focal_point_x_px = $new_width * $focal_x_percent;
    $focal_point_y_px = $new_height * $focal_y_percent;
    $crop_x = max(0, min($focal_point_x_px - ($target_width / 2), $new_width - $target_width));
    $crop_y = max(0, min($focal_point_y_px - ($target_height / 2), $new_height - $target_height));

    $cropped_img = imagecrop($resized_img, ['x' => $crop_x, 'y' => $crop_y, 'width' => $target_width, 'height' => $target_height]);

    $output_filename = $original_basename . '_' . $target_width . 'x' . $target_height . '.' . $format;
    $output_filepath = $final_output_path . DIRECTORY_SEPARATOR . $output_filename;

    if ($format === 'webp') imagewebp($cropped_img, $output_filepath, 85);
    elseif ($format === 'jpg' || $format === 'jpeg') imagejpeg($cropped_img, $output_filepath, 85);
    else imagepng($cropped_img, $output_filepath, 9);
    
    $generated_files[] = ['path' => $output_filepath, 'width' => $target_width, 'format' => $format];
    imagedestroy($resized_img);
    imagedestroy($cropped_img);
}

imagedestroy($source_image_resource);

// 6. Package the Results
// --------------------------------------------------------------------------
copy($source_image_path, $final_output_path . DIRECTORY_SEPARATOR . $original_filename);
unlink($source_image_path);

$zip_path = $results_dir . $base_output_name . '.zip';
$zip = new ZipArchive();
if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
    foreach ($generated_files as $file) {
        $zip->addFile($file['path'], basename($file['path']));
    }
    $zip->addFile($final_output_path . DIRECTORY_SEPARATOR . $original_filename, $original_filename);
    $zip->close();
}

// 7. Send Final JSON Response
// --------------------------------------------------------------------------
$picture_html = "<picture>\n";
usort($generated_files, fn($a, $b) => $b['width'] <=> $a['width']);
foreach($generated_files as $file) {
    $filename = basename($file['path']);
    
    // **CORRECTED:** Use the sanitized base path to create the final public URL
    $folder_path = $base_path . $base_output_name;
    
    $picture_html .= "    <source media=\"(min-width: {$file['width']}px)\" srcset=\"{$folder_path}/{$filename}\" type=\"image/{$file['format']}\">\n";
}
$picture_html .= "    <img src=\"{$folder_path}/{$original_filename}\" alt=\"{$original_basename}\" width=\"{$original_width}\" height=\"{$original_height}\">\n";
$picture_html .= "</picture>";

header('Content-Type: application/json');
echo json_encode([
    'status' => 'success',
    'message' => 'Images processed successfully.',
    'downloadUrl' => 'results/' . $base_output_name . '.zip',
    'pictureHtml' => $picture_html
]);