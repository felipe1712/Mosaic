<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>MosaicStudio Pro • Generador de Fotomosaicos Ultra-HD</title>

    <!-- Trezo Styles -->
    @include('partials.styles')

    <!-- Custom Studio Styles -->
    <style>
        :root {
            --studio-bg: #0f172a;
            --studio-card-bg: #1e293b;
            --studio-border: #334155;
            --studio-accent: #6366f1;
            --studio-accent-hover: #4f46e5;
        }

        body[data-theme="dark"] {
            background-color: var(--studio-bg);
            color: #f1f5f9;
        }

        .studio-card {
            background-color: var(--studio-card-bg);
            border: 1px solid var(--studio-border);
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.25);
        }

        .drop-zone {
            border: 2px dashed var(--studio-border);
            border-radius: 10px;
            padding: 24px 16px;
            text-align: center;
            background: rgba(15, 23, 42, 0.4);
            cursor: pointer;
            transition: all 0.25s ease;
        }

        .drop-zone:hover, .drop-zone.dragover {
            border-color: var(--studio-accent);
            background: rgba(99, 102, 241, 0.08);
        }

        .target-preview-img {
            max-height: 180px;
            width: 100%;
            object-fit: contain;
            border-radius: 8px;
            background: #000;
        }

        .tile-thumb {
            width: 44px;
            height: 44px;
            object-fit: cover;
            border-radius: 6px;
            border: 1px solid var(--studio-border);
        }

        /* Panzoom Viewer Container */
        .viewer-viewport {
            width: 100%;
            height: 680px;
            background: #020617;
            background-image: radial-gradient(#1e293b 1px, transparent 1px);
            background-size: 20px 20px;
            border-radius: 12px;
            border: 1px solid var(--studio-border);
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .panzoom-element {
            cursor: grab;
            display: inline-block;
            transition: transform 0.05s ease-out;
            max-width: 100%;
            user-select: none;
        }

        .panzoom-element:active {
            cursor: grabbing;
        }

        .panzoom-element img {
            max-width: none;
            display: block;
            pointer-events: none;
            box-shadow: 0 10px 40px rgba(0,0,0,0.6);
        }

        .viewer-toolbar {
            position: absolute;
            top: 16px;
            right: 16px;
            z-index: 10;
            display: flex;
            gap: 6px;
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(8px);
            padding: 6px;
            border-radius: 8px;
            border: 1px solid var(--studio-border);
        }

        .viewer-badge {
            position: absolute;
            bottom: 16px;
            left: 16px;
            z-index: 10;
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(8px);
            padding: 6px 14px;
            border-radius: 8px;
            border: 1px solid var(--studio-border);
            font-size: 13px;
        }

        .badge-status {
            padding: 5px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        /* Range Sliders */
        .form-range::-webkit-slider-thumb {
            background: var(--studio-accent);
        }
        .form-range::-moz-range-thumb {
            background: var(--studio-accent);
        }

        .thumb-gallery {
            display: flex;
            gap: 6px;
            overflow-x: auto;
            padding-bottom: 6px;
        }

        .thumb-gallery::-webkit-scrollbar {
            height: 4px;
        }
        .thumb-gallery::-webkit-scrollbar-thumb {
            background: var(--studio-border);
            border-radius: 4px;
        }
    </style>
</head>
<body class="boxed-size" data-theme="dark">

    @include('partials.preloader')
    @include('partials.sidebar')

    <div class="container-fluid">
        <div class="main-content d-flex flex-column">
            @include('partials.header')

            <div class="main-content-container p-3 p-md-4">
                <!-- Page Header -->
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
                    <div>
                        <h2 class="mb-1 text-white fw-bold d-flex align-items-center gap-2">
                            <i class="ri-grid-fill text-primary fs-28"></i>
                            MosaicStudio <span class="badge bg-primary text-white fs-12 px-2 py-1">PRO UHD</span>
                        </h2>
                        <p class="text-secondary mb-0 fs-14">
                            Plataforma de alta resolución para fotomosaicos de hasta 15,000 × 15,000 px con aceleración espacial KDTree.
                        </p>
                    </div>

                    <div class="d-flex align-items-center gap-2">
                        <select class="form-select form-select-sm bg-dark text-white border-secondary" id="projectSelector" style="min-width: 200px;">
                            <option value="">-- Nuevo Proyecto --</option>
                            @foreach($recentProjects as $proj)
                                <option value="{{ $proj->id }}" {{ $loop->first ? 'selected' : '' }}>
                                    {{ $proj->name }} ({{ $proj->status }})
                                </option>
                            @endforeach
                        </select>
                        <button class="btn btn-sm btn-outline-light d-flex align-items-center gap-1" id="btnNewProject" data-bs-toggle="modal" data-bs-target="#modalNewProject">
                            <i class="ri-add-line"></i> Crear Proyecto
                        </button>
                    </div>
                </div>

                <!-- Main Layout 2 Columns -->
                <div class="row g-4">
                    <!-- LEFT COLUMN: Control Panel & Settings -->
                    <div class="col-lg-5 col-xl-4">
                        <div class="studio-card p-3 p-md-4 mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="text-white mb-0 fs-16 fw-semibold d-flex align-items-center gap-2">
                                    <i class="ri-settings-4-line text-primary"></i> Configuración del Mosaico
                                </h5>
                                <span class="badge-status bg-secondary text-white" id="statusBadge">Borrador</span>
                            </div>

                            <!-- Target Image Upload -->
                            <div class="mb-4">
                                <label class="form-label text-light fw-medium fs-13 d-flex justify-content-between">
                                    <span>1. Imagen Principal (Objetivo)</span>
                                    <span class="text-secondary fs-12" id="targetSpecs">Sin imagen</span>
                                </label>
                                <div class="drop-zone" id="targetDropZone">
                                    <input type="file" id="targetInput" accept="image/*" class="d-none">
                                    <div id="targetPlaceholder">
                                        <i class="ri-image-add-line fs-32 text-primary mb-2 d-block"></i>
                                        <span class="fs-13 text-light d-block fw-medium">Arrastra la foto principal aquí</span>
                                        <span class="fs-12 text-secondary">o haz clic para explorar (JPEG, PNG, WEBP)</span>
                                    </div>
                                    <div id="targetPreviewWrap" class="d-none">
                                        <img src="" id="targetPreview" class="target-preview-img mb-2" alt="Target preview">
                                        <button type="button" class="btn btn-xs btn-outline-danger" id="btnChangeTarget">Cambiar Imagen</button>
                                    </div>
                                </div>
                            </div>

                            <!-- Tiles Upload Section & Toggle Mode -->
                            <div class="mb-4">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <label class="form-label text-light fw-medium fs-13 mb-0">2. Banco de Micro-Fotos (Tiles)</label>
                                    <span class="badge bg-dark border border-secondary text-light fs-12" id="tileCounterBadge">0 fotos</span>
                                </div>

                                <!-- Toggle Upload Mode -->
                                <div class="p-2 mb-2 rounded bg-dark border border-secondary d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="ri-flashlight-line text-warning fs-18"></i>
                                        <div>
                                            <div class="fs-12 text-white fw-semibold" id="modeLabel">Modo Local Rápido</div>
                                            <div class="fs-11 text-secondary" id="modeDesc">Originales directos a RAM (32 GB)</div>
                                        </div>
                                    </div>
                                    <div class="form-check form-switch mb-0">
                                        <input class="form-check-input cursor-pointer" type="checkbox" id="modeToggle">
                                    </div>
                                </div>

                                <!-- Tiles Dropzone -->
                                <div class="drop-zone" id="tilesDropZone">
                                    <input type="file" id="tilesInput" accept="image/*" multiple class="d-none">
                                    <i class="ri-folder-upload-line fs-32 text-info mb-2 d-block"></i>
                                    <span class="fs-13 text-light d-block fw-medium">Arrastra un lote masivo de fotos</span>
                                    <span class="fs-12 text-secondary">Carga 10 a 500+ fotos secundarias</span>
                                </div>

                                <!-- Upload Progress Bar -->
                                <div class="progress mt-2 d-none" id="uploadProgressContainer" style="height: 6px;">
                                    <div class="progress-bar bg-info progress-bar-striped progress-bar-animated" id="uploadProgressBar" style="width: 0%"></div>
                                </div>
                                <div class="fs-11 text-secondary mt-1 d-none" id="uploadStatusText">Subiendo fotos...</div>

                                <!-- Thumbnails Preview Strip -->
                                <div class="thumb-gallery mt-2 d-none" id="tilesGallery"></div>
                            </div>

                            <hr class="border-secondary my-3">

                            <!-- Mathematical Parameters & Sliders -->
                            <div class="mb-3">
                                <div class="d-flex justify-content-between text-light fs-13 mb-1">
                                    <label for="sliderColumns" class="fw-medium">Columnas de Cuadrícula</label>
                                    <span class="text-primary fw-bold" id="valColumns">80 cols</span>
                                </div>
                                <input type="range" class="form-range" id="sliderColumns" min="40" max="250" step="5" value="80">
                                <div class="d-flex justify-content-between text-secondary fs-11">
                                    <span>40 (Rápido)</span>
                                    <span id="calcTotalCells">~4,800 celdas</span>
                                    <span>250 (Ultra Detalle)</span>
                                </div>
                            </div>

                            <div class="mb-3">
                                <div class="d-flex justify-content-between text-light fs-13 mb-1">
                                    <label for="sliderTileRes" class="fw-medium">Resolución por Micro-Foto</label>
                                    <span class="text-primary fw-bold" id="valTileRes">60 px</span>
                                </div>
                                <input type="range" class="form-range" id="sliderTileRes" min="40" max="200" step="10" value="60">
                                <div class="d-flex justify-content-between text-secondary fs-11">
                                    <span>40px</span>
                                    <span id="calcCanvasRes">Lienzo: ~4,800 px</span>
                                    <span>200px (15K UHD)</span>
                                </div>
                            </div>

                            <div class="mb-3">
                                <div class="d-flex justify-content-between text-light fs-13 mb-1">
                                    <label for="sliderBlend" class="fw-medium">Fusión con Foto Original (Blend)</label>
                                    <span class="text-primary fw-bold" id="valBlend">15%</span>
                                </div>
                                <input type="range" class="form-range" id="sliderBlend" min="0" max="50" step="1" value="15">
                                <div class="d-flex justify-content-between text-secondary fs-11">
                                    <span>0% (Puro mosaico)</span>
                                    <span>Recomendado: 10%-20%</span>
                                    <span>50%</span>
                                </div>
                            </div>

                            <div class="mb-3">
                                <div class="d-flex justify-content-between text-light fs-13 mb-1">
                                    <label for="sliderRepetition" class="fw-medium">Penalización de Repetición Cercana</label>
                                    <span class="text-primary fw-bold" id="valRepetition">Radio 3</span>
                                </div>
                                <input type="range" class="form-range" id="sliderRepetition" min="0" max="10" step="1" value="3">
                                <div class="d-flex justify-content-between text-secondary fs-11">
                                    <span>0 (Permitir)</span>
                                    <span>Exclusión espacial KDTree</span>
                                    <span>10 (Máxima variedad)</span>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mb-4 p-2 bg-dark rounded border border-secondary">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="ri-swap-box-line text-primary fs-18"></i>
                                    <div>
                                        <div class="fs-12 text-white fw-semibold">Espejado Horizontal (Flips)</div>
                                        <div class="fs-11 text-secondary">Duplica paleta de fotos espejándolas</div>
                                    </div>
                                </div>
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input cursor-pointer" type="checkbox" id="checkFlips" checked>
                                </div>
                            </div>

                            <!-- Generate Button -->
                            <button type="button" class="btn btn-primary w-100 py-3 fw-bold fs-15 d-flex align-items-center justify-content-center gap-2 shadow" id="btnGenerate">
                                <i class="ri-sparkling-fill fs-18"></i>
                                <span id="btnGenerateText">Generar Fotomosaico Ultra-HD</span>
                                <span class="spinner-border spinner-border-sm d-none" id="btnGenerateSpinner"></span>
                            </button>

                            <!-- Render Progress -->
                            <div class="mt-3 d-none" id="renderProgressWrap">
                                <div class="d-flex justify-content-between text-light fs-12 mb-1">
                                    <span id="renderStepText">Procesando en RAM...</span>
                                    <span id="renderTimerText">0s</span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-success progress-bar-striped progress-bar-animated" id="renderProgressBar" style="width: 100%"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- RIGHT COLUMN: Interactive Deep Zoom Viewer & Downloads -->
                    <div class="col-lg-7 col-xl-8">
                        <div class="studio-card p-3 p-md-4">
                            <!-- Viewer Header & Telemetry -->
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                                <div>
                                    <h5 class="text-white mb-0 fs-16 fw-semibold d-flex align-items-center gap-2">
                                        <i class="ri-zoom-in-line text-info"></i> Visor Interactivo Ultra-HD
                                    </h5>
                                    <span class="text-secondary fs-12" id="telemetryDetails">
                                        Haz zoom con la rueda del mouse o arrastra para ver cada micro-foto individual.
                                    </span>
                                </div>

                                <div class="d-flex gap-2" id="downloadButtonsWrap">
                                    <button class="btn btn-sm btn-outline-light d-flex align-items-center gap-1 disabled" id="btnDownloadPreview">
                                        <i class="ri-download-2-line"></i> Preview (Marca de Agua)
                                    </button>
                                    <button class="btn btn-sm btn-success d-flex align-items-center gap-1 disabled" id="btnDownloadUhd">
                                        <i class="ri-file-download-fill"></i> Descargar Master Ultra-HD
                                    </button>
                                </div>
                            </div>

                            <!-- Viewport Container -->
                            <div class="viewer-viewport" id="viewport">
                                <!-- Floating Controls -->
                                <div class="viewer-toolbar">
                                    <button class="btn btn-sm btn-dark text-white border-0 py-1 px-2" id="btnZoomIn" title="Acercar (+)">
                                        <i class="ri-zoom-in-line fs-16"></i>
                                    </button>
                                    <button class="btn btn-sm btn-dark text-white border-0 py-1 px-2" id="btnZoomOut" title="Alejar (-)">
                                        <i class="ri-zoom-out-line fs-16"></i>
                                    </button>
                                    <button class="btn btn-sm btn-dark text-white border-0 py-1 px-2" id="btnResetZoom" title="Restablecer (100%)">
                                        <i class="ri-refresh-line fs-16"></i>
                                    </button>
                                    <button class="btn btn-sm btn-dark text-white border-0 py-1 px-2" id="btnFullscreen" title="Pantalla Completa">
                                        <i class="ri-fullscreen-line fs-16"></i>
                                    </button>
                                </div>

                                <!-- Floating Zoom Level Badge -->
                                <div class="viewer-badge" id="zoomBadge">
                                    Zoom: <span class="fw-bold text-info" id="zoomLevelText">100%</span>
                                </div>

                                <!-- Panzoom Canvas Element -->
                                <div class="panzoom-element" id="panzoomTarget">
                                    <img src="" id="mosaicImage" alt="Fotomosaico Ultra-HD" class="d-none">
                                    <div id="viewerPlaceholder" class="text-center p-5 text-secondary">
                                        <i class="ri-image-2-line fs-60 text-secondary mb-3 d-block opacity-50"></i>
                                        <h5 class="text-light fs-16 fw-medium">El visor se activará aquí al generar el mosaico</h5>
                                        <p class="fs-13 text-secondary mb-0">Carga la foto principal, el lote de micro-fotos y presiona "Generar".</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Canvas Specifications Footer -->
                            <div class="row g-2 mt-3 pt-2 border-top border-secondary text-secondary fs-12" id="specFooter">
                                <div class="col-sm-3">
                                    <span class="d-block text-white fw-semibold" id="specResolution">-- × -- px</span>
                                    <span>Resolución Final</span>
                                </div>
                                <div class="col-sm-3">
                                    <span class="d-block text-white fw-semibold" id="specCells">-- celdas</span>
                                    <span>Micro-Fotos Colocadas</span>
                                </div>
                                <div class="col-sm-3">
                                    <span class="d-block text-white fw-semibold" id="specPalette">-- fotos</span>
                                    <span>Paleta Disponible</span>
                                </div>
                                <div class="col-sm-3">
                                    <span class="d-block text-white fw-semibold" id="specTime">-- s</span>
                                    <span>Tiempo de Render</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex-grow-1"></div>
            @include('partials.footer')
        </div>
    </div>

    <!-- Modal New Project -->
    <div class="modal fade" id="modalNewProject" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-dark text-white border-secondary">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title fs-16">Crear Nuevo Proyecto de Fotomosaico</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="projectNameInput" class="form-label fs-13 text-light">Nombre del Proyecto</label>
                        <input type="text" class="form-control bg-secondary text-white border-0" id="projectNameInput" placeholder="Ej: Mosaico Aniversario 15K" value="Mosaico Ultra-HD {{ date('Y-m-d') }}">
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-sm btn-outline-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-sm btn-primary" id="btnSubmitNewProject">Crear e Iniciar</button>
                </div>
            </div>
        </div>
    </div>

    @include('partials.theme_settings')
    @include('partials.scripts')

    <!-- Panzoom Library -->
    <script src="{{ asset('assets/js/panzoom.min.js') }}"></script>

    <!-- Studio Core Script (Vanilla JS + Fetch API + Canvas Compression) -->
    <script>
        (function() {
            // State
            let currentProjectId = "{{ $recentProjects->first()?->id ?? '' }}";
            let panzoomInstance = null;
            let pollingInterval = null;
            let renderStartTime = null;
            let renderTimerInterval = null;

            // DOM Elements
            const targetDropZone = document.getElementById('targetDropZone');
            const targetInput = document.getElementById('targetInput');
            const targetPreviewWrap = document.getElementById('targetPreviewWrap');
            const targetPlaceholder = document.getElementById('targetPlaceholder');
            const targetPreview = document.getElementById('targetPreview');
            const btnChangeTarget = document.getElementById('btnChangeTarget');

            const tilesDropZone = document.getElementById('tilesDropZone');
            const tilesInput = document.getElementById('tilesInput');
            const tileCounterBadge = document.getElementById('tileCounterBadge');
            const tilesGallery = document.getElementById('tilesGallery');
            const uploadProgressContainer = document.getElementById('uploadProgressContainer');
            const uploadProgressBar = document.getElementById('uploadProgressBar');
            const uploadStatusText = document.getElementById('uploadStatusText');

            const modeToggle = document.getElementById('modeToggle');
            const modeLabel = document.getElementById('modeLabel');
            const modeDesc = document.getElementById('modeDesc');

            const sliderColumns = document.getElementById('sliderColumns');
            const valColumns = document.getElementById('valColumns');
            const calcTotalCells = document.getElementById('calcTotalCells');

            const sliderTileRes = document.getElementById('sliderTileRes');
            const valTileRes = document.getElementById('valTileRes');
            const calcCanvasRes = document.getElementById('calcCanvasRes');

            const sliderBlend = document.getElementById('sliderBlend');
            const valBlend = document.getElementById('valBlend');

            const sliderRepetition = document.getElementById('sliderRepetition');
            const valRepetition = document.getElementById('valRepetition');

            const checkFlips = document.getElementById('checkFlips');
            const btnGenerate = document.getElementById('btnGenerate');
            const btnGenerateText = document.getElementById('btnGenerateText');
            const btnGenerateSpinner = document.getElementById('btnGenerateSpinner');
            const renderProgressWrap = document.getElementById('renderProgressWrap');
            const renderStepText = document.getElementById('renderStepText');
            const renderTimerText = document.getElementById('renderTimerText');
            const statusBadge = document.getElementById('statusBadge');

            const mosaicImage = document.getElementById('mosaicImage');
            const viewerPlaceholder = document.getElementById('viewerPlaceholder');
            const panzoomTarget = document.getElementById('panzoomTarget');
            const zoomLevelText = document.getElementById('zoomLevelText');
            const btnZoomIn = document.getElementById('btnZoomIn');
            const btnZoomOut = document.getElementById('btnZoomOut');
            const btnResetZoom = document.getElementById('btnResetZoom');
            const btnFullscreen = document.getElementById('btnFullscreen');
            const viewport = document.getElementById('viewport');

            const btnDownloadPreview = document.getElementById('btnDownloadPreview');
            const btnDownloadUhd = document.getElementById('btnDownloadUhd');
            const projectSelector = document.getElementById('projectSelector');
            const btnSubmitNewProject = document.getElementById('btnSubmitNewProject');
            const projectNameInput = document.getElementById('projectNameInput');

            // Initialize Panzoom
            function initPanzoom() {
                if (panzoomInstance) {
                    panzoomInstance.destroy();
                }
                panzoomInstance = Panzoom(panzoomTarget, {
                    maxScale: 25,
                    minScale: 0.2,
                    contain: 'outside',
                    cursor: 'grab',
                });

                panzoomTarget.parentElement.addEventListener('wheel', panzoomInstance.zoomWithWheel);

                panzoomTarget.addEventListener('panzoomchange', (e) => {
                    const scale = Math.round(e.detail.scale * 100);
                    zoomLevelText.textContent = `${scale}%`;
                });
            }

            // Slider Listeners & Calculations
            sliderColumns.addEventListener('input', () => {
                const cols = parseInt(sliderColumns.value);
                valColumns.textContent = `${cols} cols`;
                const approxRows = Math.round(cols * 0.75);
                calcTotalCells.textContent = `~${(cols * approxRows).toLocaleString()} celdas`;
                updateCanvasCalc();
            });

            sliderTileRes.addEventListener('input', () => {
                valTileRes.textContent = `${sliderTileRes.value} px`;
                updateCanvasCalc();
            });

            sliderBlend.addEventListener('input', () => {
                valBlend.textContent = `${sliderBlend.value}%`;
            });

            sliderRepetition.addEventListener('input', () => {
                valRepetition.textContent = `Radio ${sliderRepetition.value}`;
            });

            function updateCanvasCalc() {
                const cols = parseInt(sliderColumns.value);
                const res = parseInt(sliderTileRes.value);
                calcCanvasRes.textContent = `Lienzo: ~${(cols * res).toLocaleString()} px`;
            }

            // Mode Toggle Switch
            modeToggle.addEventListener('change', () => {
                if (modeToggle.checked) {
                    modeLabel.textContent = "Modo Producción";
                    modeDesc.textContent = "Recorte 300×300px en navegador con Canvas";
                } else {
                    modeLabel.textContent = "Modo Local Rápido";
                    modeDesc.textContent = "Originales directos a RAM (32 GB)";
                }
            });

            // Target Image Drag & Drop
            targetDropZone.addEventListener('click', () => targetInput.click());
            btnChangeTarget.addEventListener('click', (e) => {
                e.stopPropagation();
                targetInput.click();
            });

            ['dragenter', 'dragover'].forEach(eventName => {
                targetDropZone.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    targetDropZone.classList.add('dragover');
                });
            });

            ['dragleave', 'drop'].forEach(eventName => {
                targetDropZone.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    targetDropZone.classList.remove('dragover');
                });
            });

            targetDropZone.addEventListener('drop', (e) => {
                if (e.dataTransfer.files && e.dataTransfer.files[0]) {
                    handleTargetFile(e.dataTransfer.files[0]);
                }
            });

            targetInput.addEventListener('change', () => {
                if (targetInput.files && targetInput.files[0]) {
                    handleTargetFile(targetInput.files[0]);
                }
            });

            async function ensureProject() {
                if (!currentProjectId) {
                    const res = await fetch('/api/projects', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ name: 'Mosaico Ultra-HD ' + new Date().toISOString().slice(0, 10) })
                    });
                    const data = await res.json();
                    if (data.success) {
                        currentProjectId = data.project.id;
                        const opt = new Option(data.project.name, data.project.id, true, true);
                        projectSelector.add(opt);
                    }
                }
                return currentProjectId;
            }

            async function handleTargetFile(file) {
                await ensureProject();

                // Local Preview
                const reader = new FileReader();
                reader.onload = (e) => {
                    targetPreview.src = e.target.result;
                    targetPreviewWrap.classList.remove('d-none');
                    targetPlaceholder.classList.add('d-none');
                };
                reader.readAsDataURL(file);

                const formData = new FormData();
                formData.append('target_image', file);

                try {
                    const res = await fetch(`/api/projects/${currentProjectId}/target`, {
                        method: 'POST',
                        body: formData
                    });
                    const data = await res.json();
                    if (data.success) {
                        document.getElementById('targetSpecs').textContent = `${(file.size / (1024 * 1024)).toFixed(1)} MB`;
                    }
                } catch (err) {
                    console.error('Error subiendo imagen objetivo:', err);
                }
            }

            // Tiles Drag & Drop & Batch Upload
            tilesDropZone.addEventListener('click', () => tilesInput.click());

            ['dragenter', 'dragover'].forEach(eventName => {
                tilesDropZone.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    tilesDropZone.classList.add('dragover');
                });
            });

            ['dragleave', 'drop'].forEach(eventName => {
                tilesDropZone.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    tilesDropZone.classList.remove('dragover');
                });
            });

            tilesDropZone.addEventListener('drop', (e) => {
                if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
                    handleTilesFiles(Array.from(e.dataTransfer.files));
                }
            });

            tilesInput.addEventListener('change', () => {
                if (tilesInput.files && tilesInput.files.length > 0) {
                    handleTilesFiles(Array.from(tilesInput.files));
                }
            });

            // Client-Side Canvas Pre-processing to 300x300
            function processTileWithCanvas(file) {
                return new Promise((resolve) => {
                    const img = new Image();
                    const url = URL.createObjectURL(file);
                    img.onload = () => {
                        URL.revokeObjectURL(url);
                        const canvas = document.createElement('canvas');
                        canvas.width = 300;
                        canvas.height = 300;
                        const ctx = canvas.getContext('2d');

                        const minDim = Math.min(img.width, img.height);
                        const sx = (img.width - minDim) / 2;
                        const sy = (img.height - minDim) / 2;

                        ctx.drawImage(img, sx, sy, minDim, minDim, 0, 0, 300, 300);
                        canvas.toBlob((blob) => {
                            resolve(new File([blob], file.name, { type: 'image/jpeg' }));
                        }, 'image/jpeg', 0.85);
                    };
                    img.src = url;
                });
            }

            async function handleTilesFiles(files) {
                await ensureProject();
                const isProductionMode = modeToggle.checked;

                uploadProgressContainer.classList.remove('d-none');
                uploadStatusText.classList.remove('d-none');
                tilesGallery.classList.remove('d-none');

                const totalFiles = files.length;
                let processedFiles = [];

                uploadStatusText.textContent = isProductionMode 
                    ? `Pre-procesando ${totalFiles} fotos en Canvas a 300x300...`
                    : `Preparando ${totalFiles} fotos para subida directa a RAM...`;

                // Quick preview of first 12 thumbs
                files.slice(0, 12).forEach(f => {
                    const thumb = document.createElement('img');
                    thumb.src = URL.createObjectURL(f);
                    thumb.className = 'tile-thumb';
                    tilesGallery.appendChild(thumb);
                });

                if (isProductionMode) {
                    for (let i = 0; i < totalFiles; i++) {
                        const cropped = await processTileWithCanvas(files[i]);
                        processedFiles.push(cropped);
                        const pct = Math.round(((i + 1) / totalFiles) * 30);
                        uploadProgressBar.style.width = `${pct}%`;
                    }
                } else {
                    processedFiles = files;
                }

                // Batch Upload in chunks of 20
                const batchSize = 25;
                let uploaded = 0;

                for (let i = 0; i < processedFiles.length; i += batchSize) {
                    const chunk = processedFiles.slice(i, i + batchSize);
                    const formData = new FormData();
                    chunk.forEach(f => formData.append('tiles[]', f));

                    const res = await fetch(`/api/projects/${currentProjectId}/tiles`, {
                        method: 'POST',
                        body: formData
                    });
                    const data = await res.json();
                    if (data.success) {
                        uploaded += chunk.length;
                        tileCounterBadge.textContent = `${data.total_tiles} fotos`;
                        const pct = isProductionMode 
                            ? 30 + Math.round((uploaded / totalFiles) * 70)
                            : Math.round((uploaded / totalFiles) * 100);
                        uploadProgressBar.style.width = `${pct}%`;
                        uploadStatusText.textContent = `Subidas ${uploaded} de ${totalFiles} fotos...`;
                    }
                }

                uploadStatusText.textContent = `¡${uploaded} fotos añadidas con éxito!`;
                setTimeout(() => {
                    uploadProgressContainer.classList.add('d-none');
                }, 2000);
            }

            // Generate Button Action
            btnGenerate.addEventListener('click', async () => {
                await ensureProject();

                btnGenerate.disabled = true;
                btnGenerateSpinner.classList.remove('d-none');
                btnGenerateText.textContent = "Procesando...";
                renderProgressWrap.classList.remove('d-none');
                statusBadge.textContent = "Renderizando";
                statusBadge.className = "badge-status bg-warning text-dark";

                renderStartTime = Date.now();
                renderTimerInterval = setInterval(() => {
                    const elapsed = Math.round((Date.now() - renderStartTime) / 1000);
                    renderTimerText.textContent = `${elapsed}s`;
                }, 1000);

                const payload = {
                    grid_size: parseInt(sliderColumns.value),
                    tile_res: parseInt(sliderTileRes.value),
                    blend_alpha: parseFloat(sliderBlend.value) / 100,
                    min_distance_radius: parseInt(sliderRepetition.value),
                    allow_flips: checkFlips.checked,
                    color_space: 'lab'
                };

                try {
                    const res = await fetch(`/api/projects/${currentProjectId}/generate`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload)
                    });
                    const data = await res.json();

                    if (!data.success) {
                        alert(data.message || 'Error al iniciar la generación');
                        resetGenerateButton();
                        return;
                    }

                    // Start polling
                    startStatusPolling();

                } catch (err) {
                    console.error('Error generando mosaico:', err);
                    alert('Ocurrió un error al despachar el proceso.');
                    resetGenerateButton();
                }
            });

            function startStatusPolling() {
                if (pollingInterval) clearInterval(pollingInterval);

                pollingInterval = setInterval(async () => {
                    try {
                        const res = await fetch(`/api/projects/${currentProjectId}/status`);
                        const data = await res.json();

                        if (data.project) {
                            if (data.project.status === 'completed') {
                                clearInterval(pollingInterval);
                                clearInterval(renderTimerInterval);
                                loadCompletedMosaic(data);
                                resetGenerateButton();
                            } else if (data.project.status === 'failed') {
                                clearInterval(pollingInterval);
                                clearInterval(renderTimerInterval);
                                statusBadge.textContent = "Fallido";
                                statusBadge.className = "badge-status bg-danger text-white";
                                alert('El motor de procesamiento falló: ' + (data.project.settings?.error_message || 'Error desconocido'));
                                resetGenerateButton();
                            }
                        }
                    } catch (e) {
                        console.error('Polling error:', e);
                    }
                }, 1500);
            }

            function loadCompletedMosaic(data) {
                statusBadge.textContent = "Completado";
                statusBadge.className = "badge-status bg-success text-white";

                const previewUrl = data.preview_url + '?t=' + Date.now();
                mosaicImage.src = previewUrl;
                mosaicImage.classList.remove('d-none');
                viewerPlaceholder.classList.add('d-none');

                mosaicImage.onload = () => {
                    initPanzoom();
                    panzoomInstance.reset();
                };

                // Enable Downloads
                btnDownloadPreview.classList.remove('disabled');
                btnDownloadPreview.onclick = () => window.location.href = `/api/projects/${currentProjectId}/download/preview`;

                btnDownloadUhd.classList.remove('disabled');
                btnDownloadUhd.onclick = () => window.location.href = `/api/projects/${currentProjectId}/download/master`;

                // Update Telemetry Footer
                const stats = data.project.settings?.result_stats;
                if (stats) {
                    if (stats.resolution) {
                        document.getElementById('specResolution').textContent = `${stats.resolution.width.toLocaleString()} × ${stats.resolution.height.toLocaleString()} px (${stats.resolution.megapixels} MP)`;
                    }
                    if (stats.grid) {
                        document.getElementById('specCells').textContent = `${stats.grid.total_cells.toLocaleString()} celdas`;
                    }
                    if (stats.total_palette_size) {
                        document.getElementById('specPalette').textContent = `${stats.total_palette_size} fotos`;
                    }
                    if (stats.execution_time_seconds) {
                        document.getElementById('specTime').textContent = `${stats.execution_time_seconds} s`;
                    }
                }
            }

            function resetGenerateButton() {
                btnGenerate.disabled = false;
                btnGenerateSpinner.classList.add('d-none');
                btnGenerateText.textContent = "Generar Fotomosaico Ultra-HD";
                renderProgressWrap.classList.add('d-none');
            }

            // Toolbar Controls
            btnZoomIn.addEventListener('click', () => {
                if (panzoomInstance) panzoomInstance.zoomIn();
            });

            btnZoomOut.addEventListener('click', () => {
                if (panzoomInstance) panzoomInstance.zoomOut();
            });

            btnResetZoom.addEventListener('click', () => {
                if (panzoomInstance) panzoomInstance.reset();
            });

            btnFullscreen.addEventListener('click', () => {
                if (!document.fullscreenElement) {
                    viewport.requestFullscreen().catch(err => alert(err.message));
                } else {
                    document.exitFullscreen();
                }
            });

            // Project Selector Change
            projectSelector.addEventListener('change', async () => {
                if (projectSelector.value) {
                    currentProjectId = projectSelector.value;
                    const res = await fetch(`/api/projects/${currentProjectId}/status`);
                    const data = await res.json();
                    if (data.project) {
                        statusBadge.textContent = data.project.status;
                        tileCounterBadge.textContent = `${data.tiles_count} fotos`;

                        if (data.target_url) {
                            targetPreview.src = data.target_url;
                            targetPreviewWrap.classList.remove('d-none');
                            targetPlaceholder.classList.add('d-none');
                        }

                        if (data.project.status === 'completed' && data.preview_url) {
                            loadCompletedMosaic(data);
                        } else {
                            mosaicImage.classList.add('d-none');
                            viewerPlaceholder.classList.remove('d-none');
                            btnDownloadPreview.classList.add('disabled');
                            btnDownloadUhd.classList.add('disabled');
                        }
                    }
                }
            });

            // New Project Modal Submission
            btnSubmitNewProject.addEventListener('click', async () => {
                const name = projectNameInput.value.trim() || 'Nuevo Proyecto';
                const res = await fetch('/api/projects', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ name })
                });
                const data = await res.json();
                if (data.success) {
                    currentProjectId = data.project.id;
                    const opt = new Option(data.project.name, data.project.id, true, true);
                    projectSelector.add(opt);

                    // Reset View
                    targetPreviewWrap.classList.add('d-none');
                    targetPlaceholder.classList.remove('d-none');
                    tileCounterBadge.textContent = '0 fotos';
                    tilesGallery.innerHTML = '';
                    mosaicImage.classList.add('d-none');
                    viewerPlaceholder.classList.remove('d-none');
                    btnDownloadPreview.classList.add('disabled');
                    btnDownloadUhd.classList.add('disabled');
                    statusBadge.textContent = 'Borrador';
                    statusBadge.className = 'badge-status bg-secondary text-white';

                    const modal = bootstrap.Modal.getInstance(document.getElementById('modalNewProject'));
                    if (modal) modal.hide();
                }
            });

            // Initialize on Load
            initPanzoom();
            if (currentProjectId) {
                projectSelector.dispatchEvent(new Event('change'));
            }
        })();
    </script>
</body>
</html>
