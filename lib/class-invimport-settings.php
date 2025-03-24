<?php
/**
 * The admin-specific settings of the plugin.
 *
 * @link       pixelscodex.com
 * @since      1.0.0
 *
 * @package    Invimport
 * @subpackage Invimport/admin
 */

use Invimport\SettingView;

/**
 * Undocumented class
 */
class Invimport_Settings {



	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_id    The ID of this plugin.
	 */
	private $plugin_id;

	/**
	 * The fancy name of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The fancy name of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;


	/**
	 * All Settings instances.
	 *
	 * @var array
	 */
	private static $setting_classes = array();


	/**
	 * Settings views namespace
	 *
	 * @var string
	 */
	private static $settings_views_namespace = '\\Invimport\\Settings\\Views\\';

	/**
	 * Plugin settings page slug
	 *
	 * @var string
	 */
	private $invimport_settings_page_slug = 'invimport-extras-settings';





	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string $plugin_name       The name of this plugin.
	 * @param      string $version    The version of this plugin.
	 */
	public function __construct( $plugin_id, $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->plugin_id   = $plugin_id;
		$this->version     = $version;

		$this->construct_settings_views();
	}

	/**
	 * Add settings page to global admin menu
	 */
	public function add_settings_page() {
		add_submenu_page(
			'xls-deposit',
			__( 'Settings', 'invimport' ),
			__( 'Settings', 'invimport' ),
			'manage_options',
			$this->invimport_settings_page_slug,
			array( $this, 'build_settings_page' )
		);
	}

	/**
	 * Undocumented function
	 *
	 * @return void
	 */
	protected function construct_settings_views() {
		$settings_views_dir = plugin_dir_path( __DIR__ ) . 'lib/partials';

		$files = $this->require( $settings_views_dir );

		foreach ( $files as $file ) {
			$classes = $this->file_get_php_classes( $file );
			foreach ( $classes as $class ) {
				try {
					$full_class_name = self::$settings_views_namespace . $class;
					if ( property_exists( $full_class_name, 'type' ) ) {
						self::$setting_classes[ $full_class_name::$type ] = $full_class_name::get_instance();
					}
				} catch ( \Exception $exception ) {
					trigger_error( 'Setting control has no type:' . $full_class_name );
				}
			}
		}
	}

	/**
	 * Get data to build settings page.
	 *
	 * @return array Page build data.
	 */
	protected function get_ui_data() {

		return array(
			array(
				'slug'     => 'mainsettings',
				'name'     => __( 'Global settings', 'invimport' ),
				'save'     => true,
				'settings' => array(
					array(
						'name'        => 'invimport_google_api_key',
						'label'       => __( 'Google API key', 'invimport' ),
						'type'        => 'text',
						'description' => '',
						'options'     => array(),
					),
				),
			),

		);
	}

