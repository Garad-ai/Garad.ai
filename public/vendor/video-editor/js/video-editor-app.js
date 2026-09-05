/**
 * Video Editor App — Main Alpine.js Component
 *
 * Provides: project management, timeline, media library, canvas preview,
 * drag-and-drop, clip operations, undo/redo, AI generation, and export.
 */

/**
 * Google TTS voices data — maps language codes to available voices.
 * Same data as MagicAI's voiceover page (voicesData).
 */
window._veVoicesData = {
    "af-ZA": [{value:"af-ZA-Standard-A", label:"Ayanda (Female)"}],
    "ar-XA": [{value:"ar-XA-Standard-A", label:"Fatima (Female)"},{value:"ar-XA-Standard-B", label:"Ahmed (Male)"},{value:"ar-XA-Standard-C", label:"Mohammed (Male)"},{value:"ar-XA-Standard-D", label:"Aisha (Female)"}],
    "bg-BG": [{value:"bg-BG-Standard-A", label:"Elena (Female)"}],
    "ca-ES": [{value:"ca-ES-Standard-A", label:"Laia (Female)"}],
    "yue-HK": [{value:"yue-HK-Standard-A", label:"Wing (Female)"},{value:"yue-HK-Standard-B", label:"Ho (Male)"}],
    "zh-CN": [{value:"cmn-CN-Standard-A", label:"Mei (Female)"},{value:"cmn-CN-Standard-B", label:"Wei (Male)"},{value:"cmn-CN-Standard-C", label:"Li (Male)"},{value:"cmn-CN-Standard-D", label:"Yan (Female)"}],
    "cs-CZ": [{value:"cs-CZ-Standard-A", label:"Tereza (Female)"}],
    "da-DK": [{value:"da-DK-Standard-A", label:"Emma (Female)"},{value:"da-DK-Standard-C", label:"Noah (Male)"}],
    "nl-BE": [{value:"nl-BE-Standard-A", label:"Emma (Female)"},{value:"nl-BE-Standard-B", label:"Thomas (Male)"}],
    "nl-NL": [{value:"nl-NL-Standard-A", label:"Emma (Female)"},{value:"nl-NL-Standard-B", label:"Daan (Male)"},{value:"nl-NL-Standard-D", label:"Lotte (Female)"}],
    "en-AU": [{value:"en-AU-Standard-A", label:"Charlotte (Female)"},{value:"en-AU-Standard-B", label:"Oliver (Male)"},{value:"en-AU-Standard-C", label:"Ava (Female)"},{value:"en-AU-Standard-D", label:"Jack (Male)"}],
    "en-IN": [{value:"en-IN-Standard-A", label:"Aditi (Female)"},{value:"en-IN-Standard-B", label:"Arjun (Male)"},{value:"en-IN-Standard-D", label:"Ananya (Female)"}],
    "en-GB": [{value:"en-GB-Standard-A", label:"Emma (Female)"},{value:"en-GB-Standard-B", label:"Oliver (Male)"},{value:"en-GB-Standard-C", label:"Amelia (Female)"},{value:"en-GB-Standard-D", label:"Harry (Male)"}],
    "en-US": [{value:"en-US-Standard-A", label:"Emily (Male)"},{value:"en-US-Standard-B", label:"James (Male)"},{value:"en-US-Standard-C", label:"Sophia (Female)"},{value:"en-US-Standard-D", label:"Michael (Male)"},{value:"en-US-Standard-E", label:"Olivia (Female)"},{value:"en-US-Standard-H", label:"Emma (Female)"},{value:"en-US-Standard-J", label:"David (Male)"}],
    "fil-PH": [{value:"fil-PH-Standard-A", label:"Maria (Female)"},{value:"fil-PH-Standard-B", label:"Juan (Male)"}],
    "fi-FI": [{value:"fi-FI-Standard-A", label:"Aino (Female)"}],
    "fr-CA": [{value:"fr-CA-Standard-A", label:"Emma (Female)"},{value:"fr-CA-Standard-B", label:"Thomas (Male)"},{value:"fr-CA-Standard-C", label:"Olivia (Female)"},{value:"fr-CA-Standard-D", label:"Noah (Male)"}],
    "fr-FR": [{value:"fr-FR-Standard-A", label:"Emma (Female)"},{value:"fr-FR-Standard-B", label:"Louis (Male)"},{value:"fr-FR-Standard-C", label:"Chloe (Female)"},{value:"fr-FR-Standard-D", label:"Gabriel (Male)"}],
    "de-DE": [{value:"de-DE-Standard-A", label:"Anna (Female)"},{value:"de-DE-Standard-B", label:"Max (Male)"},{value:"de-DE-Standard-C", label:"Lea (Female)"},{value:"de-DE-Standard-D", label:"Felix (Male)"}],
    "el-GR": [{value:"el-GR-Standard-A", label:"Maria (Female)"}],
    "he-IL": [{value:"he-IL-Standard-A", label:"Sarah (Female)"},{value:"he-IL-Standard-B", label:"David (Male)"}],
    "hi-IN": [{value:"hi-IN-Standard-A", label:"Priya (Female)"},{value:"hi-IN-Standard-B", label:"Ravi (Male)"},{value:"hi-IN-Standard-C", label:"Arjun (Male)"},{value:"hi-IN-Standard-D", label:"Anita (Female)"}],
    "hu-HU": [{value:"hu-HU-Standard-A", label:"Anna (Female)"}],
    "is-IS": [{value:"is-IS-Standard-A", label:"Gudrun (Female)"}],
    "id-ID": [{value:"id-ID-Standard-A", label:"Sari (Female)"},{value:"id-ID-Standard-B", label:"Budi (Male)"}],
    "it-IT": [{value:"it-IT-Standard-A", label:"Sofia (Female)"},{value:"it-IT-Standard-B", label:"Alessandro (Female)"},{value:"it-IT-Standard-C", label:"Marco (Male)"},{value:"it-IT-Standard-D", label:"Giulia (Male)"}],
    "ja-JP": [{value:"ja-JP-Standard-A", label:"Sakura (Female)"},{value:"ja-JP-Standard-B", label:"Yuki (Female)"},{value:"ja-JP-Standard-C", label:"Haruto (Male)"},{value:"ja-JP-Standard-D", label:"Ren (Male)"}],
    "kn-IN": [{value:"kn-IN-Standard-A", label:"Kavya (Female)"},{value:"kn-IN-Standard-B", label:"Arun (Male)"}],
    "ko-KR": [{value:"ko-KR-Standard-A", label:"Seo-yeon (Female)"},{value:"ko-KR-Standard-B", label:"Ji-hoon (Female)"},{value:"ko-KR-Standard-C", label:"Min-jun (Male)"},{value:"ko-KR-Standard-D", label:"Ye-jin (Male)"}],
    "lv-LV": [{value:"lv-LV-Standard-A", label:"Ieva (Male)"}],
    "ms-MY": [{value:"ms-MY-Standard-A", label:"Nurul (Female)"},{value:"ms-MY-Standard-B", label:"Ahmad (Male)"}],
    "ml-IN": [{value:"ml-IN-Standard-A", label:"Lakshmi (Female)"},{value:"ml-IN-Standard-B", label:"Krishna (Male)"}],
    "cmn-CN": [{value:"cmn-CN-Standard-A", label:"Mei (Female)"},{value:"cmn-CN-Standard-B", label:"Wei (Male)"}],
    "nb-NO": [{value:"nb-NO-Standard-A", label:"Emma (Female)"},{value:"nb-NO-Standard-B", label:"Magnus (Male)"}],
    "pl-PL": [{value:"pl-PL-Standard-A", label:"Zofia (Female)"},{value:"pl-PL-Standard-B", label:"Jan (Male)"}],
    "pt-BR": [{value:"pt-BR-Standard-A", label:"Ana (Female)"},{value:"pt-BR-Standard-B", label:"Pedro (Male)"}],
    "pt-PT": [{value:"pt-PT-Standard-A", label:"Maria (Female)"},{value:"pt-PT-Standard-B", label:"Joao (Male)"}],
    "ro-RO": [{value:"ro-RO-Standard-A", label:"Maria (Female)"}],
    "ru-RU": [{value:"ru-RU-Standard-A", label:"Anastasia (Female)"},{value:"ru-RU-Standard-B", label:"Dmitry (Male)"},{value:"ru-RU-Standard-C", label:"Olga (Female)"},{value:"ru-RU-Standard-D", label:"Sergei (Male)"}],
    "sr-RS": [{value:"sr-RS-Standard-A", label:"Jelena (Female)"}],
    "sk-SK": [{value:"sk-SK-Standard-A", label:"Laura (Female)"}],
    "es-ES": [{value:"es-ES-Standard-A", label:"Lucia (Female)"},{value:"es-ES-Standard-B", label:"Pablo (Male)"}],
    "sv-SE": [{value:"sv-SE-Standard-A", label:"Astrid (Female)"},{value:"sv-SE-Standard-B", label:"Erik (Male)"}],
    "ta-IN": [{value:"ta-IN-Standard-A", label:"Priya (Female)"},{value:"ta-IN-Standard-B", label:"Karthik (Male)"}],
    "th-TH": [{value:"th-TH-Standard-A", label:"Siri (Female)"}],
    "tr-TR": [{value:"tr-TR-Standard-A", label:"Elif (Female)"},{value:"tr-TR-Standard-B", label:"Emre (Male)"}],
    "uk-UA": [{value:"uk-UA-Standard-A", label:"Oksana (Female)"}],
    "vi-VN": [{value:"vi-VN-Standard-A", label:"Linh (Female)"},{value:"vi-VN-Standard-B", label:"Minh (Male)"}]
};

