<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @link       pixelscodex.com
 * @since      1.0.0
 *
 * @package    Invimport
 * @subpackage Invimport/lib
 */

namespace Invimport;

/**
 * Abstract class managing for setting view display and saving.
 */
abstract class Invimport_Setting_View {

	/**
	 * Type of meta.
	 *
	 * @var string
	 */
	private static $type;

	/**
	 * Class instance for the singleton.
	 *
	 * @var array
	 */
	private static $instances = array();

	/**
	 * Options used generate view properly.
	 *
	 * @var array
	 */
	protected $options;

	/**
	 * Constructor which set internal variables and view rendering filter.
	 *
	 * @param  array $options Options used to init meta view.
	 */
	public function __construct(
		$options = array()
	) {
		$this->options = $options;

		$filter_name = 'invimport_render_heading_setting_view_' . static::$type;
		add_filter( $filter_name, array( $this, 'get_heading_view' ), 10, 3 );

		$filter_name = 'invimport_render_data_setting_view_' . static::$type;
		add_filter( $filter_name, array( $this, 'get_data_view' ), 10, 3 );
	}

	/**
	 * Accessor to singleton instance.
	 *
	 * @return Invimport_Meta_View singleton instance.
	 */
	public static function get_instance() {

		$class = get_called_class();
		if ( ! isset( self::$instances[ $class ] ) ) {
			self::$instances[ $class ] = new static();
		}
		return self::$instances[ $class ];
	}

	/**
	 * Abstract function to get heading view content of the setting.
	 *
	 * @param  string $heading_view previous view.
	 * @param  array  $data Data to set setting.
	 * @return string View content.
	 */
	abstract public function get_heading_view( $heading_view, $data ): string;

	/**
	 * Abstract function to get body view content of the setting.
	 *
	 * @param  string $view_content Previous part of the body view.
	 * @param  array  $data Data to set setting.
	 * @param  mixed  $value Input value for setting inputs.
	 * @return string View content.
	 */
	abstract public function get_data_view( $view_content, $data, $value = null );
}
