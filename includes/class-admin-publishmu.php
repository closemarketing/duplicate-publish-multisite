<?php //phpcs:ignore
/**
 * Metabox Send Contract
 *
 * @package    WordPress
 * @author     David Perez <david@closemarketing.es>
 * @copyright  2021 Closemarketing
 * @version    1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Metabox.
 *
 * Metabox Send emails.
 *
 * @since 1.0
 */
class PUBMULT_Publish {

	private $entries;
	/**
	 * Construct of Class
	 */
	public function __construct() {

		// Publish to other site.
		add_action( 'save_post_post', array( $this, 'publish_other_site' ), 10, 3 );

		add_action( 'admin_enqueue_scripts', array( $this, 'scripts_sync_all_entries' ) );
		add_action( 'wp_ajax_sync_all_entries', array( $this, 'sync_all_entries' ) );
		add_action( 'wp_ajax_nopriv_sync_all_entries', array( $this, 'sync_all_entries' ) );
	}

	/**
	 * # Publish to Multisite
	 * ---------------------------------------------------------------------------------------------------- */
	/**
	 * Publish to other site
	 *
	 * @param string  $post_id Post ID.
	 * @param object  $post Post of actual.
	 * @param boolean $update Update.
	 * @return void
	 */
	public function publish_other_site( $post_id, $post, $update ) {
		$options = get_option( 'publish_mu_setttings' );

		// Only set for post_type = post! or isset options.
		if ( 'post' !== $post->post_type && ! isset( $options['musite'] ) ) {
			return;
		}
		$this->log_it( $options );
		if ( isset( $options['musite'] ) && $options['musite'] ) {
			foreach ( $options['musite'] as $site ) {
				$sep         = strpos( $site['taxonomy'], '|' ) ? '|' : '-';
				$tax         = explode( $sep, $site['taxonomy'] );
				$tax_name    = $tax[0];
				$term_id     = $tax[1];
				$target_cats = explode( ',', $site['target_cat'] );

				$check_terms   = array( $term_id );
				$target_author = is_numeric( $site['author'] ) ? $site['author'] : '';

				$children_terms = get_term_children( $term_id, $tax_name );
				if ( $children_terms ) {
					$check_terms = array_merge( $children_terms, $check_terms );
				}
				$this->log_it( 'Terms: ' . implode( ',', $check_terms ) );
				if ( has_term( $check_terms, $tax_name, $post->ID ) ) {
					$target_post_id = get_post_meta( $post->ID, 'publish_mu_site_' . $site['site'], true );
					$this->update_post( $site['site'], $post->ID, $target_post_id, $target_author, $target_cats );
				} else {
					$this->log_it( 'Not update: ' . $post->ID . ' Site:' . $site['site'] );
				}
			}
		}
	}

