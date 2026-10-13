(function ($) {
	'use strict';

	$(function () {
		var panel = $('#gfsw-preview');
		var content = $('#gfsw-preview-content');
		var status = $('#gfsw-preview-status');
		var request;
		var opener;
		var sequence = 0;

		$('.gfsw-preview-button').on('click', function () {
			opener = this;
			var current = ++sequence;
			if (request) {
				request.abort();
			}
			var data = new FormData();
			if ($(this).attr('data-map-id')) {
				data.append('saved_id', $(this).attr('data-map-id'));
			} else {
				data = new FormData(document.querySelector('.gfsw-form'));
			}
			data.set('action', 'gfsw_preview');
			data.set('_ajax_nonce', gfswPreview.nonce);
			panel.prop('hidden', false).attr('aria-busy', 'true');
			content.empty();
			status.text(gfswPreview.loading);
			panel[0].focus();
			request = $.ajax({
				url: gfswPreview.url,
				method: 'POST',
				data: data,
				processData: false,
				contentType: false,
				dataType: 'json'
			}).done(function (response) {
				if (current !== sequence) {
					return;
				}
				if (!response.success || !response.data || typeof response.data.html !== 'string') {
					status.text(gfswPreview.error);
					return;
				}
				content.html(response.data.html);
				status.text(gfswPreview.loaded);
			}).fail(function (xhr, reason) {
				if (current !== sequence || reason === 'abort') {
					return;
				}
				var response = xhr.responseJSON;
				status.text(gfswPreview.error);
				if (response && response.data && response.data.errors) {
					var errors = $('<ul>');
					$.each(response.data.errors, function (field, message) {
						$('<li>').text(message).appendTo(errors);
					});
					content.append(errors);
				}
			}).always(function () {
				if (current === sequence) {
					panel.attr('aria-busy', 'false');
					request = null;
				}
			});
		});

		$('#gfsw-preview-close').on('click', function () {
			++sequence;
			if (request) {
				request.abort();
				request = null;
			}
			content.empty();
			panel.prop('hidden', true).attr('aria-busy', 'false');
			if (opener) {
				opener.focus();
			}
		});
	});
})(jQuery);
