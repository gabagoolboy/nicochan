/*
 * local-time.js
 * https://github.com/savetheinternet/Tinyboard/blob/master/js/local-time.js
 *
 * Released under the MIT license
 * Copyright (c) 2012 Michael Save <savetheinternet@tinyboard.org>
 * Copyright (c) 2013-2014 Marcin Łabanowski <marcin@6irc.net>
 * Copyright (c) 2024 Perdedora <weav@anche.no>
 *
 * Usage:
 *   $config['additional_javascript'][] = 'js/local-time.js';
 *
 */
document.addEventListener("DOMContentLoaded", () => {
	'use strict';

	const iso8601 = (s) => {
    	return new Date(s.replace(/\.\d{3}/, '')
        	.replace(/-/g, '/')
        	.replace('T', ' ')
        	.replace('Z', ' UTC')
        	.replace(/([+-]\d{2}):?(\d{2})/, ' $1$2'));
	};

	const zeropad = (num, count) => String(num).padStart(count, '0');

	const formatDate = (date) => {
    	const day = zeropad(date.getDate(), 2);
    	const month = zeropad(date.getMonth() + 1, 2);
    	const year = date.getFullYear().toString().substring(2);
    	const dayOfWeek = _(["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"][date.getDay()]);
    	const hours = zeropad(date.getHours(), 2);
    	const minutes = zeropad(date.getMinutes(), 2);
    	const seconds = zeropad(date.getSeconds(), 2);

		return `${day}/${month}/${year} (${dayOfWeek}.) ${hours}:${minutes}:${seconds}`;
	};

	const timeDifference = (current, previous) => {
		const msPerMinute = 60 * 1000;
		const msPerHour = msPerMinute * 60;
		const msPerDay = msPerHour * 24;
		const msPerMonth = msPerDay * 30;
		const msPerYear = msPerDay * 365;

		const elapsed = current - previous;

		const formatTimeDifference = (time, unitSingular, unitPlural) => {
			const roundedTime = Math.round(time);
			return `${roundedTime} ${roundedTime <= 1 ? _(unitSingular) : _(unitPlural)}`;
		}

		if (elapsed < msPerMinute) {
			return _('Just now');
		} else if (elapsed < msPerHour) {
			return formatTimeDifference(elapsed / msPerMinute, ' minute ago', ' minutes ago');
		} else if (elapsed < msPerDay) {
			return formatTimeDifference(elapsed / msPerHour, ' hour ago', ' hours ago');
		} else if (elapsed < msPerMonth) {
			return formatTimeDifference(elapsed / msPerDay, ' day ago', ' days ago');
		} else if (elapsed < msPerYear) {
			return formatTimeDifference(elapsed / msPerMonth, ' month ago', ' months ago');
		} else {
			return formatTimeDifference(elapsed / msPerYear, ' year ago', ' years ago');
		}
	}

	const doLocalTime = (elem) => {
		const times = elem.querySelectorAll('time');
		const currentTime = Date.now();

		times.forEach(timeElem => {
			const t = timeElem.getAttribute('datetime');
			const postTime = new Date(t);

			timeElem.dataset.local = 'true';

			if (!localStorage.show_relative_time || localStorage.show_relative_time === 'false') {
				timeElem.setAttribute('title', timeDifference(currentTime, postTime));
			} else {
				timeElem.textContent = timeDifference(currentTime, postTime);
				timeElem.setAttribute('title', formatDate(iso8601(t)));
			}
		});
	};

	if (window.Options && Options.get_tab('general')) {
    	let intervalId;
    
    	Options.extend_tab(
        	'general',
        	`<fieldset><legend>${_('Dates')}</legend><label id="show-relative-time"><input type="checkbox"> ${_('Show relative time')}</label></fieldset>`
    	);

    	const showRelativeTimeCheckbox = document.querySelector('#show-relative-time>input');

    	const toggleRelativeTime = () => {
        	const isEnabled = localStorage.show_relative_time === 'true';
        	localStorage.show_relative_time = isEnabled ? 'false' : 'true';

        	if (isEnabled) {
            	clearInterval(intervalId);
        	} else {
            	intervalId = setInterval(() => doLocalTime(document), 30000);
        	}

        	doLocalTime(document);
    	};

    	showRelativeTimeCheckbox.addEventListener('change', toggleRelativeTime);

    	if (localStorage.show_relative_time === 'true') {
        	showRelativeTimeCheckbox.checked = true;
        	intervalId = setInterval(() => doLocalTime(document), 30000);
    	}

    	document.addEventListener('new_post_js', (e) => {
        	doLocalTime(e.detail.detail);
    	});
	}

	doLocalTime(document);
});