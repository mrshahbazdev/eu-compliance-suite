/**
 * EuroComply EU VAT — WooCommerce checkout validator.
 *
 * Hits POST /wp-json/eurocomply-vat/v1/validate with the X-WP-Nonce header on
 * blur of the VAT-number field, shows an inline badge, and triggers a standard
 * WooCommerce "update_checkout" so the reverse-charge tax recalc runs.
 */
(function ($) {
	'use strict';

	if (typeof window.EuroComplyVAT === 'undefined') {
		return;
	}

	var debounceTimer = null;
	var lastChecked = '';

	function normalise(value) {
		return String(value || '')
			.toUpperCase()
			.replace(/[\s\.\-]+/g, '');
	}

	function feedback($input) {
		var $wrap = $input.parent();
		var $f = $wrap.find('.eurocomply-vat-feedback');
		if (!$f.length) {
			$f = $('<small class="eurocomply-vat-feedback"></small>').appendTo($wrap);
		}
		return $f;
	}

	function setState($input, cls, text) {
		var $f = feedback($input);
		$f.removeClass('is-checking is-valid is-invalid is-error')
			.addClass(cls)
			.text(text);
	}

	function check($input) {
		var raw = normalise($input.val());
		if (!raw || raw === lastChecked) {
			return;
		}
		if (raw.length < 4) {
			setState($input, 'is-invalid', window.EuroComplyVAT.i18n.invalid);
			return;
		}
		lastChecked = raw;
		setState($input, 'is-checking', window.EuroComplyVAT.i18n.checking);

		$.ajax({
			url: window.EuroComplyVAT.rest,
			method: 'POST',
			data: { vat: raw },
			headers: { 'X-WP-Nonce': window.EuroComplyVAT.nonce },
			timeout: 12000
		})
			.done(function (res) {
				if (res && res.valid) {
					setState(
						$input,
						'is-valid',
						window.EuroComplyVAT.i18n.valid +
							(res.name ? ' — ' + res.name : '')
					);
					$(document.body).trigger('update_checkout');
				} else {
					setState($input, 'is-invalid', window.EuroComplyVAT.i18n.invalid);
				}
			})
			.fail(function () {
				setState($input, 'is-error', window.EuroComplyVAT.i18n.error);
			});
	}

	$(document).on(
		'blur change',
		'input[name="billing_eurocomply_vat"]',
		function () {
			var $input = $(this);
			window.clearTimeout(debounceTimer);
			debounceTimer = window.setTimeout(function () {
				check($input);
			}, 300);
		}
	);
})(jQuery);
