/*
 * charcount.js
 *
 * Usage:
 *   $config['additional_javascript'][] = 'js/charcount.js';
 *
 */

document.addEventListener("DOMContentLoaded", () => {
	'use strict';

	const maxChars = max_body;
	const warningThreshold = 100;

	const initializeCountdown = (textareaId, countdownSelector) => {
		const inputArea = document.getElementById(textareaId);
		if (!inputArea) return;

		const countdownElements = document.querySelectorAll(countdownSelector);
		if (!countdownElements) return;

		const updateCountdown = () => {
			const charCount = maxChars - inputArea.value.length;
			countdownElements.forEach(elem => {
				elem.textContent = charCount;

				if (charCount <= warningThreshold) {
					elem.classList.add('warning');
				} else {
					elem.classList.remove('warning');
				}
			});
		};

		updateCountdown();

		inputArea.addEventListener('input', updateCountdown);
		inputArea.addEventListener('selectionchange', updateCountdown);

		inputArea.addEventListener('input', () => {
			if (inputArea.value.length > maxChars) {
				inputArea.value = inputArea.value.substring(0, maxChars);
				updateCountdown();
			}
		});
	};

	initializeCountdown('body', '.countdown');

	const handleQuickReply = () => {
		initializeCountdown('body', '#quick-reply .countdown');
	};

	window.addEventListener('quick-reply', handleQuickReply);

});
