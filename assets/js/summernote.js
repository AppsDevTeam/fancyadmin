import 'summernote/dist/summernote-bs5.css';
import 'summernote/dist/summernote-bs5';
import 'summernote/dist/lang/summernote-cs-CZ';
import '@emericklaw/summernote-cleaner'

$.nette.ext('live').after(function (el) {
	$(el).find('.summernote').each((i, el) => {
		$(el).summernote({
			height: null,
			minHeight: 200,
			maxHeight: null,
			linkTargetBlank: false,
			lang: 'cs-CZ',
			callbacks: {
				onInit: function () {
					$(this).next().find('.note-editable').addClass('portal-collapse-detail-content');
				},
				onFileUpload: function (file) {
					myOwnCallBack(file[0], el);
				},
			},
			styleTags: [
				'p',
				'h2',
				'h3'
			],
			buttons: {
				insertNbsp: function (context) {
					var ui = $.summernote.ui;
					var button = ui.button({
						contents: '&amp;nbsp; <svg style="margin-left: 3px;" width="18" height="18" viewBox="0 0 24 24" version="1.1" xmlns="http://www.w3.org/2000/svg"><g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd"><path d="M11,11 L8,11 C7.44771525,11 7,10.5522847 7,10 L7,10 C7,9.44771525 7.44771525,9 8,9 L11,9 L11,6 C11,5.44771525 11.4477153,5 12,5 L12,5 C12.5522847,5 13,5.44771525 13,6 L13,9 L16,9 C16.5522847,9 17,9.44771525 17,10 L17,10 C17,10.5522847 16.5522847,11 16,11 L13,11 L13,14 C13,14.5522847 12.5522847,15 12,15 L12,15 C11.4477153,15 11,14.5522847 11,14 L11,11 Z M21,15 L21,20 L3,20 L3,15 C3,14.4477153 3.44771525,14 4,14 L4,14 C4.55228475,14 5,14.4477153 5,15 L5,18 L19,18 L19,15 C19,14.4477153 19.4477153,14 20,14 L20,14 C20.5522847,14 21,14.4477153 21,15 Z" fill="#6c757d"></path></g></svg>',
						tooltip: 'Vložit pevnou mezeru',
						click: function () {
							context.invoke('editor.insertText', '\u00A0');
						}
					});
					return button.render();
				},
			},
			toolbar: [
				['style', ['style']],
				['font', ['bold', 'italic', 'underline']],
				// ['fontsize', ['fontsize']],
				// ['color', ['color']],
				['para', ['ul', 'ol']],
				// ['table', ['table']],
				['insert', ['link', 'file']],
				['view', ['fullscreen', 'codeview']]
			],
			popover: {
				image: [
					// ['imagesize', ['', '', '', '']],
					// 	['float', []],
					// 	['remove', ['removeMedia']]
				],
				link: [
					['link', ['linkDialogShow', 'unlink']]
				]
			},
			// contentsCss: ['/css/summernote-custom.css'],
			cleaner: {
				action: 'paste', // both|button|paste 'button' only cleans via toolbar button, 'paste' only clean when pasting content, both does both options.
				icon: '<i class="note-icon"><svg xmlns="http://www.w3.org/2000/svg" id="libre-paintbrush" viewBox="0 0 14 14" width="14" height="14"><path d="m 11.821425,1 q 0.46875,0 0.82031,0.311384 0.35157,0.311384 0.35157,0.780134 0,0.421875 -0.30134,1.01116 -2.22322,4.212054 -3.11384,5.035715 -0.64956,0.609375 -1.45982,0.609375 -0.84375,0 -1.44978,-0.61942 -0.60603,-0.61942 -0.60603,-1.469866 0,-0.857143 0.61608,-1.419643 l 4.27232,-3.877232 Q 11.345985,1 11.821425,1 z m -6.08705,6.924107 q 0.26116,0.508928 0.71317,0.870536 0.45201,0.361607 1.00781,0.508928 l 0.007,0.475447 q 0.0268,1.426339 -0.86719,2.32366 Q 5.700895,13 4.261155,13 q -0.82366,0 -1.45982,-0.311384 -0.63616,-0.311384 -1.0212,-0.853795 -0.38505,-0.54241 -0.57924,-1.225446 -0.1942,-0.683036 -0.1942,-1.473214 0.0469,0.03348 0.27455,0.200893 0.22768,0.16741 0.41518,0.29799 0.1875,0.130581 0.39509,0.24442 0.20759,0.113839 0.30804,0.113839 0.27455,0 0.3683,-0.247767 0.16741,-0.441965 0.38505,-0.753349 0.21763,-0.311383 0.4654,-0.508928 0.24776,-0.197545 0.58928,-0.31808 0.34152,-0.120536 0.68974,-0.170759 0.34821,-0.05022 0.83705,-0.07031 z"/></svg></i>',
				keepHtml: true,
				keepTagContents: ['span'], //Remove tags and keep the contents
				badTags: ['applet', 'col', 'colgroup', 'embed', 'noframes', 'noscript', 'script', 'style', 'title', 'meta', 'link', 'head'], //Remove full tags with contents
				badAttributes: ['bgcolor', 'border', 'height', 'cellpadding', 'cellspacing', 'lang', 'start', 'style', 'valign', 'width', 'data-(.*?)', 'class'], //Remove attributes from remaining tags
				limitChars: 0, // 0|# 0 disables option
				limitDisplay: 'both', // none|text|html|both
				limitStop: false, // true/false
				limitType: 'text', // text|html
				notTimeOut: 850, //time before status message is hidden in miliseconds
				keepImages: true, // if false replace with imagePlaceholder
				imagePlaceholder: 'https://via.placeholder.com/200'
			}
		});
	});
});

