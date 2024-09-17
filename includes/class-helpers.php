<?php
/**
 * Helpers class
 *
 * @package    WordPress
 * @author     David Perez <david@close.technology>
 * @copyright  2023 Closemarketing
 * @version    1.0
 */

namespace Close\DuplicatePublishMultisite;

defined( 'ABSPATH' ) || exit;

/**
 * Helpers.
 *
 * @since 1.6.2
 */
class HELPER {

	/**
	 * Get list to sites publish
	 *
	 * @return array
	 */
	public static function get_sites_publish() {
		$sites    = array();
		$subsites = get_sites( array( 'number' => 500 ) ); // get first 500 sites.

		foreach ( $subsites as $subsite ) {
			if ( empty( get_object_vars( $subsite )['blog_id'] ) ) {
				continue;
			}
			$subsite_id    = (int) get_object_vars( $subsite )['blog_id'];
			$subsite_name  = get_blog_details( $subsite_id )->blogname;
			$subsite_name .= ' - ' . $subsite->domain . $subsite->path;

			if ( get_current_blog_id() !== $subsite_id ) {
				$sites[ $subsite_id ] = $subsite_name;
			}
		}
		asort( $sites );
		return $sites;
	}

	/**
	 * Get list of post types
	 *
	 * @return array
	 */
	public static function get_post_types() {
		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		$post_types = array_filter(
			$post_types,
			function ( $post_type ) {
				return ! in_array( $post_type->name, array( 'attachment', 'revision', 'nav_menu_item' ), true );
			}
		);
		$post_types = array_map(
			function ( $post_type ) {
				return $post_type->label;
			},
			$post_types
		);
		return $post_types;
	}

	/**
	 * Gets taxonomies from post type or whole site.
	 *
	 * @param string $post_type Post type.
	 * @return array
	 */
	public static function get_taxonomies( $post_type = '' ) {
		$taxonomies_options = array();
		$taxonomies         = get_taxonomies( array( 'public' => true ), 'objects' );
		foreach ( $taxonomies as $taxonomy ) {
			if ( empty( $taxonomy->object_type ) ) {
				continue;
			}
			foreach ( $taxonomy->object_type as $object_type ) {
				if ( 'attachment' === $object_type || in_array( $taxonomy->name, array( 'nav_menu', 'link_category', 'post_format' ), true ) ) {
					continue;
				}
				if ( empty( $post_type ) ) {
					$taxonomies_options[ $object_type ][ $taxonomy->name ] = $taxonomy->label;
				} elseif ( $post_type === $object_type ) {
					$taxonomies_options[ $taxonomy->name ] = $taxonomy->label;
				}
			}
		}
		return $taxonomies_options;
	}

	/**
	 * Get categories from.
	 *
	 * @param integer $site Site ID.
	 * @return array
	 */
	public static function get_terms_from( $site = 0, $taxonomy = '', $post_type = '' ) {
		$posts_options = array();
		if ( 0 !== $site ) {
			$original_blog_id = get_current_blog_id();
			switch_to_blog( $site );
		}

		if ( empty( $taxonomy ) ) {
			$taxonomy_post_types = self::get_taxonomies();
			if ( ! empty( $taxonomy_post_types[ $post_type ] ) ) {
				$taxonomy = key( $taxonomy_post_types[ $post_type ] );
			}
		}

		if ( empty( $taxonomy ) ) {
			return array();
		}

		// * Get posts in array
		$args_query  = array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'orderby'    => 'title', // menu_order, rand, date.
			'order'      => 'ASC',
		);
		$terms_array = get_terms( $args_query );
		if ( ! empty( $terms_array ) ) {
			foreach ( $terms_array as $term ) {
				$term_name = '';
				if ( 0 !== $term->parent ) {
					$term_parent = get_term_by( 'id', $term->parent, $taxonomy );
					$term_name  .= $term_parent->name . ' > ';
				}
				$term_name .= $term->name;

				$posts_options[ $taxonomy . '-' . $term->term_id ] = $term_name;
			}
		}
		if ( 0 !== $site ) {
			switch_to_blog( $original_blog_id );
		}
		asort( $posts_options );
		return $posts_options;
	}

	/**
	 * Get authors from.
	 *
	 * @param integer $site Site ID.
	 * @return array
	 */
	public static function get_authors_from( $site = 0 ) {
		$authors_options = array();
		if ( 0 !== $site ) {
			$original_blog_id = get_current_blog_id();
			switch_to_blog( $site );
		}

		$users = get_users();
		foreach ( $users as $user ) {
			$authors_options[ $user->ID ] = $user->display_name;
		}
		if ( 0 !== $site ) {
			switch_to_blog( $original_blog_id );
		}
		asort( $authors_options );
		$authors_options = array( 'any' => esc_html__( 'Autodetect', 'duplicate-publish-multisite' ) ) + $authors_options;

		return $authors_options;
	}
}
