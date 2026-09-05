<script>
	function dropHandler(ev, id) {
		ev.preventDefault();
		const el = document.getElementById(id);
		if (!el) return;
		el.files = ev.dataTransfer.files;
		if (typeof resizeImage === 'function') {
			resizeImage();
		}
		const fileName = ev.dataTransfer.files.length > 1
			? ev.dataTransfer.files.length + ' files selected'
			: ev.dataTransfer.files[0].name;
		const label = el.closest('label');
		if (label) {
			const fileNameEl = label.querySelector('.file-name');
			if (fileNameEl) {
				fileNameEl.textContent = fileName;
			}
		}
	}

	function dragOverHandler(ev) {
		ev.preventDefault();
	}

	function handleFileSelect(id) {
		const el = document.getElementById(id);
		if (!el) return;
		const files = el.files;
		const fileName = files.length > 1
			? files.length + ' files selected'
			: files[0].name;
		const label = el.closest('label');
		if (label) {
			const fileNameEl = label.querySelector('.file-name');
			if (fileNameEl) {
				fileNameEl.textContent = fileName;
			}
		}
	}
</script>
