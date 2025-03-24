<?php
/**
 * The admin-partials setting view of the plugin.
 *
 * @link       pixelscodex.com
 * @since      1.0.0
 *
 * @package    Invimport
 * @subpackage Invimport/admin
 */

namespace Invimport\Settings\Views;

use Invimport\Invimport_Setting_View;

if ( ! class_exists( 'Invimport_Input_Setting_View' ) ) {
	/**
	 * Class managing input setting view display and saving.
	 */
	class Invimport_Input_Setting_View extends Invimport_Setting_View {


		public function __construct(
			$options = array()
		) {
			parent::__construct( $options );
		}

		/**
		 * Undocumented function
		 *
		 * @param  [type] $type
		 * @return void
		 */
		public function set_type( $type ) {
			$this->input_type = $type;
		}

		/**
		 * Get body html content of the setting.
		 *
		 * @param  string $view_content Previous part of the view.
		 * @param  array  $data Data to use to generate metabox content.
		 * @param  mixed  $value Value to set to metabox inputs.
		 * @return string Body view with this setting part.
		 */
		public function get_data_view( $view_content, $data, $value = null ) {
			if ( isset( $data['id'] ) ) {
				$id = $data['id'];} else {
				$re    = '/(.*)\[([0-9]+)\]/m';
				$subst = '$1-$2';
				$id    = preg_replace( $re, $subst, $data['name'] );
				}
				ob_start();
				?><p>
				<input type="<?php echo esc_attr( $this->input_type ); ?>" name="<?php echo esc_attr( $data['name'] ); ?>" id="<?php echo esc_attr( $id ); ?>" class="meta-video regular-text" value="<?php echo $value; ?>"/>
			</p>
			<?php
			$meta_viewcontent = ob_get_clean();
			return $view_content . $meta_viewcontent;
		}

		/**
		 * Get heading html content of the setting.
		 *
		 * @param  string $heading_view Previous part of the view.
		 * @param  array  $data Data to use to generate setting content.
		 * @return string Heading view with this setting part.
		 */
		public function get_heading_view( $heading_view, $data ): string {
			return $data['label'];
		}
	}

}
