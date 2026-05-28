/**
 * SMS-length counter shared across the settings, test, campaign, and metabox pages.
 *
 * Attaches to every textarea with class .sendsmsro-content and updates its
 * sibling .sendsmsro-length-counter with an estimated SMS-segment count.
 */
(function () {
	'use strict';

	function approxSmsCount(length) {
		if (length <= 0) {
			return 0;
		}
		if (length % 160 === 0) {
			return length / 160;
		}
		return Math.floor(length / 160) + 1;
	}

	function update(textarea) {
		var counter = textarea.parentNode
			? textarea.parentNode.querySelector('.sendsmsro-length-counter')
			: null;
		if (!counter) {
			return;
		}
		var length = textarea.value.length;
		if (length <= 0) {
			counter.textContent = window.SendsmsroL10n
				? window.SendsmsroL10n.empty
				: 'The field is empty.';
			return;
		}
		var label = window.SendsmsroL10n
			? window.SendsmsroL10n.approx
			: 'The approximate number of messages: ';
		counter.textContent = label + approxSmsCount(length) + ' (' + length + ')';
	}

	function init() {
		var textareas = document.querySelectorAll('.sendsmsro-content');
		for (var i = 0; i < textareas.length; i++) {
			(function (textarea) {
				textarea.addEventListener('input', function () { update(textarea); });
				textarea.addEventListener('change', function () { update(textarea); });
				update(textarea);
			}(textareas[i]));
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
}());
