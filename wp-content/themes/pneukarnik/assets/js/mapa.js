/**
 * Mapa se načte z Google Maps až po kliknutí Zákazníka (do té doby web nic od Googlu nestahuje).
 */
document.querySelectorAll('[data-mapa]').forEach(function (element) {
	var box = /** @type {HTMLElement} */ (element);
	var button = /** @type {HTMLButtonElement} */ (box.querySelector('.mapa__nacist'));
	button.hidden = false;
	button.addEventListener('click', function () {
		var frame = document.createElement('iframe');
		frame.src = box.dataset.mapa || '';
		frame.title = 'Mapa';
		frame.width = '600';
		frame.height = '400';
		frame.loading = 'lazy';
		frame.referrerPolicy = 'no-referrer-when-downgrade';
		frame.setAttribute('allowfullscreen', '');
		box.replaceChildren(frame);
	});
});
