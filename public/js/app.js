const { createApp } = Vue;

const ARCHIVE_FORMATS = [
    "7Z", "ACE", "ALZ", "ARC", "ARJ", "BZ", "BZ2", "CAB", "CPIO", "DEB", "DMG",
    "GZ", "IMG", "ISO", "JAR", "LHA", "LZ", "LZMA", "LZO", "RAR", "RPM", "RZ",
    "TAR", "TAR.7Z", "TAR.BZ", "TAR.BZ2", "TAR.GZ", "TAR.LZO", "TAR.XZ", "TAR.Z",
    "TBZ", "TBZ2", "TGZ", "TZ", "TZO", "XZ", "Z", "ZIP",
];
const PPTX_TARGETS = ["PDF", "DOCX", "JPG", "PNG", "GIF", "TIFF", "MP4", "WMV", "PPT", "ODP", "HTML"];

function withJpegAlias(formats) {
    if (!Array.isArray(formats) || !formats.length) {
        return formats || [];
    }

    const normalized = [];
    let hasJpeg = false;

    for (const format of formats) {
        normalized.push(format);
        if (format === "JPEG") {
            hasJpeg = true;
        }
        if (format === "JPG" && !formats.includes("JPEG")) {
            normalized.push("JPEG");
            hasJpeg = true;
        }
    }

    if (!hasJpeg && formats.includes("JPG")) {
        normalized.push("JPEG");
    }

    return [...new Set(normalized)];
}

const PDF_TEXT_TARGETS = ["DOC", "DOCX", "XLS", "XLSX", "PPT", "PPTX", "TXT", "RTF", "HTML", "CSV", "XML", "JPG", "JPEG", "PNG", "GIF", "EPUB", "ODT"];
const PDF_SCAN_MARKERS = ["/Image", "/Subtype /Image", "/Filter /DCTDecode", "/Filter /JPXDecode", "/XObject"];
const PDF_TEXT_MARKERS = ["/Font", "/ToUnicode", "BT", " Tj", " TJ"];

