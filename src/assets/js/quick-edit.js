jQuery(document).ready(function ($) {
	if (typeof inlineEditPost === 'undefined') {
		return;
	}

	var originalEdit = inlineEditPost.edit;
	inlineEditPost.edit = function (id) {
		originalEdit.apply(this, arguments);

		var postId = 0;
		if (typeof id === 'object') {
			postId = parseInt(this.getId(id), 10);
		} else {
			postId = parseInt(id, 10);
		}

		if (postId > 0) {
			var $row = $('#post-' + postId);
			var $editRow = $('#edit-' + postId);
			var title = $row.find('.quinrel-post-meta-title').text() || '';
			var desc = $row.find('.quinrel-post-meta-desc').text() || '';
			var noindex = $row.find('.quinrel-post-noindex').text() === '1';

			$editRow.find('.quinrel-quick-title').val(title);
			$editRow.find('.quinrel-quick-desc').val(desc);
			$editRow.find('.quinrel-quick-noindex').prop('checked', noindex);
		}
	};
});
