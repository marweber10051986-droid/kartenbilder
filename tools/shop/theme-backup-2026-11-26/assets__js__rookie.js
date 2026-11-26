/* Rookie Business: drop countdown + subtle hero parallax */
(function () {
	'use strict';

	var pad = function (n) { return String(n).padStart(2, '0'); };

	document.querySelectorAll('[data-rk-countdown]').forEach(function (el) {
		var target = new Date(el.getAttribute('data-rk-countdown')).getTime();
		if (isNaN(target)) { return; }
		var units = {};
		el.querySelectorAll('[data-unit]').forEach(function (b) { units[b.getAttribute('data-unit')] = b; });

		var tick = function () {
			var diff = Math.max(0, target - Date.now());
			var s = Math.floor(diff / 1000);
			if (units.d) { units.d.textContent = pad(Math.floor(s / 86400)); }
			if (units.h) { units.h.textContent = pad(Math.floor((s % 86400) / 3600)); }
			if (units.m) { units.m.textContent = pad(Math.floor((s % 3600) / 60)); }
			if (units.s) { units.s.textContent = pad(s % 60); }
			if (diff === 0) {
				el.classList.add('is-live');
				var title = el.querySelector('.rk-drop__title');
				if (title) { title.textContent = 'Der Drop ist live.'; }
				return;
			}
			setTimeout(tick, 1000);
		};
		tick();
	});

	var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	var cards = document.querySelectorAll('.rk-hero .rk-card');
	if (!reduce && cards.length) {
		var ticking = false;
		window.addEventListener('scroll', function () {
			if (ticking) { return; }
			ticking = true;
			requestAnimationFrame(function () {
				var y = window.scrollY;
				cards.forEach(function (c, i) {
					var speed = (i % 2 === 0 ? 0.12 : -0.08) * (1 + (i % 3) * 0.3);
					c.style.setProperty('--rk-shift', (y * speed).toFixed(1) + 'px');
				});
				ticking = false;
			});
		}, { passive: true });
	}
})();
