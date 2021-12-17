jQuery(document).ready(function($) {
	
	$('#sync-all-entries').click(function(e) {
		e.preventDefault();
		
		$.ajax({
			type: 'POST',
			url: ajaxSyncEntries.url,
			data: {
				action: 'sync_all_entries',
				post_id: $('#sync_all_entries').attr("data-post-id"),
				nonce: ajaxSyncEntries.nonce
			},
			beforeSend: function() { $(".sync-all-entries-action .spinner").addClass("is-active"); },
			complete: function() { $(".sync-all-entries-action .spinner").removeClass("is-active"); },
			success: function(response){
				$('#sync-all-entries-result').html( response.data );
			},
			error: function(xhr, textStatus, error) {
				console.log(xhr.statusText);
				console.log(textStatus);
				console.log(error);
			}
		});
	});

});