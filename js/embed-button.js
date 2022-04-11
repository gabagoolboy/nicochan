/*
 * embed-button.js
 *
 * Usage:
 *   $config['additional_javascript'][] = 'js/jquery.min.js';
 *   $config['additional_javascript'][] = 'js/embed-button.js';
 *
 */

$(document).ready(function () {
	if (active_page == 'catalog')
		return;

	if (window.Options && Options.get_tab('general')) {
		Options.extend_tab("general", "<label><input type='checkbox' id='disable-embedding' /> " + _('Disable link embeds') + "</label>");

		$('#disable-embedding').on('change', function () {
			if (this.checked) {
				localStorage.disable_embedding = 'true';
			} else {
				localStorage.disable_embedding = 'false';
			}
		});

		if (localStorage.disable_embedding === 'true') {
			$('#disable-embedding').attr('checked', 'checked');
		}
		else {
			enableEmbedButtons();
		}
	}
	else {
		enableEmbedButtons();
	}
});

function enableEmbedButtons() {
	addEmbedButtons();

	onDomChange($('body')[0], function () {
		addEmbedButtons();
	});
}

function addEmbedButtons() {
	$('a.uninitialized.embed-link').removeClass('uninitialized').after(function () {
		var embedButton = $('<span> [<a href="javascript:void(0);" class="embed-button no-decoration" data-embed-type="' + this.getAttribute('data-embed-type') + '" data-embed-data="' + this.getAttribute('data-embed-data') + '">Embed</a>]</span>')
		embedButton.find('a').click(toggleEmbed);
		return embedButton;
	});
}

function toggleEmbed() {
	if (this.textContent == 'Embed') {
		var embedId = generateEmbedId();
		this.setAttribute('data-embed-id', embedId);

		var embedCode = getEmbedHTML(this.getAttribute('data-embed-type'), '640', '360', this.getAttribute('data-embed-data'));

		var embeddedElement = $(embedCode).insertAfter($(this).parent());
		embeddedElement.attr('id', 'embed_frame_' + embedId);
		embeddedElement.addClass('embed_container');

		this.textContent = 'Remove';
	}
	else {
		var embedId = this.getAttribute('data-embed-id');
		$('#embed_frame_' + embedId).remove();

		this.textContent = 'Embed';
	}
}

var embedIdCounter = 0;
function generateEmbedId() {
	embedIdCounter++;
	return embedIdCounter;
}

function getEmbedHTML(type, width, height, data) {
	switch (type) {
		case 'youtube':
			return '<iframe width="' + width + '" height="' + height + '" src="https://href.li/?https://youtube.com/embed/' + data + '?autoplay=1&html5=1" frameborder="0" allowfullscreen scrolling="no"></iframe>';
		case 'dailymotion':
			return '<iframe width="' + width + '" height="' + height + '" src="https://href.li/?https://www.dailymotion.com/embed/video/' + data + '?autoplay=1" frameborder="0" allowfullscreen></iframe>';
		case 'vimeo':
			return '<iframe width="' + width + '" height="' + height + '" src="https://href.li/?https://player.vimeo.com/video/' + data + '?byline=0&portrait=0&autoplay=1" frameborder="0" webkitallowfullscreen mozallowfullscreen allowfullscreen></iframe>';
		case 'vidme':
			return '<iframe width="' + width + '" height="' + height + '" src="https://href.li/?https://vid.me/e/' + data + '" frameborder="0" allowfullscreen webkitallowfullscreen mozallowfullscreen scrolling="no"></iframe>';
		case 'liveleak':
			return '<iframe width="' + width + '" height="' + height + '" src="https://href.li/?https://www.liveleak.com/ll_embed?i=' + data + '" frameborder="0" allowfullscreen></iframe>';
		case 'metacafe':
			return '<iframe width="' + width + '" height="' + height + '" src="https://href.li/?https://www.metacafe.com/embed/' + data + '/" frameborder="0" allowfullscreen></iframe>';
		case 'soundcloud':
			return '<iframe width="640" height="166" scrolling="no" frameborder="no" src="https://href.li/?https://w.soundcloud.com/player/?url=https://soundcloud.com/' + data + '&amp;color=ff5500&amp;auto_play=true&amp;hide_related=false&amp;show_comments=false&amp;show_user=false&amp;show_reposts=false"></iframe>';
		case 'vocaroo':
			return '<div><iframe width="399" height="60" src="https://vocaroo.com/embed/'+data+'?autoplay=0" frameborder="0" allow="autoplay"></iframe></div>';

		default:
			return '<span>Unknown embed type: "' + type + '"</span>';
	}
}

var onDomChange = (function () {
	var MutationObserver = window.MutationObserver || window.WebKitMutationObserver,
        eventListenerSupported = window.addEventListener;

	return function (obj, callback) {
		if (MutationObserver) {
			// define a new observer
			var obs = new MutationObserver(function (mutations, observer) {
				if (mutations[0].addedNodes.length || mutations[0].removedNodes.length)
					callback();
			});
			// have the observer observe foo for changes in children
			obs.observe(obj, { childList: true, subtree: true });
		}
		else if (eventListenerSupported) {
			obj.addEventListener('DOMNodeInserted', callback, false);
			obj.addEventListener('DOMNodeRemoved', callback, false);
		}
	};
})();
