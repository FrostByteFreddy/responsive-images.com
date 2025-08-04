<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;
use finfo;
use App\Http\Controllers\Controller;
use Exception;

class ResponsiveImageController extends Controller
{
    // Class properties to hold image details for access in helper methods
    private $originalWidth;
    private $originalHeight;
    private $focalXPercent;
    private $focalYPercent;

    /**
     * Display the main image generator page.
     */
    public function index()
    {
        return view('welcome');
    }

    /**
     * Generate responsive versions of the uploaded image.
     */
    public function generate(Request $request)
    {
        // 1. Validation: Added 'avif' to the allowed mimes.
        $validated = $request->validate([
            'source_image' => 'required|file|image|mimes:jpeg,png,webp,avif|max:15360', // 15MB
            'json_config' => 'required|json',
            'base_path' => 'nullable|string',
            'focalPointX' => 'required|string',
            'focalPointY' => 'required|string',
        ]);

        // 2. Handle Secure File Upload
        $sourceImage = $request->file('source_image');
        $originalBasename = pathinfo($sourceImage->getClientOriginalName(), PATHINFO_FILENAME);
        $sourceImagePath = $sourceImage->store('uploads');
        $absoluteSourcePath = Storage::path($sourceImagePath);

        // Validate image dimensions
        list($width, $height) = getimagesize($absoluteSourcePath);
        if ($width > 8000 || $height > 8000) {
            Storage::delete($sourceImagePath);
            return response()->json(['status' => 'error', 'message' => 'Image dimensions are too large (max 8000x8000 pixels).'], 422);
        }

        // 3. Parse Input Data and set class properties
        $jsonConfig = json_decode($request->input('json_config'), true);
        $basePath = rtrim($request->input('base_path', ''), '/') . '/';
        $this->focalXPercent = floatval(rtrim($request->input('focalPointX'), '%')) / 100;
        $this->focalYPercent = floatval(rtrim($request->input('focalPointY'), '%')) / 100;
        $outputs = $jsonConfig['outputs'];

        // 4. Prepare Output Directory
        $uniqueId = Str::random(8);
        $baseOutputName = 'responsive-images-' . date('Y-m-d') . '-' . $uniqueId;
        $finalOutputPath = 'results/' . $baseOutputName;
        Storage::makeDirectory($finalOutputPath);

        // 5. Core Image Processing
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($absoluteSourcePath);

        // Added 'image/avif' support
        if ($mimeType === 'image/avif' && !function_exists('imagecreatefromavif')) {
            return response()->json(['status' => 'error', 'message' => 'AVIF support is not enabled in this server\'s PHP/GD installation.'], 501);
        }

        $sourceImageResource = match ($mimeType) {
            'image/jpeg' => imagecreatefromjpeg($absoluteSourcePath),
            'image/png' => imagecreatefrompng($absoluteSourcePath),
            'image/webp' => imagecreatefromwebp($absoluteSourcePath),
            'image/avif' => imagecreatefromavif($absoluteSourcePath),
            default => null,
        };

        if (!$sourceImageResource) {
            return response()->json(['status' => 'error', 'message' => 'Failed to load image resource.'], 500);
        }

        $this->originalWidth = imagesx($sourceImageResource);
        $this->originalHeight = imagesy($sourceImageResource);
        $generatedFiles = [];
        $pictureSources = []; // New array to build the picture element structure

        // Loop to handle both simple and density-based outputs
        foreach ($outputs as $output) {
            try {
                if (isset($output['width'])) { // Simple output
                    $targetWidth = intval($output['width']);
                    $targetHeight = intval($output['height']);
                    $format = strtolower($output['format'] ?? 'jpeg');
                    $quality = intval($output['quality'] ?? 85);
                    $filename = $this->processAndSaveImage($sourceImageResource, $originalBasename, $targetWidth, $targetHeight, $format, $quality, $finalOutputPath);
                    if ($filename) {
                        $generatedFiles[] = ['path' => $finalOutputPath . '/' . $filename];
                        $pictureSources[] = ['type' => 'simple', 'width' => $targetWidth, 'height' => $targetHeight, 'format' => $format, 'filename' => $filename];
                    }
                } elseif (isset($output['densities']) && isset($output['breakpoint'])) { // Density-based output
                    $breakpoint = intval($output['breakpoint']);
                    $format = strtolower($output['format'] ?? 'jpeg');
                    $quality = intval($output['quality'] ?? 85);
                    $srcsetItems = [];
                    foreach ($output['densities'] as $density => $dims) {
                        $targetWidth = intval($dims['width']);
                        $targetHeight = intval($dims['height']);
                        $filename = $this->processAndSaveImage($sourceImageResource, $originalBasename, $targetWidth, $targetHeight, $format, $quality, $finalOutputPath);
                        if ($filename) {
                            $generatedFiles[] = ['path' => $finalOutputPath . '/' . $filename];
                            $srcsetItems[] = ['filename' => $filename, 'density' => strtolower($density), 'width' => $targetWidth, 'height' => $targetHeight];
                        }
                    }
                    if (!empty($srcsetItems)) {
                        $pictureSources[] = ['type' => 'density', 'width' => $breakpoint, 'format' => $format, 'items' => $srcsetItems];
                    }
                }
            } catch (Exception $e) {
                // If saving fails (e.g., format not supported), return an error
                imagedestroy($sourceImageResource);
                Storage::deleteDirectory('results/' . $baseOutputName);
                Storage::delete($sourceImagePath);
                return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
            }
        }
        imagedestroy($sourceImageResource);

        // 6. Package the Results
        Storage::copy($sourceImagePath, $finalOutputPath . '/' . $sourceImage->getClientOriginalName());
        Storage::delete($sourceImagePath);

        $zipPath = Storage::path('results/' . $baseOutputName . '.zip');
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            foreach ($generatedFiles as $file) {
                $zip->addFile(Storage::path($file['path']), basename($file['path']));
            }
            $zip->addFile(Storage::path($finalOutputPath . '/' . $sourceImage->getClientOriginalName()), $sourceImage->getClientOriginalName());
            $zip->close();
        }

