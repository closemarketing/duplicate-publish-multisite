jQuery(document).ready(function($) {
	$('.site-publish').change(function(e) {
		e.preventDefault();

		catpub = $(this).closest('.publishmu').find('.category-publish');
		
		$.ajax({
			type: 'POST',
			url: ajaxAction.url,
			data: {
				action: 'category_publish',
				site_id: $(this).val(),
				nonce: ajaxAction.nonce
			},
			beforeSend: function() { $(".category-publish-action .spinner").addClass("is-active"); },
			complete: function() { $(".category-publish-action .spinner").removeClass("is-active"); },
			success: function(response){
				catpub.empty().append(response.data);
			},
			error: function(xhr, textStatus, error) {
				console.log(xhr.statusText);
				console.log(textStatus);
				console.log(error);
			}
		});
	});

});