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
require_once 'class-invimport-input-setting-view.php';


class Invimport_Text_Setting_View extends Invimport_Input_Setting_View {

	public static $type = 'text';

	public function __construct(
		$options = array()
	) {
		parent::__construct(
			$options = array()
		);
		$this->set_type( 'text' );
	}
}

class NumberSettingView extends Invimport_Input_Setting_View {

	public static $type = 'number';

	public function __construct(
		$options = array()
	) {
		parent::__construct(
			$options = array()
		);
		$this->set_type( 'number' );
	}
}

class DateSettingView extends Invimport_Input_Setting_View {

	public static $type = 'date';

	public function __construct(
		$options = array()
	) {
		parent::__construct(
			$options = array()
		);
		$this->set_type( 'date' );
	}
}

class TimeSettingView extends Invimport_Input_Setting_View {

	public static $type = 'time';

	public function __construct(
		$options = array()
	) {
		parent::__construct(
			$options = array()
		);
		$this->set_type( 'time' );
	}
}


class EmailSettingView extends Invimport_Input_Setting_View {

	public static $type = 'mail';

	public function __construct(
		$options = array()
	) {
		parent::__construct(
			$options = array()
		);
		$this->set_type( 'email' );
	}
}

class LongTextSettingView extends Invimport_Setting_View {

	public static $type = 'long';

	public function __construct(
		$options = array()
	) {
		parent::__construct(
			$options = array()
		);
	}

	public function get_data_view( $view_content, $data, $value = null ) {
		ob_start();
		?>
		<p>
			<textarea style="max-width: 25em; min-height: 120px;" name="<?php echo $data['name']; ?>" id="<?php echo $data['name']; ?>" class="regular-text"><?php echo $value; ?></textarea>
		</p>
		<?php
		$view_setting = ob_get_clean();
		return $view_content . $view_setting;
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

class EditorSettingView extends Invimport_Setting_View {

	public static $type = 'editor';

	public function __construct(
		$options = array()
	) {
		parent::__construct(
			$options = array()
		);
	}

	public function get_data_view( $view_content, $data, $value = null ) {
		global $post;
		$settings = array(
			'textarea_name' => $data['name'],
			'quicktags'     => array( 'buttons' => 'em,strong,link' ),
			'tinymce'       => array(
				'theme_advanced_buttons1' => 'bold,italic,strikethrough,separator,bullist,numlist,separator,blockquote,separator,justifyleft,justifycenter,justifyright,separator,link,unlink,separator,undo,redo,separator',
				'theme_advanced_buttons2' => '',
			),
			'editor_css'    => '<style>.wp-editor-area{height:175px; width:100%;}</style>',
		);

		$editor_id = $data['id'] . '-' . $post->ID;
		ob_start();
		wp_editor( htmlspecialchars_decode( $value, ENT_QUOTES ), $editor_id, $settings );
		$out           = ob_get_contents();
		$js            = wp_json_encode( $out );
		$id_editor_ctn = $editor_id . '-ctn';
		ob_clean();
		?>
		<div id="<?php echo $id_editor_ctn; ?>"></div>
		<script>
		setTimeout(function() {
				// inject editor later
				var id_ctn = '#<?php echo $id_editor_ctn; ?>';
				jQuery(id_ctn).append(<?php echo $js; ?>);
				// init editor
				setTimeout(function() {
					// find the editor button and simulate a click on it
					jQuery('#<?php echo $editor_id; ?>-tmce').trigger('click');
				},   500);
		}, 1000);
		</script>
		<?php
		$out = ob_get_contents();
		ob_get_clean();

		return $view_content . $out;
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

class KeyValueSettingView extends Invimport_Setting_View {
	public static $type = 'pair';

	public function __construct(
		$options = array()
	) {
		parent::__construct(
			$options = array()
		);
	}

	public function get_data_view( $view_content, $data, $value = null ) {
		$key_value   = '';
		$value_value = '';
		if ( ! empty( $value ) && is_array( $value ) ) {
			if ( array_key_exists( 'key', $value ) ) {
				$key_value = 'value="' . $value['key'] . '"';}
			if ( array_key_exists( 'value', $value ) ) {
				$value_value = 'value="' . $value['value'] . '"';}
		}
		$viewcontent = '<p>
        <input type="text" name="' . $data['name'] . '[key]" id="' . $data['name'] . '-key" ' . $key_value . '/>
        <input type="text" name="' . $data['name'] . '[value]" id="' . $data['name'] . '-value" ' . $value_value . '/>
        </p>';
		return $viewcontent;
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
