<style>
	[x-cloak] { display: none !important; }
</style>

@if(\App\Helpers\Classes\MarketplaceHelper::isRegistered('content-manager') && setting('content_manager_enabled', '1') === '1')
	{{-- Include the Media Manager Modal Component --}}
	@livewire('media-manager-modal')
	{{-- Media Manager JavaScript --}}
	<script>
		// Make content manager availability global
		window.contentManagerEnabled = true;
		// Store selected media data globally
		window.selectedMediaData = new Map();
		// Global media manager function
		window.openMediaManager = function(targetInput, fileTypes = ['all'], isMultiple = false) {
			if (targetInput) {
				window.currentFileInput = targetInput;
			}
			Livewire.dispatch('openMediaManager', {
				allowedTypes: fileTypes,
				isMultiple: isMultiple,
			});
			return false;
		};
		// Auto-enhance all file inputs when DOM is ready
		document.addEventListener('DOMContentLoaded', function() {
			initializeMediaManagerForFileInputs();
			observeNewFileInputs();
		});

		// Initialize existing file inputs
		function initializeMediaManagerForFileInputs() {
			const fileInputs = document.querySelectorAll('input[type="file"]:not([data-media-manager-enabled])');
			fileInputs.forEach(function(input) {
				enhanceFileInputWithMediaManager(input);
			});
		}

		// Enhance a single file input
		function enhanceFileInputWithMediaManager(input) {
			// Skip if already enhanced or excluded (on input or ancestor)
			if (input.hasAttribute('data-media-manager-enabled') || input.hasAttribute('data-exclude-media-manager') || input.closest('[data-exclude-media-manager]')) {
				return;
			}
			// Skip video inputs in AI Video Pro form (static check, no Alpine timing)
			if (input.closest('[data-ai-video-pro-form]') && input.accept && String(input.accept).includes('video')) {
				return;
			}
			// Mark as enhanced
			input.setAttribute('data-media-manager-enabled', 'true');
			// Store original onclick if exists
			const originalOnClick = input.onclick;
			// Add click handler
			input.addEventListener('click', function(e) {
				e.preventDefault();
				e.stopPropagation();
				// Execute original onclick if it exists
				if (originalOnClick && typeof originalOnClick === 'function') {
					const result = originalOnClick.call(this, e);
					// If original onclick returns false, don't proceed
					if (result === false) {
						return false;
					}
				}
				// Store reference and open media manager
				window.currentFileInput = this;
				const allowedType = this.dataset.mediaType || this.accept;
				const isMultiple = this.multiple || this.hasAttribute('multiple') || this.dataset.multiple === 'true';

				let allowedTypes = [];
				if (allowedType.includes('image') || allowedType.includes('.png') || allowedType.includes('.jpg') || allowedType.includes('.jpeg') || allowedType.includes('.gif')) {
					allowedTypes.push('image');
				}
				if (allowedType.includes('video') || allowedType.includes('.mp4') || allowedType.includes('.webm') || allowedType.includes('.avi')) {
					allowedTypes.push('video');
				}
				if (allowedType.includes('application') || allowedType.includes('file') || allowedType.includes('.pdf') || allowedType.includes('.doc')) {
					allowedTypes.push('file');
				}
				if (allowedTypes.length === 0) allowedTypes = ['all'];
				window.openMediaManager(this, allowedTypes, isMultiple);
				return false;
			});
		}
		// Watch for dynamically added file inputs (defer so Alpine bindings are applied first)
		function observeNewFileInputs() {
			const observer = new MutationObserver(function(mutations) {
				const inputsToEnhance = [];
				mutations.forEach(function(mutation) {
					mutation.addedNodes.forEach(function(node) {
						if (node.nodeType === 1) {
							if (node.matches && node.matches('input[type="file"]')) {
								inputsToEnhance.push(node);
							}
							if (node.querySelectorAll) {
								node.querySelectorAll('input[type="file"]').forEach(function(input) {
									inputsToEnhance.push(input);
								});
							}
						}
					});
				});
				if (inputsToEnhance.length > 0) {
					setTimeout(function() {
						inputsToEnhance.forEach(enhanceFileInputWithMediaManager);
					}, 0);
				}
			});
			observer.observe(document.body, {
				childList: true,
				subtree: true
			});
		}

		// Handle media selection from the modal
		document.addEventListener('livewire:init', function() {
			Livewire.on('mediaSelected', function(eventData) {
				// Extract data from array if needed
				const data = Array.isArray(eventData) ? eventData[0] : eventData;
				if (window.currentFileInput && data && data.items) {
					// Convert items to array if it's an object
					let items = data.items;
					if (typeof items === 'object' && !Array.isArray(items)) {
						items = Object.values(items);
					}
					// Store the selected media data
					const inputKey = window.currentFileInput.name || 'default';
					window.selectedMediaData.set(inputKey, items);
					// Create File objects and update input.files
					const input = window.currentFileInput;
					cleanupClonedInputs(input);
					Promise.all(
						items.map(async (item, index) => {
							try {
								const response = await fetch(item.url);
								const blob = await response.blob();

								const file = new File([blob], item.title || `file-${index}`, {
									type: blob.type,
									lastModified: Date.now()
								});

								return file;
							} catch (error) {
								console.error(`❌ Failed to load file from URL: ${item.url}`, error);
								return null;
							}
						})
					).then((downloadedFiles) => {
						const files = downloadedFiles.filter(file => file instanceof File);
						if (files.length === 0) {
							return;
						}

						// Set files on the original input
						const dataTransfer = new DataTransfer();
						files.forEach(file => dataTransfer.items.add(file));
						input.files = dataTransfer.files;

						// Fallback for environments where programmatic input.files assignment is blocked.
						if ((input.files?.length || 0) !== files.length) {
							createHiddenInputsForSelectedMedia(input, files);
						}

						// Trigger change and input events
						input.dispatchEvent(new Event('change', { bubbles: true }));
						input.dispatchEvent(new Event('input', { bubbles: true }));
					});

					// Dispatch custom event
					const event = new CustomEvent('mediaManagerSelection', {
						detail: {
							input: input,
							selectedItems: items,
							type: data.type
						}
					});
					input.dispatchEvent(event);
					
					window.dispatchEvent(new CustomEvent('presentationAttachmentSelected', {
    detail: items
}));

					// Clear reference
					window.currentFileInput = null;
				} else {
					console.warn('⚠️ Media selection event missing required data:', {
						hasCurrentInput: !!window.currentFileInput,
						hasData: !!data,
						hasItems: !!(data && data.items),
						dataStructure: data
					});
				}
			});
		});

		function cleanupClonedInputs(input) {
			// Remove existing _media_manager[] hidden inputs if any
			const existingHiddenInputs = input.parentNode.querySelectorAll(`input[name="${input.name}_media_manager[]"]`);
			existingHiddenInputs.forEach(hidden => hidden.remove());

			// Clean up previously injected file inputs
			const oldClones = input.form?.querySelectorAll(`input[type="file"][data-cloned="true"]`) || [];
			oldClones.forEach(el => el.remove());
		}

		// Create hidden cloned file inputs only as a compatibility fallback.
		function createHiddenInputsForSelectedMedia(input, files) {
			cleanupClonedInputs(input);
			files.forEach(file => {
				const fakeInput = document.createElement('input');
				fakeInput.type = 'file';
				fakeInput.name = input.name;
				fakeInput.setAttribute('data-cloned', 'true');
				fakeInput.style.display = 'none';

				const dt = new DataTransfer();
				dt.items.add(file);
				fakeInput.files = dt.files;

				if (input.form) {
					input.form.appendChild(fakeInput);
				} else {
					input.parentNode.appendChild(fakeInput);
				}
			});
		}

		// Add form submission handler to log what's being sent
		document.addEventListener('submit', function(e) {
			const form = e.target;
			const formData = new FormData(form);
			for (let [key, value] of formData.entries()) {
				if (value instanceof File) {
					// console.log(`  ${key}: [File] ${value.name} (${value.size} bytes)`);
				} else {
					// console.log(`  ${key}: ${value}`);
				}
			}

			// Check for media manager data
			const mediaManagerInputs = form.querySelectorAll('input[name*="_media_manager"]');
			if (mediaManagerInputs.length > 0) {
				mediaManagerInputs.forEach(input => {
					try {
						const data = JSON.parse(input.value);
						// console.log(`  ${input.name}:`, data);
					} catch (e) {
						// console.log(`  ${input.name}: ${input.value}`);
					}
				});
			}
		});
	</script>
@else
	{{-- Content manager not registered - use regular file inputs --}}
	<script>
		window.contentManagerEnabled = false;
		window.openMediaManager = function() {
			return true;
		};
	</script>
@endif