	/**
	 * Updates post for multisite
	 *
	 * @param int     $site Site origin.
	 * @param int     $source_post_id Post id origin.
	 * @param boolean $target_post_id Target.
	 * @param int     $target_author Target author.
	 * @param array   $target_cats Cats to target.
	 * @return void
	 */
	private function update_post( $site, $source_post_id, $target_post_id = false, $target_author = 0, $target_cats = array() ) {
		// Get data to copy.
		$source_post      = get_post( $source_post_id );
		$source_data      = get_post_custom( $source_post_id );
		$source_permalink = get_the_permalink( $source_post_id );

		// Get image data.
		$post_thumbnail_id = get_post_thumbnail_id( $source_post_id );
		$image_url         = wp_get_attachment_image_src( $post_thumbnail_id, 'full' );

		// Copy data.
		$original_blog_id = get_current_blog_id();
		switch_to_blog( $site );
		$this->log_it( 'Sourcepermalink:' . $source_permalink );

		if ( ! $target_post_id ) {
			$post_arg       = array(
				'post_title'   => $source_post->post_title,
				'post_content' => $source_post->post_content,
				'post_status'  => $source_post->post_status,
				'post_type'    => $source_post->post_type,
				'post_date'    => $source_post->post_date,
			);
			if ( $target_author ) {
				$post_arg['post_author'] = $target_author;
			}
			$target_post_id = wp_insert_post( $post_arg );
			foreach ( $source_data as $key => $values ) {
				foreach ( $values as $value ) {
					if ( '_thumbnail_id' !== $key ) {
						add_post_meta( $target_post_id, $key, $value );
					}
				}
			}

			$this->log_it( 'Create post: ' . $target_post_id . ' Site:' . $site );
		} else {
			$post_arg = array(
				'ID'           => $target_post_id,
				'post_title'   => $source_post->post_title,
				'post_content' => $source_post->post_content,
				'post_status'  => $source_post->post_status,
				'post_type'    => $source_post->post_type,
				'post_date'    => $source_post->post_date,
			);
			if ( $target_author ) {
				$post_arg['post_author'] = $target_author;
			}

			wp_update_post( $post_arg );
			foreach ( $source_data as $key => $values ) {
				foreach ( $values as $value ) {
					if ( '_thumbnail_id' !== $key ) {
						update_post_meta( $target_post_id, $key, $value );
					}
				}
			}
			$this->log_it( 'Update post: ' . $target_post_id . ' Site:' . $site );
		}
		/**
		 * ## Terms
		 * --------------------------- */
		$this->log_it( 'has terms:' . implode( ',', $target_cats ) );
		$cats_id = array();
		foreach ( $target_cats as $target_cat ) {
			if ( strpos( $target_cat, '-' ) ) {
				$term_cat      = explode( '-', $target_cat );
				$term_cat_name = $term_cat[0];
				$cats_id[]     = $term_cat[1];
			}
		}
		if ( ! empty( $cats_id ) ) {
			wp_set_post_terms(
				$target_post_id,
				$cats_id,
				$term_cat_name
			);
		}

		/**
		 * ## Thumbnail
		 * --------------------------- */
		$this->log_it( 'has thumb Target_post_id:' . has_post_thumbnail( $target_post_id ) );
		if ( ! has_post_thumbnail( $target_post_id ) ) {
			// Add Featured Image to Post.
			$upload_dir = wp_upload_dir();
			if ( is_array( $image_url ) ) {
				$image_url = $image_url[0];

				$image_data = file_get_contents( $image_url ); //phpcs:ignore
				$filename   = basename( $image_url );

				// Check folder permission and define file location.
				if ( wp_mkdir_p( $upload_dir['path'] ) ) {
					$file = $upload_dir['path'] . '/' . $filename;
				} else {
					$file = $upload_dir['basedir'] . '/' . $filename;
				}
				$this->log_it( 'Image data: ' . $image_data );

				// Create the image  file on the server.
				file_put_contents( $file, $image_data ); //phpcs:ignore

				// Check image file type.
				$wp_filetype = wp_check_filetype( $filename, null );

				// Set attachment data.
				$attachment = array(
					'post_mime_type' => $wp_filetype['type'],
					'post_title'     => sanitize_file_name( $filename ),
					'post_content'   => '',
					'post_status'    => 'inherit',
				);

				// Create the attachment.
				$attach_id = wp_insert_attachment( $attachment, $file, $target_post_id );

				// Include image.php.
				require_once ABSPATH . 'wp-admin/includes/image.php';

				// Define attachment metadata.
				$attach_data = wp_generate_attachment_metadata( $attach_id, $file );

				// Assign metadata to attachment.
				wp_update_attachment_metadata( $attach_id, $attach_data );

				$this->log_it( 'attachment id:' . $attach_id );

				// And finally assign featured image to post.
				set_post_thumbnail( $target_post_id, $attach_id );
			}
		}

		// Adds canonical SEO.
		$this->adds_seo_tags( $source_permalink, $target_post_id );

		switch_to_blog( $original_blog_id );

		update_post_meta( $source_post_id, 'publish_mu_site_' . $site, $target_post_id );
	}

	/**
	 * Adds SEO Tags
	 *
	 * @param string  $url URL from canonical.
	 * @param integer $post_id Post id target.
	 * @return void
	 */
	private function adds_seo_tags( $url, $post_id ) {

		$this->log_it( 'Canonical' . $url );

		if ( is_plugin_active( 'seo-by-rank-math/rank-math.php' ) ) {
			$this->log_it( 'Canonical rank:' . $url . ' pid' . $post_id );

			add_post_meta( $post_id, 'rank_math_canonical_url', $url );
		} elseif ( is_plugin_active( 'wordpress-seo/wp-seo.php' ) || is_plugin_active( 'wordpress-seo-premium/wp-seo-premium.php' ) ) {
			add_post_meta( $post_id, '_yoast_wpseo_canonical', $url );
		}
	}

	/**
	 * Logs to debug
	 *
	 * @param array $message Message to log.
	 * @return void
	 */
	private function log_it( $message ) {
		if ( WP_DEBUG === true ) {
			if ( is_array( $message ) || is_object( $message ) ) {
				error_log( 'Publish MU: ' . print_r( $message, true ) ); //phpcs:ignore
			} else {
				error_log( 'Publish MU: ' . $message ); //phpcs:ignore
			}
		}
	}
	/**
	 * # AJAX Sync
	 * ---------------------------------------------------------------------------------------------------- */

