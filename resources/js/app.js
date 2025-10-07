import "./bootstrap";
import CodeMirror from 'codemirror';
import 'codemirror/mode/javascript/javascript';
import 'codemirror/mode/xml/xml';
import 'codemirror/lib/codemirror.css';
import 'codemirror/theme/material-darker.css';

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
    
    // NEW: Function to save config to localStorage
    const saveConfig = () => {
        localStorage.setItem('responsiveImageConfig', jsonEditor.getValue());
        localStorage.setItem('responsiveImageBasePath', document.getElementById('base_path_config').value);
    };

    // NEW: Function to load config from localStorage
    const loadConfig = () => {
        const savedConfig = localStorage.getItem('responsiveImageConfig');
        const savedBasePath = localStorage.getItem('responsiveImageBasePath');

        if (savedConfig) {
            jsonEditor.setValue(savedConfig);
        }
        if (savedBasePath !== null) {
            document.getElementById('base_path_config').value = savedBasePath;
        }
    };

    // MODIFIED: Listen for changes on the editor and base path input to save them
    jsonEditor.on('change', saveConfig);
    document.getElementById('base_path_config').addEventListener('input', saveConfig);
    
    // NEW: Load any saved configuration when the page is ready
    loadConfig();

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
         }, 10);
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

        // USERSTUFF: Need this later when we add user stuff, quotas etc.
        // const apiToken = '1|0fidFBHYfHGe5tEP6aSg3Llu5JByvJiACykoJTBIce20741c';

        try {
            const response = await fetch('/api/v1/generate', {
                method: 'POST',
                headers: {
                    // USERSTUFF: Need this later when we add user stuff, quotas etc.
                    // 'Authorization': `Bearer ${apiToken}`,
                    'Accept': 'application/json'
                },
                body: formData
            });

            const result = await response.json();

            // The API returns a flat object, not one nested under "data".
            // We also use the correct camelCase key names from the JSON response.
            if (response.ok && result.status === 'success') {
                pictureOutputEditor.setValue(result.pictureHtml);
                document.getElementById('download-zip-link').href = result.downloadUrl;
                showStep(5);
            } else {
                const errorMessage = result.message || 'An unknown error occurred.';
                alert(`An error occurred: ${errorMessage}`);
                showStep(3);
            }
        } catch (error) {
            alert('A critical network error occurred. Please check the console.');
            console.error('Submission Error:', error);
            showStep(3);
        }
    }
});