function videoEditorApp(config) {
    // Fallback: if the Blade-rendered projectId is missing for any reason
    // (route binding failure, stale view cache, etc.) but the current URL is
    // `/.../editor/{id}`, recover the id from the path. Otherwise saveProject
    // would hit the POST branch and silently create a duplicate project.
    const projectIdFromUrl = (() => {
        const m = window.location.pathname.match(/\/editor\/(\d+)(?:\/|$)/);
        return m ? parseInt(m[1], 10) : null;
    })();

    return {
        // ──────────────────────────── Config / Routes ─────────────────────────
        projectId: config.projectId || projectIdFromUrl,
        csrfToken: config.csrfToken,
        routes: config.routes,
        ttsProvider: config.ttsProvider || 'openai',
        ttsGoogleEnabled: !!config.ttsGoogleEnabled,
        ttsElevenlabsEnabled: !!config.ttsElevenlabsEnabled,
        aiVideoProEnabled: !!config.aiVideoProEnabled,
        aiMusicProEnabled: !!config.aiMusicProEnabled,
        musicProvider: config.musicProvider || 'elevenlabs',
        ttsEnabled: !!config.ttsEnabled,

        // ──────────────────────────── Project State ───────────────────────────
        projectName: config.projectName || 'Untitled Project',
        projectWidth: config.projectWidth || 1920,
        projectHeight: config.projectHeight || 1080,
        projectFps: config.projectFps || 30,
        projectAspectRatio: config.projectAspectRatio || '16:9',
        editingName: false,
        saving: false,
        lastSavedText: '',
        hasUnsavedChanges: false,

        // ──────────────────────────── Timeline State ──────────────────────────
        tracks: [],
        currentTime: 0,
        totalDuration: 0,
        playing: false,
        playbackRAF: null,
        _mediaMap: {},           // mediaId → media object (fast lookup)
        _activeVideos: [],       // currently playing <video> elements
        timelineZoom: 1,
        timelineHeight: 220,
        trackItemHeight: 48,
        snapEnabled: true,
        timelineContentWidth: 2000,
        timelineScrollY: 0,      // vertical scroll position of tracks area — mirrored onto labels via translate
        _cachedRulerTicks: null,
        _cachedRulerKey: '',
        _recalcDebounce: null,
        _waveformCache: {},      // mediaId → peaks array (Float32 normalised 0-1) or null while loading
        _audioCtx: null,

        // ──────────────────────────── Selection ──────────────────────────────
        selectedClipId: null,
        selectedTrackId: null,
        selectedClipType: null,
        selectedClipProps: {},
        selectedTextBox: null,   // {x, y, w, h} in canvas (project) coords for the selected text overlay

        // ──────────────────────────── Panels ─────────────────────────────────
        leftPanelOpen: true,
        leftPanelTab: 'library',
        rightPanelOpen: true,
        showImportPanel: false,

        // ──────────────────────────── Media Library ──────────────────────────
        mediaItems: [],
        mediaFilter: 'all',
        mediaPage: 1,
        mediaHasMore: false,
        uploading: false,
        uploadProgress: 0,

        // ──────────────────────────── Canvas / Preview ───────────────────────
        canvasZoom: 0.4,
        canvasCtx: null,
        videoElements: {},
        masterVolume: 1,
        masterMuted: false,

        // ──────────────────────────── AI Generation ──────────────────────────
        // Nested config mirrors AiVideoPro\ModelConfigurationService:
        //   { groupKey: { label, description, subModels: { subKey: { features: { featureSlug: { label, description, inputs:[] } } } } } }
        aiConfig: {},
        aiSelectedAction: '',
        aiSelectedSubModel: '',
        aiSelectedFeature: '',
        aiFormValues: {},
        aiFiles: {},
        aiModelDropdownOpen: false,
        aiFeatureDropdownOpen: false,
        aiOpenPill: null,
        aiCredits: null,
        aiGenerating: false,
        aiGenerations: [],
        aiPollInterval: null,

        // ──────────────────────────── AI Voice (TTS) ─────────────────────────
        voiceLang: 'en-US',
        voiceId: '',
        voiceModel: 'tts-1',
        voicePace: 'medium',
        voiceText: '',
        voiceGenerating: false,
        voiceOptions: [],
        voiceDataLoaded: false,
        _voiceElevenlabsVoices: [],

        // ──────────────────────────── AI Music ──────────────────────────────
        // musicProvider is initialised above from config; declared here for grouping only
        musicPrompt: '',
        musicStyle: '',
        musicDuration: 30,
        musicLyrics: '',
        musicOutputFormat: 'mp3',
        musicImages: [],
        musicImagePreviews: [],
        musicGenerating: false,
        musicAdvancedOpen: false,
        musicExamplePrompts: [
            'Dreamy lo-fi hip hop beat for studying, with soft piano and vinyl crackle.',
            'Epic orchestral soundtrack with choirs and cinematic percussion.',
            'Futuristic synthwave track inspired by neon cityscapes.',
            'Gentle acoustic guitar melody with birds chirping in the background.',
            'Dark techno track with deep bass and hypnotic rhythms.',
            'Upbeat funk groove with slap bass, brass, and electric guitar.',
            'Meditative ambient soundscape with evolving pads and nature sounds.',
            'Fast-paced video game chiptune with retro 8-bit sounds.',
        ],

        // ──────────────────────────── Export ──────────────────────────────────
        exportModalOpen: false,
        exporting: false,
        exportProgress: 0,
        exportComplete: false,
        exportError: null,
        exportJobId: null,
        exportPollInterval: null,

        // ──────────────────────────── Undo / Redo ────────────────────────────
        undoStack: [],
        redoStack: [],
        maxUndoSteps: 50,
        canUndo: false,
        canRedo: false,

        // ──────────────────────────── Drag State ─────────────────────────────
        draggingMedia: null,
        draggingClip: null,
        trimming: null,
        resizingTimeline: false,
        _undoDebounceTimer: null,

        // =====================================================================
        //  INIT
        // =====================================================================
        init() {
            // Load project data if editing existing project
            if (config.projectData) {
                this.loadProjectData(config.projectData);
            } else {
                this.initDefaultTracks();
            }

            // Pre-populate media map with project media (ensures timeline clips
            // can render even before the paginated library loads)
            if (config.projectMedia && Array.isArray(config.projectMedia)) {
                for (const m of config.projectMedia) {
                    this._mediaMap[m.id] = m;
                }
            }

            this.recalcDuration();
            this.recalcTimelineWidth();
            this.loadMedia();
            if (this.aiVideoProEnabled) {
                this.loadAiModels();
            }
            // Pre-populate voice list with Google voices for the default language
            // so the list isn't empty before loadVoiceModels() resolves. Skipped
            // entirely when no TTS provider is enabled (Voice tab is hidden too).
            if (this.ttsEnabled) {
                this.$nextTick(() => this.onVoiceLanguageChange());
            }

            // Init canvas context
            this.$nextTick(() => {
                const canvas = this.$refs.previewCanvas;
                if (canvas) {
                    this.canvasCtx = canvas.getContext('2d');
                    this.renderFrame();
                }
                this.renderTimeRuler();
                this.zoomCanvasFit();
            });

            // Watch timeline zoom to re-render ruler
            this.$watch('timelineZoom', () => {
                this.recalcTimelineWidth();
            });

            // Listen for Content Manager media selection
            this._initContentManagerListener();
        },

        /**
         * Listen for MagicAI Content Manager's events.
         * Uses both the mediaManagerSelection DOM event (on the file input)
         * and the Livewire mediaSelected event as fallback.
         */
        _initContentManagerListener() {
            // 1. Listen on the hidden file input for the mediaManagerSelection custom event.
            //    The Content Manager dispatches this synchronously on the target input
            //    BEFORE the async blob fetch, so it works even with CORS issues.
            this.$nextTick(() => {
                const input = this.$refs.mediaUploadInput;
                if (input) {
                    input.addEventListener('mediaManagerSelection', (e) => {
                        if (!this._waitingForContentManager) return;
                        this._waitingForContentManager = false;

                        const items = e.detail?.selectedItems;
                        if (items && items.length) {
                            this._importContentManagerItems(items);
                        }
                    });
                }
            });

            // 2. Livewire events
            if (typeof Livewire !== 'undefined') {
                // mediaSelected — fires when user selects existing files and clicks Insert
                Livewire.on('mediaSelected', (eventData) => {
                    const data = Array.isArray(eventData) ? eventData[0] : eventData;
                    if (!data || !data.items || !this._waitingForContentManager) return;

                    this._waitingForContentManager = false;

                    let items = data.items;
                    if (typeof items === 'object' && !Array.isArray(items)) {
                        items = Object.values(items);
                    }

                    if (!items.length) return;

                    this._importContentManagerItems(items);
                });

            }
        },

        /**
         * Open MagicAI's Content Manager modal for file selection.
         */
        openContentManager() {
            const input = this.$refs.mediaUploadInput;
            // Content Manager defines window.openMediaManager as a no-op stub
            // when disabled, so checking for the function alone isn't enough.
            if (window.contentManagerEnabled && window.openMediaManager) {
                this._waitingForContentManager = true;
                // Pass the hidden file input as target so Content Manager
                // dispatches mediaManagerSelection event on it
                window.openMediaManager(input, ['all'], true);
            } else {
                // Fallback: use native file picker if Content Manager not available
                if (input) {
                    input.click();
                }
            }
        },

        /**
         * Import items selected from Content Manager into Video Editor's media library.
         */
        async _importContentManagerItems(items) {
            // Prevent the Content Manager's blob-fetch change event from
            // triggering handleMediaUpload (which would create duplicates)
            this._contentManagerHandled = true;

            this.uploading = true;
            this.uploadProgress = 50;

            try {
                const res = await fetch(this.routes.importFromContentManager, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        items: items,
                        project_id: this.projectId,
                    }),
                });

                const data = await res.json();

                if (res.ok && data.media) {
                    const mediaList = Array.isArray(data.media) ? data.media : [data.media];
                    for (const media of mediaList) {
                        this.mediaItems.unshift(media);
                        this._mediaMap[media.id] = media;
                    }
                    if (window.toastr) toastr.success(data.message || 'Media imported');
                } else {
                    const msg = data.message || 'Import failed';
                    if (window.toastr) toastr.error(msg);
                    console.error('Content Manager import failed:', data);
                }
            } catch (e) {
                console.error('Content Manager import error:', e);
                if (window.toastr) toastr.error('Failed to import media');
            }

            this.uploading = false;
            this.uploadProgress = 0;
        },

        // =====================================================================
        //  PROJECT DATA
        // =====================================================================
        loadProjectData(data) {
            if (data && data.tracks) {
                this.tracks = data.tracks;
                this._backfillLanes();
            } else {
                this.initDefaultTracks();
            }
        },

        /**
         * Compute a `lane` for any clip missing one. Older saves (before the
         * server-side validator whitelisted `lane`) stripped this field, which
         * collapsed every overlapping clip onto lane 0 — they then rendered
         * stacked on top of each other in the timeline. Recompute deterministically:
         * walk each track's clips by startTime and assign the lowest lane index
         * that doesn't already collide on that lane.
         */
        _backfillLanes() {
            for (const track of this.tracks) {
                if (!track.clips || !track.clips.length) continue;

                const needsBackfill = track.clips.some(c => typeof c.lane !== 'number');
                if (!needsBackfill) continue;

                const sorted = [...track.clips].sort((a, b) => (a.startTime || 0) - (b.startTime || 0));
                const placed = [];
                for (const clip of sorted) {
                    const start = clip.startTime || 0;
                    const end = clip.endTime || start;
                    let lane = 0;
                    while (placed.some(p => p.lane === lane && p.start < end && p.end > start)) {
                        lane++;
                    }
                    clip.lane = lane;
                    placed.push({ lane, start, end });
                }
            }
        },

        initDefaultTracks() {
            this.tracks = [
                { id: this.uid(), type: 'video', label: 'Video', locked: false, muted: false, clips: [] },
                { id: this.uid(), type: 'audio', label: 'Audio', locked: false, muted: false, clips: [] },
                { id: this.uid(), type: 'text',  label: 'Text',  locked: false, muted: false, clips: [] },
            ];
        },

        // =====================================================================
        //  SAVE PROJECT
        // =====================================================================
        async saveProject() {
            if (this.saving) return;
            this.saving = true;

            const payload = {
                name: this.projectName,
                timeline_data: { tracks: this.tracks, version: 1 },
                duration_ms: this.totalDuration,
                aspect_ratio: this.projectAspectRatio,
            };

            // Capture canvas snapshot for project thumbnail (non-blocking)
            const thumb = this.captureProjectThumbnail();
            if (thumb && thumb.length < 400000) payload.thumbnail = thumb;

            try {
                let url, method;
                if (this.projectId) {
                    // Reconstruct from projectStore if the rendered update route
                    // came through empty (happens when $project failed to bind
                    // server-side but the URL still carries the id).
                    url = this.routes.projectUpdate || (this.routes.projectStore + '/' + this.projectId);
                    method = 'PUT';
                } else {
                    url = this.routes.projectStore;
                    method = 'POST';
                }

                const res = await this.apiRequest(url, method, payload);
                const data = await res.json();

                if (data.success !== false && data.project) {
                    if (!this.projectId) {
                        this.projectId = data.project.id;
                        // Update URL without reload
                        const newUrl = this.routes.index + '/' + data.project.id + '/edit';
                        window.history.replaceState({}, '', newUrl);
                        // Update routes for future saves
                        this.routes.projectUpdate = this.routes.projectStore.replace(/\/store$/, '') + '/' + data.project.id;
                        this.routes.projectShow = this.routes.projectUpdate;
                    }
                    this.hasUnsavedChanges = false;
                    this.lastSavedText = 'Saved ' + new Date().toLocaleTimeString();
                    if (window.toastr) toastr.success('Project saved');
                } else {
                    if (window.toastr) toastr.error(data.message || 'Failed to save');
                }
            } catch (e) {
                console.error('Save failed:', e);
                if (window.toastr) toastr.error(e.message || 'Failed to save project');
            } finally {
                this.saving = false;
            }
        },

        // =====================================================================
        //  MEDIA LIBRARY
        // =====================================================================
        async loadMedia(reset = true) {
            if (reset) this.mediaPage = 1;

            const params = new URLSearchParams({ page: this.mediaPage });
            if (this.mediaFilter !== 'all') params.set('type', this.mediaFilter);

            try {
                const res = await this.apiRequest(this.routes.mediaIndex + '?' + params, 'GET');
                const data = await res.json();

                // Backend returns {status, media: {data: [...], current_page, last_page}}
                const paginated = data.media || data;
                const items = paginated.data || [];

                if (reset) {
                    this.mediaItems = items;
                } else {
                    this.mediaItems = this.mediaItems.concat(items);
                }
                this.mediaHasMore = (paginated.current_page || 1) < (paginated.last_page || 1);

                // Rebuild fast lookup map
                this._rebuildMediaMap();

                // Re-render canvas now that media is available
                this.renderFrame();

                // Generate client-side thumbnails for videos missing them
                this.$nextTick(() => this.generateMissingThumbnails());
            } catch (e) {
                console.error('Failed to load media:', e);
            }
        },

        loadMoreMedia() {
            this.mediaPage++;
            this.loadMedia(false);
        },

        async handleMediaUpload(event) {
            // Skip if Content Manager is handling the import (prevents duplicates)
            if (this._contentManagerHandled) {
                this._contentManagerHandled = false;
                event.target.value = '';
                return;
            }

            const files = event.target.files;
            if (!files.length) return;

            this.uploading = true;
            this.uploadProgress = 0;

            const totalFiles = files.length;

            for (let i = 0; i < totalFiles; i++) {
                const file = files[i];
                const formData = new FormData();
                formData.append('file', file);
                if (this.projectId) formData.append('project_id', this.projectId);

                try {
                    // Use XMLHttpRequest for upload progress tracking
                    const data = await new Promise((resolve, reject) => {
                        const xhr = new XMLHttpRequest();

                        xhr.upload.addEventListener('progress', (e) => {
                            if (e.lengthComputable) {
                                const fileProgress = Math.round((e.loaded / e.total) * 100);
                                // Overall progress: completed files + current file progress
                                this.uploadProgress = Math.round(((i + fileProgress / 100) / totalFiles) * 100);
                            }
                        });

                        xhr.addEventListener('load', () => {
                            try {
                                const json = JSON.parse(xhr.responseText);
                                if (xhr.status >= 200 && xhr.status < 300) {
                                    resolve(json);
                                } else {
                                    // Server returned an error (422 validation, etc.)
                                    const msg = json.message || (json.errors ? Object.values(json.errors).flat().join(', ') : 'Upload failed');
                                    reject(new Error(msg));
                                }
                            } catch (e) {
                                reject(new Error('Invalid server response'));
                            }
                        });

                        xhr.addEventListener('error', () => reject(new Error('Network error')));
                        xhr.addEventListener('abort', () => reject(new Error('Upload cancelled')));

                        xhr.open('POST', this.routes.mediaUpload);
                        xhr.setRequestHeader('X-CSRF-TOKEN', this.csrfToken);
                        xhr.setRequestHeader('Accept', 'application/json');
                        xhr.send(formData);
                    });

                    if (data.media) {
                        this.mediaItems.unshift(data.media);
                        this._mediaMap[data.media.id] = data.media;
                        if (window.toastr) toastr.success(file.name + ' uploaded');
                        // Generate client-side thumbnail for uploaded videos
                        if (data.media.type === 'video' && !data.media.thumbnail_url) {
                            this.$nextTick(() => this.generateVideoThumbnail(data.media));
                        }
                    }
                } catch (e) {
                    console.error('Upload failed:', e);
                    if (window.toastr) toastr.error(e.message || ('Upload failed for ' + file.name));
                }

                this.uploadProgress = Math.round(((i + 1) / totalFiles) * 100);
            }

            this.uploading = false;
            this.uploadProgress = 0;
            event.target.value = '';
        },

        async deleteMedia(mediaId) {
            if (!confirm('Delete this media file?')) return;

            try {
                const url = this.routes.mediaIndex + '/' + mediaId;
                await this.apiRequest(url, 'DELETE');
                this.mediaItems = this.mediaItems.filter(m => m.id !== mediaId);

                // Clean up cached video/image element to free memory
                const key = 'media_' + mediaId;
                const el = this.videoElements[key];
                if (el) {
                    if (el.tagName === 'VIDEO') {
                        el.pause();
                        el.src = '';
                        el.load();
                    }
                    delete this.videoElements[key];
                }
                // Remove from media map
                delete this._mediaMap[mediaId];
                // Remove from thumbnail cache
                delete this._thumbCache[mediaId];
            } catch (e) {
                console.error('Delete failed:', e);
            }
        },

        async importFromMagicAi(type) {
            try {
                const url = this.routes.mediaIndex.replace(/\?.*$/, '').replace(/\/$/, '') + '/import-from-library';
                const payload = {
                    source_type: type === 'ai_video' ? 'ai_video' : 'ai_image',
                };
                if (this.projectId) payload.project_id = this.projectId;

                if (window.toastr) toastr.info('Importing...');

                const res = await this.apiRequest(url, 'POST', payload);
                const data = await res.json();

                if (data.imported && data.imported > 0) {
                    if (window.toastr) toastr.success(data.message || ('Imported ' + data.imported + ' items'));
                    this.loadMedia();
                } else {
                    if (window.toastr) toastr.info(data.message || 'No new items to import');
                }
            } catch (e) {
                console.error('Import failed:', e);
                if (window.toastr) toastr.error('Import failed');
            }
        },

        // =====================================================================
        //  DRAG & DROP — Library to Timeline
        // =====================================================================
        handleMediaDragStart(event, media) {
            this.draggingMedia = media;
            event.dataTransfer.effectAllowed = 'copy';
            event.dataTransfer.setData('text/plain', JSON.stringify({ type: 'media', id: media.id }));
        },

        handleMediaDragEnd(event) {
            this.draggingMedia = null;
        },

        handleTimelineDragOver(event) {
            if (this.draggingMedia) {
                event.dataTransfer.dropEffect = 'copy';
            }
        },

        handleTimelineDrop(event) {
            if (!this.draggingMedia) return;

            const media = this.draggingMedia;
            const rect = this.$refs.tracksContainer.getBoundingClientRect();
            const y = event.clientY - rect.top;
            const x = event.clientX - rect.left + (this.$refs.timelineScroll?.scrollLeft || 0);
            const startTime = Math.max(0, this.pixelsToMs(x));
            const duration = media.duration_ms || 5000;

            // Track rows now have variable heights (one row per lane), so a flat
            // floor(y / trackItemHeight) is no longer sufficient — walk cumulative
            // heights to find which track the drop landed on.
            let cumY = 0;
            let preferred = null;
            for (const t of this.tracks) {
                const h = this.getTrackHeight(t);
                if (y >= cumY && y < cumY + h) {
                    preferred = t;
                    break;
                }
                cumY += h;
            }

            // Hard reject only if the user dropped on a clearly incompatible row
            // (e.g. video onto an audio track). Otherwise let the track-picker
            // route it to a compatible track or create a new one below.
            if (preferred && !this.isMediaCompatible(media.type, preferred.type)) {
                if (window.toastr) toastr.warning('Incompatible media type for this track');
                this.draggingMedia = null;
                return;
            }

            this.pushUndo();
            const track = this.findOrCreateTrackForClip(media.type, startTime, duration, preferred);
            this.addClipToTrack(track, media, startTime);
            this.recalcDuration();
            this.renderFrame();
            this.markChanged();
            this.draggingMedia = null;
        },

        handleCanvasDragOver(event) {
            if (this.draggingMedia) {
                event.dataTransfer.dropEffect = 'copy';
            }
        },

        handleCanvasDrop(event) {
            if (!this.draggingMedia) return;

            const media = this.draggingMedia;
            const startTime = this.currentTime;
            const duration = media.duration_ms || 5000;

            this.pushUndo();
            const track = this.findOrCreateTrackForClip(media.type, startTime, duration);
            this.addClipToTrack(track, media, startTime);
            this.recalcDuration();
            this.renderFrame();
            this.markChanged();
            this.draggingMedia = null;
        },

        /**
         * Add media to the timeline at the current playhead position.
         * Called on double-click of a media item in the library.
         */
        addMediaToTimeline(media) {
            const startTime = this.currentTime;
            const duration = media.duration_ms || 5000;

            this.pushUndo();
            // Each clip lands on the first existing compatible track that's free
            // at the playhead — otherwise a new same-type track is spawned below,
            // so stacking multiple clips at the same time creates parallel rows
            // instead of overlapping on one row.
            const track = this.findOrCreateTrackForClip(media.type, startTime, duration);
            this.addClipToTrack(track, media, startTime);
            this.recalcDuration();
            this.markChanged();
            this.renderFrame();

            if (window.toastr) toastr.success('Added to timeline');
        },

        isMediaCompatible(mediaType, trackType) {
            if (mediaType === 'video' && (trackType === 'video' || trackType === 'image')) return true;
            if (mediaType === 'image' && (trackType === 'video' || trackType === 'image')) return true;
            if (mediaType === 'audio' && trackType === 'audio') return true;
            return false;
        },

        createTrackForMedia(mediaType) {
            const type = mediaType === 'audio' ? 'audio' : 'video';
            const label = type === 'audio' ? 'Audio' : 'Video';
            const track = { id: this.uid(), type, label, locked: false, muted: false, clips: [] };

            // Insert below the last track of the same group so the timeline stays
            // organised (video/image at top, audio in the middle, text at the bottom).
            let insertIndex = this.tracks.length;
            if (type === 'video') {
                let lastVideo = -1;
                for (let i = 0; i < this.tracks.length; i++) {
                    if (this.tracks[i].type === 'video' || this.tracks[i].type === 'image') {
                        lastVideo = i;
                    }
                }
                insertIndex = lastVideo + 1;
            } else if (type === 'audio') {
                let lastAudio = -1;
                let lastVideo = -1;
                for (let i = 0; i < this.tracks.length; i++) {
                    if (this.tracks[i].type === 'audio') lastAudio = i;
                    if (this.tracks[i].type === 'video' || this.tracks[i].type === 'image') lastVideo = i;
                }
                insertIndex = lastAudio !== -1 ? lastAudio + 1 : lastVideo + 1;
            }

            this.tracks.splice(insertIndex, 0, track);
            return track;
        },

        addClipToTrack(track, media, startTime) {
            const hasKnownDuration = !!media.duration_ms;
            const duration = media.duration_ms || 5000; // 5s default for images / unknown

            // Scale media to fit canvas while preserving original aspect ratio
            let drawW = this.projectWidth;
            let drawH = this.projectHeight;
            let drawX = 0;
            let drawY = 0;

            if (media.width && media.height) {
                const scaleX = this.projectWidth / media.width;
                const scaleY = this.projectHeight / media.height;
                const scale = Math.min(scaleX, scaleY); // Fit (preserve ratio)
                drawW = Math.round(media.width * scale);
                drawH = Math.round(media.height * scale);
                drawX = Math.round((this.projectWidth - drawW) / 2);
                drawY = Math.round((this.projectHeight - drawH) / 2);
            }

            const placedStart = Math.max(0, Math.round(startTime));
            // Sub-lane within the track: clips that would overlap another in time
            // get stacked vertically inside the same track row (row grows taller).
            const lane = this.findFreeLane(track, placedStart, duration);

            const clip = {
                id: this.uid(),
                mediaId: media.id,
                startTime: placedStart,
                endTime: placedStart + duration,
                trimStart: 0,
                trimEnd: duration,
                volume: 1.0,
                opacity: 1,
                speed: 1,
                lane: lane,
                x: drawX,
                y: drawY,
                width: drawW,
                height: drawH,
                _durationProbed: hasKnownDuration,
            };

            track.clips.push(clip);
            track.clips.sort((a, b) => a.startTime - b.startTime);

            // For audio with unknown duration, probe asynchronously and patch this clip
            // once we know the real length (Web Audio decoding doubles as waveform source).
            if (media.type === 'audio') {
                this._generateWaveform(media);
            } else if (media.type === 'video' && !hasKnownDuration) {
                this._probeMediaDurationFromElement(media);
            }
        },

        // =====================================================================
        //  CLIP OPERATIONS
        // =====================================================================
        selectClip(clipId, trackId) {
            this.selectedClipId = clipId;
            this.selectedTrackId = trackId;

            const track = this.tracks.find(t => t.id === trackId);
            if (track) {
                this.selectedClipType = track.type;
                const clip = track.clips.find(c => c.id === clipId);
                if (clip) {
                    this.selectedClipProps = { ...clip };
                }
            }
            this.rightPanelOpen = true;
        },

        deselectClip() {
            this.selectedClipId = null;
            this.selectedTrackId = null;
            this.selectedClipType = null;
            this.selectedClipProps = {};
            this.selectedTextBox = null;
        },

        updateClipProp(prop, value) {
            if (!this.selectedClipId || !this.selectedTrackId) return;

            this.pushUndo();
            const track = this.tracks.find(t => t.id === this.selectedTrackId);
            if (!track) return;

            const clip = track.clips.find(c => c.id === this.selectedClipId);
            if (!clip) return;

            clip[prop] = value;

            // Speed affects the clip's timeline length: dur = (trimEnd - trimStart) / speed.
            // Without this, changing speed leaves the timeline duration untouched and the
            // export/preview ignores the new playback rate at the timeline level.
            if (prop === 'speed') {
                const speed = Number(value) > 0 ? Number(value) : 1;
                const trimStart = clip.trimStart || 0;
                const trimEnd = clip.trimEnd ?? (clip.endTime - clip.startTime);
                const newDuration = Math.max(1, Math.round((trimEnd - trimStart) / speed));
                clip.endTime = clip.startTime + newDuration;
            }

            this.selectedClipProps = { ...clip };
            this.recalcDuration();
            this.renderFrame();
            this.markChanged();
        },

        /**
         * Live update for continuous inputs (range sliders).
         * Debounces undo snapshots so dragging a slider only creates one undo entry.
         */
        updateClipPropLive(prop, value) {
            if (!this.selectedClipId || !this.selectedTrackId) return;

            const track = this.tracks.find(t => t.id === this.selectedTrackId);
            if (!track) return;

            const clip = track.clips.find(c => c.id === this.selectedClipId);
            if (!clip) return;

            // Push undo only once per drag gesture
            if (!this._undoDebounceTimer) {
                this.pushUndo();
            }
            clearTimeout(this._undoDebounceTimer);
            this._undoDebounceTimer = setTimeout(() => {
                this._undoDebounceTimer = null;
            }, 300);

            clip[prop] = value;
            this.selectedClipProps = { ...clip };
            this.renderFrame();
            this.markChanged();
        },

        splitClipAtPlayhead() {
            if (!this.selectedClipId || !this.selectedTrackId) return;

            const track = this.tracks.find(t => t.id === this.selectedTrackId);
            if (!track) return;

            const clipIndex = track.clips.findIndex(c => c.id === this.selectedClipId);
            if (clipIndex === -1) return;

            const clip = track.clips[clipIndex];
            if (this.currentTime <= clip.startTime || this.currentTime >= clip.endTime) {
                if (window.toastr) toastr.warning('Playhead must be within the selected clip');
                return;
            }

            this.pushUndo();

            const splitPoint = this.currentTime;

            // Save ALL original values before any modification
            const origStartTime = clip.startTime;
            const origEndTime = clip.endTime;
            const origTrimStart = clip.trimStart || 0;
            const origTrimEnd = clip.trimEnd || (origEndTime - origStartTime);
            const origMediaId = clip.mediaId;
            const origVolume = clip.volume;
            const origOpacity = clip.opacity;
            const origSpeed = clip.speed;
            const origX = clip.x;
            const origY = clip.y;
            const origWidth = clip.width;
            const origHeight = clip.height;
            const origText = clip.text;
            const origColor = clip.color;
            const origFontSize = clip.fontSize;
            const origFontFamily = clip.fontFamily;
            const origFontWeight = clip.fontWeight;

            // The media time at the split point
            const mediaSplitTime = origTrimStart + (splitPoint - origStartTime);

            // === Left clip: shrink original clip's endTime ===
            clip.endTime = splitPoint;
            // trimStart stays the same, trimEnd adjusts
            clip.trimEnd = origTrimEnd;  // keep original trimEnd reference

            // === Right clip: build from scratch (avoid structuredClone on proxy) ===
            const newClip = {
                id: this.uid(),
                mediaId: origMediaId,
                startTime: splitPoint,
                endTime: origEndTime,
                trimStart: mediaSplitTime,
                trimEnd: origTrimEnd,
                volume: origVolume,
                opacity: origOpacity,
                speed: origSpeed,
                x: origX,
                y: origY,
                width: origWidth,
                height: origHeight,
                text: origText,
                color: origColor,
                fontSize: origFontSize,
                fontFamily: origFontFamily,
                fontWeight: origFontWeight,
                _ratioFixed: true,
            };

            // Force Alpine reactivity: reassign the clips array
            track.clips = [
                ...track.clips.slice(0, clipIndex + 1),
                newClip,
                ...track.clips.slice(clipIndex + 1),
            ];

            // Select the right clip
            this.selectClip(newClip.id, track.id);

            this.recalcDuration();
            this.renderFrame();
            this.markChanged();
        },

        deleteClip(clipId) {
            if (!clipId) return;

            this.pushUndo();

            for (const track of this.tracks) {
                const idx = track.clips.findIndex(c => c.id === clipId);
                if (idx !== -1) {
                    track.clips.splice(idx, 1);
                    break;
                }
            }

            if (this.selectedClipId === clipId) {
                this.deselectClip();
            }

            this.recalcDuration();
            this.renderFrame();
            this.markChanged();
        },

        duplicateClip(clipId) {
            if (!clipId) return;

            for (const track of this.tracks) {
                const clip = track.clips.find(c => c.id === clipId);
                if (clip) {
                    this.pushUndo();

                    const duration = clip.endTime - clip.startTime;
                    // Plain spread instead of structuredClone — clip is an Alpine Proxy
                    // which structuredClone refuses to clone. Clips are flat objects so
                    // a shallow copy is sufficient.
                    const newClip = {
                        ...clip,
                        id: this.uid(),
                        startTime: clip.endTime,
                        endTime: clip.endTime + duration,
                    };

                    track.clips.push(newClip);
                    track.clips.sort((a, b) => a.startTime - b.startTime);
                    this.recalcDuration();
                    this.renderFrame();
                    this.markChanged();
                    break;
                }
            }
        },

        /**
         * Reorder the visual track that contains `clipId` among other visual
         * (video/image) tracks. The export overlay chain composites later
         * entries in `tracks[]` on top of earlier ones, so moving forward in
         * the array == moving forward in the visible stack.
         *
         *  direction: 'front' | 'forward' | 'backward' | 'back'
         */
        moveClipLayer(clipId, direction) {
            if (!clipId) return;

            let trackIndex = -1;
            for (let i = 0; i < this.tracks.length; i++) {
                if (this.tracks[i].clips?.some(c => c.id === clipId)) {
                    trackIndex = i;
                    break;
                }
            }
            if (trackIndex === -1) return;
            const track = this.tracks[trackIndex];
            if (track.type !== 'video' && track.type !== 'image') return;

            const visualIndices = [];
            for (let i = 0; i < this.tracks.length; i++) {
                const t = this.tracks[i];
                if (t.type === 'video' || t.type === 'image') visualIndices.push(i);
            }
            const here = visualIndices.indexOf(trackIndex);
            if (here === -1) return;

            let slot;
            if (direction === 'front') slot = visualIndices.length - 1;
            else if (direction === 'back') slot = 0;
            else if (direction === 'forward') slot = Math.min(visualIndices.length - 1, here + 1);
            else if (direction === 'backward') slot = Math.max(0, here - 1);
            else return;
            if (slot === here) return;

            this.pushUndo();
            const destIndex = visualIndices[slot];
            // splice math: index shift after removal cancels out, so destIndex
            // works as the insertion point regardless of move direction.
            this.tracks.splice(trackIndex, 1);
            this.tracks.splice(destIndex, 0, track);
            this.renderFrame();
            this.markChanged();
        },

        /**
         * Whether `moveClipLayer(clipId, direction)` would actually change the
         * stacking. Used by the properties panel to disable inert buttons.
         */
        canMoveClipLayer(clipId, direction) {
            if (!clipId) return false;
            let trackIndex = -1;
            for (let i = 0; i < this.tracks.length; i++) {
                if (this.tracks[i].clips?.some(c => c.id === clipId)) {
                    trackIndex = i;
                    break;
                }
            }
            if (trackIndex === -1) return false;
            const track = this.tracks[trackIndex];
            if (track.type !== 'video' && track.type !== 'image') return false;

            let total = 0;
            let here = -1;
            for (let i = 0; i < this.tracks.length; i++) {
                const t = this.tracks[i];
                if (t.type === 'video' || t.type === 'image') {
                    if (i === trackIndex) here = total;
                    total++;
                }
            }
            if (total < 2) return false;
            if (direction === 'front' || direction === 'forward') return here < total - 1;
            return here > 0;
        },

        // =====================================================================
        //  CLIP DRAGGING
        //  RAF-throttled: moves DOM directly, renders canvas preview,
        //  commits to Alpine only on mouseup.
        // =====================================================================
        startClipDrag(event, clip, track) {
            if (track.locked) return;

            const startX = event.clientX;
            const origStart = clip.startTime;
            const duration = clip.endTime - clip.startTime;
            let hasMoved = false;

            const clipEl = event.currentTarget;
            let pendingStart = origStart;
            let rafId = null;

            const doUpdate = () => {
                rafId = null;
                clipEl.style.left = this.msToPixels(pendingStart) + 'px';
                // Show frame at the new clip position
                this._currentTimeInternal = pendingStart;
                this.renderFrame();
            };

            const onMove = (e) => {
                const dx = e.clientX - startX;
                if (Math.abs(dx) > 3) hasMoved = true;
                if (!hasMoved) return;

                let newStart = Math.max(0, origStart + this.pixelsToMs(dx));
                if (this.snapEnabled) newStart = this.snapToGrid(newStart);
                pendingStart = Math.round(newStart);

                if (!rafId) rafId = requestAnimationFrame(doUpdate);
            };

            const onUp = () => {
                document.removeEventListener('mousemove', onMove);
                document.removeEventListener('mouseup', onUp);
                if (rafId) { cancelAnimationFrame(rafId); rafId = null; }

                if (hasMoved) {
                    this.pushUndo();
                    clip.startTime = pendingStart;
                    clip.endTime = pendingStart + duration;
                    this._currentTimeInternal = null;
                    this.recalcDuration();
                    this.markChanged();
                    this.renderFrame();
                }
            };

            document.addEventListener('mousemove', onMove);
            document.addEventListener('mouseup', onUp);
        },

        // =====================================================================
        //  CLIP TRIMMING
        //  RAF-throttled: resizes DOM directly, seeks video element for
        //  live canvas preview of the trim frame, commits on mouseup.
        // =====================================================================
        startClipTrim(event, clip, track, side) {
            if (track.locked) return;

            const startX = event.clientX;
            const origStart = clip.startTime;
            const origEnd = clip.endTime;
            const origTrimStart = clip.trimStart || 0;
            const origTrimEnd = clip.trimEnd || (origEnd - origStart);

            // trimStart/trimEnd are in source-media time; the clip's timeline length is
            // (trimEnd - trimStart) / speed. A 1ms drag on the timeline therefore moves
            // the trim boundary by `speed` ms in source time.
            const speed = (clip.speed && clip.speed > 0) ? clip.speed : 1;

            const clipEl = event.currentTarget.parentElement;
            let pendingStart = origStart;
            let pendingEnd = origEnd;
            let pendingTrimStart = origTrimStart;
            let pendingTrimEnd = origTrimEnd;
            let rafId = null;

            const doUpdate = () => {
                rafId = null;

                // Update DOM directly
                clipEl.style.left = this.msToPixels(pendingStart) + 'px';
                clipEl.style.width = Math.max(this.msToPixels(pendingEnd - pendingStart), 20) + 'px';

                // For canvas preview: seek video to the trim edge frame.
                // We need to update the clip temporarily so renderClipToCanvas
                // calculates the correct source frame from trimStart/trimEnd.
                const savedStart = clip.startTime;
                const savedEnd = clip.endTime;
                const savedTrimStart = clip.trimStart;
                const savedTrimEnd = clip.trimEnd;

                // Temporarily write pending values (renderFrame reads them)
                clip.startTime = pendingStart;
                clip.endTime = pendingEnd;
                clip.trimStart = pendingTrimStart;
                clip.trimEnd = pendingTrimEnd;

                const previewTime = (side === 'left') ? pendingStart : Math.max(pendingStart, pendingEnd - 1);
                this._currentTimeInternal = previewTime;
                this.renderFrame();

                // Restore originals so Alpine doesn't see a change
                clip.startTime = savedStart;
                clip.endTime = savedEnd;
                clip.trimStart = savedTrimStart;
                clip.trimEnd = savedTrimEnd;
            };

            const onMove = (e) => {
                const dx = e.clientX - startX;
                const dtMs = this.pixelsToMs(dx);

                if (side === 'left') {
                    let newStart = Math.max(0, origStart + dtMs);
                    // Don't let trimStart go below 0 in source time:
                    //   origTrimStart + (newStart - origStart) * speed >= 0
                    const minStart = origStart - origTrimStart / speed;
                    if (newStart < minStart) newStart = minStart;
                    if (this.snapEnabled) newStart = this.snapToGrid(newStart);
                    newStart = Math.round(newStart);
                    if (newStart >= pendingEnd - 100) return;
                    pendingStart = newStart;
                    pendingTrimStart = origTrimStart + (pendingStart - origStart) * speed;
                } else {
                    let newEnd = origEnd + dtMs;
                    if (this.snapEnabled) newEnd = this.snapToGrid(newEnd);
                    newEnd = Math.round(newEnd);
                    if (newEnd <= pendingStart + 100) return;

                    // For finite-length sources (video, audio), prevent stretching past
                    // the end of the underlying media — there's nothing there to play.
                    if (track.type === 'video' || track.type === 'audio') {
                        const media = this._mediaMap[clip.mediaId];
                        const mediaDuration = media && media.duration_ms ? media.duration_ms : null;
                        if (mediaDuration) {
                            // trimEnd must stay <= mediaDuration in source time, so the
                            // max timeline end is (mediaDuration - origTrimEnd) / speed past origEnd.
                            const maxEnd = origEnd + (mediaDuration - origTrimEnd) / speed;
                            if (newEnd > maxEnd) newEnd = maxEnd;
                            if (newEnd <= pendingStart + 100) return;
                        }
                    }

                    pendingEnd = newEnd;
                    pendingTrimEnd = origTrimEnd + (pendingEnd - origEnd) * speed;
                }

                if (!rafId) rafId = requestAnimationFrame(doUpdate);
            };

            const onUp = () => {
                document.removeEventListener('mousemove', onMove);
                document.removeEventListener('mouseup', onUp);
                if (rafId) { cancelAnimationFrame(rafId); rafId = null; }

                // Final commit to Alpine
                this.pushUndo();
                clip.startTime = pendingStart;
                clip.endTime = pendingEnd;
                clip.trimStart = pendingTrimStart;
                clip.trimEnd = pendingTrimEnd;
                this._currentTimeInternal = null;
                this.recalcDuration();
                this.markChanged();
                this.renderFrame();
            };

            document.addEventListener('mousemove', onMove);
            document.addEventListener('mouseup', onUp);
        },

        // =====================================================================
        //  TRACKS
        // =====================================================================
        addTrack(type) {
            this.pushUndo();
            const labels = { video: 'Video', audio: 'Audio', text: 'Text' };
            const track = {
                id: this.uid(),
                type,
                label: labels[type] || 'Track',
                locked: false,
                muted: false,
                clips: [],
            };

            // Insert at logical position: video at top, audio in middle, text at bottom
            if (type === 'text') {
                this.tracks.push(track);
            } else if (type === 'audio') {
                const lastVideo = this.tracks.reduce((acc, t, i) => t.type === 'video' || t.type === 'image' ? i : acc, -1);
                this.tracks.splice(lastVideo + 1, 0, track);
            } else {
                const lastVideo = this.tracks.reduce((acc, t, i) => t.type === 'video' || t.type === 'image' ? i : acc, -1);
                this.tracks.splice(lastVideo + 1, 0, track);
            }

            this.markChanged();
        },

        removeTrack(trackId) {
            if (this.tracks.length <= 1) {
                if (window.toastr) toastr.warning('Cannot remove the last track');
                return;
            }
            this.pushUndo();
            this.tracks = this.tracks.filter(t => t.id !== trackId);
            this.recalcDuration();
            this.markChanged();
        },

        toggleTrackLock(trackId) {
            const track = this.tracks.find(t => t.id === trackId);
            if (track) track.locked = !track.locked;
        },

        toggleTrackMute(trackId) {
            const track = this.tracks.find(t => t.id === trackId);
            if (track) track.muted = !track.muted;
        },

        // =====================================================================
        //  TIMELINE CALCULATIONS
        // =====================================================================
        msToPixels(ms) {
            return (ms / 1000) * 100 * this.timelineZoom;
        },

        pixelsToMs(px) {
            return (px / (100 * this.timelineZoom)) * 1000;
        },

        recalcDuration() {
            let maxEnd = 0;
            for (const track of this.tracks) {
                for (const clip of track.clips) {
                    if (clip.endTime > maxEnd) maxEnd = clip.endTime;
                }
            }
            this.totalDuration = Math.max(maxEnd, 1000); // At least 1 second
            this.recalcTimelineWidth();
        },

        /** Debounced version — use during drag/trim mousemove to avoid lag */
        recalcDurationDebounced() {
            if (this._recalcDebounce) return; // Already scheduled
            this._recalcDebounce = requestAnimationFrame(() => {
                this._recalcDebounce = null;
                this.recalcDuration();
            });
        },

        recalcTimelineWidth() {
            // Add 20% padding beyond the last clip
            this.timelineContentWidth = Math.max(this.msToPixels(this.totalDuration * 1.2), 800);
        },

        snapToGrid(ms) {
            const gridSize = 100; // 100ms grid
            return Math.round(ms / gridSize) * gridSize;
        },

        snapClipPosition(track, startTime, duration) {
            if (!this.snapEnabled) return startTime;

            // Snap to other clip edges
            const snapThreshold = this.pixelsToMs(8);
            let snapped = startTime;

            for (const clip of track.clips) {
                if (Math.abs(startTime - clip.endTime) < snapThreshold) {
                    snapped = clip.endTime;
                    break;
                }
                if (Math.abs(startTime + duration - clip.startTime) < snapThreshold) {
                    snapped = clip.startTime - duration;
                    break;
                }
            }

            return Math.max(0, snapped);
        },

        /**
         * Pick a track for a new clip of `mediaType`. A clip lands on an existing
         * compatible track only when that track has no time-overlapping clip at
         * the placement window; otherwise a brand-new track is created so the
         * clip composites *above* the existing one in the export overlay chain
         * instead of being hidden behind it on a sub-lane.
         */
        findOrCreateTrackForClip(mediaType, startTime, duration, preferredTrack = null) {
            const endTime = startTime + duration;
            const overlapsAtPlacement = (track) => (track.clips || []).some(c =>
                c.startTime < endTime && c.endTime > startTime,
            );

            if (preferredTrack
                && !preferredTrack.locked
                && this.isMediaCompatible(mediaType, preferredTrack.type)
                && !overlapsAtPlacement(preferredTrack)) {
                return preferredTrack;
            }
            for (const track of this.tracks) {
                if (track.locked) continue;
                if (!this.isMediaCompatible(mediaType, track.type)) continue;
                if (overlapsAtPlacement(track)) continue;
                return track;
            }
            return this.createTrackForMedia(mediaType);
        },

        /**
         * Lowest lane index on `track` where a clip of `duration` starting at
         * `startTime` doesn't overlap any existing clip on that lane. New lanes
         * are appended only when every existing lane is occupied at this time.
         */
        findFreeLane(track, startTime, duration) {
            const endTime = startTime + duration;
            let maxLane = 0;
            for (const c of track.clips) {
                const l = c.lane || 0;
                if (l + 1 > maxLane) maxLane = l + 1;
            }
            for (let lane = 0; lane <= maxLane; lane++) {
                const overlap = track.clips.some(c =>
                    (c.lane || 0) === lane
                    && c.startTime < endTime
                    && c.endTime > startTime,
                );
                if (!overlap) return lane;
            }
            return maxLane;
        },

        /**
         * Height of a track row in px, accounting for the lanes it currently holds.
         * A track with no clips, or all clips on lane 0, is one trackItemHeight tall.
         */
        getTrackHeight(track) {
            let maxLane = 0;
            for (const c of track.clips) {
                const l = c.lane || 0;
                if (l > maxLane) maxLane = l;
            }
            return (maxLane + 1) * this.trackItemHeight;
        },

        // =====================================================================
        //  TIMELINE RESIZE
        // =====================================================================
        startTimelineResize(event) {
            const startY = event.clientY;
            const startHeight = this.timelineHeight;
            let rafId = null;

            const onMove = (e) => {
                if (rafId) return;
                rafId = requestAnimationFrame(() => {
                    rafId = null;
                    const dy = startY - e.clientY;
                    this.timelineHeight = Math.min(Math.max(startHeight + dy, 120), 500);
                });
            };

            const onUp = () => {
                if (rafId) cancelAnimationFrame(rafId);
                document.removeEventListener('mousemove', onMove);
                document.removeEventListener('mouseup', onUp);
            };

            document.addEventListener('mousemove', onMove);
            document.addEventListener('mouseup', onUp);
        },

        syncTimelineScroll() {
            // Mirror the tracks area's vertical scroll onto the labels column via
            // a translateY (the labels column doesn't have its own scrollbar — this
            // keeps a single Y scrollbar on the tracks side).
            const tracks = this.$refs.timelineScroll;
            if (tracks) {
                this.timelineScrollY = tracks.scrollTop;
            }
        },

        // =====================================================================
        //  TIME RULER
        // =====================================================================
        renderTimeRuler() {
            // No-op: ruler is now HTML-based via getRulerTicks()
        },

        getRulerTicks() {
            // Cache key: only recompute when zoom or duration changes
            const cacheKey = this.timelineZoom + ':' + this.totalDuration;
            if (this._cachedRulerKey === cacheKey && this._cachedRulerTicks) {
                return this._cachedRulerTicks;
            }

            const ticks = [];

            // Determine tick interval based on zoom
            let majorInterval = 1000; // ms
            if (this.timelineZoom < 0.3) majorInterval = 5000;
            else if (this.timelineZoom < 0.7) majorInterval = 2000;
            else if (this.timelineZoom > 3) majorInterval = 500;

            const minorInterval = majorInterval / 4;
            const totalMs = this.totalDuration * 1.2;

            for (let ms = 0; ms <= totalMs; ms += minorInterval) {
                const isMajor = ms % majorInterval === 0;
                ticks.push({
                    ms: ms,
                    px: this.msToPixels(ms),
                    major: isMajor,
                    label: isMajor ? this.formatTimecodeShort(ms) : '',
                });
            }

            this._cachedRulerTicks = ticks;
            this._cachedRulerKey = cacheKey;
            return ticks;
        },

        handleRulerClick(event) {
            const rect = event.currentTarget.getBoundingClientRect();
            const x = event.clientX - rect.left;
            this.seekTo(this.pixelsToMs(x));
        },

        /**
         * Ruler scrub: mousedown on the ruler starts a drag that continuously
         * seeks + renders the canvas frame under the cursor. The playhead
         * follows the mouse in real-time at 60fps.
         */
        startRulerScrub(event) {
            // Pause playback if playing
            if (this.playing) this.pausePlayback();

            // Use the timelineScroll container as reference for position calculations.
            // This works whether triggered from the ruler itself or the playhead handle.
            const scrollEl = this.$refs.timelineScroll;
            if (!scrollEl) return;
            const scrollRect = scrollEl.getBoundingClientRect();
            let rafId = null;
            let pendingMs = 0;

            const calcMs = (e) => {
                const x = e.clientX - scrollRect.left + scrollEl.scrollLeft;
                return Math.max(0, Math.min(this.pixelsToMs(x), this.totalDuration));
            };

            const doUpdate = () => {
                rafId = null;
                this.currentTime = pendingMs;
                this._currentTimeInternal = pendingMs;
                this.renderFrame();
            };

            // Immediately seek to click position
            pendingMs = calcMs(event);
            doUpdate();

            const onMove = (e) => {
                pendingMs = calcMs(e);
                if (!rafId) rafId = requestAnimationFrame(doUpdate);
            };

            const onUp = () => {
                document.removeEventListener('mousemove', onMove);
                document.removeEventListener('mouseup', onUp);
                if (rafId) { cancelAnimationFrame(rafId); rafId = null; }
                this.currentTime = pendingMs;
                this._currentTimeInternal = null;
                this.renderFrame();
            };

            document.addEventListener('mousemove', onMove);
            document.addEventListener('mouseup', onUp);
        },

        // =====================================================================
        //  PLAYBACK
        // =====================================================================
        togglePlayback() {
            if (this.playing) {
                this.pausePlayback();
            } else {
                this.startPlayback();
            }
        },

        /**
         * Compute the media-element seek time (in seconds) for a clip at timeline time t (ms).
         *   local = t - clip.startTime            (how far into the clip on the timeline)
         *   mediaTime = trimStart + local * speed  (position inside the source file)
         * Returns null if t is outside the clip's active range.
         */
        _clipMediaTime(clip, t) {
            const local = t - clip.startTime;
            if (local < 0 || local >= (clip.endTime - clip.startTime)) return null;
            const trimSec = (clip.trimStart || 0) / 1000;
            const localSec = local / 1000;
            return trimSec + localSec * (clip.speed || 1);
        },

        startPlayback() {
            // Clean up any previous playback
            if (this.playbackRAF) {
                cancelAnimationFrame(this.playbackRAF);
                this.playbackRAF = null;
            }
            for (const k in this.videoElements) {
                const el = this.videoElements[k];
                if (el && (el.tagName === 'VIDEO' || el.tagName === 'AUDIO') && !el.paused) {
                    try { el.pause(); } catch (e) {}
                }
            }

            if (this.currentTime >= this.totalDuration) {
                this.currentTime = 0;
            }

            this.playing = true;
            const base = this.currentTime;
            const origin = performance.now();
            this._currentTimeInternal = base;
            this._lastSync = 0;

            // Seek and .play() all active video AND audio elements.
            // Video elements must be muted for reliable autoplay.
            for (const track of this.tracks) {
                if (track.muted || track.type === 'text' || track.type === 'image') continue;
                for (const clip of track.clips) {
                    const mt = this._clipMediaTime(clip, base);
                    if (mt === null) continue;
                    const media = this._mediaMap[clip.mediaId];
                    if (!media || (media.type !== 'video' && media.type !== 'audio')) continue;
                    const el = this.videoElements['media_' + media.id];
                    if (!el || (el.tagName !== 'VIDEO' && el.tagName !== 'AUDIO')) continue;

                    el.currentTime = mt;
                    el.playbackRate = clip.speed || 1;
                    // startPlayback() runs from a user-gesture handler, so
                    // unmuted audio on video is allowed. Both <video> and
                    // <audio> respect masterMuted / masterVolume / clip.volume
                    // so the preview matches the exported mix.
                    el.muted = this.masterMuted;
                    el.volume = this.masterMuted ? 0 : Math.min(1, this.masterVolume * (clip.volume ?? 1));
                    el.play().catch(() => {});
                }
            }

            // Master-clock tick loop
            const tick = () => {
                if (!this.playing) return;
                const t = base + (performance.now() - origin);
                if (t >= this.totalDuration) {
                    this._currentTimeInternal = this.totalDuration;
                    this.currentTime = this.totalDuration;
                    this.pausePlayback();
                    this.renderFrame();
                    return;
                }
                this._currentTimeInternal = t;
                this.currentTime = t;

                // Sync audio/video every ~5 frames (~12 Hz)
                this._lastSync++;
                if (this._lastSync >= 5) {
                    this._syncMediaElements(t);
                    this._lastSync = 0;
                }

                this.renderFrame();
                this.playbackRAF = requestAnimationFrame(tick);
            };
            this.playbackRAF = requestAnimationFrame(tick);
        },

        pausePlayback() {
            this.playing = false;
            if (this.playbackRAF) {
                cancelAnimationFrame(this.playbackRAF);
                this.playbackRAF = null;
            }
            if (this._currentTimeInternal != null) {
                this.currentTime = this._currentTimeInternal;
            }
            // Pause ALL video and audio elements
            for (const k in this.videoElements) {
                const el = this.videoElements[k];
                if (el && (el.tagName === 'VIDEO' || el.tagName === 'AUDIO') && !el.paused) {
                    try { el.pause(); } catch (e) {}
                }
            }
            this.renderFrame();
        },

        /**
         * Sync all media elements during playback: start clips entering view,
         * stop clips leaving, correct drift > 150ms.
         */
        _syncMediaElements(t) {
            const activeEls = new Set();

            for (const track of this.tracks) {
                if (track.muted || track.type === 'text' || track.type === 'image') continue;
                for (const clip of track.clips) {
                    const expected = this._clipMediaTime(clip, t);
                    if (expected === null) continue;
                    const media = this._mediaMap[clip.mediaId];
                    if (!media || (media.type !== 'video' && media.type !== 'audio')) continue;
                    const el = this.videoElements['media_' + media.id];
                    if (!el || (el.tagName !== 'VIDEO' && el.tagName !== 'AUDIO')) continue;

                    activeEls.add(el);

                    if (el.paused) {
                        // Element should be playing but isn't — start it
                        el.currentTime = expected;
                        el.playbackRate = clip.speed || 1;
                        el.muted = this.masterMuted;
                        el.volume = this.masterMuted ? 0 : Math.min(1, this.masterVolume * (clip.volume ?? 1));
                        el.play().catch(() => {});
                    } else if (Math.abs(el.currentTime - expected) > 0.15) {
                        // Drift too large — correct
                        el.currentTime = expected;
                    }
                }
            }

            // Pause elements that should no longer be active
            for (const k in this.videoElements) {
                const el = this.videoElements[k];
                if (el && (el.tagName === 'VIDEO' || el.tagName === 'AUDIO') && !el.paused && !activeEls.has(el)) {
                    try { el.pause(); } catch (e) {}
                }
            }
        },

        seekTo(ms) {
            const wasPlaying = this.playing;
            if (wasPlaying) this.pausePlayback();

            this.currentTime = Math.max(0, Math.min(ms, this.totalDuration));
            this._currentTimeInternal = this.currentTime;
            this.renderFrame();

            if (wasPlaying) this.startPlayback();
        },

        stepFrame(direction) {
            const frameMs = 1000 / this.projectFps;
            this.seekTo(this.currentTime + frameMs * direction);
        },

        // =====================================================================
        //  MEDIA MAP (fast lookup by id)
        // =====================================================================
        _rebuildMediaMap() {
            const map = {};

            // Preserve any existing entries for media referenced by timeline clips
            // (these may have come from a previous full load or individual fetch)
            const referencedIds = new Set();
            for (const track of this.tracks) {
                for (const clip of (track.clips || [])) {
                    if (clip.mediaId) referencedIds.add(clip.mediaId);
                }
            }
            for (const [id, media] of Object.entries(this._mediaMap)) {
                if (referencedIds.has(Number(id)) || referencedIds.has(id)) {
                    map[id] = media;
                }
            }

            // Add/overwrite with current library items
            for (const m of this.mediaItems) {
                map[m.id] = m;
            }
            this._mediaMap = map;
        },

        // =====================================================================
        //  CANVAS PREVIEW
        // =====================================================================
        renderFrame() {
            if (!this.canvasCtx) return;

            const ctx = this.canvasCtx;
            const w = this.projectWidth;
            const h = this.projectHeight;
            const t = this._currentTimeInternal ?? this.currentTime;

            // Clear canvas
            ctx.fillStyle = '#000000';
            ctx.fillRect(0, 0, w, h);

            // Render video/image clips at current time
            for (const track of this.tracks) {
                if (track.muted) continue;

                // Load audio elements (they don't render on canvas but need to be ready for playback)
                if (track.type === 'audio') {
                    for (const clip of track.clips) {
                        const media = this._mediaMap[clip.mediaId];
                        if (!media) continue;
                        const key = 'media_' + media.id;
                        if (!(key in this.videoElements)) {
                            this._loadMediaElement(media, key);
                        }
                    }
                    continue;
                }

                if (track.type !== 'video' && track.type !== 'image') continue; // Only render visual tracks

                for (const clip of track.clips) {
                    if (t >= clip.startTime && t < clip.endTime) {
                        this.renderClipToCanvas(ctx, clip, track, w, h, t);
                    }
                }
            }
        },

        renderClipToCanvas(ctx, clip, track, canvasW, canvasH, t) {
            const media = this._mediaMap[clip.mediaId];
            if (!media) {
                return;
            }

            const key = 'media_' + media.id;

            // Load element if not cached
            if (!(key in this.videoElements)) {
                this._loadMediaElement(media, key);
                return;
            }

            const element = this.videoElements[key];
            if (!element) return; // Still loading (null placeholder)

            // When paused: seek video to the exact frame for scrubbing/preview.
            // When playing: video is natively playing via .play(), just drawImage.
            if (element.tagName === 'VIDEO' && !this.playing) {
                const targetTime = this._clipMediaTime(clip, t);
                if (targetTime !== null && Math.abs(element.currentTime - targetTime) > 0.02) {
                    element.currentTime = targetTime;
                }
            }

            ctx.globalAlpha = clip.opacity ?? 1;

            let drawX = clip.x || 0;
            let drawY = clip.y || 0;
            let drawW = clip.width || canvasW;
            let drawH = clip.height || canvasH;

            // Fix aspect ratio: detect if clip dimensions don't match the
            // element's natural aspect ratio (e.g. stretched to canvas size)
            const natW = element.videoWidth || element.naturalWidth || 0;
            const natH = element.videoHeight || element.naturalHeight || 0;
            if (natW && natH && !clip._ratioFixed) {
                const clipRatio = drawW / drawH;
                const natRatio = natW / natH;
                // If ratios differ significantly, the clip is stretched — recalculate
                if (Math.abs(clipRatio - natRatio) > 0.01) {
                    const scaleX = canvasW / natW;
                    const scaleY = canvasH / natH;
                    const scale = Math.min(scaleX, scaleY);
                    drawW = Math.round(natW * scale);
                    drawH = Math.round(natH * scale);
                    drawX = Math.round((canvasW - drawW) / 2);
                    drawY = Math.round((canvasH - drawH) / 2);
                    clip.width = drawW;
                    clip.height = drawH;
                    clip.x = drawX;
                    clip.y = drawY;
                }
                clip._ratioFixed = true;
            }

            try {
                // drawImage works with both <video> and <img> elements
                // For video: draws the currently displayed frame
                ctx.drawImage(element, drawX, drawY, drawW, drawH);
            } catch (e) {
                // Video element may not have enough data yet — ignore
            }

            ctx.globalAlpha = 1;
        },

        /**
         * Load a media element (image or video) into the cache.
         * Does NOT set crossOrigin to avoid CORS failures with external CDN URLs.
         * Note: This means captureProjectThumbnail() may produce a black canvas
         * for cross-origin videos — that's acceptable (thumbnails are nice-to-have).
         */
        _loadMediaElement(media, key) {
            // Mark as loading immediately to prevent duplicate loads
            this.videoElements[key] = null;

            if (media.type === 'image') {
                const src = media.url || media.thumbnail_url;
                if (!src) return;

                const img = new Image();
                img.onload = () => {
                    this.videoElements[key] = img;
                    this.renderFrame();
                };
                img.onerror = () => {
                    console.warn('[VideoEditor] Image load failed:', src);
                    // Try thumbnail as fallback
                    if (media.thumbnail_url && src !== media.thumbnail_url) {
                        const fallback = new Image();
                        fallback.onload = () => {
                            this.videoElements[key] = fallback;
                            this.renderFrame();
                        };
                        fallback.src = media.thumbnail_url;
                    }
                };
                img.src = src;
            } else if (media.type === 'video') {
                const src = media.url;
                if (!src) {
                    // No video URL — use thumbnail image as static fallback
                    if (media.thumbnail_url) {
                        const img = new Image();
                        img.onload = () => {
                            this.videoElements[key] = img;
                            this.renderFrame();
                        };
                        img.src = media.thumbnail_url;
                    }
                    return;
                }

                const video = document.createElement('video');
                video.preload = 'auto';
                video.muted = true;        // Start muted (required for autoplay policy)
                video.playsInline = true;
                video.src = src;

                video.addEventListener('loadeddata', () => {
                    this.videoElements[key] = video;
                    this.renderFrame();
                }, { once: true });

                video.addEventListener('error', () => {
                    console.warn('Video load failed:', src);
                    // Fall back to thumbnail image
                    if (media.thumbnail_url) {
                        const img = new Image();
                        img.onload = () => {
                            this.videoElements[key] = img;
                            this.renderFrame();
                        };
                        img.src = media.thumbnail_url;
                    }
                }, { once: true });
            } else if (media.type === 'audio') {
                const src = media.url;
                if (!src) return;

                const audio = document.createElement('audio');
                audio.preload = 'auto';
                audio.src = src;

                audio.addEventListener('loadeddata', () => {
                    this.videoElements[key] = audio;
                }, { once: true });

                audio.addEventListener('error', () => {
                    console.warn('[VideoEditor] Audio load failed:', src);
                }, { once: true });
            }
        },

        /**
         * Decode an audio file via Web Audio API to:
         *   - extract waveform peaks for timeline visualisation
         *   - learn its true duration (fallback when ffprobe is unavailable server-side)
         * Cached per media id so subsequent calls are no-ops.
         */
        async _generateWaveform(media) {
            if (!media || media.type !== 'audio' || !media.url) {
                return;
            }
            if (media.id in this._waveformCache) {
                return;
            }

            this._waveformCache[media.id] = null;

            try {
                const AC = window.AudioContext || window.webkitAudioContext;
                if (!AC) {
                    return;
                }
                if (!this._audioCtx) {
                    this._audioCtx = new AC();
                }

                const response = await fetch(media.url);
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                const buffer = await response.arrayBuffer();
                const audioBuffer = await this._audioCtx.decodeAudioData(buffer);
                const channel = audioBuffer.getChannelData(0);
                const samples = 240;
                const blockSize = Math.max(1, Math.floor(channel.length / samples));
                const peaks = new Array(samples);
                let maxPeak = 0;
                for (let i = 0; i < samples; i++) {
                    let max = 0;
                    const start = i * blockSize;
                    const end = Math.min(channel.length, start + blockSize);
                    for (let j = start; j < end; j++) {
                        const v = Math.abs(channel[j]);
                        if (v > max) max = v;
                    }
                    peaks[i] = max;
                    if (max > maxPeak) maxPeak = max;
                }
                const norm = maxPeak || 1;
                for (let i = 0; i < samples; i++) {
                    peaks[i] = peaks[i] / norm;
                }

                this._waveformCache = { ...this._waveformCache, [media.id]: peaks };

                const trueDurationMs = Math.round(audioBuffer.duration * 1000);
                if (trueDurationMs > 0 && (!media.duration_ms || Math.abs(media.duration_ms - trueDurationMs) > 50)) {
                    media.duration_ms = trueDurationMs;
                    this._reconcileClipDurations(media);
                }
            } catch (e) {
                console.warn('[VideoEditor] Waveform decode failed:', e);
                this._waveformCache = { ...this._waveformCache, [media.id]: [] };
                this._probeMediaDurationFromElement(media);
            }
        },

        /**
         * Fallback duration probe via HTMLAudioElement metadata when Web Audio decoding
         * isn't available (e.g. CORS-restricted blob). Updates media.duration_ms in place.
         */
        _probeMediaDurationFromElement(media) {
            if (!media || !media.url || media.duration_ms) {
                return;
            }
            const el = document.createElement(media.type === 'video' ? 'video' : 'audio');
            el.preload = 'metadata';
            el.src = media.url;
            el.addEventListener('loadedmetadata', () => {
                const ms = Math.round((el.duration || 0) * 1000);
                if (ms > 0) {
                    media.duration_ms = ms;
                    this._reconcileClipDurations(media);
                }
            }, { once: true });
        },

        /**
         * After learning a media's true duration, fix any clips that were added with
         * the placeholder fallback duration (and respect the new source-media ceiling).
         */
        _reconcileClipDurations(media) {
            if (!media || !media.duration_ms) {
                return;
            }
            let touched = false;
            for (const track of this.tracks) {
                for (const clip of track.clips) {
                    if (clip.mediaId !== media.id) continue;
                    const sourceLen = clip.endTime - clip.startTime;
                    const trimEnd = clip.trimEnd || sourceLen;
                    // Cap trim/endTime to the real media duration
                    if (trimEnd > media.duration_ms) {
                        const excess = trimEnd - media.duration_ms;
                        clip.trimEnd = media.duration_ms;
                        clip.endTime = Math.max(clip.startTime + 100, clip.endTime - excess);
                        touched = true;
                    }
                    // If clip is still at the exact 5s placeholder (untouched since
                    // add), expand to actual length. We require an exact match so a user
                    // who trimmed before the probe completed doesn't have their trim undone.
                    if (!clip._durationProbed && sourceLen === 5000 && (clip.trimStart || 0) === 0
                        && (clip.trimEnd ?? sourceLen) === sourceLen && media.duration_ms > sourceLen) {
                        clip.endTime = clip.startTime + media.duration_ms;
                        clip.trimEnd = media.duration_ms;
                        touched = true;
                    }
                    clip._durationProbed = true;
                }
            }
            if (touched) {
                this.recalcDuration();
                if (this.selectedClipId) {
                    for (const track of this.tracks) {
                        const c = track.clips.find(cc => cc.id === this.selectedClipId);
                        if (c) {
                            this.selectedClipProps = { ...c };
                            break;
                        }
                    }
                }
            }
        },

        /**
         * Reactive accessor: returns peaks for a media id and kicks off generation lazily.
         * Returns [] while loading so the SVG renders empty.
         */
        getAudioPeaks(mediaId) {
            const media = this._mediaMap[mediaId];
            if (!media || media.type !== 'audio') return [];
            const peaks = this._waveformCache[mediaId];
            if (peaks === undefined) {
                queueMicrotask(() => this._generateWaveform(media));
                return [];
            }
            return peaks || [];
        },

        /**
         * Build an SVG path describing a symmetric waveform fill for the given peaks
         * sized to `width × height` (in pixels). Empty string when no peaks yet.
         */
        buildWaveformPath(peaks, width, height) {
            if (!peaks || !peaks.length || width <= 0 || height <= 0) return '';
            const mid = height / 2;
            const step = width / peaks.length;
            const amp = mid * 0.9;
            let d = 'M 0 ' + mid.toFixed(1);
            for (let i = 0; i < peaks.length; i++) {
                const x = (i * step).toFixed(1);
                const y = (mid - peaks[i] * amp).toFixed(1);
                d += ' L ' + x + ' ' + y;
            }
            for (let i = peaks.length - 1; i >= 0; i--) {
                const x = (i * step).toFixed(1);
                const y = (mid + peaks[i] * amp).toFixed(1);
                d += ' L ' + x + ' ' + y;
            }
            return d + ' Z';
        },

        getVisibleTextClips() {
            // No caching: Alpine's reactivity must observe clip.fontSize/text/etc on
            // every evaluation, otherwise the on-canvas overlay (and its outline)
            // freezes when properties change but currentTime doesn't.
            const t = this.currentTime;
            const visible = [];
            for (const track of this.tracks) {
                if (track.type !== 'text' || track.muted) continue;
                for (const clip of track.clips) {
                    if (t >= clip.startTime && t < clip.endTime) {
                        visible.push({ ...clip, trackId: track.id });
                    }
                }
            }
            return visible;
        },

        /**
         * Drag video/image clips on the canvas to reposition them.
         * Finds the topmost visible clip at the click point and drags it.
         */
        startCanvasClipDrag(event) {
            const canvas = this.$refs.previewCanvas;
            if (!canvas) return;

            const rect = canvas.getBoundingClientRect();
            const clickX = (event.clientX - rect.left) / this.canvasZoom;
            const clickY = (event.clientY - rect.top) / this.canvasZoom;
            const t = this.currentTime;

            // Find the topmost visible video/image clip under the cursor
            let hitClip = null;
            let hitTrack = null;

            // Iterate tracks in reverse so topmost (last rendered) is found first
            for (let i = this.tracks.length - 1; i >= 0; i--) {
                const track = this.tracks[i];
                if (track.type !== 'video' && track.type !== 'image') continue;
                if (track.muted) continue;

                for (const clip of track.clips) {
                    if (t < clip.startTime || t >= clip.endTime) continue;

                    const cx = clip.x || 0;
                    const cy = clip.y || 0;
                    const cw = clip.width || this.projectWidth;
                    const ch = clip.height || this.projectHeight;

                    if (clickX >= cx && clickX <= cx + cw && clickY >= cy && clickY <= cy + ch) {
                        hitClip = clip;
                        hitTrack = track;
                        break;
                    }
                }
                if (hitClip) break;
            }

            if (!hitClip) return;

            // Select the clip
            this.selectClip(hitClip.id, hitTrack.id);

            const startX = event.clientX;
            const startY = event.clientY;
            const origX = hitClip.x || 0;
            const origY = hitClip.y || 0;

            const onMove = (e) => {
                const dx = (e.clientX - startX) / this.canvasZoom;
                const dy = (e.clientY - startY) / this.canvasZoom;

                hitClip.x = Math.round(origX + dx);
                hitClip.y = Math.round(origY + dy);
                this.selectedClipProps = { ...hitClip };
                this.renderFrame();
            };

            const onUp = () => {
                document.removeEventListener('mousemove', onMove);
                document.removeEventListener('mouseup', onUp);
                this.pushUndo();
                this.markChanged();
            };

            document.addEventListener('mousemove', onMove);
            document.addEventListener('mouseup', onUp);
        },

        /**
         * Resize a video/image clip from a corner handle, maintaining aspect ratio.
         * corner: 'tl' | 'tr' | 'bl' | 'se'
         */
        startCanvasClipResize(event, corner) {
            if (!this.selectedClipId || !this.selectedTrackId) return;

            const track = this.tracks.find(t => t.id === this.selectedTrackId);
            if (!track) return;
            const clip = track.clips.find(c => c.id === this.selectedClipId);
            if (!clip) return;

            const startMouseX = event.clientX;
            const startMouseY = event.clientY;
            const origX = clip.x || 0;
            const origY = clip.y || 0;
            const origW = clip.width || this.projectWidth;
            const origH = clip.height || this.projectHeight;
            const ratio = origW / origH;

            const onMove = (e) => {
                const dx = (e.clientX - startMouseX) / this.canvasZoom;
                const dy = (e.clientY - startMouseY) / this.canvasZoom;

                let newW, newH, newX, newY;

                if (corner === 'se') {
                    // Bottom-right: grow/shrink from top-left anchor
                    newW = Math.max(40, origW + dx);
                    newH = newW / ratio;
                    newX = origX;
                    newY = origY;
                } else if (corner === 'tl') {
                    // Top-left: grow/shrink from bottom-right anchor
                    newW = Math.max(40, origW - dx);
                    newH = newW / ratio;
                    newX = origX + (origW - newW);
                    newY = origY + (origH - newH);
                } else if (corner === 'tr') {
                    // Top-right: grow/shrink from bottom-left anchor
                    newW = Math.max(40, origW + dx);
                    newH = newW / ratio;
                    newX = origX;
                    newY = origY + (origH - newH);
                } else if (corner === 'bl') {
                    // Bottom-left: grow/shrink from top-right anchor
                    newW = Math.max(40, origW - dx);
                    newH = newW / ratio;
                    newX = origX + (origW - newW);
                    newY = origY;
                }

                clip.x = Math.round(newX);
                clip.y = Math.round(newY);
                clip.width = Math.round(newW);
                clip.height = Math.round(newH);
                this.selectedClipProps = { ...clip };
                this.renderFrame();
            };

            const onUp = () => {
                document.removeEventListener('mousemove', onMove);
                document.removeEventListener('mouseup', onUp);
                this.pushUndo();
                this.markChanged();
            };

            document.addEventListener('mousemove', onMove);
            document.addEventListener('mouseup', onUp);
        },

        /**
         * Measure the currently-selected text overlay's bounding box in canvas
         * (project) coordinates. Text dimensions are auto-sized by the browser,
         * so we read the rendered rect from the DOM whenever something that can
         * affect text size changes (selection, font, content, time, zoom).
         */
        measureSelectedTextBox() {
            if (!this.selectedClipId || this.selectedClipType !== 'text') {
                this.selectedTextBox = null;
                return;
            }
            const canvas = this.$refs.previewCanvas;
            const el = document.querySelector(`[data-text-clip-id="${this.selectedClipId}"]`);
            if (!canvas || !el) {
                this.selectedTextBox = null;
                return;
            }
            const cRect = canvas.getBoundingClientRect();
            const eRect = el.getBoundingClientRect();
            const z = this.canvasZoom || 1;
            this.selectedTextBox = {
                x: (eRect.left - cRect.left) / z,
                y: (eRect.top - cRect.top) / z,
                w: eRect.width / z,
                h: eRect.height / z,
            };
        },

        /**
         * Resize the selected text clip by scaling fontSize from the dragged
         * corner. Text width/height are intrinsic — we drive size via fontSize
         * and reposition the clip's anchor so the *opposite* corner stays put.
         */
        startTextResize(event, corner) {
            if (!this.selectedClipId || !this.selectedTrackId) return;

            const track = this.tracks.find(t => t.id === this.selectedTrackId);
            if (!track) return;
            const clip = track.clips.find(c => c.id === this.selectedClipId);
            if (!clip) return;

            const box = this.selectedTextBox;
            if (!box || !box.w || !box.h) return;

            const startMouseX = event.clientX;
            const startMouseY = event.clientY;
            const origFontSize = clip.fontSize || 32;
            const origX = clip.x || 0;
            const origY = clip.y || 0;
            const origW = box.w;
            const origH = box.h;

            // Anchor (the fixed corner) in project coords
            let anchorX, anchorY;
            if (corner === 'br') { anchorX = origX;          anchorY = origY; }
            else if (corner === 'tl') { anchorX = origX + origW; anchorY = origY + origH; }
            else if (corner === 'tr') { anchorX = origX;          anchorY = origY + origH; }
            else if (corner === 'bl') { anchorX = origX + origW; anchorY = origY; }

            const onMove = (e) => {
                const dx = (e.clientX - startMouseX) / this.canvasZoom;
                const dy = (e.clientY - startMouseY) / this.canvasZoom;

                // Pick the dominant axis delta for scaling (text is one-axis size).
                // Outward drag for the active corner = grow.
                let signedDelta;
                if (corner === 'br')      { signedDelta = Math.max(dx, dy); }
                else if (corner === 'tl') { signedDelta = Math.max(-dx, -dy); }
                else if (corner === 'tr') { signedDelta = Math.max(dx, -dy); }
                else if (corner === 'bl') { signedDelta = Math.max(-dx, dy); }

                const scale = Math.max(0.1, (origW + signedDelta) / origW);
                const newFontSize = Math.max(8, Math.round(origFontSize * scale));
                clip.fontSize = newFontSize;

                // Reposition so the anchor corner stays fixed. Estimate the new
                // box from the linear scaling factor (actual DOM size is
                // measured after the next tick via measureSelectedTextBox).
                const newW = origW * (newFontSize / origFontSize);
                const newH = origH * (newFontSize / origFontSize);
                if (corner === 'br')      { clip.x = Math.round(anchorX);          clip.y = Math.round(anchorY); }
                else if (corner === 'tl') { clip.x = Math.round(anchorX - newW);    clip.y = Math.round(anchorY - newH); }
                else if (corner === 'tr') { clip.x = Math.round(anchorX);          clip.y = Math.round(anchorY - newH); }
                else if (corner === 'bl') { clip.x = Math.round(anchorX - newW);    clip.y = Math.round(anchorY); }

                this.selectedClipProps = { ...clip };
                this.$nextTick(() => this.measureSelectedTextBox());
            };

            const onUp = () => {
                document.removeEventListener('mousemove', onMove);
                document.removeEventListener('mouseup', onUp);
                this.pushUndo();
                this.markChanged();
            };

            document.addEventListener('mousemove', onMove);
            document.addEventListener('mouseup', onUp);
        },

        startTextDrag(event, textClip) {
            const startX = event.clientX;
            const startY = event.clientY;
            const origX = textClip.x || 0;
            const origY = textClip.y || 0;

            const onMove = (e) => {
                const dx = (e.clientX - startX) / this.canvasZoom;
                const dy = (e.clientY - startY) / this.canvasZoom;

                const track = this.tracks.find(t => t.id === textClip.trackId);
                if (!track) return;
                const clip = track.clips.find(c => c.id === textClip.id);
                if (!clip) return;

                clip.x = Math.round(origX + dx);
                clip.y = Math.round(origY + dy);
                this.selectedClipProps = { ...clip };
                this.$nextTick(() => this.measureSelectedTextBox());
            };

            const onUp = () => {
                document.removeEventListener('mousemove', onMove);
                document.removeEventListener('mouseup', onUp);
                this.pushUndo();
                this.markChanged();
            };

            document.addEventListener('mousemove', onMove);
            document.addEventListener('mouseup', onUp);
        },

        // =====================================================================
        //  CANVAS ZOOM
        // =====================================================================
        zoomCanvas(delta) {
            this.canvasZoom = Math.min(Math.max(this.canvasZoom + delta, 0.1), 2);
        },

        zoomCanvasFit() {
            const container = this.$refs.previewCanvas?.parentElement?.parentElement;
            if (!container) return;

            const padding = 40;
            const availW = container.clientWidth - padding * 2;
            const availH = container.clientHeight - padding * 2;

            const scaleX = availW / this.projectWidth;
            const scaleY = availH / this.projectHeight;

            this.canvasZoom = Math.min(scaleX, scaleY, 1);
        },

        // =====================================================================
        //  UNDO / REDO
        // =====================================================================
        pushUndo() {
            const snapshot = JSON.stringify(this.tracks);
            // Guard against excessively large snapshots (>5MB)
            if (snapshot.length > 5_000_000) {
                // Keep only the last 10 entries to free memory
                this.undoStack = this.undoStack.slice(-10);
            }
            this.undoStack.push(snapshot);
            if (this.undoStack.length > this.maxUndoSteps) {
                this.undoStack.shift();
            }
            this.redoStack = [];
            this.canUndo = this.undoStack.length > 0;
            this.canRedo = false;
        },

        undo() {
            if (!this.undoStack.length) return;

            const current = JSON.stringify(this.tracks);
            this.redoStack.push(current);

            const prev = this.undoStack.pop();
            this.tracks = JSON.parse(prev);

            this.canUndo = this.undoStack.length > 0;
            this.canRedo = this.redoStack.length > 0;
            this.deselectClip();
            this.recalcDuration();
            this.markChanged();
        },

        redo() {
            if (!this.redoStack.length) return;

            const current = JSON.stringify(this.tracks);
            this.undoStack.push(current);

            const next = this.redoStack.pop();
            this.tracks = JSON.parse(next);

            this.canUndo = this.undoStack.length > 0;
            this.canRedo = this.redoStack.length > 0;
            this.deselectClip();
            this.recalcDuration();
            this.markChanged();
        },

        // =====================================================================
        //  AI VIDEO GENERATION
        //
        //  The editor consumes the live nested config from AiVideoPro via the
        //  /ai/models endpoint, so any new model/feature added in AiVideoPro
        //  automatically appears here. UI mirrors AiVideoPro's input-section:
        //  multi-level model dropdown → sub-models → features → dynamic inputs.
        // =====================================================================
        async loadAiModels() {
            try {
                const res = await this.apiRequest(this.routes.aiModels, 'GET');
                const data = await res.json();
                if (data.config && Object.keys(data.config).length) {
                    this.aiConfig = data.config;
                    this.aiAutoSelectInitial();
                }
            } catch (e) {
                console.error('Failed to load AI models:', e);
            }
        },

        aiAutoSelectInitial() {
            const firstAction = Object.keys(this.aiConfig).find(k => this.aiConfig[k]?.isActive !== false);
            if (!firstAction) return;
            this.aiSelectedAction = firstAction;
            this.aiAutoSelectSubModel();
            this.aiAutoSelectFeature();
        },

        aiAutoSelectSubModel() {
            const subModels = this.aiSubModels;
            const keys = Object.keys(subModels);
            this.aiSelectedSubModel = keys.length ? keys[0] : '';
        },

        aiAutoSelectFeature() {
            const features = this.aiFeatures;
            if (features.length) {
                this.aiSelectedFeature = features[0].value;
                this.aiInitializeFormValues();
                this.checkAiCredits();
            } else {
                this.aiSelectedFeature = '';
                this.aiFormValues = {};
                this.aiCredits = null;
            }
        },

        aiInitializeFormValues() {
            const feature = this.aiCurrentFeature;
            const values = {};
            const files = {};
            for (const input of (feature?.inputs || [])) {
                if (input.type === 'file') {
                    files[input.name] = input.multiple ? [] : null;
                    continue;
                }
                if (input.type === 'checkbox') {
                    values[input.name] = input.default === true || input.default === 'true' || input.default === 1;
                } else {
                    values[input.name] = input.default ?? '';
                }
            }
            this.aiFormValues = values;
            this.aiFiles = files;
        },

        aiHandleFileSelect(name, event) {
            const fileList = Array.from(event.target.files || []);
            const input = (this.aiCurrentFeature?.inputs || []).find(i => i.name === name);
            if (input?.multiple) {
                this.aiFiles[name] = fileList;
            } else {
                this.aiFiles[name] = fileList[0] || null;
            }
        },

        aiFileLabel(name) {
            const val = this.aiFiles?.[name];
            if (!val) return '';
            if (Array.isArray(val)) {
                if (!val.length) return '';
                return val.length === 1 ? val[0].name : `${val.length} files selected`;
            }
            return val.name || '';
        },

        get aiCurrentModel() {
            return this.aiSelectedAction && this.aiConfig[this.aiSelectedAction]
                ? this.aiConfig[this.aiSelectedAction]
                : null;
        },

        get aiSubModels() {
            return this.aiCurrentModel?.subModels || {};
        },

        get aiCurrentSubModel() {
            return this.aiSelectedSubModel && this.aiSubModels[this.aiSelectedSubModel]
                ? this.aiSubModels[this.aiSelectedSubModel]
                : null;
        },

        get aiFeatures() {
            const features = this.aiCurrentSubModel?.features;
            if (!features) return [];
            return Object.entries(features).map(([value, def]) => ({
                value,
                label: def.label || value,
                description: def.description || '',
            }));
        },

        get aiCurrentFeature() {
            const features = this.aiCurrentSubModel?.features;
            if (!features || !this.aiSelectedFeature) return null;
            return features[this.aiSelectedFeature] || null;
        },

        get aiShouldShowSubModels() {
            const keys = Object.keys(this.aiSubModels);
            if (keys.length <= 1) return false;
            return true;
        },

        get aiShouldShowFeatures() {
            return this.aiFeatures.length > 1;
        },

        aiIsPrimaryInput(input) {
            if (!input) return false;
            if (input.type === 'file') return true;
            if (input.type === 'textarea' && input.required) return true;
            if (input.name === 'prompt') return true;
            return false;
        },

        get aiPrimaryInputs() {
            const inputs = this.aiCurrentFeature?.inputs || [];
            return inputs.filter(input => this.aiIsPrimaryInput(input));
        },

        get aiAdvancedInputs() {
            const inputs = this.aiCurrentFeature?.inputs || [];
            return inputs.filter(input => !this.aiIsPrimaryInput(input));
        },

        aiShouldShowInput(input) {
            if (!input?.show_if) return true;
            return this.aiFormValues[input.show_if] === true;
        },

        aiGetSelectedModelLabel() {
            const m = this.aiCurrentModel;
            if (!m) return 'Select AI Model';
            let label = m.label || '';
            if (this.aiShouldShowSubModels && this.aiSelectedSubModel) {
                label += ' / ' + this.aiSelectedSubModel;
            }
            return label;
        },

        aiGetSelectedFeatureLabel() {
            const f = this.aiFeatures.find(f => f.value === this.aiSelectedFeature);
            return f ? f.label : 'Select Feature';
        },

        aiGetSelectLabel(input) {
            const val = this.aiFormValues[input.name];
            if (input.options && input.options.length) {
                const opt = input.options.find(o => String(o.value) === String(val));
                if (opt) return opt.label;
            }
            return val || input.placeholder || 'Select';
        },

        aiGetPillIcon(input) {
            if (input.name === 'duration' || input.name === 'sora_seconds') return 'clock';
            if (input.name === 'generate_audio' || input.name === 'keep_original_sound') return 'volume';
            if (input.name === 'aspect_ratio' || input.name === 'sora_size') return 'ratio';
            if (input.name === 'resolution') return 'resolution';
            if (input.name === 'enhance_prompt') return 'sparkle';
            if (input.name === 'auto_fix') return 'wand';
            if (input.name === 'seed') return 'dice';
            if (input.name === 'negative_prompt') return 'ban';
            if (input.type === 'textarea') return 'text';
            if (input.type === 'number') return 'dice';
            return 'settings';
        },

        aiGetPillDisplayValue(input) {
            const val = this.aiFormValues[input.name];
            if (input.type === 'checkbox') {
                return val ? 'On' : 'Off';
            }
            if (input.options && input.options.length) {
                const opt = input.options.find(o => String(o.value) === String(val));
                if (opt) return opt.label;
                return val || input.default || input.label || '';
            }
            if (input.type === 'textarea') {
                if (!val) return input.label || '';
                return val.length > 15 ? val.substring(0, 15) + '…' : val;
            }
            return val || input.label || '';
        },

        aiSelectModelFromDropdown(modelKey) {
            const subKeys = Object.keys(this.aiConfig[modelKey]?.subModels || {});
            if (subKeys.length <= 1) {
                this.aiSelectedAction = modelKey;
                this.aiAutoSelectSubModel();
                this.aiAutoSelectFeature();
                this.aiModelDropdownOpen = false;
            } else {
                // Stay open so the user can hover/click into a sub-model
                this.aiSelectedAction = modelKey;
                this.aiAutoSelectSubModel();
                this.aiAutoSelectFeature();
            }
        },

        aiSelectSubModelFromDropdown(modelKey, subKey) {
            this.aiSelectedAction = modelKey;
            this.aiSelectedSubModel = subKey;
            this.aiAutoSelectFeature();
            this.aiModelDropdownOpen = false;
        },

        aiHasPending() {
            return (this.aiGenerations || []).some(g =>
                g.status === 'IN_QUEUE' ||
                g.status === 'queued' ||
                g.status === 'processing'
            );
        },

        aiHasPrompt() {
            const inputs = this.aiCurrentFeature?.inputs || [];
            // If a prompt input exists and is required, enforce it
            const promptInput = inputs.find(i => i.name === 'prompt');
            if (promptInput && (promptInput.required || promptInput.type === 'textarea')) {
                if (!this.aiFormValues.prompt || !String(this.aiFormValues.prompt).trim()) {
                    // Allow generation if no prompt but a required file is set
                    const requiredFile = inputs.find(i => i.type === 'file' && i.required);
                    if (requiredFile) return !!this.aiFiles?.[requiredFile.name];
                    return false;
                }
            }
            // Enforce any required file inputs
            for (const input of inputs) {
                if (input.type === 'file' && input.required) {
                    const val = this.aiFiles?.[input.name];
                    if (input.multiple ? !(Array.isArray(val) && val.length) : !val) return false;
                }
            }
            return true;
        },

        async checkAiCredits() {
            if (!this.aiSelectedFeature) {
                this.aiCredits = null;
                return;
            }
            try {
                const res = await this.apiRequest(this.routes.aiCredits + '?model=' + encodeURIComponent(this.aiSelectedFeature), 'GET');
                this.aiCredits = await res.json();
            } catch (e) {
                console.error('Failed to check credits:', e);
            }
        },

        async generateAiVideo() {
            if (this.aiGenerating || !this.aiHasPrompt() || !this.aiSelectedFeature) return;

            this.aiGenerating = true;

            try {
                const hasFiles = Object.values(this.aiFiles || {}).some(v => Array.isArray(v) ? v.length : !!v);

                let res;
                if (hasFiles) {
                    const fd = new FormData();
                    fd.append('model', this.aiSelectedFeature);
                    fd.append('action', this.aiSelectedAction);
                    fd.append('feature', this.aiSelectedFeature);
                    if (this.projectId) fd.append('project_id', this.projectId);

                    for (const [key, value] of Object.entries(this.aiFormValues)) {
                        if (value === '' || value === null || value === undefined) continue;
                        if (typeof value === 'boolean') {
                            fd.append(key, value ? '1' : '0');
                        } else {
                            fd.append(key, value);
                        }
                    }

                    for (const [name, val] of Object.entries(this.aiFiles || {})) {
                        if (!val) continue;
                        if (Array.isArray(val)) {
                            val.forEach(file => fd.append(`${name}[]`, file));
                        } else {
                            fd.append(name, val);
                        }
                    }

                    res = await fetch(this.routes.aiGenerate, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': this.csrfToken,
                            'Accept': 'application/json',
                        },
                        body: fd,
                    });
                } else {
                    const payload = {
                        model: this.aiSelectedFeature,
                        action: this.aiSelectedAction,
                        feature: this.aiSelectedFeature,
                        project_id: this.projectId,
                    };

                    for (const [key, value] of Object.entries(this.aiFormValues)) {
                        if (value !== '' && value !== null && value !== undefined) {
                            payload[key] = value;
                        }
                    }

                    res = await this.apiRequest(this.routes.aiGenerate, 'POST', payload);
                }
                const data = await res.json();

                if (data.status === 'error') {
                    if (window.toastr) toastr.error(data.message || 'Generation failed');
                    if (data.upgrade_url) {
                        window.open(data.upgrade_url, '_blank');
                    }
                    return;
                }

                if (data.generation) {
                    this.aiGenerations.unshift(data.generation);
                    this.startAiPolling();
                    // Clear prompt after successful generation
                    this.aiFormValues.prompt = '';
                    if (window.toastr) toastr.success(data.message || 'Video generation started');
                }
            } catch (e) {
                console.error('AI generation failed:', e);
                if (window.toastr) toastr.error('Failed to start generation');
            } finally {
                this.aiGenerating = false;
            }
        },

        startAiPolling() {
            if (this.aiPollInterval) return;

            this.aiPollInterval = setInterval(async () => {
                const pending = this.aiGenerations.filter(g =>
                    g.status === 'IN_QUEUE' || g.status === 'queued' || g.status === 'processing'
                );
                if (!pending.length) {
                    clearInterval(this.aiPollInterval);
                    this.aiPollInterval = null;
                    return;
                }

                try {
                    const res = await this.apiRequest(this.routes.aiStatus, 'POST', {
                        entries: pending.map(g => g.id),
                    });
                    const data = await res.json();

                    if (data.results) {
                        for (const updated of data.results) {
                            const idx = this.aiGenerations.findIndex(g => g.id === updated.id);
                            if (idx !== -1) {
                                Object.assign(this.aiGenerations[idx], updated);
                            }
                        }
                    }
                } catch (e) {
                    console.error('AI poll failed:', e);
                }
            }, 5000);
        },

        async importAiVideoToEditor(generationId) {
            try {
                const res = await this.apiRequest(this.routes.aiImport, 'POST', {
                    user_fall_id: generationId,
                    project_id: this.projectId,
                });

                const data = await res.json();
                if (data.media) {
                    this.mediaItems.unshift(data.media);
                    this._mediaMap[data.media.id] = data.media;
                    this.leftPanelTab = 'library';
                    // Generate client-side thumbnail for imported video
                    if (data.media.type === 'video' && !data.media.thumbnail_url) {
                        this.$nextTick(() => this.generateVideoThumbnail(data.media));
                    }
                    if (window.toastr) toastr.success(data.message || 'Video imported to library');
                }
            } catch (e) {
                console.error('Import failed:', e);
            }
        },

        // =====================================================================
        //  AI VOICE (TTS)
        // =====================================================================

        /**
         * Load voice data from backend (ElevenLabs voices for non-OpenAI providers).
         * For OpenAI, voices are server-rendered — this just populates ElevenLabs data.
         */
        async loadVoiceModels() {
            if (this.voiceDataLoaded) return;
            try {
                const res = await this.apiRequest(this.routes.voiceModels, 'GET');
                const data = await res.json();
                this.voiceDataLoaded = true;

                if (data.elevenlabs_voices) {
                    this._voiceElevenlabsVoices = data.elevenlabs_voices;
                }

                // For non-OpenAI, populate voices for the default language
                if (data.provider !== 'openai') {
                    this.onVoiceLanguageChange();
                }
            } catch (e) {
                console.error('Failed to load voice data:', e);
            }
        },

        /**
         * When language changes, repopulate the voice dropdown with Google voices
         * for that language, plus any ElevenLabs voices if the language is supported.
         */
        onVoiceLanguageChange() {
            const lang = this.voiceLang;
            const options = [];

            // Google voices: only seed when Google TTS is admin-enabled,
            // otherwise the user could pick a Google voice ID and submit it
            // to ElevenLabs (which rejects it with "An invalid ID has been
            // received").
            if (this.ttsGoogleEnabled) {
                const googleVoices = window._veVoicesData?.[lang];
                if (googleVoices && Array.isArray(googleVoices)) {
                    googleVoices.forEach(v => {
                        options.push({ value: v.value, label: v.label, platform: 'google' });
                    });
                }
            }

            // ElevenLabs voices: only seed when ElevenLabs is admin-enabled
            // and the selected language is supported.
            const elevenlabsLangs = [
                'en-US','en-AU','en-IN','en-GB','hi-IN','pt-PT','es-ES','fr-FR',
                'de-DE','ja-JP','ar-XA','ru-RU','ko-KR','id-ID','it-IT','nl-NL',
                'tr-TR','pl-PL','sv-SE','fil-PH','ms-MY','ro-RO','uk-UA','el-GR',
                'cs-CZ','da-DK','fi-FI','bg-BG','sk-SK','ta-IN'
            ];
            if (this.ttsElevenlabsEnabled
                && elevenlabsLangs.includes(lang)
                && this._voiceElevenlabsVoices.length) {
                this._voiceElevenlabsVoices.forEach(v => {
                    options.push({ value: v.voice_id, label: v.name + ' (ElevenLabs)', platform: 'elevenlabs' });
                });
            }

            this.voiceOptions = options;
            this.voiceId = options[0]?.value || '';
        },

        /**
         * Get the platform of the currently selected voice (for the API call).
         * For OpenAI mode, returns 'openai'. For others, checks voiceOptions.
         */
        _getSelectedVoicePlatform() {
            if (this.ttsProvider === 'openai') return 'openai';
            const opt = this.voiceOptions.find(v => v.value === this.voiceId);
            return opt?.platform || 'google';
        },

        /**
         * Generate voiceover using the admin-configured TTS provider.
         */
        async generateVoice() {
            if (!this.voiceText?.trim() || this.voiceGenerating) return;
            this.voiceGenerating = true;

            try {
                const payload = {
                    voice: this.voiceId,
                    text: this.voiceText,
                    project_id: this.projectId,
                };

                // Add provider-specific fields based on admin's TTS setting
                if (this.ttsProvider === 'openai') {
                    // OpenAI mode — voice and model are sent
                    payload.model = this.voiceModel;
                } else {
                    // Google/ElevenLabs/Azure — send lang, pace, platform
                    const platform = this._getSelectedVoicePlatform();
                    payload.lang = this.voiceLang;
                    payload.pace = this.voicePace;
                    payload.platform = platform;
                }

                const res = await this.apiRequest(this.routes.voiceGenerate, 'POST', payload);
                const data = await res.json();

                if (data.status === 'success' && data.media) {
                    this.mediaItems.unshift(data.media);
                    // Mirror the entry into the lookup map every render/playback
                    // path reads from — without this the dropped clip can't
                    // resolve its media until a page refresh rebuilds the map.
                    this._mediaMap[data.media.id] = data.media;
                    this.leftPanelTab = 'library';
                    this.mediaFilter = 'audio';
                    this.voiceText = '';
                    if (window.toastr) toastr.success(data.message || 'Voiceover generated');
                } else {
                    if (window.toastr) toastr.error(data.message || 'Voiceover generation failed');
                }
            } catch (e) {
                console.error('Voice generation failed:', e);
                if (window.toastr) toastr.error(e?.message || 'Voiceover generation failed');
            } finally {
                this.voiceGenerating = false;
            }
        },

        // =====================================================================
        //  AI MUSIC
        // =====================================================================
        musicRandomPrompt() {
            const idx = Math.floor(Math.random() * this.musicExamplePrompts.length);
            this.musicPrompt = this.musicExamplePrompts[idx];
        },

        musicAddImages(event) {
            const files = Array.from(event.target.files || []);
            const remaining = 10 - this.musicImages.length;
            files.slice(0, remaining).forEach(file => {
                this.musicImages.push(file);
                this.musicImagePreviews.push(URL.createObjectURL(file));
            });
            event.target.value = '';
        },

        musicRemoveImage(index) {
            try { URL.revokeObjectURL(this.musicImagePreviews[index]); } catch (e) { /* noop */ }
            this.musicImages.splice(index, 1);
            this.musicImagePreviews.splice(index, 1);
        },

        async generateMusic() {
            if (!this.musicPrompt?.trim() || this.musicGenerating) return;
            this.musicGenerating = true;

            try {
                let res;
                const hasImages = this.musicProvider !== 'elevenlabs' && this.musicImages.length > 0;

                if (hasImages) {
                    const fd = new FormData();
                    fd.append('prompt', this.musicPrompt);
                    if (this.musicStyle) fd.append('style', this.musicStyle);
                    if (this.projectId) fd.append('project_id', this.projectId);
                    if (this.musicLyrics) fd.append('lyrics', this.musicLyrics);
                    if (this.musicProvider === 'lyria3pro') {
                        fd.append('output_format', this.musicOutputFormat || 'mp3');
                    }
                    this.musicImages.forEach(file => fd.append('images[]', file));

                    res = await fetch(this.routes.musicGenerate, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': this.csrfToken,
                            'Accept': 'application/json',
                        },
                        body: fd,
                    });
                } else {
                    const payload = {
                        prompt: this.musicPrompt,
                        style: this.musicStyle || null,
                        project_id: this.projectId,
                    };

                    if (this.musicProvider === 'elevenlabs') {
                        payload.duration = this.musicDuration || 30;
                    } else {
                        if (this.musicLyrics) payload.lyrics = this.musicLyrics;
                        if (this.musicProvider === 'lyria3pro') {
                            payload.output_format = this.musicOutputFormat || 'mp3';
                        }
                    }

                    res = await this.apiRequest(this.routes.musicGenerate, 'POST', payload);
                }

                const data = await res.json();
                if (data.status === 'success' && data.media) {
                    this.mediaItems.unshift(data.media);
                    this._mediaMap[data.media.id] = data.media;
                    this.leftPanelTab = 'library';
                    this.mediaFilter = 'audio';
                    this.musicPrompt = '';
                    this.musicLyrics = '';
                    this.musicImagePreviews.forEach(url => { try { URL.revokeObjectURL(url); } catch (e) {} });
                    this.musicImages = [];
                    this.musicImagePreviews = [];
                    if (window.toastr) toastr.success(data.message || 'Music generated');
                } else {
                    if (window.toastr) toastr.error(data.message || 'Music generation failed');
                }
            } catch (e) {
                console.error('Music generation failed:', e);
                if (window.toastr) toastr.error(e?.message || 'Music generation failed');
            } finally {
                this.musicGenerating = false;
            }
        },

        // =====================================================================
        //  EXPORT
        // =====================================================================
        openExportModal() {
            this.exportModalOpen = true;
            this.exportComplete = false;
            this.exportError = null;
            this.exportProgress = 0;
        },

        async startExport() {
            if (!this.projectId) {
                await this.saveProject();
                if (!this.projectId) {
                    if (window.toastr) toastr.error('Please save the project first');
                    return;
                }
            }

            // Save latest changes before export
            await this.saveProject();

            this.exporting = true;
            this.exportProgress = 0;
            this.exportError = null;

            try {
                const res = await this.apiRequest(this.routes.exportStart, 'POST', {
                    project_id: this.projectId,
                });

                const data = await res.json();

                if (data.success === false) {
                    this.exportError = data.message || 'Export failed';
                    this.exporting = false;
                    return;
                }

                if (data.export_id) {
                    this.exportJobId = data.export_id;
                    this.startExportPolling();
                }
            } catch (e) {
                console.error('Export start failed:', e);
                this.exportError = 'Failed to start export';
                this.exporting = false;
            }
        },

        startExportPolling() {
            this.exportPollInterval = setInterval(async () => {
                try {
                    const url = this.routes.exportStart.replace(/\/start$/, '') + '/' + this.exportJobId + '/status';
                    const res = await this.apiRequest(url, 'GET');
                    const data = await res.json();

                    const exp = data.export || {};
                    this.exportProgress = exp.progress || 0;

                    if (exp.status === 'completed') {
                        clearInterval(this.exportPollInterval);
                        this.exportPollInterval = null;
                        this.exporting = false;
                        this.exportComplete = true;
                        this.exportProgress = 100;
                    } else if (exp.status === 'failed') {
                        clearInterval(this.exportPollInterval);
                        this.exportPollInterval = null;
                        this.exporting = false;
                        this.exportError = exp.error_message || 'Export failed';
                    }
                } catch (e) {
                    console.error('Export poll failed:', e);
                }
            }, 2000);
        },

        downloadExport() {
            if (!this.exportJobId) return;
            const url = this.routes.exportStart.replace(/\/start$/, '') + '/' + this.exportJobId + '/download';
            window.open(url, '_blank');
        },

        resetExport() {
            this.exportModalOpen = false;
            this.exporting = false;
            this.exportComplete = false;
            this.exportError = null;
            this.exportProgress = 0;
            this.exportJobId = null;
        },

        // =====================================================================
        //  KEYBOARD SHORTCUTS
        // =====================================================================
        handleKeydown(event) {
            // Ignore when typing in inputs
            if (['INPUT', 'TEXTAREA', 'SELECT'].includes(event.target.tagName)) return;

            const key = event.key.toLowerCase();
            const ctrl = event.ctrlKey || event.metaKey;
            const shift = event.shiftKey;

            if (key === ' ') {
                event.preventDefault();
                this.togglePlayback();
            } else if (key === 'delete' || key === 'backspace') {
                event.preventDefault();
                if (this.selectedClipId) this.deleteClip(this.selectedClipId);
            } else if (ctrl && !shift && key === 'z') {
                event.preventDefault();
                this.undo();
            } else if (ctrl && shift && key === 'z') {
                event.preventDefault();
                this.redo();
            } else if (ctrl && key === 's') {
                event.preventDefault();
                this.saveProject();
            } else if (key === 's' && !ctrl) {
                event.preventDefault();
                this.splitClipAtPlayhead();
            } else if (key === 'arrowleft') {
                event.preventDefault();
                this.stepFrame(-1);
            } else if (key === 'arrowright') {
                event.preventDefault();
                this.stepFrame(1);
            } else if (key === 'escape') {
                this.deselectClip();
                this.exportModalOpen = false;
            }
        },

        handleBeforeUnload(event) {
            if (this.hasUnsavedChanges || this.exporting) {
                event.preventDefault();
                event.returnValue = '';
            }
        },

        // =====================================================================
        //  VOLUME
        // =====================================================================
        updateMasterVolume() {
            // Live-update every cached video/audio element so the slider
            // responds without waiting for the next 12 Hz sync tick.
            for (const key in this.videoElements) {
                const el = this.videoElements[key];
                if (el && (el.tagName === 'VIDEO' || el.tagName === 'AUDIO')) {
                    el.muted = this.masterMuted;
                    el.volume = this.masterMuted ? 0 : Math.min(1, this.masterVolume);
                }
            }
        },

        // =====================================================================
        //  TEXT CLIPS
        // =====================================================================
        addTextClip() {
            let textTrack = this.tracks.find(t => t.type === 'text' && !t.locked);
            if (!textTrack) {
                this.addTrack('text');
                textTrack = this.tracks[this.tracks.length - 1];
            }

            this.pushUndo();

            const startTime = this.currentTime;
            const duration = 3000;
            // Stack on the lowest free lane so a second text added at the same
            // playhead lands under the first instead of overlapping it.
            const lane = this.findFreeLane(textTrack, startTime, duration);

            const clip = {
                id: this.uid(),
                text: 'New Text',
                fontFamily: 'Inter',
                fontSize: 48,
                fontWeight: '400',
                color: '#ffffff',
                x: Math.round(this.projectWidth / 2 - 100),
                y: Math.round(this.projectHeight / 2),
                startTime: startTime,
                endTime: startTime + duration,
                lane: lane,
            };

            textTrack.clips.push(clip);
            this.recalcDuration();
            this.markChanged();
            this.selectClip(clip.id, textTrack.id);
        },

        // =====================================================================
        //  HELPERS
        // =====================================================================
        getClipMedia(clip) {
            return this._mediaMap[clip.mediaId] || null;
        },

        formatTimecode(ms) {
            if (ms == null || isNaN(ms)) return '00:00.000';
            const totalSec = Math.floor(ms / 1000);
            const min = Math.floor(totalSec / 60);
            const sec = totalSec % 60;
            const msRem = Math.floor(ms % 1000);
            return String(min).padStart(2, '0') + ':' + String(sec).padStart(2, '0') + '.' + String(msRem).padStart(3, '0');
        },

        formatTimecodeShort(ms) {
            const totalSec = Math.floor(ms / 1000);
            const min = Math.floor(totalSec / 60);
            const sec = totalSec % 60;
            return String(min).padStart(2, '0') + ':' + String(sec).padStart(2, '0');
        },

        formatDuration(ms) {
            const totalSec = Math.floor(ms / 1000);
            const min = Math.floor(totalSec / 60);
            const sec = totalSec % 60;
            return min + ':' + String(sec).padStart(2, '0');
        },

        markChanged() {
            this.hasUnsavedChanges = true;
        },

        uid() {
            return 'id_' + Date.now().toString(36) + '_' + Math.random().toString(36).substr(2, 9);
        },

        async apiRequest(url, method, body = null) {
            const options = {
                method,
                headers: {
                    'X-CSRF-TOKEN': this.csrfToken,
                    'Accept': 'application/json',
                },
            };

            if (body && method !== 'GET') {
                options.headers['Content-Type'] = 'application/json';
                options.body = JSON.stringify(body);
            }

            const response = await fetch(url, options);

            if (!response.ok) {
                if (response.status === 419) {
                    // CSRF token expired — reload page
                    window.location.reload();
                    throw new Error('Session expired');
                }
                // Try to extract JSON error message
                try {
                    const err = await response.clone().json();
                    throw new Error(err.message || `Request failed (${response.status})`);
                } catch (parseErr) {
                    if (parseErr.message && !parseErr.message.includes('Request failed')) {
                        throw parseErr;
                    }
                    throw new Error(`Request failed (${response.status})`);
                }
            }

            return response;
        },

        // =================================================================
        //  CLIENT-SIDE VIDEO THUMBNAIL GENERATION
        // =================================================================

        /** In-memory cache: mediaId → dataURL */
        _thumbCache: {},

        /**
         * Generate a thumbnail for a video media item by grabbing the
         * first visible frame (at 1 s) from a hidden <video> element.
         * Returns a data-URL string or null.  Results are cached.
         */
        generateVideoThumbnail(media) {
            if (!media || media.type !== 'video' || !media.url) return;
            if (media.thumbnail_url) return;           // already has server thumb
            if (this._thumbCache[media.id]) {          // already cached
                media.thumbnail_url = this._thumbCache[media.id];
                return;
            }

            const video = document.createElement('video');
            video.muted = true;
            video.preload = 'metadata';
            // Required for canvas.toDataURL() to work on cross-origin sources (AI CDNs).
            // If the server doesn't return CORS headers the load will fail and we fall
            // back to the server-generated thumbnail (when available).
            video.crossOrigin = 'anonymous';

            const cleanup = () => {
                video.src = '';
                video.load();
            };

            video.addEventListener('loadeddata', () => {
                // Seek to 1 s (or 0 s if shorter)
                video.currentTime = Math.min(1, video.duration || 0);
            }, { once: true });

            video.addEventListener('seeked', () => {
                try {
                    const canvas = document.createElement('canvas');
                    // Small thumb — 320 px wide, keep aspect ratio
                    const scale = 320 / (video.videoWidth || 320);
                    canvas.width = 320;
                    canvas.height = Math.round((video.videoHeight || 180) * scale);
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                    const dataUrl = canvas.toDataURL('image/jpeg', 0.7);
                    this._thumbCache[media.id] = dataUrl;
                    // Reactively update the media item so Alpine re-renders
                    media.thumbnail_url = dataUrl;
                } catch (e) {
                    // CORS / tainted canvas — silently ignore
                }
                cleanup();
            }, { once: true });

            // Clean up on error to prevent orphan video elements
            video.addEventListener('error', cleanup, { once: true });

            // Safety timeout: clean up if events never fire (10s)
            setTimeout(() => {
                if (video.src) cleanup();
            }, 10000);

            video.src = media.url;
        },

        /**
         * Batch-generate thumbnails for all loaded media items that are
         * videos without a thumbnail.  Called after loadMedia / upload.
         */
        generateMissingThumbnails() {
            (this.mediaItems || []).forEach(m => {
                if (m.type === 'video' && !m.thumbnail_url) {
                    this.generateVideoThumbnail(m);
                }
            });
        },

        // =================================================================
        //  PROJECT THUMBNAIL (canvas snapshot on save)
        // =================================================================

        /**
         * Capture a project thumbnail as a JPEG data-URL.
         * 1. Try reading the preview canvas (works for same-origin media).
         * 2. If tainted, return the first media item's existing thumbnail_url
         *    so the server can download and store it.
         * Returns null if nothing can be captured.
         */
        captureProjectThumbnail() {
            try {
                const srcCanvas = this.$refs.previewCanvas;
                if (srcCanvas) {
                    const maxW = 320;
                    const scale = Math.min(1, maxW / (srcCanvas.width || 1));
                    const w = Math.round((srcCanvas.width || 1) * scale);
                    const h = Math.round((srcCanvas.height || 1) * scale);

                    const tmp = document.createElement('canvas');
                    tmp.width = w;
                    tmp.height = h;
                    const ctx = tmp.getContext('2d');
                    ctx.drawImage(srcCanvas, 0, 0, w, h);
                    const dataUrl = tmp.toDataURL('image/jpeg', 0.5);
                    // Reject "all-black" frames (canvas tainted / no media yet / nothing rendered
                    // at currentTime). A blank JPEG compresses to a very small payload — under
                    // ~2KB at this resolution — so anything below that is almost certainly empty.
                    if (dataUrl && dataUrl.length > 2000) {
                        return dataUrl;
                    }
                }
            } catch (e) {
                // Canvas tainted by cross-origin media — fall through to URL fallback
            }

            // Fallback: send the URL of the first visual media in the project so
            // the server can download and store it as the thumbnail. Prefer the
            // first clip on the timeline (matches what the user "sees"), then
            // any project media.
            const fallbackUrl = this._firstMediaThumbnailUrl();
            if (fallbackUrl) {
                return 'url:' + fallbackUrl;
            }

            return null;
        },

        _firstMediaThumbnailUrl() {
            // Walk timeline tracks for the first video/image clip with a media url
            for (const track of this.tracks || []) {
                if (track.type !== 'video' && track.type !== 'image') continue;
                for (const clip of track.clips || []) {
                    const media = this._mediaMap?.[clip.mediaId];
                    if (!media) continue;
                    const url = media.thumbnail_url || media.url;
                    if (url && /^https?:\/\//i.test(url)) return url;
                }
            }
            // Then fall back to any media item in the library
            for (const media of this.mediaItems || []) {
                if (media.type !== 'video' && media.type !== 'image') continue;
                const url = media.thumbnail_url || media.url;
                if (url && /^https?:\/\//i.test(url)) return url;
            }
            return null;
        },

    };
}