	public function scripts_sync_all_entries() {

		wp_enqueue_script( 
			'sync-all-entries',
			plugins_url( '/assets/sync-all-entries.js', __FILE__ ),
			array( 'jquery' ),
			PUBLISHMU_VERSION
		);

		wp_localize_script(
			'sync-all-entries',
			'ajaxSyncEntries',
			array(
				'url'   => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'sync_all_entries_nonce' ),
			)
		);
	}
	/**
	 * Ajax function to load info
	 *
	 * @return void
	 */
	public function sync_all_entries() {
		$doing_ajax   = defined( 'DOING_AJAX' ) && DOING_AJAX;
		$not_sapi_cli = substr( php_sapi_name(), 0, 3 ) != 'cli' ? true : false;
		$nonce        = isset( $_POST['nonce'] ) ? esc_attr( $_POST['nonce'] ) : '';

		// Variables of loop.
		$source_cat_id    = isset( $_POST['source_cat_id'] ) ? substr( esc_attr( $_POST['source_cat_id'] ), 9 ) : '';
		$target_site_id   = isset( $_POST['target_site_id'] ) ? esc_attr( $_POST['target_site_id'] ) : '';
		$target_cat_id    = isset( $_POST['target_cat_id'] ) ? esc_attr( $_POST['target_cat_id'] ) : '';
		$target_author_id = isset( $_POST['target_author_id'] ) ? esc_attr( $_POST['target_author_id'] ) : '';
		$sync_loop        = isset( $_POST['sync_loop'] ) ? esc_attr( $_POST['sync_loop'] ) : 0;

		if ( isset( $_POST['target_cats_id'] ) ) {
			foreach ( $_POST['target_cats_id'] as $target_cat ){
				$posstr = strpos( esc_attr( $target_cat ), 'target_cat_category-' );
				if ( false !== $posstr ) {
					$string_cat = substr( $target_cat, $posstr + 20 );
					$target_cats[] = substr( $string_cat, 0, -1 );
				}
			}
		}

		check_ajax_referer( 'sync_all_entries_nonce', 'nonce' );
		// Start.
		if ( ! isset( $this->entries ) ) {
			$args_posts = array(
				'post_type'   => 'post',
				'numberposts' => -1,
				'orderby'     => 'date',
				'order'       => 'ASC',
				'fields'      => 'ids',
				'category'    => $source_cat_id,
			);

			$this->entries[ $source_cat_id ] = get_posts( $args_posts );
		}

		if ( false === $this->entries ) {
			if ( $doing_ajax ) {
				wp_send_json_error( array( 'msg' => 'Error' ) );
			} else {
				die();
			}
		} else {
			$entries_count            = count( $this->entries[ $source_cat_id ] );
			$item_id                  = $this->entries[ $source_cat_id ][ $sync_loop ];
			$error_products_html      = '';
			$this->msg_error_products = array();

			if ( $entries_count ) {
				if ( ( $doing_ajax ) || $not_sapi_cli ) {
					$count = $sync_loop + 1;
				}
				if ( $sync_loop > $entries_count ) {
					if ( $doing_ajax ) {
						wp_send_json_error(
							array(
								'msg' => __( 'No entries to sync', 'duplicate-publish-multisite' ),
							)
						);
					} else {
						die( esc_html( __( 'No entries to sync', 'duplicate-publish-multisite' ) ) );
					}
				} else {
					$target_post_id = get_post_meta( $item_id, 'publish_mu_site_' . $target_site_id, true );
					$this->update_post( $target_site_id, $item_id, $target_post_id, $target_author_id, $target_cats );
				}

				if ( $doing_ajax || $not_sapi_cli ) {
					$entries_synced = $sync_loop + 1;

					if ( $entries_synced <= $entries_count ) {
						$this->ajax_msg = $entries_synced . '/' . $entries_count . ' ';

						if ( $entries_synced == $entries_count ) {
							$this->ajax_msg .= __( 'Done!', 'duplicate-publish-multisite' );
						} else {
							$this->ajax_msg .= __( 'Entries', 'duplicate-publish-multisite' );
						}

						$args = array(
							'msg'   => $this->ajax_msg,
							'count' => $entries_count,
						);
						if ( $doing_ajax ) {
							if ( $entries_synced < $entries_count ) {
								$args['loop']  = $sync_loop + 1;
							}
							wp_send_json_success( $args );
						} elseif ( $not_sapi_cli && $entries_synced < $entries_count ) {
							$url  = home_url() . '/?sync=true';
							$url .= '&sync_loop=' . ( $sync_loop + 1 );
							?>
							<script>
								window.location.href = '<?php echo esc_url( $url ); ?>';
							</script>
							<?php
							echo esc_html( $args['msg'] );
							die( 0 );
						}
					}
				}
			} else {
				if ( $doing_ajax ) {
					wp_send_json_error( array( 'msg' => __( 'No posts to import', 'duplicate-publish-multisite' ) ) );
				} else {
					die( esc_html( __( 'No posts to import', 'duplicate-publish-multisite' ) ) );
				}
			}
		}
		if ( $doing_ajax ) {
			wp_die();
		}
	}
}

if ( is_admin() ) {
	new PUBMULT_Publish();
}
