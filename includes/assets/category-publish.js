jQuery(document).ready(function($) {
	$('.site-publish').change(function(e) {
		e.preventDefault();

		catpub = $(this).closest('.publishmu').find('.category-publish .options');
		authpub = $(this).closest('.publishmu').find('.author-publish');
		strindex = $(this).closest('.publishmu').find('.category-publish').attr('for').replaceAll('][',',').replace('[','').replace(']','').split(',');

		$.ajax({
			type: 'POST',
			url: ajaxAction.url,
			data: {
				action: 'category_publish',
				site_id: $(this).val(),
				index: strindex[1],
				nonce: ajaxAction.nonce
			},
			beforeSend: function() { $(".category-publish-action .spinner").addClass("is-active"); },
			complete: function() { $(".category-publish-action .spinner").removeClass("is-active"); },
			success: function(response){
				catpub.empty().append(response.data[0]);
				authpub.empty().append(response.data[1]);
			},
			error: function(xhr, textStatus, error) {
				console.log(xhr.statusText);
				console.log(textStatus);
				console.log(error);
			}
		});
	});

});