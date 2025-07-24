<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;
use finfo;

class ImageController extends Controller
{
    /**
     * Display the main image generator page.
     */
    public function index()
    {
        return view('welcome');
    }

    /**
     * Process the uploaded image and generate responsive versions.
     */
    public function process(Request $request)
    {
        // 1. Laravel's built-in validation is cleaner and more powerful.
        $validated = $request->validate([
            'source_image' => 'required|file|image|mimes:jpeg,png,webp|max:15360', // 15MB in kilobytes
            'json_config' => 'required|json',
            'base_path' => 'nullable|string',
            'focalPointX' => 'required|string',
            'focalPointY' => 'required|string',
        ]);

        // 2. Handle Secure File Upload using Laravel's Storage facade.
        // This stores the file in `storage/app/uploads`.
        $sourceImage = $request->file('source_image');
        $originalBasename = pathinfo($sourceImage->getClientOriginalName(), PATHINFO_FILENAME);
        $sourceImagePath = $sourceImage->store('uploads'); // Returns 'uploads/randomfilename.jpg'

        // Get absolute path for GD functions
        $absoluteSourcePath = Storage::path($sourceImagePath);

        // Validate image dimensions after storing it
        list($width, $height) = getimagesize($absoluteSourcePath);
        if ($width > 8000 || $height > 8000) {
            Storage::delete($sourceImagePath); // Clean up uploaded file
            return response()->json(['status' => 'error', 'message' => 'Image dimensions are too large (max 8000x8000 pixels).'], 422);
        }

        // 3. Parse Input Data
        $jsonConfig = json_decode($request->input('json_config'), true);
        $basePath = rtrim($request->input('base_path', ''), '/') . '/';
        $focalXPercent = floatval(rtrim($request->input('focalPointX'), '%')) / 100;
        $focalYPercent = floatval(rtrim($request->input('focalPointY'), '%')) / 100;
        $outputs = $jsonConfig['outputs'];

        // 4. Prepare Output Directory
        $uniqueId = Str::random(8);
        $baseOutputName = 'responsive-images-' . date('Y-m-d') . '-' . $uniqueId;
        $finalOutputPath = 'results/' . $baseOutputName; // Relative to `storage/app`
        Storage::makeDirectory($finalOutputPath);

        // 5. Core Image Processing Loop (with GD)
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($absoluteSourcePath);

        $sourceImageResource = match ($mimeType) {
            'image/jpeg' => imagecreatefromjpeg($absoluteSourcePath),
            'image/png' => imagecreatefrompng($absoluteSourcePath),
            'image/webp' => imagecreatefromwebp($absoluteSourcePath),
            default => null,
        };

        if (!$sourceImageResource) {
            return response()->json(['status' => 'error', 'message' => 'Failed to load image resource.'], 500);
        }

        $originalWidth = imagesx($sourceImageResource);
        $originalHeight = imagesy($sourceImageResource);
        $originalAspect = $originalWidth / $originalHeight;
        $generatedFiles = [];

        foreach ($outputs as $output) {
            $targetWidth = intval($output['width'] ?? 0);
            $targetHeight = intval($output['height'] ?? 0);
            $format = strtolower($output['format'] ?? '');
            if ($targetWidth <= 0 || $targetHeight <= 0 || !in_array($format, ['jpeg', 'jpg', 'png', 'webp'])) continue;

            $targetAspect = $targetWidth / $targetHeight;
            $newWidth = ($originalAspect > $targetAspect) ? intval($targetHeight * $originalAspect) : $targetWidth;
            $newHeight = ($originalAspect > $targetAspect) ? $targetHeight : intval($targetWidth / $originalAspect);

            $resizedImg = imagecreatetruecolor($newWidth, $newHeight);
            imagecopyresampled($resizedImg, $sourceImageResource, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);

            $focalPointXPx = $newWidth * $focalXPercent;
            $focalPointYPx = $newHeight * $focalYPercent;
            $cropX = max(0, min($focalPointXPx - ($targetWidth / 2), $newWidth - $targetWidth));
            $cropY = max(0, min($focalPointYPx - ($targetHeight / 2), $newHeight - $targetHeight));

            $croppedImg = imagecrop($resizedImg, ['x' => $cropX, 'y' => $cropY, 'width' => $targetWidth, 'height' => $targetHeight]);
            
            $outputFilename = $originalBasename . '_' . $targetWidth . 'x' . $targetHeight . '.' . $format;
            $absoluteOutputFilePath = Storage::path($finalOutputPath . '/' . $outputFilename);

            if ($format === 'webp') imagewebp($croppedImg, $absoluteOutputFilePath, 85);
            elseif ($format === 'jpg' || $format === 'jpeg') imagejpeg($croppedImg, $absoluteOutputFilePath, 85);
            else imagepng($croppedImg, $absoluteOutputFilePath, 9);

            $generatedFiles[] = ['path' => $finalOutputPath . '/' . $outputFilename, 'width' => $targetWidth, 'format' => $format];
            imagedestroy($resizedImg);
            imagedestroy($croppedImg);
        }
        imagedestroy($sourceImageResource);

        // 6. Package the Results
        Storage::copy($sourceImagePath, $finalOutputPath . '/' . $sourceImage->getClientOriginalName());
        Storage::delete($sourceImagePath);

        $zipPath = Storage::path('results/' . $baseOutputName . '.zip');
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
            foreach ($generatedFiles as $file) {
                $zip->addFile(Storage::path($file['path']), basename($file['path']));
            }
            $zip->addFile(Storage::path($finalOutputPath . '/' . $sourceImage->getClientOriginalName()), $sourceImage->getClientOriginalName());
            $zip->close();
        }

        // 7. Generate Picture HTML and send Final JSON Response
        $pictureHtml = "<picture>\n";
        usort($generatedFiles, fn($a, $b) => $b['width'] <=> $a['width']);
        foreach($generatedFiles as $file) {
            $filename = basename($file['path']);
            $folderPath = $basePath . $baseOutputName;
            $pictureHtml .= "    <source media=\"(min-width: {$file['width']}px)\" srcset=\"{$folderPath}/{$filename}\" type=\"image/{$file['format']}\">\n";
        }
        $pictureHtml .= "    <img src=\"{$folderPath}/{$sourceImage->getClientOriginalName()}\" alt=\"{$originalBasename}\" width=\"{$originalWidth}\" height=\"{$originalHeight}\">\n";
        $pictureHtml .= "</picture>";

        return response()->json([
            'status' => 'success',
            'message' => 'Images processed successfully.',
            'downloadUrl' => route('download', ['file' => $baseOutputName . '.zip']),
            'pictureHtml' => $pictureHtml
        ]);
    }

    /**
     * Handle the file download request.
     */
    public function download(Request $request)
    {
        $fileName = basename($request->query('file'));
        $filePath = 'results/' . $fileName;

        // Use Laravel's built-in, secure download response.
        // It handles headers, security, and streaming automatically.
        if (Storage::disk('local')->exists($filePath)) {
            return Storage::disk('local')->download($filePath);
        }

        abort(404, 'The requested file could not be found.');
    }
}