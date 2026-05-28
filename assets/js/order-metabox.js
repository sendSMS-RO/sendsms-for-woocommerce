/**
 * Order edit page: "Send SMS" metabox.
 *
 * Depends on jQuery and `RosendsmsMetabox` (wp_localize_script).
 */
(function ($) {
	'use strict';

	$(document).ready(function () {
		var $btn = $('#rosendsms-mb-send');
		if (!$btn.length) {
			return;
		}
		var cfg = window.RosendsmsMetabox || {};

		$btn.on('click', function (event) {
			event.preventDefault();
			$btn.html(cfg.sending || 'Sending...').attr('disabled', 'disabled');

			var data = {
				action:   'rosendsms_single',
				security: cfg.nonce,
				order:    $('#rosendsms-order-id').val(),
				phone:    $('#rosendsms-mb-phone').val(),
				content:  $('#rosendsms-mb-content').val(),
				short:    $('#rosendsms-mb-short').is(':checked'),
				gdpr:     $('#rosendsms-mb-gdpr').is(':checked')
			};

			$.post(cfg.ajaxUrl, data, function (response) {
				$btn.html(cfg.send || 'Send').removeAttr('disabled');
				// Leave the phone field alone — it's the order's billing phone, useful for follow-ups.
				$('#rosendsms-mb-content').val('').trigger('input');
				$('#rosendsms-mb-short').prop('checked', false);
				$('#rosendsms-mb-gdpr').prop('checked', false);
				window.alert(response);
			}).fail(function () {
				$btn.html(cfg.send || 'Send').removeAttr('disabled');
				window.alert('Request failed.');
			});
		});
	});
}(window.jQuery));
