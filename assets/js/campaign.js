/**
 * Campaign page: send + estimate.
 *
 * Depends on jQuery (for the wc-enhanced-select integration) and the
 * `RosendsmsCampaign` global injected via wp_localize_script.
 */
(function ($) {
	'use strict';

	$(document).ready(function () {
		var $form     = $('#rosendsms-campaign-form');
		var $sendBtn  = $('#rosendsms-campaign-send');
		var $estBtn   = $('#rosendsms-campaign-estimate');
		var $message  = $('#rosendsms-campaign-message');
		var $allFlag  = $('#rosendsms-send-to-all');
		var $phones   = $('#rosendsms-phones');
		var cfg       = window.RosendsmsCampaign || {};

		$form.on('submit', function (event) {
			event.preventDefault();
			send();
		});

		$sendBtn.on('click', function (event) {
			event.preventDefault();
			send();
		});

		$estBtn.on('click', function (event) {
			event.preventDefault();
			estimate();
		});

		function send() {
			$sendBtn.html(cfg.sending || 'Sending...').attr('disabled', 'disabled');

			var sendToAll = $allFlag.is(':checked');
			var phones    = '';
			if (!sendToAll) {
				phones = ($phones.val() || []).join('|');
			}

			var data = $.extend(
				{
					action:   'rosendsms_campaign',
					security: cfg.nonce,
					all:      sendToAll ? 'true' : 'false',
					phones:   phones,
					content:  $message.val()
				},
				sendToAll ? cfg.getQuery : {}
			);

			$.post(cfg.ajaxUrl, data, function (response) {
				$sendBtn.html(cfg.send || 'Send').removeAttr('disabled');
				window.alert(response);
			}).fail(function () {
				$sendBtn.html(cfg.send || 'Send').removeAttr('disabled');
				window.alert('Request failed.');
			});
		}

		function estimate() {
			var sendToAll = $allFlag.is(':checked');
			var phoneCount = sendToAll
				? $phones.find('option').length
				: ($phones.val() || []).length;

			var length = ($message.val() || '').length;
			if (length <= 0) {
				window.alert(cfg.fillMessage || 'Please write a message first.');
				return;
			}
			var price = parseFloat(cfg.pricePerSms || 0);
			if (price <= 0) {
				window.alert(cfg.sendMessage || 'Send a test SMS first to populate the price cache.');
				return;
			}
			var segments = (length % 160 === 0) ? length / 160 : Math.floor(length / 160) + 1;
			var total    = (segments * price * phoneCount).toPrecision(4);
			window.alert(
				(cfg.estimateLabel || 'Estimated price: ') + total + (cfg.estimateNote || '')
			);
		}
	});
}(window.jQuery));
