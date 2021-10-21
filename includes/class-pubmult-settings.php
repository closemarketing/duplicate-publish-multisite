<?php
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
class PUBMULT_Settings {

	/**
	 * Construct of Class
	 */
	public function __construct() {

		add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
		add_action( 'admin_init', array( $this, 'page_init' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'load_admin_styles' ) );

		// AJAX.
		add_action( 'wp_ajax_category_publish', array( $this, 'category_publish' ) );
		add_action( 'wp_ajax_nopriv_category_publish', array( $this, 'category_publish' ) );
	}

	/**
	 * # Functions
	 * ---------------------------------------------------------------------------------------------------- */

	/**
	 * Adds plugin page.
	 *
	 * @return void
	 */
	public function add_plugin_page() {
		add_submenu_page(
			'options-general.php',
			__( 'Publish Multisite', 'duplicate-publish-multisite' ),
			__( 'Publish Multisite', 'duplicate-publish-multisite' ),
			'manage_options',
			'publish_multisite_admin',
			array( $this, 'create_admin_page' ),
		);
	}

	/**
	 * Loads admin styles
	 *
	 * @return void
	 */
	public function load_admin_styles() {
		wp_enqueue_style(
			'admin_css_foo',
			plugins_url( 'assets/admin-publishmu.css', __FILE__ ),
			false,
			PUBLISHMU_VERSION
		);

		wp_enqueue_script( 
			'category-publish',
			plugins_url( '/assets/category-publish.js', __FILE__ ),
			array( 'jquery' ),
			true
		);

		wp_localize_script(
			'category-publish',
			'ajaxAction',
			array(
				'url'   => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'category_publish_nonce' ),
			)
		);
	}

	/**
	 * Create admin page.
	 *
	 * @return void
	 */
	public function create_admin_page() {
		$this->publish_mu_setttings = get_option( 'publish_mu_setttings' );
		?>
		<div class="wrap">
			<h2><?php esc_html_e( 'Publish Multisite Settings', 'duplicate-publish-multisite' ); ?>
			</h2>
			<p></p>
			<?php
			settings_errors();
			?>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'publish_mu_setttings' );
				do_settings_sections( 'pubmult-admin' );
				submit_button( __( 'Save options', 'duplicate-publish-multisite' ), 'primary', 'submit_settings' );
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Init for page
	 *
	 * @return void
	 */
	public function page_init() {
		register_setting(
			'publish_mu_setttings',
			'publish_mu_setttings',
			array( $this, 'sanitize_fields' )
		);

		add_settings_section(
			'pubmult_setting_section',
			__( 'Settings for publishing directly multisite', 'duplicate-publish-multisite' ),
			array( $this, 'pubmult_section_info' ),
			'pubmult-admin'
		);

		add_settings_field(
			'musite',
			__( 'Site relations', 'duplicate-publish-multisite' ),
			array( $this, 'musite_callback' ),
			'pubmult-admin',
			'pubmult_setting_section'
		);

	}

	/**
	 * Sanitize fiels before saves in DB
	 *
	 * @param array $input Input fields.
	 * @return array
	 */
	public function sanitize_fields( $input ) {
		$sanitary_values = array();
		// Save Spider options.
		if ( isset( $input['musite'] ) ) {
			$index = 0;
			foreach ( $input['musite'] as $musite ) {
				if ( $musite['taxonomy'] ) {
					$cat_string = array();
					foreach( $musite as $key => $value ) {
						if ( false !== strpos( $key, 'target_cat_' ) ) {
							$cat_string[] = str_replace( 'target_cat_', '', $key );
						} else {
							$sanitary_values['musite'][ $index ][ $key ] = sanitize_text_field( $value );
						}

					}
					$sanitary_values['musite'][ $index ]['target_cat'] = implode( ',', $cat_string );
					$index++;
				}
			}
		}
		return $sanitary_values;
	}

	/**
	 * Info for holded automate section.
	 *
	 * @return void
	 */
	public function pubmult_section_info() {
		esc_html_e( 'Make the relations between sites and categories.', 'duplicate-publish-multisite' );
	}

	/**
	 * Get list to sites publish
	 *
	 * @return array
	 */
	private function get_sites_publish() {
		$sites    = array();
		$subsites = get_sites();

		foreach ( $subsites as $subsite ) {
			$subsite_id           = (int) get_object_vars( $subsite )['blog_id'];
			$subsite_name         = get_blog_details( $subsite_id )->blogname;

			if (  get_current_blog_id() !== $subsite_id ) {
				$sites[ $subsite_id ] = $subsite_name;
			}
		}

		return $sites;
	}

	/**
	 * Get categories from.
	 *
	 * @param integer $site Site ID.
	 * @return array
	 */
	private function get_categories_from( $site = 0 ) {
		$posts_options = array();
		if ( 0 !== $site ) {
			$original_blog_id = get_current_blog_id();
			switch_to_blog( $site );
		}

		$taxonomies = array(
			'category',
			'post_tag',
		);
		foreach ( $taxonomies as $taxonomy ) {
			$taxonomy_obj = get_taxonomy( $taxonomy );
			// * Get posts in array
			$args_query  = array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'orderby'    => 'title', // menu_order, rand, date.
				'order'      => 'ASC',
			);
			$terms_array = get_terms( $args_query );
			if ( ! empty( $terms_array)){
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
		}
		if ( 0 !== $site ) {
			switch_to_blog( $original_blog_id );
		}
		return $posts_options;
	}

	/**
	 * Get authors from.
	 *
	 * @param integer $site Site ID.
	 * @return array
	 */
	private function get_authors_from( $site = 0 ) {
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
		return $authors_options;
	}

	/**
	 * Ajax function to load info
	 *
	 * @return void
	 */
	public function category_publish() {
		$site_id = isset( $_POST['site_id'] ) ? sanitize_key( $_POST['site_id'] ) : '';
		$index   = isset( $_POST['index'] ) ? sanitize_key( $_POST['index'] ) : '';
		$nonce   = isset( $_POST['nonce'] ) ? sanitize_key( $_POST['nonce'] ) : '';
		check_ajax_referer( 'category_publish_nonce', 'nonce' );
		if ( true ) {
			$html_cat = '';
			foreach ( $this->get_categories_from( $site_id ) as $key => $value ) {
				//$html_cat .= '<option value="' . esc_html( $key ) . '" >' . esc_html( $value ) . '</option>';
				$html_cat .= '<p><input type="checkbox"';
				$html_cat .= 'name="publish_mu_setttings[musite][' . esc_html( $index ) . '][target_cat_' . esc_html( $key ) . ']"';
				$html_cat .= ' value="' . esc_html( $key ) . '"';
				$html_cat .= '/>' . esc_html( $value ) . '</p>';
			}
			$html_auth = '';
			foreach ( $this->get_authors_from( $site_id ) as $key => $value ) {
				$html_auth .= '<option value="' . esc_html( $key ) . '" >' . esc_html( $value ) . '</option>';
			}
			wp_send_json_success( array( $html_cat, $html_auth ) );
		} else {
			wp_send_json_error( array( 'error' => 'Error' ) );
		}
	}

	/**
	 * Spider URL Callback
	 *
	 * @return void
	 */
	public function musite_callback() {
		$options       = get_option( 'publish_mu_setttings' );
		$sites_options = $this->get_sites_publish();
		$posts_options = $this->get_categories_from();
		$size          = isset( $options['musite'] ) ? count( $options['musite'] ) -1 : 0;

		for ( $idx = 0, $size; $idx <= $size; ++$idx ) {
			?>
			<div class="publishmu repeating" style="border: 1px solid #ccc; padding: 10px; margin-bottom: 10px;">
				<div class="save-item">
					<p><strong><?php esc_html_e( 'Category from load', 'duplicate-publish-multisite' ); ?></strong></p>
					<select name='publish_mu_setttings[musite][<?php echo esc_html( $idx ); ?>][taxonomy]'>
						<option value=''></option>
						<?php
						$taxonomy = isset( $options['musite'][ $idx ]['taxonomy'] ) ? $options['musite'][ $idx ]['taxonomy'] : '';
						// Load Page Options.
						foreach ( $posts_options as $key => $value ) {
							echo '<option value="' . esc_html( $key ) . '" ';
							selected( $key, $taxonomy );
							echo '>' . esc_html( $value ) . '</option>';
						}
						?>
					</select>
				</div>
				<div class="save-item">
					<p><strong><?php esc_html_e( 'Site to publish', 'duplicate-publish-multisite' ); ?></strong></p>
					<select name='publish_mu_setttings[musite][<?php echo esc_html( $idx ); ?>][site]' class="site-publish" data-row="<?php echo esc_html( $idx ); ?>">
						<option value=''></option>
						<?php
						$site = isset( $options['musite'][ $idx ]['site'] ) ? $options['musite'][ $idx ]['site'] : '';
						// Load Page Options.
						foreach ( $sites_options as $key => $value ) {
							echo '<option value="' . esc_html( $key ) . '" ';
							selected( $key, $site );
							echo '>' . esc_html( $value ) . '</option>';
						}
						?>
					</select>
				</div>
				<div class="save-item">
					<p><strong><?php esc_html_e( 'Category to publish', 'duplicate-publish-multisite' ); ?></strong></p>
					<?php
					$site_target = isset( $options['musite'][ $idx ]['site'] ) ? $options['musite'][ $idx ]['site'] : '';
					$target_cat  = isset( $options['musite'][ $idx ]['target_cat'] ) ? $options['musite'][ $idx ]['target_cat'] : '';
					if ( strpos( $target_cat, '|' ) ) {
						// old method.
						$tax_string    = str_replace( '|', '-', $target_cat );
						$terms_checked = array( $tax_string );
					} else {
						$terms_checked = explode( ',', $target_cat );
					}
					$cats_target_options = $this->get_categories_from( $site_target );
					echo '<label class="category-publish" for="[musite][' . esc_html( $idx ) . '][label]">';
					// Load Page Options.
					foreach ( $cats_target_options as $key => $value ) {
						echo '<p><input type="checkbox" id="catid-row-' . esc_html( $idx ) . '-' . esc_html( $key ) . '" ';
						echo 'name="publish_mu_setttings[musite][' . esc_html( $idx ) . '][target_cat_' . esc_html( $key ) . ']"';
						echo ' value="1"';
						if ( false !== array_search( esc_html( $key ), $terms_checked ) ) {
							echo ' checked="checked" ';
						}
						echo '/>' . esc_html( $value ) . '</p>';
					}
					echo '</label>';
					?>
				</div>
				<div class="save-item">
					<p><strong><?php esc_html_e( 'Author of entries', 'duplicate-publish-multisite' ); ?></strong></p>
					<select id="authorid-row-<?php echo esc_html( $idx ); ?>" name='publish_mu_setttings[musite][<?php echo esc_html( $idx ); ?>][author]' class="author-publish">
						<option value=''></option>
						<?php
						$site_target = isset( $options['musite'][ $idx ]['site'] ) ? $options['musite'][ $idx ]['site'] : '';
						$auth_cat  = isset( $options['musite'][ $idx ]['author'] ) ? $options['musite'][ $idx ]['author'] : '';

						$authors_target_options = $this->get_authors_from( $site_target );
						// Load Page Options.
						foreach ( $authors_target_options as $key => $value ) {
							echo '<option value="' . esc_html( $key ) . '" ';
							selected( $key, $auth_cat );
							echo '>' . esc_html( $value ) . '</option>';
						}
						?>
					</select>
				</div>
				<div class="save-item">
					<a href="#" class="button alt remove"><span class="dashicons dashicons-remove"></span><?php esc_html_e( 'Remove', 'sync-ecommerce-course' ); ?></a>
				</div>
			</div>
			<?php
		}
		?>
		<a href="#" class="button repeat"><span class="dashicons dashicons-insert"></span><?php esc_html_e( 'Add Another', 'sync-ecommerce-course' ); ?></a>
		<script type="text/javascript">
		// Prepare new attributes for the repeating section
		var attrs = ['for', 'id', 'name'];
		function resetAttributeNames(section) { 
		var tags = section.find('select, input, label'), idx = section.index();
		tags.each(function() {
			var $this = jQuery(this);
			jQuery.each(attrs, function(i, attr) {
				var attr_val = $this.attr(attr);
				if (attr_val) {
					$this.attr(attr, attr_val.replace(/\[musite\]\[\d+\]\[/, '\[musite\]\['+(idx + 1)+'\]\['))
				}
			})
		})
		}

		// Clone the previous section, and remove all of the values                  
		jQuery('.remove').click(function(e){
			e.preventDefault();
			jQuery(this).parent().parent().remove();
		});

		// Clone the previous section, and remove all of the values                  
		jQuery('.repeat').click(function(e){
			e.preventDefault();
			var lastRepeatingGroup = jQuery('.repeating').last();
			var cloned = lastRepeatingGroup.clone(true)  
			cloned.insertAfter(lastRepeatingGroup);
			cloned.find("input").val("");
			cloned.find("select").val("");
			resetAttributeNames(cloned)
		});
		</script>
		<?php
	}
}

if ( is_admin() ) {
	new PUBMULT_Settings();
}
