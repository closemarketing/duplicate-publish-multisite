jQuery(document).ready(function($) {

	$('.save-item .sync-all-entries').on('click', function(e){
		e.preventDefault();

		var_source_cat_id  = $(this).closest('.publishmu').find('.source-category').val();
		var_target_site_id = $(this).closest('.publishmu').find('.site-publish').val();
		var_target_author_id = $(this).closest('.publishmu').find('.author-publish').val();
		authpub = $(this).closest('.publishmu').find('.author-publish');
		strindex = $(this).closest('.publishmu').find('.category-publish').attr('for').replaceAll('][',',').replace('[','').replace(']','').split(',');
		results_html = $(this).closest('.publishmu').find(".sync-all-entries-result");

		var var_target_cats_id =$(this).closest('.publishmu').find('.category-publish input:checkbox:checked').map(function(){
			return $(this).attr('name');
		 }).get();

		var syncAjaxCall = function(x){
			$.ajax({
				type: 'POST',
				url: ajaxSyncEntries.url,
				data: {
					action: 'sync_all_entries',
					source_cat_id: var_source_cat_id,
					target_site_id: var_target_site_id,
					target_cats_id: var_target_cats_id,
					target_author_id: var_target_author_id,
					sync_loop: x,
					nonce: ajaxSyncEntries.nonce
				},
				success: function(results){
					if( results.data.msg != undefined ){
						results_html.html( results.data.msg );
					}
					if ( results.data.loop && results.data.loop <= results.data.count ) {
						syncAjaxCall(results.data.loop);
					}
				},
				error: function(xhr, textStatus, error) {
					console.log(xhr.statusText);
					console.log(textStatus);
					console.log(error);
				}
			});
		}
		syncAjaxCall(0);
	});

});