function myOwnCallBack(file, el) {
	let data = new FormData();
	data.append("file", file);
	$.ajax({
		data: data,
		type: "POST",
		url: $(document.body).data('summernoteUpload'),
		cache: false,
		contentType: false,
		processData: false,
		xhr: function() { //Handle progress upload
			let myXhr = $.ajaxSettings.xhr();
			if (myXhr.upload) myXhr.upload.addEventListener('progress', progressHandlingFunction, false);
			return myXhr;
		},
		success: function(reponse) {
			if(reponse.status === true) {
				let listMimeImg = ['image/png', 'image/jpeg', 'image/webp', 'image/gif', 'image/svg'];
				let listMimeAudio = ['audio/mpeg', 'audio/ogg'];
				let listMimeVideo = ['video/mpeg', 'video/mp4', 'video/webm'];
				let elem;

				if (listMimeImg.indexOf(file.type) > -1) {
					//Picture
					$(el).summernote('editor.insertImage', reponse.filename);
				} else if (listMimeAudio.indexOf(file.type) > -1) {
					//Audio
					elem = document.createElement("audio");
					elem.setAttribute("src", reponse.filename);
					elem.setAttribute("controls", "controls");
					elem.setAttribute("preload", "metadata");
					console.log(elem);
					$(el).summernote('editor.insertNode', elem);
				} else if (listMimeVideo.indexOf(file.type) > -1) {
					//Video
					elem = document.createElement("video");
					elem.setAttribute("src", reponse.filename);
					elem.setAttribute("controls", "controls");
					elem.setAttribute("preload", "metadata");
					$(el).summernote('editor.insertNode', elem);
					console.log(elem);
				} else {
					//Other file type
					elem = document.createElement("a");
					let linkText = document.createTextNode(file.name);
					elem.appendChild(linkText);
					elem.title = file.name;
					elem.href = reponse.filename;
					$(el).summernote('editor.insertNode', elem);
					console.log(elem);
				}
			}
		}
	});
}

function progressHandlingFunction(e) {
	if (e.lengthComputable) {
		//Log current progress
		console.log((e.loaded / e.total * 100) + '%');

		//Reset progress on complete
		if (e.loaded === e.total) {
			console.log("Upload finished.");
		}
	}
}

import './summernote-file'