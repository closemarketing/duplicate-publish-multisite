jQuery(document).ready(function($) {
	$('.source-post_type, .source-taxonomy, .site-publish').change(function(e) {
		e.preventDefault();

		post_type = $(this).closest('.publishmu').find('.source-post_type').val();
		taxpub = $(this).closest('.publishmu').find('.source-taxonomy');
		catpub = $(this).closest('.publishmu').find('.category-publish .options');
		authpub = $(this).closest('.publishmu').find('.author-publish');
		site_id = $(this).closest('.publishmu').find('.site-publish').val();
		strindex = $(this).closest('.publishmu').find('.category-publish').attr('for').replaceAll('][',',').replace('[','').replace(']','').split(',');

		$.ajax({
			type: 'POST',
			url: ajaxAction.url,
			data: {
				action: 'category_publish',
				site_id: site_id,
				post_type: post_type,
				taxonomy: taxpub.val(),
				index: strindex[1],
				nonce: ajaxAction.nonce
			},
			beforeSend: function() { $(".category-publish-action .spinner").addClass("is-active"); },
			complete: function() { $(".category-publish-action .spinner").removeClass("is-active"); },
			success: function(response){
				taxpub.empty().append(response.data[0]);
				catpub.empty().append(response.data[1]);
				authpub.empty().append(response.data[2]);
			},
			error: function(xhr, textStatus, error) {
				console.log(xhr.statusText);
				console.log(textStatus);
				console.log(error);
			}
		});
	});

});