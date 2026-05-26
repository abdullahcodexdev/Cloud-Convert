<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FluxConvert | Smart File Conversion</title>
    <meta name="description" content="Professional file conversion platform with a polished, fast workflow.">
    <link rel="icon" type="image/svg+xml" href="{{ asset('img/fluxconvert-logo.svg') }}">
    <link rel="apple-touch-icon" href="{{ asset('img/fluxconvert-logo.svg') }}">
    <link href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}?v={{ $assetVersion }}" rel="stylesheet">
    <link href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.css') }}?v={{ $assetVersion }}" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/styles.css') }}?v={{ $assetVersion }}">
</head>
<body>
    <script>
        window.FLUX_CURRENT_USER = @json($currentUser ?? null);
    </script>
    <div id="app" v-cloak>
        <header class="site-header">
            <nav class="navbar navbar-expand-lg">
                <div class="container">
                    <a class="navbar-brand brand-mark" href="#">
                        <img class="brand-logo" src="{{ asset('img/fluxconvert-logo.svg') }}" alt="FluxConvert logo">
                        <span>FluxConvert</span>
                    </a>
                    <button class="navbar-toggler shadow-none border-0" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
                        <span class="navbar-toggler-icon"></span>
                    </button>
                    <div class="collapse navbar-collapse" id="mainNav">
                        <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2">
                            <li class="nav-item"><a class="nav-link" href="#">Home</a></li>
                            <li class="nav-item nav-tools" :class="{ 'tools-open': toolsMenuOpen }">
                                <a class="nav-link tools-trigger" href="#tools-panel" @click.prevent.stop="toggleToolsMenu">Tools</a>
                                <div class="tools-mega-menu" id="tools-panel">
                                    <div class="tools-grid">
                                        <div class="tool-group" v-for="group in toolGroups" :key="group.title">
                                            <h3>
                                                <i class="bi" :class="group.icon"></i>
                                                [[ group.title ]]
                                            </h3>
                                            <ul>
                                                <li v-for="item in group.items" :key="item">
                                                    <a href="#converter" @click.prevent.stop="openToolPreset(item)">[[ item ]]</a>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </li>
                            <li class="nav-item"><a class="nav-link" href="#features">Features</a></li>
                            <li class="nav-item"><a class="nav-link" href="#solutions">Solutions</a></li>
                            @if($currentUser ?? false)
                            <li class="nav-item"><a class="nav-link" href="/my-files">My Files</a></li>
                            <li class="nav-item nav-profile" :class="{ 'profile-open': profileMenuOpen }" @click.stop>
                                <button type="button" class="profile-trigger" @click="toggleProfileMenu">
                                    <span class="profile-avatar">
                                        <img v-if="currentUser.avatar" :src="currentUser.avatar" alt="">
                                        <span v-else>[[ userInitials ]]</span>
                                    </span>
                                    <span class="profile-trigger-copy">
                                        <strong>[[ userDisplayName ]]</strong>
                                        <small>[[ currentUser.provider ]] account</small>
                                    </span>
                                    <i class="bi bi-chevron-down"></i>
                                </button>
                                <div class="profile-menu">
                                    <div class="profile-menu-head">
                                        <span class="profile-avatar profile-avatar-large">
                                            <img v-if="currentUser.avatar" :src="currentUser.avatar" alt="">
                                            <span v-else>[[ userInitials ]]</span>
                                        </span>
                                        <div>
                                            <strong>[[ userDisplayName ]]</strong>
                                            <span>[[ currentUser.provider ]] account</span>
                                        </div>
                                    </div>
                                    <div class="profile-menu-item profile-menu-static">
                                        <i class="bi bi-folder-check"></i>
                                        <span>[[ conversionHistory.length ]] saved files</span>
                                    </div>
                                    <a href="/profile" class="profile-menu-item">
                                        <i class="bi bi-person"></i>
                                        <span>Profile</span>
                                    </a>
                                    <a href="/settings" class="profile-menu-item">
                                        <i class="bi bi-gear"></i>
                                        <span>Settings</span>
                                    </a>
                                    <a href="/signout" class="profile-menu-item profile-menu-signout">
                                        <i class="bi bi-box-arrow-right"></i>
                                        <span>Sign Out</span>
                                    </a>
                                </div>
                            </li>
                            @else
                            <li class="nav-item"><a class="nav-link nav-signin" href="/signin">Sign In</a></li>
                            @endif
                        </ul>
                    </div>
                </div>
            </nav>
        </header>

        <main>
            <section class="hero-section">
                <div class="container">
                    <div class="hero-inner row align-items-center">
                        <div class="col-lg-7">
                            <h1 class="hero-title">File Converter</h1>
                            <p class="hero-description">
                                CloudConvert is an online file converter. We support nearly all audio, video,
                                document, ebook, archive, image, spreadsheet, and presentation formats.
                                To get started, use the button below and select files to convert from your computer.
                            </p>
                        </div>
                        <div class="col-lg-5">
                            <div class="hero-controls" id="converter">
                                <span class="control-label">convert</span>
                                <div class="format-picker-wrap">
                                    <button type="button" class="control-select control-button" @click.stop="togglePicker('from')">
                                        <span>[[ displaySelectedFrom ]]</span>
                                        <i class="bi bi-chevron-down"></i>
                                    </button>
                                    <div class="format-picker-panel" v-show="pickerOpenFor === 'from'" @click.stop>
                                        <div class="format-search" @click.stop>
                                            <i class="bi bi-search"></i>
                                            <input type="text" v-model="pickerSearch" placeholder="Search Format">
                                        </div>
                                        <div class="format-picker-body">
                                            <div class="format-categories">
                                                <button
                                                    type="button"
                                                    class="format-category"
                                                    v-for="group in filteredFormatGroups"
                                                    :key="'from-' + group.name"
                                                    :class="{ active: activeFormatCategory === group.name }"
                                                    @click="setActiveFormatCategory(group.name)"
                                                >
                                                    <span>[[ group.name ]]</span>
                                                    <i v-if="activeFormatCategory === group.name" class="bi bi-chevron-right"></i>
                                                </button>
                                            </div>
                                            <div class="format-grid">
                                                <button
                                                    type="button"
                                                    class="format-chip"
                                                    v-for="format in currentFormatGroup.formats"
                                                    :key="'from-chip-' + format"
                                                    @click="selectFormat('from', format)"
                                                >
                                                    [[ format ]]
                                                </button>
                                                <span v-if="!currentFormatGroup.formats.length" class="format-empty-state">No supported formats</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="conversion-direction" aria-hidden="true">
                                    <span class="conversion-direction-line conversion-direction-line-left"></span>
                                    <span class="conversion-direction-icon">
                                        <i class="bi bi-arrow-repeat"></i>
                                    </span>
                                    <span class="conversion-direction-line conversion-direction-line-right"></span>
                                </div>
                                <div class="format-picker-wrap">
                                    <button type="button" class="control-select control-button" @click.stop="togglePicker('to')">
                                        <span>[[ displaySelectedTo ]]</span>
                                        <i class="bi bi-chevron-down"></i>
                                    </button>
                                    <div class="format-picker-panel" v-show="pickerOpenFor === 'to'" @click.stop>
                                        <div class="format-search" @click.stop>
                                            <i class="bi bi-search"></i>
                                            <input type="text" v-model="pickerSearch" placeholder="Search Format">
                                        </div>
                                        <div class="format-picker-body">
                                            <div class="format-categories">
                                                <button
                                                    type="button"
                                                    class="format-category"
                                                    v-for="group in filteredFormatGroups"
                                                    :key="'to-' + group.name"
                                                    :class="{ active: activeFormatCategory === group.name }"
                                                    @click="setActiveFormatCategory(group.name)"
                                                >
                                                    <span>[[ group.name ]]</span>
                                                    <i v-if="activeFormatCategory === group.name" class="bi bi-chevron-right"></i>
                                                </button>
                                            </div>
                                            <div class="format-grid">
                                                <button
                                                    type="button"
                                                    class="format-chip"
                                                    v-for="format in currentFormatGroup.formats"
                                                    :key="'to-chip-' + format"
                                                    @click="selectFormat('to', format)"
                                                >
                                                    [[ format ]]
                                                </button>
                                                <span v-if="!currentFormatGroup.formats.length" class="format-empty-state">No supported target formats</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="upload-section">
                <div class="container text-center">
                    <input
                        ref="fileInput"
                        type="file"
                        class="d-none"
                        :accept="uploadAccept"
                        :multiple="isMergePdfMode"
                        @change="handleFileChange"
                    >
                    <button type="button" class="btn upload-button" @click="triggerFileSelect">
                        <i class="bi bi-file-earmark-plus"></i>
                        <span>[[ uploadButtonLabel ]]</span>
                        <i class="bi bi-chevron-down"></i>
                    </button>
                    <p v-if="selectedFileName" class="upload-meta">
                        [[ selectedFileName ]] <span class="upload-meta-sep">&bull;</span> [[ selectedFileSize ]]
                    </p>
                    <div v-if="selectedFiles.length" class="conversion-queue" :class="{ 'merge-queue': isMergePdfMode }">
                        <div v-if="isMergePdfMode" class="merge-queue-head">
                            <div>
                                <strong>Merge PDF files</strong>
                                <span>[[ selectedFiles.length ]] PDF file[[ selectedFiles.length === 1 ? '' : 's' ]] selected</span>
                            </div>
                            <button v-if="selectedFiles.length >= 2" type="button" class="btn queue-secondary-btn" @click="triggerFileSelect">
                                <i class="bi bi-file-earmark-plus"></i>
                                <span>Add more Files</span>
                            </button>
                        </div>
                        <div
                            class="queue-list"
                            :class="{ 'queue-list-compact': selectedFiles.length >= 2 && !isMergePdfMode, 'queue-list-three': selectedFiles.length >= 3 && !isMergePdfMode, 'merge-file-list': isMergePdfMode }"
                        >
                            <article class="queue-item" v-for="item in selectedFiles" :key="item.id">
                                <div class="queue-item-meta">
                                    <i class="bi bi-file-earmark-text"></i>
                                    <div>
                                        <strong>[[ item.name ]]</strong>
                                        <span>[[ item.size ]]</span>
                                    </div>
                                </div>
                                <div v-if="!isMergePdfMode" class="queue-item-convert">
                                    <i class="bi bi-arrow-repeat"></i>
                                    <span>Convert to</span>
                                    <div class="format-picker-wrap queue-picker-wrap">
                                        <button type="button" class="queue-target-btn" @click.stop="togglePicker('queue-to', item.id)">
                                            <span>[[ item.targetFormat || '...' ]]</span>
                                            <i class="bi bi-chevron-down"></i>
                                        </button>
                                        <div class="format-picker-panel queue-format-picker-panel" v-show="pickerOpenFor === 'queue-to' && queuePickerItemId === item.id" @click.stop>
                                            <div class="format-search" @click.stop>
                                                <i class="bi bi-search"></i>
                                                <input type="text" v-model="pickerSearch" placeholder="Search Format">
                                            </div>
                                            <div class="format-picker-body">
                                                <div class="format-categories">
                                                    <button
                                                        type="button"
                                                        class="format-category"
                                                        v-for="group in filteredFormatGroups"
                                                        :key="'queue-to-' + group.name"
                                                        :class="{ active: activeFormatCategory === group.name }"
                                                        @click="setActiveFormatCategory(group.name)"
                                                    >
                                                        <span>[[ group.name ]]</span>
                                                        <i v-if="activeFormatCategory === group.name" class="bi bi-chevron-right"></i>
                                                    </button>
                                                </div>
                                                <div class="format-grid">
                                                    <button
                                                        type="button"
                                                        class="format-chip"
                                                        v-for="format in currentFormatGroup.formats"
                                                        :key="'queue-to-chip-' + format"
                                                        @click="selectFormat('queue-to', format)"
                                                    >
                                                        [[ format ]]
                                                    </button>
                                                    <span v-if="!currentFormatGroup.formats.length" class="format-empty-state">No supported target formats</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div v-else class="queue-item-convert merge-item-target">
                                    <i class="bi bi-union"></i>
                                    <span>Merge into PDF</span>
                                </div>
                                <p v-if="item.isImageOnlyPdf" class="queue-item-warning">
                                    This PDF looks scanned, so text export needs OCR.
                                </p>
                                <p v-if="item.error" class="queue-item-error">[[ item.error ]]</p>
                                <div class="queue-item-actions">
                                    <button
                                        v-if="item.downloadUrl"
                                        type="button"
                                        class="btn queue-inline-download-btn"
                                        @click="downloadQueuedFile(item)"
                                    >
                                        Download
                                    </button>
                                    <button type="button" class="queue-remove-btn" @click="removeQueuedFile(item.id)">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </div>
                            </article>
                        </div>
                        <div class="queue-actions">
                            <button v-if="!isMergePdfMode" type="button" class="btn queue-secondary-btn" @click="triggerFileSelect">
                                <i class="bi bi-file-earmark-plus"></i>
                                <span>Add more Files</span>
                                <i class="bi bi-chevron-down"></i>
                            </button>
                            <button
                                v-if="isMergePdfMode && mergedFile"
                                type="button"
                                class="btn queue-download-btn"
                                @click="downloadQueuedFile(mergedFile)"
                            >
                                <i class="bi bi-download"></i>
                                <span>Download Merged PDF</span>
                            </button>
                            <div class="queue-action-group">
                                <button
                                    v-if="!isMergePdfMode && convertedFiles.length > 1"
                                    type="button"
                                    class="btn queue-download-btn"
                                    @click="downloadAllConvertedFiles"
                                >
                                    <i class="bi bi-download"></i>
                                    <span>Download All Files</span>
                                </button>
                                <button type="button" class="btn queue-primary-btn" @click="convertQueuedFiles" :disabled="isConverting">
                                    <i class="bi" :class="isMergePdfMode ? 'bi-union' : 'bi-arrow-repeat'"></i>
                                    <span>[[ primaryActionLabel ]]</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="stats-section">
                <div class="container">
                    <div class="hero-stats row g-3">
                        <div class="col-sm-4" v-for="stat in stats" :key="stat.label">
                            <div class="stat-card">
                                <strong>[[ stat.value ]]</strong>
                                <span>[[ stat.label ]]</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="feature-section" id="features">
                <div class="container">
                    <div class="section-heading text-center">
                        <span class="eyebrow">Capabilities</span>
                        <h2>Built around the real file tasks people run every day.</h2>
                    </div>
                    <div class="row g-4 mt-2">
                        <div class="col-md-6 col-xl-3" v-for="tool in tools" :key="tool.name">
                            <article class="feature-card reveal-card">
                                <i class="bi" :class="tool.icon"></i>
                                <h3>[[ tool.name ]] Conversion</h3>
                                <p>Support for [[ tool.formats ]] with a polished, minimal conversion flow.</p>
                            </article>
                        </div>
                    </div>
                </div>
            </section>

            <section class="formats-security-section" id="solutions">
                <div class="container">
                    <div class="formats-security-grid">
                        <div class="formats-panel">
                            <span class="dark-eyebrow"><i class="bi bi-box-seam"></i> Formats supported</span>
                            <h2>Hundreds of formats, thousands of conversion types.</h2>
                            <p>
                                The everyday ones, the niche ones, and the formats teams depend on for documents,
                                images, video, audio, spreadsheets, archives, and presentations.
                            </p>

                            <div class="format-type-tabs">
                                <button type="button" class="format-type-tab active"><i class="bi bi-file-earmark-text"></i><span>Documents</span><small>23</small></button>
                                <button type="button" class="format-type-tab"><i class="bi bi-image"></i><span>Images</span><small>42</small></button>
                                <button type="button" class="format-type-tab"><i class="bi bi-camera-video"></i><span>Video</span><small>27</small></button>
                                <button type="button" class="format-type-tab"><i class="bi bi-music-note-beamed"></i><span>Audio</span><small>20</small></button>
                                <button type="button" class="format-type-tab"><i class="bi bi-table"></i><span>Spreadsheets</span><small>8</small></button>
                                <button type="button" class="format-type-tab"><i class="bi bi-display"></i><span>Slides</span><small>11</small></button>
                                <button type="button" class="format-type-tab"><i class="bi bi-book"></i><span>E-books</span><small>22</small></button>
                                <button type="button" class="format-type-tab"><i class="bi bi-archive"></i><span>Archives</span><small>39</small></button>
                                <button type="button" class="format-type-tab"><i class="bi bi-bounding-box"></i><span>Vector</span><small>10</small></button>
                                <button type="button" class="format-type-tab"><i class="bi bi-badge-3d"></i><span>CAD</span><small>3</small></button>
                            </div>

                            <div class="supported-format-cloud">
                                <button type="button" @click="selectFormat('from', 'PDF')">PDF</button>
                                <button type="button" @click="selectFormat('from', 'DOCX')">DOCX</button>
                                <button type="button" @click="selectFormat('from', 'DOC')">DOC</button>
                                <button type="button" @click="selectFormat('from', 'TXT')">TXT</button>
                                <button type="button" @click="selectFormat('from', 'RTF')">RTF</button>
                                <button type="button" @click="selectFormat('from', 'ODT')">ODT</button>
                                <button type="button" @click="selectFormat('from', 'HTML')">HTML</button>
                                <button type="button" @click="selectFormat('from', 'CSV')">CSV</button>
                                <button type="button" @click="selectFormat('from', 'XLSX')">XLSX</button>
                                <button type="button" @click="selectFormat('from', 'PPTX')">PPTX</button>
                                <button type="button" @click="selectFormat('from', 'JPG')">JPG</button>
                                <button type="button" @click="selectFormat('from', 'PNG')">PNG</button>
                                <button type="button" @click="selectFormat('from', 'WEBP')">WEBP</button>
                                <button type="button" @click="selectFormat('from', 'MP4')">MP4</button>
                                <button type="button" @click="selectFormat('from', 'MP3')">MP3</button>
                                <button type="button" @click="selectFormat('from', 'ZIP')">ZIP</button>
                            </div>
                        </div>

                        <aside class="security-panel">
                            <span class="dark-eyebrow"><i class="bi bi-shield-lock-fill"></i> Data security</span>
                            <h2>Your files, handled securely.</h2>
                            <p>
                                Each conversion runs in a dedicated process. Files are kept private,
                                prepared only for download, and never exposed through public listings.
                            </p>
                            <a href="#converter">Start a secure conversion <i class="bi bi-arrow-right"></i></a>
                            <div class="security-seal" aria-hidden="true">
                                <div>
                                    <i class="bi bi-shield-check"></i>
                                    <strong>Secure</strong>
                                    <span>File handling</span>
                                </div>
                            </div>
                        </aside>
                    </div>
                </div>
            </section>

        </main>

        <footer class="site-footer">
            <div class="container footer-shell">
                <div class="footer-brand-block">
                    <a class="footer-brand" href="#">
                        <img class="brand-logo" src="{{ asset('img/fluxconvert-logo.svg') }}" alt="FluxConvert logo">
                        <span>FluxConvert</span>
                    </a>
                    <p class="mb-0">Modern file conversion for documents, media, images, and archives with a cleaner workflow built for speed and clarity.</p>
                </div>
                <div class="footer-links-grid">
                    <div>
                        <span class="footer-title">Product</span>
                        <div class="footer-links">
                            <a href="#features">Features</a>
                            <a href="#solutions">Solutions</a>
                        </div>
                    </div>
                    <div>
                        <span class="footer-title">Access</span>
                        <div class="footer-links">
                            @if($currentUser ?? false)
                            <a href="/signout">Sign Out</a>
                            @else
                            <a href="/signin">Sign In</a>
                            <a href="/signup">Sign Up</a>
                            @endif
                            <a href="#converter">Launch Tool</a>
                        </div>
                    </div>
                </div>
                <div class="footer-socials">
                    <a href="#" class="footer-social-link" aria-label="Facebook">
                        <span class="social-tooltip">Facebook</span>
                        <i class="bi bi-facebook"></i>
                    </a>
                    <a href="#" class="footer-social-link" aria-label="Instagram">
                        <span class="social-tooltip">Instagram</span>
                        <i class="bi bi-instagram"></i>
                    </a>
                    <a href="#" class="footer-social-link" aria-label="Twitter">
                        <span class="social-tooltip">Twitter</span>
                        <i class="bi bi-twitter-x"></i>
                    </a>
                    <a href="#" class="footer-social-link" aria-label="LinkedIn">
                        <span class="social-tooltip">LinkedIn</span>
                        <i class="bi bi-linkedin"></i>
                    </a>
                </div>
            </div>
            <div class="container footer-bottom">
                <span>&copy; 2026 FluxConvert</span>
                <div class="footer-bottom-links">
                    <a href="#">Privacy</a>
                    <a href="#">Terms</a>
                    <a href="#">Support</a>
                </div>
            </div>
        </footer>

        <button
            v-show="showBackToTop"
            type="button"
            class="back-to-top"
            @click="scrollToTop"
            aria-label="Back to top"
        >
            <i class="bi bi-arrow-up"></i>
        </button>
    </div>

    @include('partials.whatsapp-chat')

    <script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}?v={{ $assetVersion }}"></script>
    <script src="{{ asset('vendor/vue/vue.global.prod.js') }}?v={{ $assetVersion }}"></script>
    <script src="{{ asset('js/app.js') }}?v={{ $assetVersion }}"></script>
    @include('partials.performance')
</body>
</html>


