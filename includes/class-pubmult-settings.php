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
			__( 'Publish Multisite', 'publish-multisite' ),
			__( 'Publish Multisite', 'publish-multisite' ),
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
			plugins_url( 'css/admin-publishmu.css', __FILE__ ),
			false,
			PUBLISHMU_VERSION
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
			<h2><?php esc_html_e( 'Publish Multisite Settings', 'publish_multisite' ); ?>
			</h2>
			<p></p>
			<?php
			settings_errors();
			?>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'publish_mu_setttings' );
				do_settings_sections( 'pubmult-admin' );
				submit_button( 'Guardar opciones', 'primary', 'submit_settings' );
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
			__( 'Settings for publishing directly multisite', 'publish-multisite' ),
			array( $this, 'pubmult_section_info' ),
			'pubmult-admin'
		);

		add_settings_field(
			'musite',
			__( 'Site relations', 'publish-multisite' ),
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
					$sanitary_values['musite'][ $index ]['taxonomy']   = sanitize_text_field( $musite['taxonomy'] );
					$sanitary_values['musite'][ $index ]['site']       = sanitize_text_field( $musite['site'] );
					$sanitary_values['musite'][ $index ]['author']     = sanitize_text_field( $musite['author'] );
					$sanitary_values['musite'][ $index ]['target_cat'] = sanitize_text_field( $musite['target_cat'] );
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
		esc_html_e( 'Rellena a continuación los ajustes del envío de contrato.', 'publish-multisite' );
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
			$subsite_id           = get_object_vars( $subsite )['blog_id'];
			$subsite_name         = get_blog_details( $subsite_id )->blogname;
			$sites[ $subsite_id ] = $subsite_name;
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
			$posts_options[] = '--- ' . $taxonomy_obj->label . ' ---';

			$args_query  = array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'orderby'    => 'title', // menu_order, rand, date.
				'order'      => 'ASC',
			);
			$terms_array = get_terms( $args_query );
			foreach ( $terms_array as $term ) {
				$term_name = '';
				if ( 0 !== $term->parent ) {
					$term_parent = get_term_by( 'id', $term->parent, $taxonomy );
					$term_name  .= $term_parent->name . ' > ';
				}
				$term_name .= $term->name;

				$posts_options[ $taxonomy . '|' . $term->term_id ] = $term_name;
			}
		}
		if ( 0 !== $site ) {
			switch_to_blog( $original_blog_id );
		}
		return $posts_options;
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
		$size          = isset( $options['musite'] ) ? count( $options['musite'] ) : 0;

		for ( $idx = 0, $size; $idx <= $size; ++$idx ) {
			?>
			<div class="publishmu repeating" style="border: 1px solid #ccc; padding: 10px; margin-bottom: 10px;">
				<div class="save-item">
					<p><strong><?php esc_html_e( 'Category from load', 'publish-multisite' ); ?></strong></p>
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
					<p><strong><?php esc_html_e( 'Site to publish', 'publish-multisite' ); ?></strong></p>
					<select name='publish_mu_setttings[musite][<?php echo esc_html( $idx ); ?>][site]'>
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
					<p><strong><?php esc_html_e( 'Category to publish', 'publish-multisite' ); ?></strong></p>
					<select name='publish_mu_setttings[musite][<?php echo esc_html( $idx ); ?>][target_cat]'>
						<option value=''></option>
						<?php
						$site_target = isset( $options['musite'][ $idx ]['site'] ) ? $options['musite'][ $idx ]['site'] : '';
						$target_cat  = isset( $options['musite'][ $idx ]['target_cat'] ) ? $options['musite'][ $idx ]['target_cat'] : '';

						$cats_target_options = $this->get_categories_from( $site_target );
						// Load Page Options.
						foreach ( $cats_target_options as $key => $value ) {
							echo '<option value="' . esc_html( $key ) . '" ';
							selected( $key, $target_cat );
							echo '>' . esc_html( $value ) . '</option>';
						}
						?>
					</select>
				</div>
				<div class="save-item">
					<p><strong><?php esc_html_e( 'Author' ); ?></strong></p>
					<input type="text" size="30" name="publish_mu_setttings[musite][<?php echo esc_html( $idx ); ?>][author]" value="<?php echo isset( $options['musite'][ $idx ]['author'] ) ? esc_html( $options['musite'][ $idx ]['author'] ) : ''; ?>" />
				</div>
				<div class="save-item">
					<p><a href="#" class="repeat"><?php esc_html_e( 'Add Another', 'publish-multisite' ); ?></a></p>
				</div>
			</div>
			<?php
		}
		?>
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
