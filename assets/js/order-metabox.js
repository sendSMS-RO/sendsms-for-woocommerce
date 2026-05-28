/**
 * Order edit page: "Send SMS" metabox.
 *
 * Depends on jQuery and `SendsmsroMetabox` (wp_localize_script).
 */
(function ($) {
	'use strict';

	$(document).ready(function () {
		var $btn = $('#sendsmsro-mb-send');
		if (!$btn.length) {
			return;
		}
		var cfg = window.SendsmsroMetabox || {};

		$btn.on('click', function (event) {
			event.preventDefault();
			$btn.html(cfg.sending || 'Sending...').attr('disabled', 'disabled');

			var data = {
				action:   'sendsmsro_single',
				security: cfg.nonce,
				order:    $('#sendsmsro-order-id').val(),
				phone:    $('#sendsmsro-mb-phone').val(),
				content:  $('#sendsmsro-mb-content').val(),
				short:    $('#sendsmsro-mb-short').is(':checked'),
				gdpr:     $('#sendsmsro-mb-gdpr').is(':checked')
			};

			$.post(cfg.ajaxUrl, data, function (response) {
				$btn.html(cfg.send || 'Send').removeAttr('disabled');
				// Leave the phone field alone — it's the order's billing phone, useful for follow-ups.
				$('#sendsmsro-mb-content').val('').trigger('input');
				$('#sendsmsro-mb-short').prop('checked', false);
				$('#sendsmsro-mb-gdpr').prop('checked', false);
				window.alert(response);
			}).fail(function () {
				$btn.html(cfg.send || 'Send').removeAttr('disabled');
				window.alert('Request failed.');
			});
		});
	});
}(window.jQuery));
