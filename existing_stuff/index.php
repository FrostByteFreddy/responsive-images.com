<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <title>Responsive Image Generator with Focal Point Control</title>
    <meta name="description" content="Save time with our free responsive image generator. Set a focal point, define your sizes, and instantly get perfectly cropped images for your website.">
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.15/theme/material-darker.min.css">
    
    <style>
        :root {
            --step-transition-duration: 0.3s;
        }
        body {
            background-color: var(--bs-body-bg);
            margin: 6rem 0;
        }
        .main-container {
            max-width: 1200px;
            width: 100%;
        }
        .step-content {
            display: none;
            animation: fadeOut var(--step-transition-duration) forwards;
        }
        .step-content.active {
            display: block;
            animation: fadeIn var(--step-transition-duration) forwards;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes fadeOut {
            from { opacity: 1; transform: translateY(0); }
            to { opacity: 0; transform: translateY(-10px); }
        }
        .progress-stepper {
            display: flex;
            justify-content: space-between;
            width: 100%;
            position: relative;
            margin-bottom: 2rem;
        }
        .progress-stepper::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            transform: translateY(-50%);
            height: 2px;
            width: 100%;
            background-color: var(--bs-border-color);
            z-index: 1;
        }
        .progress-stepper .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            position: relative;
            z-index: 2;
            background-color: var(--bs-body-bg);
            padding: 0 0.75rem;
            font-size: 0.85rem;
            color: var(--bs-secondary-text-emphasis);
            transition: color 0.3s ease;
        }
        .step .step-icon {
            height: 3rem;
            width: 3rem;
            border-radius: 50%;
            border: 2px solid var(--bs-border-color);
            display: grid;
            place-items: center;
            margin-bottom: 0.5rem;
            font-size: 1.25rem;
            background-color: var(--bs-body-bg);
            transition: border-color 0.3s ease, background-color 0.3s ease, color 0.3s ease;
        }
        .step.active { color: var(--bs-primary); }
        .step.active .step-icon {
            border-color: var(--bs-primary);
            background-color: var(--bs-primary);
            color: var(--bs-light);
        }
        .step.completed .step-icon {
             border-color: var(--bs-success);
             background-color: var(--bs-success);
             color: var(--bs-light);
        }
        .step.completed .bi-check-lg { font-weight: bold; }
        #file-drop-zone {
            border: 2px dashed var(--bs-border-color);
            border-radius: var(--bs-card-border-radius);
            padding: 3rem 1rem;
            text-align: center;
            cursor: pointer;
            transition: border-color 0.2s, background-color 0.2s;
        }
        #file-drop-zone.dragover {
            border-color: var(--bs-primary);
            background-color: var(--bs-primary-bg-subtle);
        }
        #file-drop-zone .icon { font-size: 3rem; color: var(--bs-primary); }
        #file-drop-zone p { margin: 0.5rem 0 0; }
        #focal-point-area { cursor: crosshair; }
        #focal-point-marker {
            position: absolute;
            width: 24px;
            height: 24px;
            border: 2px solid var(--bs-primary);
            background-color: rgba(var(--bs-primary-rgb), 0.2);
            backdrop-filter: blur(2px);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            pointer-events: none;
            display: none;
        }
        #focal-point-marker::before, #focal-point-marker::after {
            content: '';
            position: absolute;
            background-color: var(--bs-primary);
        }
        #focal-point-marker::before { width: 12px; height: 2px; top: 50%; left: 50%; transform: translate(-50%, -50%); }
        #focal-point-marker::after { width: 2px; height: 12px; top: 50%; left: 50%; transform: translate(-50%, -50%); }
        /* CodeMirror styles */
        .CodeMirror {
            border: 1px solid var(--bs-border-color);
            border-radius: var(--bs-border-radius);
            height: auto;
            font-family: var(--bs-font-monospace);
            font-size: 0.9rem;
        }
    </style>
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
                            <input class="d-none" type="file" id="source_image" accept="image/jpeg, image/png, image/webp">
                            <div class="icon"><i class="bi bi-cloud-arrow-up"></i></div>
                            <p class="h5"><strong>Click to upload</strong> or drag and drop</p>
                            <p class="text-body-secondary" id="file-info-text">Select a high-quality source image</p>
                        </div>
                        <div class="d-flex justify-content-end mt-4">
                            <button id="btn-step1-next" class="btn btn-primary btn-lg" disabled>Next <i class="bi bi-arrow-right-short"></i></button>
                        </div>
                    </div>
                </div>
            </div>

            <div id="step-2" class="step-content">
                <div class="card shadow-lg">
                    <div class="card-body p-4 p-md-5">
                        <h3 class="card-title mb-3"><i class="bi bi-crosshair me-2"></i>Step 2: Set Focal Point</h3>
                        <p class="card-text text-body-secondary">Click on the most important part of the image.</p>
                        <div id="focal-point-area" class="border rounded mb-3" style="position: relative; line-height: 0;">
                            <img id="image-preview" src="" alt="Image preview" class="rounded" style="max-width: 100%;">
                            <div id="focal-point-marker"></div>
                        </div>
                        <div class="d-flex justify-content-between">
                            <button id="btn-step2-back" class="btn btn-secondary"><i class="bi bi-arrow-left-short"></i> Back</button>
                            <button id="btn-step2-next" class="btn btn-primary btn-lg">Next <i class="bi bi-arrow-right-short"></i></button>
                        </div>
                    </div>
                </div>
            </div>

            <div id="step-3" class="step-content">
                <div class="card shadow-lg">
                    <div class="card-body p-4 p-md-5">
                        <h3 class="card-title mb-4"><i class="bi bi-gear-fill me-2"></i>Step 3: Configure & Generate</h3>
                        <div class="mb-4">
                            <label for="base_path_config" class="form-label"><strong>Image Base Path</strong></label>
                            <input type="text" class="form-control" id="base_path_config" value="/wp-content/uploads/" placeholder="e.g., /assets/images/ or ../../images/">
                            <div class="form-text">The public URL path where your final image folder will live on your server. It will be prefixed to the paths in the generated HTML snippet.</div>
                        </div>
                        <div class="mb-3">
                             <label for="json_config" class="form-label"><strong>Output Definitions (JSON)</strong></label>
                            <textarea id="json_config"><?php
                            echo htmlspecialchars(json_encode([
                                'outputs' => [
                                    ['width' => 1920, 'height' => 1080, 'format' => 'webp'],
                                    ['width' => 1080, 'height' => 1080, 'format' => 'jpeg'],
                                    ['width' => 768, 'height' => 1024, 'format' => 'webp']
                                ]
                            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                            ?></textarea>
                             <div class="form-text">Define each image size you need. The script will generate a file named like `your-image_1920x1080.webp` for each entry.</div>
                        </div>
                        <div class="d-flex justify-content-between mt-4">
                            <button id="btn-step3-back" class="btn btn-secondary"><i class="bi bi-arrow-left-short"></i> Back</button>
                            <button id="btn-generate" class="btn btn-success btn-lg"><i class="bi bi-lightning-charge-fill"></i> Create Images</button>
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
                        <h3 class="card-title mb-3 text-success"><i class="bi bi-check-circle-fill me-2"></i>Success!</h3>
                        <p>Your images have been generated. Copy the HTML below and download your files.</p>
                        <div class="mb-3">
                            <label for="picture-output" class="form-label">Generated `<picture>` Tag</label>
                            <textarea id="picture-output"></textarea>
                        </div>
                        <div class="d-grid gap-2 d-md-flex justify-content-md-between">
                            <a href="#" id="download-zip-link" class="btn btn-success btn-lg"><i class="bi bi-download"></i> Download ZIP File</a>
                            <button id="btn-start-over" class="btn btn-secondary"><i class="bi bi-arrow-counterclockwise"></i> Start Over</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.15/codemirror.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.15/mode/javascript/javascript.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.15/mode/xml/xml.min.js"></script>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        
        const jsonEditor = CodeMirror.fromTextArea(document.getElementById('json_config'), {
            lineNumbers: true,
            mode: { name: "javascript", json: true },
            theme: "material-darker",
            lineWrapping: true,
            gutters: ["CodeMirror-linenumbers"]
        });

        const pictureOutputEditor = CodeMirror.fromTextArea(document.getElementById('picture-output'), {
            lineNumbers: true,
            mode: 'xml',
            theme: "material-darker",
            readOnly: true,
            lineWrapping: true,
            gutters: ["CodeMirror-linenumbers"]
        });

        jsonEditor.setSize(null, 'auto');
        pictureOutputEditor.setSize(null, 'auto');

        const appState = { currentStep: 1, imageFile: null, focalPoint: { x: 50, y: 50 } };
        const steps = { 1: document.getElementById('step-1'), 2: document.getElementById('step-2'), 3: document.getElementById('step-3'), 4: document.getElementById('step-4-loading'), 5: document.getElementById('step-5-results') };
        const progressSteps = { 1: document.getElementById('progress-step-1'), 2: document.getElementById('progress-step-2'), 3: document.getElementById('progress-step-3') };
        
        const fileInput = document.getElementById('source_image');
        const fileDropZone = document.getElementById('file-drop-zone');
        const fileInfoText = document.getElementById('file-info-text');
        const imagePreview = document.getElementById('image-preview');
        const focalPointArea = document.getElementById('focal-point-area');
        const focalPointMarker = document.getElementById('focal-point-marker');
        
        const showStep = (stepNumber) => {
            if (appState.currentStep === stepNumber) return;
            appState.currentStep = stepNumber;
            Object.values(steps).forEach(stepDiv => stepDiv.classList.remove('active'));
            if(steps[stepNumber]) steps[stepNumber].classList.add('active');
            
            Object.values(progressSteps).forEach(el => el.classList.remove('active', 'completed'));
            const currentProgressStep = progressSteps[stepNumber] || progressSteps[3];
            if (currentProgressStep) {
                 currentProgressStep.classList.add('active');
                 for(let i = 1; i < stepNumber; i++) {
                     if (progressSteps[i]) {
                         progressSteps[i].classList.add('completed');
                         progressSteps[i].querySelector('.step-icon i').className = 'bi bi-check-lg';
                     }
                 }
            }
             for(let i = stepNumber; i <= 3; i++) {
                if (progressSteps[i]) {
                    const icon = progressSteps[i].querySelector('.step-icon i');
                    if (i === 1) icon.className = 'bi bi-upload';
                    if (i === 2) icon.className = 'bi bi-crosshair';
                    if (i === 3) icon.className = 'bi bi-gear-fill';
                }
            }
            
            setTimeout(() => {
                if (stepNumber === 3) jsonEditor.refresh();
                if (stepNumber === 5) pictureOutputEditor.refresh();
            }, 10); // Small delay to ensure container is visible before refresh
        };

        document.getElementById('btn-step1-next').addEventListener('click', () => showStep(2));
        document.getElementById('btn-step2-back').addEventListener('click', () => showStep(1));
        document.getElementById('btn-step2-next').addEventListener('click', () => showStep(3));
        document.getElementById('btn-step3-back').addEventListener('click', () => showStep(2));
        document.getElementById('btn-generate').addEventListener('click', handleSubmit);
        document.getElementById('btn-start-over').addEventListener('click', () => {
            fileInput.value = '';
            fileInfoText.textContent = 'Select a high-quality source image';
            fileInfoText.classList.remove('text-success');
            document.getElementById('btn-step1-next').disabled = true;
            focalPointMarker.style.display = 'none';
            showStep(1);
        });

        const handleFileSelect = (file) => {
             if (file) {
                appState.imageFile = file;
                const reader = new FileReader();
                reader.onload = (e) => imagePreview.src = e.target.result;
                reader.readAsDataURL(file);
                document.getElementById('btn-step1-next').disabled = false;
                fileInfoText.textContent = `Selected: ${file.name}`;
                fileInfoText.classList.add('text-success');
            } else {
                document.getElementById('btn-step1-next').disabled = true;
            }
        };

        fileInput.addEventListener('change', (event) => handleFileSelect(event.target.files[0]));
        fileDropZone.addEventListener('dragover', (e) => { e.preventDefault(); fileDropZone.classList.add('dragover'); });
        fileDropZone.addEventListener('dragleave', () => fileDropZone.classList.remove('dragover'));
        fileDropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            fileDropZone.classList.remove('dragover');
            if (e.dataTransfer.files.length) {
                fileInput.files = e.dataTransfer.files;
                handleFileSelect(e.dataTransfer.files[0]);
            }
        });
        
        focalPointArea.addEventListener('click', (event) => {
            const rect = event.currentTarget.getBoundingClientRect();
            const x = event.clientX - rect.left;
            const y = event.clientY - rect.top;
            appState.focalPoint.x = (x / rect.width) * 100;
            appState.focalPoint.y = (y / rect.height) * 100;
            focalPointMarker.style.left = `${appState.focalPoint.x}%`;
            focalPointMarker.style.top = `${appState.focalPoint.y}%`;
            focalPointMarker.style.display = 'block';
        });

        async function handleSubmit() {
            if (!appState.imageFile) {
                alert('Please upload an image first.');
                return;
            }
            showStep(4);
            const formData = new FormData();
            formData.append('source_image', appState.imageFile);
            formData.append('json_config', jsonEditor.getValue());
            formData.append('base_path', document.getElementById('base_path_config').value);
            formData.append('focalPointX', `${appState.focalPoint.x}%`);
            formData.append('focalPointY', `${appState.focalPoint.y}%`);
            try {
                const response = await fetch('process.php', { method: 'POST', body: formData });
                const result = await response.json();
                if (result.status === 'success') {
                    pictureOutputEditor.setValue(result.pictureHtml);
                    const zipFilename = result.downloadUrl.split('/').pop();
                    document.getElementById('download-zip-link').href = `download.php?file=${zipFilename}`;
                    showStep(5);
                } else {
                    alert(`An error occurred: ${result.message}`);
                    showStep(3);
                }
            } catch (error) {
                alert('A critical error occurred. Please check the console.');
                console.error('Submission Error:', error);
                showStep(3);
            }
        }
    });
    </script>
</body>
</html>