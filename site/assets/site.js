document.addEventListener('DOMContentLoaded', function () {
	var root = document.documentElement;
	var header = document.querySelector('.site-header');

	function syncHeaderOffset() {
		if (!header) {
			return;
		}

		root.style.setProperty('--header-offset', header.offsetHeight + 'px');
	}

	syncHeaderOffset();
	window.addEventListener('resize', syncHeaderOffset);
});
