/**
 * Order edit page: "Send SMS" metabox.
 *
 * Depends on jQuery and `SendSmsFwcMetabox` (wp_localize_script).
 */
(function ($) {
	'use strict';

	$(document).ready(function () {
		var $btn = $('#sendsms-fwc-mb-send');
		if (!$btn.length) {
			return;
		}
		var cfg = window.SendSmsFwcMetabox || {};

		$btn.on('click', function (event) {
			event.preventDefault();
			$btn.html(cfg.sending || 'Sending...').attr('disabled', 'disabled');

			var data = {
				action:   'wc_sendsms_single',
				security: cfg.nonce,
				order:    $('#sendsms-fwc-order-id').val(),
				phone:    $('#sendsms-fwc-mb-phone').val(),
				content:  $('#sendsms-fwc-mb-content').val(),
				short:    $('#sendsms-fwc-mb-short').is(':checked'),
				gdpr:     $('#sendsms-fwc-mb-gdpr').is(':checked')
			};

			$.post(cfg.ajaxUrl, data, function (response) {
				$btn.html(cfg.send || 'Send').removeAttr('disabled');
				// Leave the phone field alone — it's the order's billing phone, useful for follow-ups.
				$('#sendsms-fwc-mb-content').val('').trigger('input');
				$('#sendsms-fwc-mb-short').prop('checked', false);
				$('#sendsms-fwc-mb-gdpr').prop('checked', false);
				window.alert(response);
			}).fail(function () {
				$btn.html(cfg.send || 'Send').removeAttr('disabled');
				window.alert('Request failed.');
			});
		});
	});
}(window.jQuery));
