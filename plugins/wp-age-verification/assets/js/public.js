/* global EuroComplyAV */
(function () {
	'use strict';

	if (typeof EuroComplyAV === 'undefined') {
		return;
	}

	document.addEventListener('DOMContentLoaded', function () {
		var overlay = document.querySelector('.eurocomply-av-overlay');
		if (!overlay) {
			return;
		}
		var form = overlay.querySelector('.eurocomply-av-form');
		var status = overlay.querySelector('.eurocomply-av-status');
		var leaveBtn = overlay.querySelector('.eurocomply-av-leave');

		document.documentElement.style.overflow = 'hidden';

		if (leaveBtn) {
			leaveBtn.addEventListener('click', function () {
				window.location.href = 'about:blank';
			});
		}

		if (!form) {
			return;
		}

		form.addEventListener('submit', function (ev) {
			ev.preventDefault();
			status.textContent = '';
			status.classList.remove('is-error', 'is-success');

			var body = new URLSearchParams();
			body.append('action', EuroComplyAV.action);
			body.append('nonce', EuroComplyAV.nonce);

			var dobField = form.querySelector('input[name="dob"]');
			var confirmField = form.querySelector('input[name="confirm"]');
			if (dobField) {
				body.append('dob', dobField.value);
				body.append('method', 'dob');
			} else if (confirmField) {
				body.append('confirm', confirmField.checked ? '1' : '0');
				body.append('method', 'checkbox');
			}

			fetch(EuroComplyAV.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
				body: body.toString()
			}).then(function (res) {
				return res.json().then(function (data) { return { ok: res.ok, data: data }; });
			}).then(function (result) {
				if (result.ok && result.data && result.data.success) {
					status.textContent = (result.data.data && result.data.data.message) || 'Verified.';
					status.classList.add('is-success');
					overlay.classList.add('is-hidden');
					overlay.style.display = 'none';
					document.documentElement.style.overflow = '';
				} else {
					var msg = (result.data && result.data.data && result.data.data.message) || 'Verification failed.';
					var redirectUrl = result.data && result.data.data && result.data.data.redirect_url;
					status.textContent = msg;
					status.classList.add('is-error');
					if (redirectUrl) {
						setTimeout(function () { window.location.href = redirectUrl; }, 1500);
					}
				}
			}).catch(function () {
				status.textContent = 'Network error — please try again.';
				status.classList.add('is-error');
			});
		});
	});
})();
