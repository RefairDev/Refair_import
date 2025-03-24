<?php
/**
 * Plugin Name: Import de site d'inventaire REFAIR
 * Description: Scan un fichier xlsx et extrait les éléments de site d'inventaire
 * Plugin URI:
 * Author: Thomas Vias
 * Version: 0.1.0
 *
 * @package Invimport
 */

defined( 'ABSPATH' ) || die( 'No script kiddies please!' );

define( 'PLUGIN_VERSION', '0.1' );
define( 'PLUGIN_ID', 'invimport' );
define( 'PLUGIN_NAME', 'Import Inventaires REFAIR' );
define( 'PLUGIN_MENU_NAME', 'Imports REFAIR' );
define( 'XLS_PLUGIN_ROOT_FILE', __FILE__ );
define( 'XLS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'XLS_PLUGIN_URI', plugin_dir_url( __FILE__ ) );
define( 'GEOJSON_FILE', plugin_dir_path( __FILE__ ) . 'geojson/IRIS_BM_coords_2.geojson' );


require_once XLS_PLUGIN_DIR . 'vendor/autoload.php';
require_once XLS_PLUGIN_DIR . 'lib/class-invimport-settings.php';
require_once XLS_PLUGIN_DIR . 'lib/class-invimport-setting-view.php';

require_once XLS_PLUGIN_DIR . 'lib/class-xlsapi.php';
require_once XLS_PLUGIN_DIR . 'lib/point_in_multipolygon.php';
require_once XLS_PLUGIN_DIR . 'lib/monitoring.php';
require_once XLS_PLUGIN_DIR . 'lib/functions.php';

$api = new \XlsInventory\Xlsapi();

add_action( 'rest_api_init', array( $api, 'register_routes' ) );

add_action( 'admin_menu', '\XlsInventory\add_plugin_menu_page' );

$plugin_settings = new Invimport_Settings( PLUGIN_NAME, PLUGIN_ID, PLUGIN_VERSION );
add_action( 'admin_menu', array( $plugin_settings, 'add_settings_page' ) );


add_action( 'admin_enqueue_scripts', '\XlsInventory\add_plugin_script' );
add_action( 'admin_enqueue_scripts', '\XlsInventory\add_plugin_styles' );

add_action( 'plugins_loaded', '\XlsInventory\set_i18n' );