	/**
	 * Build settings page
	 */
	public function build_settings_page() {

		if ( ! empty( $_REQUEST ) ) {
			if ( isset( $_REQUEST['submit'] ) ) {
				check_admin_referer( plugin_basename( XLS_PLUGIN_ROOT_FILE ), 'invimport_options' );
				$this->update_invimport_options( $_REQUEST );

			}
		}

		echo "<div class='postbox-container' style='display:block;width:100%;'>";
		echo "<form method='post'>";

		wp_nonce_field( plugin_basename( XLS_PLUGIN_ROOT_FILE ), 'invimport_options' );

		$display_save_button = true;

		$active_tab = 'mainsettings';
		if ( isset( $_REQUEST['tab'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$active_tab = $_REQUEST['tab']; // phpcs:ignore WordPress.Security.NonceVerification
		}

		printf( "<input type='hidden' name='tab' value='%s' />", esc_attr( $active_tab ) );

		$this_page = '?page=' . $this->invimport_settings_page_slug;

		/**
		 * Allows adding new tabs to the invimport menu.
		 *
		 * @param array $tabs An array of arrays defining the tabs.
		 *
		 * @return array Filtered tab array.
		 */
		$tabs = apply_filters( 'invimport_tabs', $this->get_ui_data() );
		?>
	<h2 class="nav-tab-wrapper">
		<?php
		array_walk(
			$tabs,
			function ( $tab ) use ( $this_page, $active_tab ) {
				?>
				<a href="<?php echo esc_attr( $this_page ); ?>&amp;tab=<?php echo esc_attr( $tab['slug'] ); ?>"
				class="nav-tab <?php echo esc_attr( $tab['slug'] === $active_tab ? 'nav-tab-active' : '' ); ?>">
				<?php echo esc_html( $tab['name'] ); ?></a>
				<?php
			}
		);
		?>
	</h2>
	
		<?php
		$current_tab = $tabs[ array_search( $active_tab, wp_list_pluck( $tabs, 'slug' ), true ) ];
		if ( ! $current_tab['save'] ) {
			$display_save_button = false;
		}

		$this->build_tab_content( $current_tab );

		if ( $display_save_button ) :
			?>
	
		<input type='submit' name='submit' value='<?php esc_attr_e( 'Save the options', 'invimport' ); ?>' class='button button-primary' />
	
		<?php endif; ?>
	
		</form>
	</div>
	
		<?php
	}

	/**
	 * Undocumented function
	 *
	 * @param  array $tab_data Data to build settings tabs.
	 * @return void
	 */
	protected function build_tab_content( $tab_data ) {

		?>
		<div id="<?php echo esc_attr( $tab_data['slug'] ); ?>_tab">
			<table class="form-table" role='presentation'>
				<tbody>
					<?php
					foreach ( $tab_data['settings'] as $setting ) {

						$setting_default_value = null;

						if ( array_key_exists( 'default', $setting ) ) {
							$setting_default_value = $setting['default'];
						}

						if ( null === $setting_default_value ) {
							apply_filters(
								'invimport_get_default_value_' . $setting['type'],
								$setting_default_value,
							);
						}

						$setting_value = get_option(
							$setting['name'],
							$setting_default_value
						);

						?>
						<tr>
							<th>
							<?php
							$heading_view = '';
							$heading_view = apply_filters(
								'invimport_render_heading_setting_view_' . $setting['type'],
								$heading_view,
								$setting,
							);
							echo $heading_view;

							?>
							</th>
							<td>
							<?php
							$data_view = '';
							$data_view = apply_filters(
								'invimport_render_data_setting_view_' . $setting['type'],
								$data_view,
								$setting,
								$setting_value
							);
							echo $data_view;
							?>
							</td>
						</tr>
						<?php
					}
					?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Update options from request array values.
	 *
	 * @param  array $request Request from user.
	 * @return void
	 */
	protected function update_invimport_options( array $request ) {

		$options = array(
			'invimport_google_api_key' => true,
		);

		array_walk(
			$options,
			function ( $autoload, $option ) use ( $request ) {
				if ( isset( $request[ $option ] ) ) {
					update_option( $option, $request[ $option ], $autoload );
				}
			}
		);
	}

	/**
	 * Undocumented function
	 *
	 * @param  string $dir Diretory to require.
	 * @param  array  $exclude Exclude files.
	 * @return array Files that have been required.
	 */
	public static function require( $dir, $exclude = array() ) {
		$files_returned = array();
		$files          = array_diff( scandir( $dir, 1 ), array( '.', '..', 'index.php' ) );

		foreach ( $files as $file ) {
			if ( ! in_array( $file, $exclude, true ) && ! in_array( basename( $file ), $exclude, true ) ) {
				if ( ! is_dir( $dir . '/' . $file ) ) {
					$returned         = require_once $dir . '/' . $file;
					$files_returned[] = $dir . '/' . $file;
				} else {
					self::require( $dir . '/' . $file, $exclude = array() );
				}
			}
		}
		return $files_returned;
	}

	/**
	 * Get classes in a file.
	 *
	 * @param  string $filepath Filepath.
	 * @return array Classes found in file in parameters.
	 */
	public static function file_get_php_classes( $filepath ) {
		$php_code = file_get_contents( $filepath );
		$classes  = self::get_php_classes( $php_code );
		return $classes;
	}

	/**
	 * Get classes from php code.
	 *
	 * @param  string $php_code php code read from a file.
	 * @return array Classes found in php code.
	 */
	public static function get_php_classes( $php_code ) {
		$classes = array();
		$tokens  = token_get_all( $php_code );
		$count   = count( $tokens );
		for ( $i = 2; $i < $count; $i++ ) {
			if ( T_CLASS === $tokens[ $i - 2 ][0]
				&& T_WHITESPACE === $tokens[ $i - 1 ][0]
				&& T_STRING === $tokens[ $i ][0] ) {

				$class_name = $tokens[ $i ][1];
				$classes[]  = $class_name;
			}
		}
		return $classes;
	}
}
