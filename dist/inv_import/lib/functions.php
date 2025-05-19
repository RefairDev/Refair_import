<?php
/**
 * Init plugin such as admin menu scripts enqueue
 *
 * @link       pixelscodex.com
 * @since      1.0.0
 *
 * @package    Invimport
 */

namespace XlsInventory;

/**
 * Attached to add_menu_page()
 *
 * @return void
 */
function plugin_menu_page() {

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<div>
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

		<div id="app"></div>
	</div>
	<?php
}

/**
 * Attached to admin_enqueue_scripts by add_action() *
 *
 * @param  string $hook Name of the hook.
 * @return void
 */
function add_plugin_script( $hook ) {
	if ( 'toplevel_page_xls-deposit' !== $hook ) {
		return;
	}

	wp_register_script( 'xls_script', XLS_PLUGIN_URI . 'js/xls.js', array(), gmdate( 'YmdHis', filemtime( XLS_PLUGIN_DIR . 'js/xls.js' ) ), array( 'in_footer' => true ) );

	wp_enqueue_script( 'xls_script' );
}

/**
 * Add style sheet only on toplevel_page_xls-deposit
 *
 * @param  string $hook Current hook name.
 * @return void
 */
function add_plugin_styles( $hook ) {
	if ( 'toplevel_page_xls-deposit' !== $hook ) {
		return;
	}

	wp_enqueue_style( 'xls_styles', XLS_PLUGIN_URI . 'css/admin.css', array(), '1.0.0' );
}


/**
 * Attached to admin_menu by add_action()
 *
 * @return void
 */
function add_plugin_menu_page() {
	add_menu_page(
		PLUGIN_NAME,
		PLUGIN_MENU_NAME,
		'manage_options',
		'xls-deposit',
		'\XlsInventory\plugin_menu_page'
	);

	add_submenu_page(
		'xls-deposit',
		'Monitoring',
		'Monitoring',
		'manage_options',
		'monitoring',
		'\XlsInventory\monitoring_menu_page'
	);
}

/**
 * Load language file
 *
 * @return void
 */
function set_i18n() {

	load_plugin_textdomain(
		PLUGIN_ID,
		false,
		dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
	);
}