createApp({
    delimiters: ["[[", "]]"],
    data() {
        return {
            stats: [],
            tools: [],
            toolGroups: [],
            formatGroups: [],
            formatDescriptions: {},
            steps: [],
            pricing: {
                monthly: [],
                yearly: [],
            },
            supportedTargetMap: {},
            billingPeriod: "monthly",
            activeToolGroup: null,
            selectedFrom: "",
            selectedTo: "",
            selectedFileName: "",
            selectedFileSize: "",
            selectedFiles: [],
            mergedFile: null,
            convertedFiles: [],
            conversionHistory: [],
            currentUser: window.FLUX_CURRENT_USER || null,
            activeToolName: "",
            isConverting: false,
            pickerOpenFor: null,
            queuePickerItemId: null,
            pickerSearch: "",
            activeFormatCategory: "Archive",
            hasOpenedFromPicker: false,
            toolsMenuOpen: false,
            profileMenuOpen: false,
            showBackToTop: false,
        };
    },
    computed: {
        userDisplayName() {
            return this.currentUser?.name || this.currentUser?.email || "Account";
        },
        userEmail() {
            return this.currentUser?.email || "";
        },
        userInitials() {
            const base = this.userDisplayName.trim();
            if (!base) {
                return "U";
            }

            return base
                .split(/\s+/)
                .slice(0, 2)
                .map((part) => part[0])
                .join("")
                .toUpperCase();
        },
        visiblePlans() {
            return this.pricing[this.billingPeriod] || [];
        },
        displaySelectedFrom() {
            return this.selectedFrom || "...";
        },
        displaySelectedTo() {
            return this.selectedTo || "...";
        },
        mergeConfig() {
            const map = {
                "Merge PDF": { type: "pdf", label: "PDF", formats: ["PDF"] },
                "Merge Audio": { type: "audio", label: "Audio", formats: ["MP3", "WAV", "OGG", "AAC", "FLAC", "M4A", "WMA"] },
                "Merge Video": { type: "video", label: "Video", formats: ["MP4", "MOV", "AVI", "MKV", "WEBM", "WMV", "FLV", "3GP", "M4V"] },
                "Merge Image": { type: "image", label: "Image", formats: ["JPG", "JPEG", "PNG", "GIF", "WEBP", "BMP", "AVIF", "TIF", "TIFF"] },
                "Merge Document": { type: "document", label: "Document", formats: ["TXT", "DOC", "DOCX", "HTML", "HTM", "MD", "RST", "TEX", "FB2", "XML", "JSON", "PDF"] },
                "Merge Spreadsheet": { type: "spreadsheet", label: "Spreadsheet", formats: ["XLSX", "XLS", "XLSM", "ODS", "CSV"] },
                "Merge Presentation": { type: "presentation", label: "Presentation", formats: ["PPTX"] },
            };
            return map[this.activeToolName] || null;
        },
        isMergeMode() {
            return this.mergeConfig !== null;
        },
        isMergePdfMode() {
            // Retained for templates; true for any merge tool.
            return this.isMergeMode;
        },
        uploadButtonLabel() {
            if (this.isMergeMode && this.selectedFiles.length) {
                return "Add more Files";
            }

            return this.isMergeMode ? `Select ${this.mergeConfig.label} Files` : "Select File";
        },
        primaryActionLabel() {
            if (this.isMergeMode) {
                return this.isConverting ? "Merging..." : "Merge Files";
            }

            return this.isConverting ? "Converting..." : "Convert";
        },
        activeQueueItem() {
            if (this.pickerOpenFor !== "queue-to" || !this.queuePickerItemId) {
                return null;
            }

            return this.selectedFiles.find((item) => item.id === this.queuePickerItemId) || null;
        },
        currentSourceFormat() {
            if (this.activeQueueItem?.sourceFormat) {
                return this.activeQueueItem.sourceFormat;
            }

            return this.selectedFrom;
        },
        currentTargetFormat() {
            if (this.activeQueueItem?.targetFormat) {
                return this.activeQueueItem.targetFormat;
            }

            return this.selectedTo;
        },
        uploadAccept() {
            const format = (this.selectedFrom || "").toUpperCase();
            const acceptMap = {
                JPG: ".jpg,.jpeg",
                TIF: ".tif,.tiff",
                AIF: ".aif",
                AIFC: ".aifc",
                AIFF: ".aiff,.aif",
                AC3: ".ac3",
                AMR: ".amr",
                AU: ".au,.snd",
                CAF: ".caf",
                TAR: ".tar",
                "TAR.GZ": ".tar.gz",
                TGZ: ".tgz,.tar.gz",
                "TAR.BZ2": ".tar.bz2",
                TBZ2: ".tbz2,.tar.bz2",
                "TAR.BZ": ".tar.bz",
                TBZ: ".tbz,.tar.bz",
                "TAR.XZ": ".tar.xz",
                "TAR.Z": ".tar.z",
                TZ: ".tz,.tar.z",
                TZO: ".tzo,.tar.lzo",
                "TAR.LZO": ".tar.lzo",
                DOC: ".doc",
                DOCM: ".docm",
                DOCX: ".docx",
                DOT: ".dot",
                DOTX: ".dotx",
                EOT: ".eot",
                ET: ".et",
                XLS: ".xls",
                XLSM: ".xlsm",
                XLSX: ".xlsx",
                DPS: ".dps",
                KEY: ".key",
                NUMBERS: ".numbers",
                ODP: ".odp",
                POT: ".pot",
                POTX: ".potx",
                PPS: ".pps",
                PPSX: ".ppsx",
                PPT: ".ppt",
                PPTM: ".pptm",
                PPTX: ".pptx",
                ODT: ".odt",
                ODS: ".ods",
                TXT: ".txt",
                MD: ".md,.markdown",
                HTM: ".htm,.html",
                HTML: ".html,.htm",
                EPUB: ".epub",
                FB2: ".fb2",
                HTMLZ: ".htmlz",
                XML: ".xml",
                JSON: ".json",
                CSV: ".csv",
                PDF: ".pdf",
                RST: ".rst",
                RTF: ".rtf",
                TEX: ".tex",
                ABW: ".abw",
                DJVU: ".djvu,.djv",
                HWP: ".hwp",
                LWP: ".lwp",
                OTF: ".otf",
                PAGES: ".pages",
                TTF: ".ttf",
                AZW: ".azw",
                AZW3: ".azw3",
                AZW4: ".azw4",
                CBC: ".cbc",
                CBR: ".cbr",
                CBZ: ".cbz",
                CHM: ".chm",
                LIT: ".lit",
                LRF: ".lrf",
                MOBI: ".mobi",
                PDB: ".pdb",
                PML: ".pml",
                PRC: ".prc",
                RB: ".rb",
                SNB: ".snb",
                TCR: ".tcr",
                TXTZ: ".txtz",
                WPD: ".wpd",
                WOFF: ".woff",
                WOFF2: ".woff2",
                WPS: ".wps",
                ZABW: ".zabw",
                PNG: ".png",
                GIF: ".gif",
                WEBP: ".webp",
                AVIF: ".avif",
                SVG: ".svg",
                SVGZ: ".svgz",
                BMP: ".bmp",
                CR2: ".cr2",
                CR3: ".cr3",
                CRW: ".crw",
                DCR: ".dcr",
                DNG: ".dng",
                ERF: ".erf",
                HEIC: ".heic",
                HEIF: ".heif",
                ICNS: ".icns",
                ICO: ".ico",
                JFIF: ".jfif",
                JPEG: ".jpeg,.jpg",
                MOS: ".mos",
                MRW: ".mrw",
                NEF: ".nef",
                ORF: ".orf",
                PEF: ".pef",
                PPM: ".ppm",
                PS: ".ps",
                PSB: ".psb",
                PSD: ".psd",
                EPS: ".eps",
                PUB: ".pub",
                RAF: ".raf",
                RAW: ".raw",
                RW2: ".rw2",
                SK: ".sk",
                SK1: ".sk1",
                TGA: ".tga",
                TIF: ".tif,.tiff",
                TIFF: ".tiff,.tif",
                VSD: ".vsd",
                WMF: ".wmf",
                X3F: ".x3f",
                XCF: ".xcf",
                XPS: ".xps",
                "3FR": ".3fr",
                ARW: ".arw",
                CDR: ".cdr",
                CGM: ".cgm",
                EMF: ".emf",
                AI: ".ai",
                MP3: ".mp3",
                WAV: ".wav",
                OGA: ".oga",
                OGG: ".ogg",
                AAC: ".aac",
                DSS: ".dss",
                FLAC: ".flac",
                M4A: ".m4a",
                M4B: ".m4b",
                VOC: ".voc",
                WEBA: ".weba,.webm",
                WMA: ".wma",
                MP4: ".mp4",
                MOV: ".mov",
                AVI: ".avi",
                FLV: ".flv",
                "3GP": ".3gp",
                M4V: ".m4v",
                MKV: ".mkv",
                WEBM: ".webm",
                WMV: ".wmv",
                ZIP: ".zip",
                RAR: ".rar",
                "7Z": ".7z",
                GZ: ".gz",
                BZ2: ".bz2",
                XZ: ".xz",
            };

            if (acceptMap[format]) {
                return acceptMap[format];
            }

            return format ? `.${format.toLowerCase()}` : "";
        },
        currentFormatGroup() {
            const groups = this.filteredFormatGroups;
            return groups.find((group) => group.name === this.activeFormatCategory) || groups[0] || { formats: [] };
        },
        supportedSourceFormats() {
            return Object.keys(this.supportedTargetMap || {});
        },
        supportedTargetFormats() {
            const format = (this.currentSourceFormat || "").toUpperCase();
            const directTargets = this.supportedTargetMap[format];
            if (directTargets) {
                let targets = withJpegAlias(directTargets);
                if (this.activeQueueItem?.isImageOnlyPdf) {
                    targets = targets.filter((target) => !PDF_TEXT_TARGETS.includes(target.toUpperCase()));
                }
                return targets;
            }

            return [];
        },
        isCurrentTargetSupported() {
            return this.supportedTargetFormats.includes((this.currentTargetFormat || "").toUpperCase());
        },
        selectedFormatDescription() {
            const format = (this.selectedFrom || "").toUpperCase();
            return this.formatDescriptions[format] || `${format} is a supported file format in this converter. Select a target type to transform this file into another compatible format.`;
        },
        filteredFormatGroups() {
            let baseGroups = this.contextualFormatGroups;
            const isTargetPicker = this.pickerOpenFor === "to" || this.pickerOpenFor === "queue-to";

            if (isTargetPicker) {
                const supported = new Set(this.supportedTargetFormats);
                baseGroups = this.formatGroups
                    .map((group) => ({
                        ...group,
                        formats: group.formats.filter((format) => supported.has(format.toUpperCase())),
                    }))
                    .filter((group) => group.formats.length > 0);
            } else if (this.supportedSourceFormats.length) {
                const supported = new Set(this.supportedSourceFormats);
                baseGroups = baseGroups
                    .map((group) => ({
                        ...group,
                        formats: group.formats.filter((format) => supported.has(format.toUpperCase())),
                    }))
                    .filter((group) => group.formats.length > 0);
            }

            if (!baseGroups.length && !isTargetPicker) {
                baseGroups = this.contextualFormatGroups.length ? this.contextualFormatGroups : this.formatGroups;
            }

            baseGroups = baseGroups.filter((group) => Array.isArray(group.formats) && group.formats.length > 0);

            if (!this.pickerSearch.trim()) {
                return baseGroups;
            }

            const term = this.pickerSearch.trim().toLowerCase();
            const searchedGroups = baseGroups
                .map((group) => ({
                    ...group,
                    formats: group.formats.filter((format) => format.toLowerCase().includes(term) || group.name.toLowerCase().includes(term)),
                }))
                .filter((group) => group.formats.length > 0);

            return searchedGroups.length ? searchedGroups : baseGroups;
        },
        contextualFormatGroups() {
            if (this.pickerOpenFor !== "to" && this.pickerOpenFor !== "queue-to") {
                return this.formatGroups;
            }

            const sourceCategory = this.findCategoryByFormat(this.currentSourceFormat);
            const allowedMap = {
                Archive: ["Archive"],
                Audio: ["Audio", "Video"],
                CAD: ["CAD", "Document", "Image", "Vector"],
                Document: ["Document", "Spreadsheet", "Presentation", "Image", "Ebook", "Other"],
                Ebook: ["Ebook", "Document"],
                Font: ["Font"],
                Image: ["Document", "Image"],
                Other: ["Document", "Other", "Spreadsheet"],
                Presentation: ["Document", "Image", "Presentation"],
                Spreadsheet: ["Document", "Image", "Spreadsheet"],
                Vector: ["Image", "Vector", "Document"],
                Video: ["Audio", "Image", "Video"],
            };

            const allowed = allowedMap[sourceCategory] || this.formatGroups.map((group) => group.name);
            return this.formatGroups.filter((group) => allowed.includes(group.name));
        },
    },
    methods: {
        async loadHighlights() {
            const response = await fetch("/api/highlights");
            const data = await response.json();
            this.stats = data.stats;
            this.tools = data.tools;
            this.toolGroups = data.tool_groups;
            this.formatGroups = data.format_groups;
            this.formatDescriptions = data.format_descriptions || {};
            this.supportedTargetMap = this.normalizeSupportedTargetMap(data.supported_conversion_map || {});
            this.steps = data.steps;
            this.pricing = data.pricing;
            if (this.toolGroups.length) {
                this.activeToolGroup = this.toolGroups[0].title;
            }
            if (this.formatGroups.length) {
                this.activeFormatCategory = this.formatGroups[0].name;
            }
        },
        async loadHistory() {
            if (!this.currentUser) {
                this.conversionHistory = [];
                return;
            }

            try {
                const response = await fetch("/api/history", {
                    credentials: "same-origin",
                });
                if (!response.ok) {
                    return;
                }

                const data = await response.json();
                this.conversionHistory = data.files || [];
            } catch (error) {
                console.error(error);
            }
        },
        normalizeSupportedTargetMap(map) {
            const archiveMap = ARCHIVE_FORMATS.reduce((formats, source) => ({
                ...formats,
                [source]: [...new Set([...(map[source] || []), ...ARCHIVE_FORMATS.filter((target) => target !== source)])],
            }), {});

            return {
                ...map,
                ...archiveMap,
                PPTX: [...new Set([...(map.PPTX || []), ...PPTX_TARGETS])],
            };
        },
        setBillingPeriod(period) {
            this.billingPeriod = period;
        },
        setActiveToolGroup(title) {
            this.activeToolGroup = title;
        },
        isSmallViewport() {
            return window.innerWidth <= 991;
        },
        toggleToolsMenu() {
            if (this.toolGroups.length && !this.activeToolGroup) {
                this.activeToolGroup = this.toolGroups[0].title;
            }

            this.toolsMenuOpen = !this.toolsMenuOpen;
        },
        toolPreset(item) {
            const presets = {
                "Archive Converter": { from: "ZIP", to: "7Z" },
                "Audio Converter": { from: "MP3", to: "WAV" },
                "CAD Converter": { from: "SVG", to: "PNG" },
                "Document Converter": { from: "DOCX", to: "PDF" },
                "Ebook Converter": { from: "PDF", to: "EPUB" },
                "Font Converter": { from: "TTF", to: "WOFF" },
                "Image Converter": { from: "JPG", to: "PNG" },
                "Presentation Converter": { from: "PPTX", to: "PDF" },
                "Spreadsheet Converter": { from: "XLSX", to: "CSV" },
                "Vector Converter": { from: "SVG", to: "PNG" },
                "Video Converter": { from: "MP4", to: "MOV" },
                "Compress PDF": { from: "PDF", to: "PDF" },
                "Compress PNG": { from: "PNG", to: "PNG" },
                "Compress JPG": { from: "JPG", to: "JPG" },
                "PDF OCR": { from: "PDF", to: "TXT" },
                "Merge PDF": { from: "PDF", to: "PDF" },
                "Merge Audio": { from: "MP3", to: "MP3" },
                "Merge Video": { from: "MP4", to: "MP4" },
                "Merge Image": { from: "PNG", to: "PDF" },
                "Merge Document": { from: "DOCX", to: "PDF" },
                "Merge Spreadsheet": { from: "XLSX", to: "XLSX" },
                "Merge Presentation": { from: "PPTX", to: "PPTX" },
                "Save Website as PDF": { from: "HTML", to: "PDF" },
                "Website PNG Screenshot": { from: "HTML", to: "PNG" },
                "Website JPG Screenshot": { from: "HTML", to: "JPG" },
                "Create Archive": { from: "ZIP", to: "" },
                "Extract Archive": { from: "ZIP", to: "" },
            };

            return presets[item] || { from: "", to: "" };
        },
        openToolPreset(item) {
            const preset = this.toolPreset(item);
            const wasMergeMode = this.isMergePdfMode;
            this.activeToolName = item;
            this.toolsMenuOpen = false;
            this.pickerOpenFor = null;
            this.queuePickerItemId = null;

            if (preset.from) {
                this.selectedFrom = preset.from;
                const supportedTarget = preset.to && this.isTargetSupportedForSource(preset.from, preset.to);
                this.selectedTo = supportedTarget ? preset.to : this.getDefaultTargetFormat(preset.from);
                this.activeFormatCategory = this.findCategoryByFormat(preset.from) || this.activeFormatCategory;
            }

            if (item === "Archive Converter") {
                this.activeFormatCategory = "Archive";
            }

            this.mergedFile = null;
            if (wasMergeMode !== this.isMergePdfMode) {
                this.selectedFiles = [];
                this.selectedFileName = "";
                this.selectedFileSize = "";
            }
            this.updateConvertedFiles();

            window.requestAnimationFrame(() => {
                document.getElementById("converter")?.scrollIntoView({ behavior: "smooth", block: "center" });
            });
        },
        triggerFileSelect() {
            if (this.$refs.fileInput) {
                this.$refs.fileInput.click();
            }
        },
        async handleFileChange(event) {
            const files = Array.from(event.target.files || []);
            if (!files.length) {
                return;
            }

            const detected = await Promise.all(files.map(async (file) => {
                const sourceFormat = this.getFormatFromFileName(file.name);
                return {
                    sourceFormat,
                    isImageOnlyPdf: sourceFormat === "PDF" ? await this.isLikelyImageOnlyPdf(file) : false,
                    targetFormat: "",
                    file,
                    name: file.name,
                    size: this.formatFileSize(file.size),
                };
            }));

            const queued = detected.map((item) => {
                const selectedTo = (this.selectedTo || "").toUpperCase();
                const canUseSelectedTo = this.isTargetSupportedForSource(item.sourceFormat, selectedTo)
                    && !(item.isImageOnlyPdf && PDF_TEXT_TARGETS.includes(selectedTo));

                return {
                    id: `${item.file.name}-${item.file.size}-${item.file.lastModified}-${Math.random().toString(16).slice(2)}`,
                    ...item,
                    targetFormat: this.isMergeMode ? this.mergeConfig.formats[0] : (canUseSelectedTo ? this.selectedTo : this.getDefaultTargetFormat(item.sourceFormat, item)),
                    status: "queued",
                    error: this.isMergeMode && !this.mergeConfig.formats.includes((item.sourceFormat || "").toUpperCase())
                        ? `Merge ${this.mergeConfig.label} only supports: ${this.mergeConfig.formats.join(", ")}.`
                        : item.isImageOnlyPdf
                        ? "This PDF looks scanned or image-only. Text export needs OCR."
                        : "",
                    convertedId: "",
                    downloadUrl: "",
                    convertedName: "",
                    inlineData: "",
                    mimeType: "",
                };
            });

            this.selectedFiles = [...this.selectedFiles, ...queued];
            this.mergedFile = null;
            const lastFile = queued[queued.length - 1];
            this.selectedFileName = lastFile.name;
            this.selectedFileSize = lastFile.size;
            event.target.value = "";
        },
        async isLikelyImageOnlyPdf(file) {
            try {
                const sampleSize = Math.min(file.size, 2 * 1024 * 1024);
                const sample = await file.slice(0, sampleSize).text();
                const textChunks = (sample.match(/\((?:\\.|[^\\)]){8,}\)|<([0-9A-Fa-f]{2}\s*){8,}>/g) || []).length;
                const textMarkers = PDF_TEXT_MARKERS.reduce((count, marker) => count + (sample.includes(marker) ? 1 : 0), 0);
                const imageMarkers = PDF_SCAN_MARKERS.reduce((count, marker) => count + (sample.includes(marker) ? 1 : 0), 0);
                const embeddedImages = (sample.match(/\/Subtype\s*\/Image/g) || []).length;

                if (embeddedImages >= 1 && textMarkers === 0 && textChunks < 3) {
                    return true;
                }

                return imageMarkers >= 2 && file.size > 2 * 1024 * 1024 && textChunks < 10;
            } catch (error) {
                return false;
            }
        },
        formatFileSize(bytes) {
            if (!bytes && bytes !== 0) {
                return "";
            }

            const units = ["B", "KB", "MB", "GB", "TB"];
            let size = bytes;
            let unitIndex = 0;

            while (size >= 1024 && unitIndex < units.length - 1) {
                size /= 1024;
                unitIndex += 1;
            }

            const precision = unitIndex === 0 ? 0 : size >= 10 ? 1 : 2;
            return `${size.toFixed(precision)} ${units[unitIndex]}`;
        },
        getFormatFromFileName(fileName) {
            const extension = fileName.split(".").pop();
            return extension ? extension.toUpperCase() : "";
        },
        isTargetSupportedForSource(sourceFormat, targetFormat) {
            const targets = this.supportedTargetMap[(sourceFormat || "").toUpperCase()] || [];
            return targets.includes((targetFormat || "").toUpperCase());
        },
        isTargetSupportedForItem(item) {
            const target = (item.targetFormat || "").toUpperCase();
            if (!target || !this.isTargetSupportedForSource(item.sourceFormat, target)) {
                return false;
            }

            return !(item.isImageOnlyPdf && PDF_TEXT_TARGETS.includes(target));
        },
        findCategoryByFormat(format) {
            const match = this.formatGroups.find((group) => group.formats.includes(format));
            return match ? match.name : null;
        },
        getInitialPickerCategory(kind, groups) {
            if (!groups.length) {
                return null;
            }

            if (kind === "from") {
                if (!this.hasOpenedFromPicker) {
                    return groups.some((group) => group.name === "Archive") ? "Archive" : groups[0].name;
                }
                return this.findCategoryByFormat(this.selectedFrom) || groups[0].name;
            }

            const selectedFormat = this.currentTargetFormat;
            const preferredCategory = this.findCategoryByFormat(selectedFormat);
            if (preferredCategory && groups.some((group) => group.name === preferredCategory)) {
                return preferredCategory;
            }

            if (this.activeFormatCategory && groups.some((group) => group.name === this.activeFormatCategory)) {
                return this.activeFormatCategory;
            }

            return groups[0].name;
        },
        scrollActivePickerCategoryIntoView() {
            this.$nextTick(() => {
                const visiblePanel = Array.from(this.$el.querySelectorAll(".format-picker-panel"))
                    .find((panel) => window.getComputedStyle(panel).display !== "none");

                const activeCategory = visiblePanel?.querySelector(".format-category.active");
                activeCategory?.scrollIntoView({ block: "nearest", inline: "nearest" });
            });
        },
        togglePicker(kind, itemId = null) {
            const nextState = this.pickerOpenFor === kind ? null : kind;
            this.pickerOpenFor = nextState;
            this.queuePickerItemId = nextState === "queue-to" ? itemId : null;
            this.pickerSearch = "";

            if (!nextState) {
                return;
            }

            const groups = this.filteredFormatGroups;
            const initialCategory = this.getInitialPickerCategory(kind, groups);
            if (initialCategory) {
                this.activeFormatCategory = initialCategory;
                this.scrollActivePickerCategoryIntoView();
            }

            if (kind === "from") {
                this.hasOpenedFromPicker = true;
            }
        },
        closePicker(event) {
            if (!this.$el.contains(event.target)) {
                this.pickerOpenFor = null;
                this.queuePickerItemId = null;
                this.toolsMenuOpen = false;
                this.profileMenuOpen = false;
            }
        },
        toggleProfileMenu() {
            this.profileMenuOpen = !this.profileMenuOpen;
            this.pickerOpenFor = null;
            this.queuePickerItemId = null;
        },
        setActiveFormatCategory(name) {
            this.activeFormatCategory = name;
        },
        getDefaultTargetFormat(sourceFormat, item = null) {
            const upperSource = (sourceFormat || "").toUpperCase();
            let directTargets = this.supportedTargetMap[upperSource] || [];
            if (upperSource === "PDF" && item?.isImageOnlyPdf) {
                directTargets = directTargets.filter((target) => !PDF_TEXT_TARGETS.includes(target.toUpperCase()));
            }
            return directTargets.find((format) => format.toUpperCase() !== upperSource) || "";
        },
        handleScroll() {
            this.showBackToTop = window.scrollY > 500;
        },
        handleResize() {
            if (!this.isSmallViewport()) {
                this.toolsMenuOpen = false;
            }
        },
        scrollToTop() {
            window.scrollTo({ top: 0, behavior: "smooth" });
        },
        removeQueuedFile(fileId) {
            this.selectedFiles = this.selectedFiles.filter((item) => item.id !== fileId);
            this.mergedFile = null;
            if (!this.selectedFiles.length) {
                this.selectedFileName = "";
                this.selectedFileSize = "";
            }
            this.updateConvertedFiles();
        },
        updateConvertedFiles() {
            if (this.isMergePdfMode) {
                this.convertedFiles = this.mergedFile ? [this.mergedFile] : [];
                return;
            }

            this.convertedFiles = this.selectedFiles.filter((item) => item.status === "converted" && item.convertedId);
        },
        async downloadQueuedFile(item) {
            if (!item || !item.downloadUrl) {
                return;
            }

            if (item.inlineData) {
                try {
                    const binary = atob(item.inlineData);
                    const bytes = new Uint8Array(binary.length);
                    for (let index = 0; index < binary.length; index += 1) {
                        bytes[index] = binary.charCodeAt(index);
                    }

                    const blob = new Blob([bytes], { type: item.mimeType || "application/octet-stream" });
                    if (!blob.size) {
                        throw new Error("Converted file is empty. Please convert it again.");
                    }

                    const url = window.URL.createObjectURL(blob);
                    const link = document.createElement("a");
                    link.href = url;
                    link.download = item.convertedName || "converted-file";
                    document.body.appendChild(link);
                    link.click();
                    link.remove();
                    window.setTimeout(() => window.URL.revokeObjectURL(url), 3000);
                    return;
                } catch (error) {
                    console.error(error);
                }
            }

            window.location.href = item.downloadUrl;
        },
        async downloadAllConvertedFiles() {
            const fileIds = this.convertedFiles
                .map((item) => item.convertedId)
                .filter(Boolean);

            if (fileIds.length < 2) {
                return;
            }

            try {
                const response = await fetch("/api/download-all", {
                    method: "POST",
                    credentials: "same-origin",
                    headers: {
                        "Content-Type": "application/json",
                    },
                    body: JSON.stringify({ file_ids: fileIds }),
                });

                if (!response.ok) {
                    const data = await response.json().catch(() => ({}));
                    throw new Error(data.error || "Download all failed.");
                }

                const buffer = await response.arrayBuffer();
                const blob = new Blob([buffer], { type: "application/zip" });
                const url = window.URL.createObjectURL(blob);
                const link = document.createElement("a");

                link.href = url;
                link.download = "converted-files.zip";
                document.body.appendChild(link);
                link.click();
                link.remove();
                window.setTimeout(() => window.URL.revokeObjectURL(url), 3000);
            } catch (error) {
                console.error(error);
                window.alert(error.message || "Download all failed.");
            }
        },
        async convertQueuedFiles() {
            if (!this.selectedFiles.length || this.isConverting) {
                return;
            }

            if (this.isMergePdfMode) {
                await this.mergePdfFiles();
                return;
            }

            const unsupportedFiles = this.selectedFiles.filter((item) => !this.supportedTargetMap[item.sourceFormat]);
            if (unsupportedFiles.length) {
                window.alert(`Unsupported file type: ${unsupportedFiles.map((item) => item.sourceFormat || item.name).join(", ")}`);
                return;
            }

            const invalidTargets = this.selectedFiles.filter((item) => !this.isTargetSupportedForItem(item));
            if (invalidTargets.length) {
                window.alert("Select a supported target format for each queued file first.");
                return;
            }

            const pendingFiles = this.selectedFiles.filter((item) => item.status !== "converted");
            if (!pendingFiles.length) {
                return;
            }

            this.isConverting = true;
            try {
                for (const item of pendingFiles) {
                    const formData = new FormData();
                    formData.append("files", item.file);
                    formData.append("client_ids", item.id);
                    formData.append("target_format", item.targetFormat);

                    const response = await fetch("/api/convert", {
                        method: "POST",
                        body: formData,
                    });
                    const data = await response.json();
                    if (!response.ok) {
                        this.selectedFiles = this.selectedFiles.map((selectedItem) => (
                            selectedItem.id === item.id
                                ? { ...selectedItem, status: "queued", error: data.error || `Conversion failed for ${item.name}.` }
                                : selectedItem
                        ));
                        throw new Error(data.error || `Conversion failed for ${item.name}.`);
                    }

                    const converted = data.files.find((convertedItem) => convertedItem.client_id === item.id);
                    if (!converted) {
                        throw new Error(`Conversion failed for ${item.name}.`);
                    }

                    this.selectedFiles = this.selectedFiles.map((selectedItem) => (
                        selectedItem.id === item.id
                            ? {
                                ...selectedItem,
                                status: "converted",
                                error: "",
                                convertedId: converted.id,
                                downloadUrl: converted.download_url,
                                convertedName: converted.converted_name,
                                inlineData: converted.inline_data || "",
                                mimeType: converted.mime_type || "",
                            }
                            : selectedItem
                    ));
                }
                this.updateConvertedFiles();
                await this.loadHistory();
            } catch (error) {
                console.error(error);
                this.updateConvertedFiles();
                window.alert(error.message || "Conversion failed.");
            } finally {
                this.isConverting = false;
            }
        },
        async mergePdfFiles() {
            const config = this.mergeConfig;
            if (!config) {
                return;
            }
            if (this.selectedFiles.length < 2) {
                window.alert(`Select at least 2 ${config.label} files to merge.`);
                return;
            }

            const invalidFiles = this.selectedFiles.filter(
                (item) => !config.formats.includes((item.sourceFormat || "").toUpperCase())
            );
            if (invalidFiles.length) {
                window.alert(`Merge ${config.label} only supports: ${config.formats.join(", ")}.`);
                return;
            }

            this.isConverting = true;
            try {
                const formData = new FormData();
                formData.append("merge_type", config.type);
                for (const item of this.selectedFiles) {
                    formData.append("files[]", item.file);
                }

                const response = await fetch("/api/merge", {
                    method: "POST",
                    body: formData,
                });
                const data = await response.json();
                if (!response.ok) {
                    throw new Error(data.error || `${config.label} merge failed.`);
                }

                const merged = data.files?.[0];
                if (!merged) {
                    throw new Error(`${config.label} merge failed.`);
                }

                this.mergedFile = {
                    id: "merged-output",
                    name: `Merged ${config.label}`,
                    size: "",
                    status: "converted",
                    convertedId: merged.id,
                    downloadUrl: merged.download_url,
                    convertedName: merged.converted_name,
                    inlineData: merged.inline_data || "",
                    mimeType: merged.mime_type || "",
                };
                this.selectedFiles = this.selectedFiles.map((item) => ({ ...item, status: "converted", error: "" }));
                this.updateConvertedFiles();
                await this.loadHistory();
            } catch (error) {
                console.error(error);
                window.alert(error.message || `${config.label} merge failed.`);
            } finally {
                this.isConverting = false;
            }
        },
        selectFormat(kind, format) {
            if (kind === "from") {
                this.activeToolName = "";
                this.mergedFile = null;
                this.selectedFrom = format;
                const nextTarget = this.getDefaultTargetFormat(format);
                this.selectedTo = nextTarget && nextTarget.toUpperCase() !== format.toUpperCase()
                    ? nextTarget
                    : "";
            } else if (kind === "queue-to") {
                this.selectedFiles = this.selectedFiles.map((item) => (
                    item.id === this.queuePickerItemId
                        ? {
                            ...item,
                            targetFormat: format,
                            status: item.status === "converted" ? "queued" : item.status,
                            error: "",
                            convertedId: "",
                            downloadUrl: "",
                            convertedName: "",
                            inlineData: "",
                            mimeType: "",
                        }
                        : item
                ));
                this.updateConvertedFiles();
            } else {
                this.selectedTo = format;
            }
            this.pickerOpenFor = null;
            this.queuePickerItemId = null;
            this.pickerSearch = "";
        },
    },
    async mounted() {
        await this.loadHighlights();
        await this.loadHistory();
        document.addEventListener("click", this.closePicker);
        window.addEventListener("scroll", this.handleScroll, { passive: true });
        window.addEventListener("resize", this.handleResize, { passive: true });
        this.handleScroll();
    },
    beforeUnmount() {
        document.removeEventListener("click", this.closePicker);
        window.removeEventListener("scroll", this.handleScroll);
        window.removeEventListener("resize", this.handleResize);
    },
}).mount("#app");
