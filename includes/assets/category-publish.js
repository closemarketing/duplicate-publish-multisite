jQuery(document).ready(function($) {
	// Handler específico para cuando cambia el tipo de post
	$('.source-post_type').change(function(e) {
		e.preventDefault();
		console.log("Post type changed");

		// Limpiar el valor de taxonomía padre al cambiar tipo de post
		$(this).closest('.publishmu').find('.source-taxonomy_parent').val('');
  	});

	$('.source-post_type, .source-taxonomy_parent, .source-taxonomy, .site-publish').change(function(e) {
		e.preventDefault();

		var $container = $(this).closest('.publishmu');
      var post_type  = $(this).val();
      var site_id    = $container.find('.site-publish').val();

		post_type = $(this).closest('.publishmu').find('.source-post_type').val();
		taxpub_parent = $(this).closest('.publishmu').find('.source-taxonomy_parent');
		taxpub = $(this).closest('.publishmu').find('.source-category');
		catpub = $(this).closest('.publishmu').find('.category-publish .options');
		authpub = $(this).closest('.publishmu').find('.author-publish');
		site_id = $(this).closest('.publishmu').find('.site-publish').val();

		strindex = $(this).closest('.publishmu').find('.category-publish').attr('for').replaceAll('][',',').replace('[','').replace(']','').split(',');
		//let nonce = $( this ).closest('.publishmu').find('#nonce').val();

		$.ajax({
			type: 'POST',
			url: pubmult_ajaxAction.url,
			data: {
				action: 'category_publish',
				site_id: site_id,
				post_type: post_type,
				taxonomy_parent: taxpub_parent.val(),
				taxonomy: taxpub.val(),
				index: strindex[1],
				nonce: pubmult_ajaxAction.nonce,
			},
			beforeSend: function(xhr) {
				$(".category-publish-action .spinner").addClass("is-active");
				xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
			},
			complete: function() { $(".category-publish-action .spinner").removeClass("is-active"); },
			success: function(response){
				taxpub_parent.empty().append(response.data[0]);
				taxpub.empty().append(response.data[1]);
				catpub.empty().append(response.data[2]);
				authpub.empty().append(response.data[3]);
			},
			error: function(xhr, textStatus, error) {
				console.log("AJAX Error Status:", xhr.status);
				console.log("AJAX Error Status Text:", xhr.statusText);
				console.log("AJAX Error:", textStatus);
				console.log("AJAX Error Message:", error);
			}
		});
	});

});