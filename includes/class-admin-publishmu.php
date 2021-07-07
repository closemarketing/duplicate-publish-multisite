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

	/**
	 * Construct of Class
	 */
	public function __construct() {

		// Publish to other site.
		add_action( 'save_post_post', array( $this, 'publish_other_site' ), 10, 3 );
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
				$tax         = explode( '|', $site['taxonomy'] );
				$tax_name    = $tax[0];
				$term_id     = $tax[1];
				$check_terms = array( $term_id );

				$children_terms = get_term_children( $term_id, $tax_name );
				if ( $children_terms ) {
					$check_terms = array_merge( $children_terms, $check_terms );
				}
				$this->log_it( 'Terms: ' . implode( ',', $check_terms ) );
				if ( has_term( $check_terms, $tax_name, $post->ID ) ) {
					$target_post_id = get_post_meta( $post->ID, 'publish_mu_site_' . $site['site'], true );
					$this->update_post( $site['site'], $post->ID, $target_post_id );
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
	 * @return void
	 */
	private function update_post( $site, $source_post_id, $target_post_id = false ) {
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

		if ( ! $target_post_id ) {
			$post_arg       = array(
				'post_title'   => $source_post->post_title,
				'post_content' => $source_post->post_content,
				'post_status'  => $source_post->post_status,
				'post_type'    => $source_post->post_type,
			);
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
			);
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
		$this->log_it( 'has thumb Target_post_id:' . has_post_thumbnail( $target_post_id ) );
		if ( ! has_post_thumbnail( $target_post_id ) ) {
			// Add Featured Image to Post.
			$upload_dir = wp_upload_dir();
			if ( is_array( $image_url ) ) {
				$image_url = $image_url[0];
			} else {
				$image_data = file_get_contents( $image_url ); //phpcs:ignore
				$filename   = basename( $image_url );

				// Check folder permission and define file location.
				if ( wp_mkdir_p( $upload_dir['path'] ) ) {
					$file = $upload_dir['path'] . '/' . $filename;
				} else {
					$file = $upload_dir['basedir'] . '/' . $filename;
				}

				if ( $image_data ) {
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
}

if ( is_admin() ) {
	new PUBMULT_Publish();
}
