(function ($) {
	'use strict';
	$(function () {
		// Toggle country-specific help rows in the future.
		// Kept minimal for 0.1.0 — form is server-rendered and form-table styled.
		$('select#ec-country').on('change', function () {
			var val = $(this).val();
			$('.eurocomply-wrap').attr('data-country', val || '');
		}).trigger('change');
	});
})(jQuery);
