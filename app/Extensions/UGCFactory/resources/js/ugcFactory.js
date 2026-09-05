document.addEventListener('alpine:init', () => {
	Alpine.data('ugcFactory', (initial = {}) => ({
		isDemo: !!initial.isDemo,
		csrf: initial.csrf || '',

		// Endpoints (resolved server-side)
		endpoints: {
			actors: initial.endpoints?.actors || '',
			actorsUpload: initial.endpoints?.actorsUpload || '',
			actorsGenerate: initial.endpoints?.actorsGenerate || '',
			actorsStatus: initial.endpoints?.actorsStatus || '',
			actorsDestroy: initial.endpoints?.actorsDestroy || '',
			videosStore: initial.endpoints?.videosStore || '',
			ugcStudio: initial.endpoints?.ugcStudio || '',
			aiScript: initial.endpoints?.aiScript || '',
		},

		i18n: {
			scriptSeedRequired: initial.i18n?.scriptSeedRequired || 'Type a short seed first so the AI can refine it.',
			scriptGenerationFailed: initial.i18n?.scriptGenerationFailed || 'Could not generate the script. Please try again.',
		},

		// Tabs / mode state
		audioSource: 'tts', // tts | upload | record

		// Actor selection
		presets: initial.presets || [],
		userActors: initial.userActors || [],
		selectedActor: null, // { type, id, presetKey, name, imageUrl }

		// Voice picker
		voiceProvider: initial.voiceProvider || 'openai',
		voiceLanguages: initial.voiceLanguages || [],
		voiceOptions: initial.voiceOptions || [],
		previewingVoice: '',
		previewAudio: null,

		// Script
		script: '',
		scriptGeneration: false,

		// Audio file state
		audioFile: null,
		uploadedAudioName: '',

		// Title
		title: '',

		// Form state
		submitting: false,
		errorMessage: '',

		// Modal state (kept compatible with existing markup)
		createModal: {
			el: null,
			modalOpen: false,
			activeTab: 'upload',
			uploadName: '',
			uploadFile: null,
			generateName: '',
			generatePrompt: '',
			submittingUpload: false,
			submittingGenerate: false,
			modalError: '',
		},

		// Actor status polling
		_actorPollTimer: null,

		init() {
			this.scheduleActorPoll();
		},

		// ----- Actor polling -----
		scheduleActorPoll() {
			if (this._actorPollTimer) clearTimeout(this._actorPollTimer);
			const hasPending = this.userActors.some((a) => a.status === 'pending');
			if (!hasPending) return;
			this._actorPollTimer = setTimeout(() => this.pollPendingActors(), 4000);
		},

		async pollPendingActors() {
			const pending = this.userActors.filter((a) => a.status === 'pending');
			await Promise.all(pending.map((a) => this.refreshActor(a)));
			this.scheduleActorPoll();
		},

		async refreshActor(actor) {
			if (!this.endpoints.actorsStatus) return;
			const url = this.endpoints.actorsStatus.replace('__ID__', String(actor.id));
			try {
				const res = await fetch(url, { headers: { Accept: 'application/json' } });
				if (!res.ok) return;
				const json = await res.json();
				const data = json?.data;
				if (!data) return;
				const idx = this.userActors.findIndex((a) => a.id === actor.id);
				if (idx === -1) return;
				this.userActors[idx] = { ...this.userActors[idx], ...data };
			} catch (_) {
				// next tick will retry
			}
		},

		// selectbox tracks its own selection; nothing to bootstrap here

		// ----- Actor selection -----
		selectPresetActor(preset) {
			this.errorMessage = '';
			this.selectedActor = {
				type: 'preset',
				id: 0,
				presetKey: preset.preset_key,
				name: preset.label,
				imageUrl: preset.preview_image,
			};
		},

		selectUserActor(actor) {
			if (actor.status !== 'ready') {
				return;
			}
			this.errorMessage = '';
			this.selectedActor = {
				type: actor.type,
				id: actor.id,
				presetKey: null,
				name: actor.name,
				imageUrl: actor.image_url,
			};
		},

		isActorSelected(type, id, presetKey) {
			if (!this.selectedActor) return false;
			if (type === 'preset') {
				return this.selectedActor.type === 'preset' && this.selectedActor.presetKey === presetKey;
			}
			return this.selectedActor.type === type && this.selectedActor.id === id;
		},

		// ----- Voice preview (selectbox handles selection itself) -----
		previewVoice(voiceId) {
			const found = this.voiceOptions.find((v) => v.value === voiceId);
			if (!found?.preview_url) return;
			if (this.previewAudio) {
				this.previewAudio.pause();
				this.previewAudio = null;
			}
			if (this.previewingVoice === voiceId) {
				this.previewingVoice = '';
				return;
			}
			this.previewAudio = new Audio(found.preview_url);
			this.previewAudio.addEventListener('ended', () => {
				this.previewingVoice = '';
			});
			this.previewingVoice = voiceId;
			this.previewAudio.play().catch(() => {
				this.previewingVoice = '';
			});
		},

		// ----- Script AI generation -----
		async generateAIScript() {
			if (this.scriptGeneration) return;

			const seed = (this.script || '').trim();
			if (!seed) {
				if (typeof window.toastr !== 'undefined') {
					window.toastr.warning(this.i18n.scriptSeedRequired);
				} else {
					this.errorMessage = this.i18n.scriptSeedRequired;
				}
				return;
			}

			this.scriptGeneration = true;
			this.errorMessage = '';

			const formData = new FormData();
			formData.append('_token', this.csrf);
			formData.append(
				'prompt',
				'Rewrite the script below as a short, natural, conversational UGC voiceover (max 3 sentences). Keep the same language as the input.',
			);
			formData.append('content', seed);

			try {
				const response = await fetch(this.endpoints.aiScript, {
					method: 'POST',
					headers: {
						Accept: 'application/json',
						'X-CSRF-TOKEN': this.csrf,
					},
					body: formData,
				});

				const data = await response.json().catch(() => ({}));

				if (!response.ok || !data.result) {
					const message = data.message || this.i18n.scriptGenerationFailed;
					if (typeof window.toastr !== 'undefined') {
						window.toastr.error(message);
					} else {
						this.errorMessage = message;
					}
					return;
				}

				this.script = data.result;
			} catch (err) {
				const message = err?.message || this.i18n.scriptGenerationFailed;
				if (typeof window.toastr !== 'undefined') {
					window.toastr.error(message);
				} else {
					this.errorMessage = message;
				}
			} finally {
				this.scriptGeneration = false;
			}
		},

		// ----- Audio file pickers -----
		captureAudioFile(event) {
			const file = event?.target?.files?.[0] || null;
			this.audioFile = file;
			this.uploadedAudioName = file?.name || '';
		},

		// ----- Submit video -----
		async submitVideo(event) {
			this.errorMessage = '';

			if (this.isDemo) {
				this.errorMessage = 'Video generation is disabled in demo mode.';
				return;
			}
			if (!this.selectedActor) {
				this.errorMessage = 'Please pick an actor first.';
				return;
			}

			const form = event?.target instanceof HTMLFormElement ? event.target : null;

			const formData = new FormData();
			formData.append('_token', this.csrf);
			formData.append('title', this.title || 'Untitled UGC');
			formData.append('actor_id', String(this.selectedActor.id));
			formData.append('actor_type', this.selectedActor.type);
			if (this.selectedActor.presetKey) {
				formData.append('preset_key', this.selectedActor.presetKey);
			}
			formData.append('audio_source', this.audioSource);

			if (this.audioSource === 'tts') {
				if (!this.script.trim()) {
					this.errorMessage = 'Please write a script.';
					return;
				}

				const voiceId = form?.querySelector('input[name="ugc_factory_voice"]')?.value || '';
				const voiceLanguage = form?.querySelector('input[name="ugc_factory_voice_language"]')?.value || '';

				if (!voiceId) {
					this.errorMessage = 'Please pick a voice.';
					return;
				}

				formData.append('script', this.script);
				formData.append('voice_provider', this.voiceProvider);
				formData.append('voice_id', voiceId);
				if (voiceLanguage) {
					formData.append('voice_language', voiceLanguage);
				}
			} else {
				if (!this.audioFile) {
					this.errorMessage = 'Please attach an audio file.';
					return;
				}
				formData.append('audio', this.audioFile);
			}

			this.submitting = true;
			try {
				const response = await fetch(this.endpoints.videosStore, {
					method: 'POST',
					headers: {
						Accept: 'application/json',
						'X-CSRF-TOKEN': this.csrf,
					},
					body: formData,
				});

				const data = await response.json().catch(() => ({}));

				if (!response.ok) {
					this.errorMessage = data.error || 'Could not start video generation.';
					return;
				}

				if (data.redirect_to) {
					window.location.href = data.redirect_to;
				}
			} catch (err) {
				this.errorMessage = err?.message || 'Network error.';
			} finally {
				this.submitting = false;
			}
		},

		// ----- Modal: upload actor -----
		captureUploadFile(event) {
			this.createModal.uploadFile = event?.target?.files?.[0] || null;
		},

		async submitActorUpload() {
			if (this.isDemo) {
				this.createModal.modalError = 'This feature is disabled in demo mode.';
				return;
			}
			if (!this.createModal.uploadFile) {
				this.createModal.modalError = 'Please choose an image.';
				return;
			}
			if (!this.createModal.uploadName.trim()) {
				this.createModal.modalError = 'Please enter a name.';
				return;
			}

			const fd = new FormData();
			fd.append('_token', this.csrf);
			fd.append('name', this.createModal.uploadName);
			fd.append('image', this.createModal.uploadFile);

			this.createModal.submittingUpload = true;
			this.createModal.modalError = '';
			try {
				const response = await fetch(this.endpoints.actorsUpload, {
					method: 'POST',
					headers: { Accept: 'application/json', 'X-CSRF-TOKEN': this.csrf },
					body: fd,
				});
				const data = await response.json().catch(() => ({}));
				if (!response.ok) {
					this.createModal.modalError = data.error || 'Upload failed.';
					return;
				}
				this.userActors.unshift(data.data);
				this.createModal.uploadName = '';
				this.createModal.uploadFile = null;
				this.toggleCreateModal(false);
			} catch (err) {
				this.createModal.modalError = err?.message || 'Network error.';
			} finally {
				this.createModal.submittingUpload = false;
			}
		},

		// ----- Modal: generate actor -----
		async submitActorGenerate() {
			if (this.isDemo) {
				this.createModal.modalError = 'This feature is disabled in demo mode.';
				return;
			}
			if (!this.createModal.generatePrompt.trim()) {
				this.createModal.modalError = 'Please describe the actor.';
				return;
			}
			if (!this.createModal.generateName.trim()) {
				this.createModal.modalError = 'Please enter a name.';
				return;
			}

			this.createModal.submittingGenerate = true;
			this.createModal.modalError = '';
			try {
				const response = await fetch(this.endpoints.actorsGenerate, {
					method: 'POST',
					headers: {
						Accept: 'application/json',
						'Content-Type': 'application/json',
						'X-CSRF-TOKEN': this.csrf,
					},
					body: JSON.stringify({
						_token: this.csrf,
						name: this.createModal.generateName,
						prompt: this.createModal.generatePrompt,
					}),
				});
				const data = await response.json().catch(() => ({}));
				if (!response.ok) {
					this.createModal.modalError = data.error || 'Could not start generation.';
					return;
				}
				this.userActors.unshift(data.data);
				this.createModal.generateName = '';
				this.createModal.generatePrompt = '';
				this.toggleCreateModal(false);
				this.scheduleActorPoll();
			} catch (err) {
				this.createModal.modalError = err?.message || 'Network error.';
			} finally {
				this.createModal.submittingGenerate = false;
			}
		},

		async deleteActor(actor) {
			if (this.isDemo) return;
			if (!confirm('Delete this actor?')) return;
			const url = this.endpoints.actorsDestroy.replace('__ID__', String(actor.id));
			try {
				const response = await fetch(url, {
					method: 'DELETE',
					headers: { Accept: 'application/json', 'X-CSRF-TOKEN': this.csrf },
				});
				if (response.ok) {
					this.userActors = this.userActors.filter((a) => a.id !== actor.id);
					if (this.selectedActor?.type === actor.type && this.selectedActor?.id === actor.id) {
						this.selectedActor = null;
					}
				}
			} catch (err) {
				// swallow — non-fatal
			}
		},

		// ----- Modal helpers (existing API) -----
		getModalEl() {
			if (!this.createModal.el) {
				this.createModal.el = document.querySelector('#ugc-factory-create-modal');
			}
			return this.createModal.el;
		},

		toggleCreateModal(state, tab) {
			const modalEl = this.getModalEl();
			if (modalEl) {
				const modalData = Alpine.$data(modalEl);
				modalData.modalOpen = state;
			}
			this.createModal.activeTab = tab ?? this.createModal.activeTab ?? 'upload';
			this.createModal.modalOpen = state;
			if (!state) {
				this.createModal.modalError = '';
			}
		},
	}));
});
