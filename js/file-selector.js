/*
 * file-selector.js - Add support for drag and drop file selection, and paste from clipboard on supported browsers.
 *
 * Usage:
 *   $config['additional_javascript'][] = 'js/jquery.min.js';
 *   $config['additional_javascript'][] = 'js/ajax.js';
 *   $config['additional_javascript'][] = 'js/file-selector.js';
 */

var FileSelector = {};
function init_file_selector(max_images) {

$(document).ready(function () {
	// add options panel item
	if (window.Options && Options.get_tab('general')) {
		Options.extend_tab('general', '<label id="file-drag-drop"><input type="checkbox">' + _('Drag and drop file selection') + '</label>');

		$('#file-drag-drop>input').on('click', function() {
			if ($('#file-drag-drop>input').is(':checked')) {
				localStorage.file_dragdrop = 'true';
			} else {
				localStorage.file_dragdrop = 'false';
			}
		});

		if (typeof localStorage.file_dragdrop === 'undefined') localStorage.file_dragdrop = 'true';
		if (localStorage.file_dragdrop === 'true') $('#file-drag-drop>input').prop('checked', true);
	}
});

// disabled by user, or incompatible browser.
if (localStorage.file_dragdrop == 'false' || !(window.URL.createObjectURL && window.File))
	return;

// multipost not enabled
if (typeof max_images == 'undefined') {
	var max_images = 1;
}

$('<div class="dropzone-wrap" style="display: none;">'+
	'<div class="dropzone" tabindex="0">'+
		'<div class="file-hint">'+_('Select/drop/paste files here')+'</div>'+
			'<div class="file-thumbs"></div>'+
		'</div>'+
	'</div>'+
'</div>').prependTo('#upload td');

var files = [];
$('#upload_file').remove();  // remove the original file selector
$('.dropzone-wrap').css('user-select', 'none').show();  // let jquery add browser specific prefix

FileSelector.addFile = function (file) {
	if (files.length == max_images)
		return;

	files.push(file);
	addThumb(file);
}

FileSelector.removeFile = function (file) {
	getThumbElement(file).remove();
	files.splice(files.indexOf(file), 1);
}

function getThumbElement(file) {
	return $('.tmb-container').filter(function(){return($(this).data('file-ref')==file);});
}

function addThumb(file) {

	var fileName = (file.name.length < 24) ? file.name : file.name.substr(0, 22) + '…';
	var fileType = file.type.split('/')[0];
	var fileExt = file.type.split('/')[1];
	var $container = $('<div>')
		.addClass('tmb-container')
		.data('file-ref', file)
		.append(
			$('<div>').addClass('remove-btn').html('✖'),
			$('<div>').addClass('file-tmb'),
			$('<div>').addClass('tmb-filename').html(fileName)
		)
		.appendTo('.file-thumbs');

	var $fileThumb = $container.find('.file-tmb');
	if (fileType == 'image') {
		// if image file, generate thumbnail
		var objURL = window.URL.createObjectURL(file);
		$fileThumb.css('background-image', 'url('+ objURL +')');
	} else {
		$fileThumb.html('<span>' + fileExt.toUpperCase() + '</span>');
	}
}

document.addEventListener('ajax_before_post', function (e) {
	const formData = e.detail.detail;
	for (let i = 0; i < max_images; i++) {
		let key = 'file';
		if (i > 0) key += i + 1;
		if (typeof files[i] === 'undefined') break;
		formData.append(key, files[i]);
	}
});

document.addEventListener('ajax_after_post', function () {
	files = [];
	document.querySelectorAll('.file-thumbs').forEach(element => {
    	element.innerHTML = '';
  });
});


var dragCounter = 0;
var dropHandlers = {
	dragenter: function (e) {
		e.stopPropagation();
		e.preventDefault();

		if (dragCounter === 0) $('.dropzone').addClass('dragover');
		dragCounter++;
	},
	dragover: function (e) {
		// needed for webkit to work
		e.stopPropagation();
		e.preventDefault();
	},
	dragleave: function (e) {
		e.stopPropagation();
		e.preventDefault();

		dragCounter--;
		if (dragCounter === 0) $('.dropzone').removeClass('dragover');
	},
	drop: function (e) {
		e.stopPropagation();
		e.preventDefault();

		$('.dropzone').removeClass('dragover');
		dragCounter = 0;

		var fileList = e.originalEvent.dataTransfer.files;
		for (var i=0; i<fileList.length; i++) {
			FileSelector.addFile(fileList[i]);
		}
	}
};


// attach handlers
$(document).on(dropHandlers);

$(document).on('click', '.dropzone .remove-btn', function (e) {
	e.stopPropagation();

	var file = $(e.target).parent().data('file-ref');

	FileSelector.removeFile(file);
});

$(document).on('keypress click', '.dropzone', function (e) {
	e.stopPropagation();

	// accept mouse click or Enter
	if ((e.which != 1 || e.target.className != 'file-hint') &&
		 e.which != 13)
		return;

	var $fileSelector = $('<input type="file" multiple>');

	$fileSelector.on('change', function (e) {
		if (this.files.length > 0) {
			for (var i=0; i<this.files.length; i++) {
				FileSelector.addFile(this.files[i]);
			}
		}
		$(this).remove();
	});

	$fileSelector.click();
});

$(document).on('paste', function (e) {
	var clipboard = e.originalEvent.clipboardData;
	if (typeof clipboard.items != 'undefined' && clipboard.items.length != 0) {
		
		//Webkit
		for (var i=0; i<clipboard.items.length; i++) {
			if (clipboard.items[i].kind != 'file')
				continue;

			//convert blob to file
			var file = new File([clipboard.items[i].getAsFile()], 'ClipboardImage.png', {type: 'image/png'});
			FileSelector.addFile(file);
		}
	}
});

}

document.addEventListener('DOMContentLoaded', () => {
	init_file_selector(max_images);
});