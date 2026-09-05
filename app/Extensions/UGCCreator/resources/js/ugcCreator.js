document.addEventListener('alpine:init', () => {
	Alpine.data('ugcCreator', (initial = {}) => ({
		isDemo: !!initial.isDemo,
		csrf: initial.csrf || '',

		endpoints: {
			videosStore: initial.endpoints?.videosStore || '',
			ugcStudio: initial.endpoints?.ugcStudio || '',
			aiScript: initial.endpoints?.aiScript || '',
		},

		i18n: {
			demoDisabled: initial.i18n?.demoDisabled || 'This feature is disabled in demo mode.',
			uploadFailed: initial.i18n?.uploadFailed || 'Could not upload the image. Please try again.',
			generateFailed: initial.i18n?.generateFailed || 'Could not start the video generation. Please try again.',
			detailsRequired: initial.i18n?.detailsRequired || 'Please describe the video before generating.',
			referenceRequired: initial.i18n?.referenceRequired || 'Please upload at least one product or character image, or pick a camera preset.',
			detailsSeedRequired: initial.i18n?.detailsSeedRequired || 'Type a short description first so the AI can improve it.',
			detailsImproveFailed: initial.i18n?.detailsImproveFailed || 'Could not improve the description. Please try again.',
		},

		cameraPresets: initial.cameraPresets || [],
		models: initial.models || [],
		defaultModel: initial.defaultModel || '',
		maxUploadMb: Number(initial.maxUploadMb) > 0 ? Number(initial.maxUploadMb) : 5,

		productAssets: [],
		characterAsset: null,
		uploadingProduct: false,
		uploadingCharacter: false,
		dragOverProduct: false,
		dragOverCharacter: false,
		_localIdCounter: 0,

		form: {
			videoDetails: '',
			model: initial.defaultModel || '',
			cameraPreset: 'auto',
			audioEnabled: true,
		},

		submitting: false,
		detailsImproving: false,

		init() {
			if (!this.form.model) {
				this.form.model = this.models?.[0]?.value || '';
			}
		},

		notify(type, message) {
			if (!message) return;
			if (typeof window.toastr !== 'undefined' && typeof window.toastr[type] === 'function') {
				window.toastr[type](message);
			}
		},

		get currentModelMaxRefs() {
			const model = this.models?.find((m) => m.value === this.form.model);
			return Math.max(1, Number(model?.max_refs || 1));
		},

		get currentRefCount() {
			return this.productAssets.length + (this.characterAsset ? 1 : 0);
		},

		get currentModelSupportsAudio() {
			const model = this.models?.find((m) => m.value === this.form.model);
			return !!model?.supports_audio;
		},

		// ----- Local file selection (uploaded only on Generate) -----
		selectAsset(kind, event) {
			if (this.isDemo) {
				this.notify('warning', this.i18n.demoDisabled);
				return;
			}

			// Droparea fires a custom event with { detail: { file } } or a native input change.
			let files = event?.detail?.file
				? [event.detail.file]
				: Array.from(event?.target?.files || []);
			if (files.length === 0) return;

			const allowedTypes = ['image/png', 'image/jpeg', 'image/webp'];
			const badType = files.find((f) => !allowedTypes.includes(f.type));
			if (badType) {
				this.notify('error', `${badType.name}: only PNG, JPG, or WEBP images are allowed.`);
				if (event?.target) event.target.value = '';
				return;
			}

			const oversize = files.find((f) => f.size > this.maxUploadMb * 1024 * 1024);
			if (oversize) {
				this.notify('error', `${oversize.name}: file is larger than ${this.maxUploadMb}MB.`);
				if (event?.target) event.target.value = '';
				return;
			}

			if (kind === 'product') {
				const max = this.currentModelMaxRefs;
				const remaining = max - this.currentRefCount;
				if (remaining <= 0) {
					this.notify('warning', `This model accepts up to ${max} reference image${max === 1 ? '' : 's'}.`);
					if (event?.target) event.target.value = '';
					return;
				}
				if (files.length > remaining) {
					files = files.slice(0, remaining);
				}

				for (const file of files) {
					this.productAssets.push(this._wrapFile(file));
				}
			} else {
				if (this.characterAsset) {
					this._revokeAsset(this.characterAsset);
				}
				this.characterAsset = this._wrapFile(files[0]);
			}

			if (event?.target) event.target.value = '';
		},

		removeAsset(kind, assetId = null) {
			if (kind === 'product') {
				const asset = assetId
					? this.productAssets.find((a) => a.id === assetId)
					: this.productAssets[0];
				if (!asset) return;
				this._revokeAsset(asset);
				this.productAssets = this.productAssets.filter((a) => a.id !== asset.id);
			} else {
				if (!this.characterAsset) return;
				this._revokeAsset(this.characterAsset);
				this.characterAsset = null;
			}
		},

		_wrapFile(file) {
			this._localIdCounter += 1;
			return {
				id: `local-${this._localIdCounter}`,
				file,
				image_url: URL.createObjectURL(file),
			};
		},

		_revokeAsset(asset) {
			if (asset?.image_url?.startsWith?.('blob:')) {
				try {
					URL.revokeObjectURL(asset.image_url);
				} catch (_) {
					// ignore
				}
			}
		},

		// ----- AI-improve Video Details (sparkle) -----
		async improveDetails() {
			if (this.detailsImproving) return;

			const seed = (this.form.videoDetails || '').trim();
			if (!seed) {
				this.notify('warning', this.i18n.detailsSeedRequired);
				return;
			}

			this.detailsImproving = true;

			const formData = new FormData();
			formData.append('_token', this.csrf);
			formData.append(
				'prompt',
				'Rewrite the description below into a vivid UGC script for an AI video generator. Keep the same language. Structure it so the model can perform it directly, and include ONLY the following four elements: Spoken dialogue (what the character says on-camera, in quotes), Voiceover text (off-camera narration, clearly labeled), Character actions (specific physical movements and interactions with the product), and Facial expressions (emotion and micro-expressions tied to the dialogue). Do not describe the setting, background, or camera — those are handled separately. Keep it natural and authentic to UGC style. Output plain text only, no markdown.',
			);
			formData.append('content', seed);

			try {
				const res = await fetch(this.endpoints.aiScript, {
					method: 'POST',
					headers: { Accept: 'application/json', 'X-CSRF-TOKEN': this.csrf },
					body: formData,
				});
				const data = await res.json().catch(() => ({}));

				if (!res.ok || !data.result) {
					this.notify('error', data.message || this.i18n.detailsImproveFailed);
					return;
				}

				this.form.videoDetails = data.result;
			} catch (err) {
				this.notify('error', err?.message || this.i18n.detailsImproveFailed);
			} finally {
				this.detailsImproving = false;
			}
		},

		// ----- Submit -----
		async submitVideo() {
			if (this.submitting) return;

			if (this.isDemo) {
				this.notify('warning', this.i18n.demoDisabled);
				return;
			}
			if (!this.form.videoDetails.trim()) {
				this.notify('warning', this.i18n.detailsRequired);
				return;
			}
			// fal.ai needs a real anchor image: either a user upload or a
			// non-auto camera preset whose thumbnail seeds the first frame.
			const preset = (this.form.cameraPreset || 'auto').trim();
			if (this.currentRefCount === 0 && (preset === '' || preset === 'auto')) {
				this.notify('warning', this.i18n.referenceRequired);
				return;
			}

			this.submitting = true;
			try {
				const fd = new FormData();
				fd.append('_token', this.csrf);
				fd.append('video_details', this.form.videoDetails);
				fd.append('model', this.form.model);
				fd.append('camera_preset', this.form.cameraPreset || 'auto');
				fd.append('audio_enabled', this.currentModelSupportsAudio && this.form.audioEnabled ? '1' : '0');
				this.productAssets.forEach((a) => fd.append('product_files[]', a.file, a.file.name));
				if (this.characterAsset) {
					fd.append('character_file', this.characterAsset.file, this.characterAsset.file.name);
				}

				const res = await fetch(this.endpoints.videosStore, {
					method: 'POST',
					headers: { Accept: 'application/json' },
					body: fd,
				});
				const json = await res.json().catch(() => ({}));
				if (!res.ok) throw new Error(json?.error || this.i18n.generateFailed);

				const redirect = json?.redirect_to || this.endpoints.ugcStudio;
				if (redirect) {
					window.location.href = redirect;
					// Keep the button locked while the browser navigates away
					// so a rapid second click can't fire another submission.
					return;
				}
				this.submitting = false;
			} catch (e) {
				this.notify('error', e?.message || this.i18n.generateFailed);
				this.submitting = false;
			}
		},
	}));
});
