<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    {{-- Adding the CSRF token for secure form submissions --}}
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Responsive Image Generator with Focal Point Control</title>
    <meta name="description"
        content="Save time with our free responsive image generator. Set a focal point, define your sizes, and instantly get perfectly cropped images for your website.">
    <link rel="canonical" href="https://your-domain.com/">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <link rel="icon" type="image/png" href="/favicons/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="/favicons/favicon.svg" />
    <link rel="shortcut icon" href="/favicons/favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="/favicons/apple-touch-icon.png" />
    <meta name="apple-mobile-web-app-title" content="Responsive Images" />
    <link rel="manifest" href="/favicons/site.webmanifest" />

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.15/codemirror.min.css">
    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.15/theme/material-darker.min.css">

    <!-- Our css -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

</head>

<body class="d-flex flex-column align-items-center">

    <div class="main-container">
        <div class="text-center mb-5">
            <h1 class="display-4"><i class="bi bi-images text-primary"></i> Responsive Image Generator</h1>
            <p class="lead text-body-secondary">
                A simple tool to generate responsive images with a custom focal point.
            </p>
        </div>

        <div class="progress-stepper" id="progress-stepper">
            <div class="step active" id="progress-step-1">
                <div class="step-icon"><i class="bi bi-upload"></i></div>
            </div>
            <div class="step" id="progress-step-2">
                <div class="step-icon"><i class="bi bi-crosshair"></i></div>
            </div>
            <div class="step" id="progress-step-3">
                <div class="step-icon"><i class="bi bi-gear-fill"></i></div>
            </div>
        </div>

        <div id="app-wizard">

            <div id="step-1" class="step-content active">
                <div class="card shadow-lg">
                    <div class="card-body p-4 p-md-5">
                        <h3 class="card-title mb-4"><i class="bi bi-upload me-2"></i>Step 1: Upload Image</h3>
                        <div id="file-drop-zone" onclick="document.getElementById('source_image').click();">
                            <input class="d-none" type="file" id="source_image"
                                accept="image/jpeg, image/png, image/webp, image/avif">
                            <div class="icon"><i class="bi bi-cloud-arrow-up"></i></div>
                            <p class="h5"><strong>Click to upload</strong> or drag and drop</p>
                            <p class="text-body-secondary" id="file-info-text">Select a high-quality source image</p>
                        </div>
                        <div class="d-flex justify-content-end mt-4">
                            <button id="btn-step1-next" class="btn btn-primary btn-lg" disabled>Next <i
                                    class="bi bi-arrow-right-short"></i></button>
                        </div>
                    </div>
                </div>
            </div>

            <div id="step-2" class="step-content">
                <div class="card shadow-lg">
                    <div class="card-body p-4 p-md-5">
                        <h3 class="card-title mb-3"><i class="bi bi-crosshair me-2"></i>Step 2: Set Focal Point</h3>
                        <p class="card-text text-body-secondary">Click on the most important part of the image.</p>
                        <div id="focal-point-area" class="border rounded mb-3"
                            style="position: relative; line-height: 0;">
                            <img id="image-preview" src="" alt="Image preview" class="rounded" style="max-width: 100%;">
                            <div id="focal-point-marker"></div>
                        </div>
                        <div class="d-flex justify-content-between">
                            <button id="btn-step2-back" class="btn btn-secondary"><i class="bi bi-arrow-left-short"></i>
                                Back</button>
                            <button id="btn-step2-next" class="btn btn-primary btn-lg">Next <i
                                    class="bi bi-arrow-right-short"></i></button>
                        </div>
                    </div>
                </div>
            </div>

            <div id="step-3" class="step-content">
                <div class="card shadow-lg">
                    <div class="card-body p-4 p-md-5">
                        <h3 class="card-title mb-4"><i class="bi bi-gear-fill me-2"></i>Step 3: Configure & Generate
                        </h3>
                        <div class="mb-4">
                            <label for="base_path_config" class="form-label"><strong>Image Base Path</strong></label>
                            <input type="text" class="form-control" id="base_path_config" value=""
                                placeholder="e.g., /assets/images/ or ../../images/">
                            <div class="form-text">
                                The public URL path where your final image folder will live on your server. It will be
                                prefixed to the paths in the generated HTML snippet<br>
                                Note: Leave this empty to use the public URL from responsive-imaves.com
                                .</div>
                        </div>
                        <div class="mb-3">
                            <label for="json_config" class="form-label"><strong>Output Definitions
                                    (JSON)</strong></label>
                            <textarea id="json_config"><?php
echo htmlspecialchars(json_encode([
    'outputs' => [
        [
            'width' => 1920,
            'height' => 1080,
            'format' => 'webp',
            'quality' => 80
        ],
        [
            'width' => 1080,
            'height' => 1080,
            'format' => 'jpeg',
            'quality' => 85
        ],
        [
            "breakpoint" => 768,
            "format" => "webp",
            "quality" => 80,
            "densities" => [
                "1x" => ["width" => 768, "height" => 512],
                "2x" => ["width" => 1536, "height" => 1024]
            ]
        ],
        [
            "breakpoint" => 320,
            "format" => "jpeg",
            "quality" => 85,
            "densities" => [
                "1x" => ["width" => 320, "height" => 480],
                "2x" => ["width" => 640, "height" => 960],
                "3x" => ["width" => 960, "height" => 1440]
            ]
        ]
    ]
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                            ?></textarea>
                        </div>
                        <div class="d-flex justify-content-between mt-4">
                            <button id="btn-step3-back" class="btn btn-secondary"><i class="bi bi-arrow-left-short"></i>
                                Back</button>
                            <button id="btn-generate" class="btn btn-success btn-lg"><i
                                    class="bi bi-lightning-charge-fill"></i> Create Images</button>
                        </div>
                    </div>
                </div>
            </div>

            <div id="step-4-loading" class="step-content">
                <div class="card shadow-lg">
                    <div class="card-body p-5 text-center">
                        <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="h4 mt-4">Processing your images...</p>
                        <p class="text-body-secondary">This may take a moment, please wait.</p>
                    </div>
                </div>
            </div>

            <div id="step-5-results" class="step-content">
                <div class="card shadow-lg">
                    <div class="card-body p-4 p-md-5">
                        <h3 class="card-title mb-3 text-success"><i class="bi bi-check-circle-fill me-2"></i>Success!
                        </h3>
                        <p>Your images have been generated. Copy the HTML below and download your files.</p>
                        <div class="mb-3">
                            <label for="picture-output" class="form-label">Generated `&lt;picture&gt;` Tag</label>
                            <textarea id="picture-output"></textarea>
                        </div>
                        <div class="d-grid gap-2 d-md-flex justify-content-md-between">
                            <a href="#" id="download-zip-link" class="btn btn-success btn-lg"><i
                                    class="bi bi-download"></i> Download ZIP File</a>
                            <button id="btn-start-over" class="btn btn-secondary"><i
                                    class="bi bi-arrow-counterclockwise"></i> Start Over</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <footer class="mt-5 py-4 text-center">
            <p class="text-body-secondary">
                Built with <i class="bi bi-heart-fill text-danger"></i> by <a href="https://burn.codes/" target="_blank"
                    rel="noopener noreferrer"
                    class="link-primary link-offset-2 link-underline-opacity-25 link-underline-opacity-100-hover">burn.codes</a>
            </p>
        </footer>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.15/codemirror.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.15/mode/javascript/javascript.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.15/mode/xml/xml.min.js"></script>

    <!-- Our scripts -->
    @vite(['resources/js/app.js'])

</body>

</html>