        // 7. Generate Picture HTML and send Final JSON Response
        $pictureHtml = $this->generatePictureHtml($pictureSources, $basePath, $baseOutputName, $sourceImage->getClientOriginalName(), $originalBasename);

        return response()->json([
            'status' => 'success',
            'message' => 'Images processed successfully.',
            'downloadUrl' => route('download', ['file' => $baseOutputName . '.zip']),
            'pictureHtml' => $pictureHtml
        ]);
    }

    /**
     * Processes a single image version (resize, crop, save).
     * This is a new private helper method adapted from the procedural script.
     */
    private function processAndSaveImage($sourceResource, $baseName, $targetWidth, $targetHeight, $format, $quality, $outputDir)
    {
        if ($targetWidth <= 0 || $targetHeight <= 0)
            return null;

        $originalAspect = $this->originalWidth / $this->originalHeight;
        $targetAspect = $targetWidth / $targetHeight;

        $newWidth = ($originalAspect > $targetAspect) ? intval($targetHeight * $originalAspect) : $targetWidth;
        $newHeight = ($originalAspect > $targetAspect) ? $targetHeight : intval($targetWidth / $originalAspect);

        $resizedImg = imagecreatetruecolor($newWidth, $newHeight);

        // Handle transparency for PNG, WebP, AVIF
        if (in_array($format, ['png', 'webp', 'avif'])) {
            imagealphablending($resizedImg, false);
            imagesavealpha($resizedImg, true);
        }

        imagecopyresampled($resizedImg, $sourceResource, 0, 0, 0, 0, $newWidth, $newHeight, $this->originalWidth, $this->originalHeight);

        $focalPointXPx = $newWidth * $this->focalXPercent;
        $focalPointYPx = $newHeight * $this->focalYPercent;
        $cropX = max(0, min($focalPointXPx - ($targetWidth / 2), $newWidth - $targetWidth));
        $cropY = max(0, min($focalPointYPx - ($targetHeight / 2), $newHeight - $targetHeight));

        $croppedImg = imagecrop($resizedImg, ['x' => $cropX, 'y' => $cropY, 'width' => $targetWidth, 'height' => $targetHeight]);

        $ext = ($format === 'jpeg') ? 'jpg' : $format;
        $outputFilename = $baseName . '_' . $targetWidth . 'x' . $targetHeight . '.' . $ext;
        $absoluteOutputFilePath = Storage::path($outputDir . '/' . $outputFilename);

        switch ($format) {
            case 'avif':
                if (!function_exists('imageavif')) {
                    throw new Exception('AVIF saving is not supported on this server.');
                }
                imageavif($croppedImg, $absoluteOutputFilePath, $quality);
                break;
            case 'webp':
                imagewebp($croppedImg, $absoluteOutputFilePath, $quality);
                break;
            case 'jpeg':
                imagejpeg($croppedImg, $absoluteOutputFilePath, $quality);
                break;
            case 'png':
                // Convert 0-100 quality to 0-9 compression
                $compression = (int) round(9 - ($quality / 11.11));
                imagepng($croppedImg, $absoluteOutputFilePath, $compression);
                break;
        }

        imagedestroy($resizedImg);
        imagedestroy($croppedImg);

        return $outputFilename;
    }

    /**
     * Generates the final <picture> HTML element.
     * This is a new helper method to build the HTML structure.
     */
    private function generatePictureHtml(array $pictureSources, string $basePath, string $baseOutputName, string $originalFilename, string $originalBasename)
    {
        if (empty($pictureSources)) {
            // Fallback if no images were generated
            return "<img src=\"{$basePath}{$baseOutputName}/{$originalFilename}\" alt=\"{$originalBasename}\" width=\"{$this->originalWidth}\" height=\"{$this->originalHeight}\">";
        }

        usort($pictureSources, fn($a, $b) => $b['width'] <=> $a['width']);

        $folderPath = rtrim($basePath, '/') . '/' . $baseOutputName;
        $indentL1 = "    ";
        $indentL2 = "        ";

        $html = "<picture>\n";

        foreach ($pictureSources as $source) {
            $mime = ($source['format'] === 'jpg') ? 'jpeg' : $source['format'];
            $mediaQuery = "(min-width: {$source['width']}px)";
            $html .= "{$indentL1}<source media=\"{$mediaQuery}\" type=\"image/{$mime}\" srcset=\"";

            if ($source['type'] === 'simple') {
                $html .= "{$folderPath}/{$source['filename']}\">\n";
            } elseif ($source['type'] === 'density') {
                $srcsetLines = [];
                foreach ($source['items'] as $item) {
                    $srcsetLines[] = "{$folderPath}/{$item['filename']} {$item['density']}";
                }
                $html .= "\n{$indentL2}" . implode(",\n{$indentL2}", $srcsetLines) . "\n{$indentL1}\">\n";
            }
        }

        // Determine the best fallback <img> tag
        $fallbackFilename = $originalFilename;
        $fallbackWidth = $this->originalWidth;
        $fallbackHeight = $this->originalHeight;

        $smallestSource = end($pictureSources); // Smallest breakpoint source
        if ($smallestSource) {
            if ($smallestSource['type'] === 'simple') {
                $fallbackFilename = $smallestSource['filename'];
                $fallbackWidth = $smallestSource['width'];
                $fallbackHeight = $smallestSource['height'];
            } elseif ($smallestSource['type'] === 'density') {
                // Try to find the '1x' density for the smallest breakpoint
                $fallbackItem = collect($smallestSource['items'])->firstWhere('density', '1x') ?? $smallestSource['items'][0];
                $fallbackFilename = $fallbackItem['filename'];
                $fallbackWidth = $fallbackItem['width'];
                $fallbackHeight = $fallbackItem['height'];
            }
        }

        $fallbackSrc = "{$folderPath}/{$fallbackFilename}";
        $html .= "{$indentL1}<img src=\"{$fallbackSrc}\" alt=\"{$originalBasename}\" width=\"{$fallbackWidth}\" height=\"{$fallbackHeight}\" loading=\"lazy\" decoding=\"async\">\n";
        $html .= "</picture>";

        return $html;
    }

    /**
     * Handle the file download request.
     */
    public function download(Request $request)
    {
        $fileName = basename($request->query('file'));
        // Sanitize to prevent directory traversal
        if (str_contains($fileName, '..') || str_contains($fileName, '/')) {
            abort(400, 'Invalid file name.');
        }

        $filePath = 'results/' . $fileName;

        if (Storage::disk('local')->exists($filePath)) {
            return Storage::disk('local')->download($filePath);
        }

        abort(404, 'The requested file could not be found.');
    }
}