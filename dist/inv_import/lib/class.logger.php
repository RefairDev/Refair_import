<?php

/**
 * Class for logging events and errors
 *
 * @package     WP Logging Class
 * @copyright   Copyright (c) 2012, Pippin Williamson
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 */

class REFAIR_Logger {


	/**
	 * Class constructor.
	 *
	 * @since 1.0
	 *
	 * @access public
	 * @return void
	 */
	public function __construct() {

		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'init', array( $this, 'register_taxonomy' ) );
		add_action( 'wp_logging_prune_routine', array( $this, 'prune_logs' ) );
		add_action( 'wp_ajax_erase_logs', array( $this, 'erase_logs' ) );
		add_action( 'admin_footer-edit.php', array( $this, 'add_erase_logs_btn' ) );
	}

	/**
	 * Allows you to tie in a cron job and prune old logs.
	 *
	 * @since 1.1
	 * @access public
	 *
	 * @uses $this->get_logs_to_prune()     Returns array of posts via get_posts of logs to prune
	 * @uses $this->prune_old_logs()        Deletes the logs that we don't want anymore
	 */
	public function prune_logs() {

		$should_we_prune = apply_filters( 'wp_logging_should_we_prune', false );

		if ( $should_we_prune === false ) {
			return;
		}

		$logs_to_prune = $this->get_logs_to_prune();

		if ( isset( $logs_to_prune ) && ! empty( $logs_to_prune ) ) {
			$this->prune_old_logs( $logs_to_prune );
		}
	} // prune_logs

	/**
	 * Deletes the old logs that we don't want
	 *
	 * @since 1.1
	 * @access private
	 *
	 * @param array/obj $logs     required     The array of logs we want to prune
	 *
	 * @uses wp_delete_post()                      Deletes the post from WordPress
	 *
	 * @filter wp_logging_force_delete_log         Allows user to override the force delete setting which bypasses the trash
	 */
	private function prune_old_logs( $logs ) {

		$force = apply_filters( 'wp_logging_force_delete_log', true );

		foreach ( $logs as $l ) {
			$id = is_int( $l ) ? $l : $l->ID;
			wp_delete_post( $id, $force );
		}
	} // prune_old_logs

	/**
	 * Returns an array of posts that are prune candidates.
	 *
	 * @since 1.1
	 * @access private
	 *
	 * @return array     $old_logs     The array of posts that were returned from get_posts
	 *
	 * @uses apply_filters()           Allows users to change given args
	 * @uses get_posts()               Returns an array of posts from given args
	 *
	 * @filter wp_logging_prune_when           Users can change how long ago we are looking for logs to prune
	 * @filter wp_logging_prune_query_args     Gives users access to change any query args for pruning
	 */
	private function get_logs_to_prune() {

		$how_old = apply_filters( 'wp_logging_prune_when', '2 weeks ago' );

		$args = array(
			'post_type'      => 'wp_log',
			'posts_per_page' => '100',
			'date_query'     => array(
				array(
					'column' => 'post_date_gmt',
					'before' => (string) $how_old,
				),
			),
		);

		$old_logs = get_posts( apply_filters( 'wp_logging_prune_query_args', $args ) );

		return $old_logs;
	} // get_logs_to_prune

	/**
	 * Log types
	 *
	 * Sets up the default log types and allows for new ones to be created
	 *
	 * @access      private
	 * @since       1.0
	 *
	 * @return     array
	 */
	private static function log_types() {
		$terms = array(
			'error',
			'event',
		);

		return apply_filters( 'wp_log_types', $terms );
	}


	/**
	 * Registers the wp_log Post Type
	 *
	 * @access      public
	 * @since       1.0
	 *
	 * @uses        register_post_type()
	 *
	 * @return     void
	 */
	public function register_post_type() {

		/* logs post type */

		$log_args = array(
			'labels'          => array( 'name' => __( 'Logs', 'wp-logging' ) ),
			'public'          => defined( 'WP_DEBUG' ) && WP_DEBUG,
			'show_ui'         => defined( 'WP_DEBUG' ) && WP_DEBUG,
			'show_in_menu'    => defined( 'WP_DEBUG' ) && WP_DEBUG,
			'query_var'       => false,
			'rewrite'         => false,
			'capability_type' => 'post',
			'supports'        => array( 'title', 'editor' ),
			'can_export'      => false,
		);
		register_post_type( 'wp_log', apply_filters( 'wp_logging_post_type_args', $log_args ) );
	}


	/**
	 * Registers the Type Taxonomy
	 *
	 * The Type taxonomy is used to determine the type of log entry
	 *
	 * @access      public
	 * @since       1.0
	 *
	 * @uses        register_taxonomy()
	 * @uses        term_exists()
	 * @uses        wp_insert_term()
	 *
	 * @return     void
	 */
	public function register_taxonomy() {

		register_taxonomy( 'wp_log_type', 'wp_log', array( 'public' => defined( 'WP_DEBUG' ) && WP_DEBUG ) );

		$types = self::log_types();

		foreach ( $types as $type ) {
			if ( ! term_exists( $type, 'wp_log_type' ) ) {
				wp_insert_term( $type, 'wp_log_type' );
			}
		}
	}

	/**
	 * add button to call an erase of all logs
	 *
	 * @return void
	 */
	function add_erase_logs_btn() {
		if ( ! isset( $_GET['post_type'] ) || $_GET['post_type'] != 'wp_log' ) {
			return false;
		}
		$ajax_url = admin_url( 'admin-ajax.php' );
		?>
		<script type="text/javascript">
			document.addEventListener("DOMContentLoaded",function(){
				let ankle = document.querySelector(".wrap"); 
				let before = document.querySelector(".wp-header-end");
		
				let eraseButton = document.createElement("a"); 
				eraseButton.classList.add("erase-logs"); 
				eraseButton.innerText = "Tout Supprimer";
				ankle.insertBefore(eraseButton, before); 

				let erasePackages = document.querySelector(".erase-logs");
				erasePackages.addEventListener('click',function requestErasePackagedProducts(params) {
					let status = document.querySelector(".status-logs");
					statusDisplay.classList.add("in-progress");					
					statusDisplay.classList.remove("success","error");
					status.innerText = "Effacement en cours";
					const data = new FormData();
		
					data.append( 'action', 'erase_logs' );
		
					fetch("<?php echo $ajax_url; ?>", {
					method: "POST",
					credentials: 'same-origin',
					body: data
					})
					.then((response) => response.json())
					.then((data) => {
						let status = document.querySelector(".status-logs");
						statusDisplay.classList.replace("in-progress","success");
						status.innerText = "Effacement effectué";
						cancelButton.classList.add("hidden"); 
						setTimeout(() => {
							window.location.reload();
						}, 1000);
					})
					.catch(function(error) {
						let status = document.querySelector(".status-logs");
						statusDisplay.classList.replace("in-progress","error");
						status.innerText = "Une erreur est survenue pendant l'effacement'";
					});
				});

				let statusDisplay = document.createElement("span"); 
				statusDisplay.classList.add("status-logs");					
				ankle.insertBefore(statusDisplay, before); 

			});
		</script>
		<style>
			.erase-logs {
				padding: 4px 8px;
				position: relative;
				top: -3px;
				text-decoration: none;
				border: 1px solid #0071a1;
				border-radius: 2px;
				text-shadow: none;
				font-weight: 600;
				font-size: 13px;
				line-height: normal;
				color: #0071a1;
				background: #f3f5f6;
				cursor: pointer;
			}
			.erase-logs{
				margin-left:5px;
			}
			.erase-logs:hover{
				background: #f1f1f1;
				border-color: #016087;
				color: #015080;
			}

			.status-logs{
				margin-left: 5px;
			}
			.status-logs.success{
				color: green;
			}
			.status-logs.in-progress{
				color: orange;
			}
			.status-logs.error{
				color: red;
			}
			</style>
		<?php
	}






	/**
	 * Check if a log type is valid
	 *
	 * Checks to see if the specified type is in the registered list of types
	 *
	 * @access      private
	 * @since       1.0
	 *
	 * @return     array
	 */
	private static function valid_type( $type ) {
		return in_array( $type, self::log_types() );
	}


	/**
	 * Create new log entry
	 *
	 * This is just a simple and fast way to log something. Use self::insert_log()
	 * if you need to store custom meta data
	 *
	 * @access      private
	 * @since       1.0
	 *
	 * @uses        self::insert_log()
	 *
	 * @return      int The ID of the new log entry
	 */
	public static function add( $title = '', $message = '', $parent = 0, $type = null ) {

		$log_data = array(
			'post_title'   => $title,
			'post_content' => $message,
			'post_parent'  => $parent,
			'log_type'     => $type,
		);

		return self::insert_log( $log_data );
	}


	/**
	 * Stores a log entry
	 *
	 * @access      private
	 * @since       1.0
	 *
	 * @uses        wp_parse_args()
	 * @uses        wp_insert_post()
	 * @uses        update_post_meta()
	 * @uses        wp_set_object_terms()
	 * @uses        sanitize_key()
	 *
	 * @return      int The ID of the newly created log item
	 */
	public static function insert_log( $log_data = array(), $log_meta = array() ) {

		$defaults = array(
			'post_type'    => 'wp_log',
			'post_status'  => 'publish',
			'post_parent'  => 0,
			'post_content' => '',
			'log_type'     => false,
		);

		$args = wp_parse_args( $log_data, $defaults );

		do_action( 'wp_pre_insert_log' );

		// store the log entry
		$log_id = wp_insert_post( $args );

		// set the log type, if any
		if ( $log_data['log_type'] && self::valid_type( $log_data['log_type'] ) ) {
			wp_set_object_terms( $log_id, $log_data['log_type'], 'wp_log_type', false );
		}

		// set log meta, if any
		if ( $log_id && ! empty( $log_meta ) ) {
			foreach ( (array) $log_meta as $key => $meta ) {
				update_post_meta( $log_id, '_wp_log_' . sanitize_key( $key ), $meta );
			}
		}

		do_action( 'wp_post_insert_log', $log_id );

		return $log_id;
	}


	/**
	 * Update and existing log item
	 *
	 * @access      private
	 * @since       1.0
	 *
	 * @uses        wp_parse_args()
	 * @uses        wp_update_post()
	 * @uses        update_post_meta()
	 *
	 * @return      bool True if successful, false otherwise
	 */
	public static function update_log( $log_data = array(), $log_meta = array() ) {

		$log_id = null;

		do_action( 'wp_pre_update_log', $log_id );

		$defaults = array(
			'post_type'   => 'wp_log',
			'post_status' => 'publish',
			'post_parent' => 0,
		);

		$args = wp_parse_args( $log_data, $defaults );

		// store the log entry
		$log_id = wp_update_post( $args );

		if ( $log_id && ! empty( $log_meta ) ) {
			foreach ( (array) $log_meta as $key => $meta ) {
				if ( ! empty( $meta ) ) {
					update_post_meta( $log_id, '_wp_log_' . sanitize_key( $key ), $meta );
				}
			}
		}

		do_action( 'wp_post_update_log', $log_id );
	}


	/**
	 * Easily retrieves log items for a particular object ID
	 *
	 * @access      private
	 * @since       1.0
	 *
	 * @uses        self::get_connected_logs()
	 *
	 * @return      array
	 */
	public static function get_logs( $object_id = 0, $type = null, $paged = null ) {
		return self::get_connected_logs(
			array(
				'post_parent' => $object_id,
				'paged'       => $paged,
				'log_type'    => $type,
			)
		);
	}


	/**
	 * Retrieve all connected logs
	 *
	 * Used for retrieving logs related to particular items, such as a specific purchase.
	 *
	 * @access  private
	 * @since   1.0
	 *
	 * @uses    wp_parse_args()
	 * @uses    get_posts()
	 * @uses    get_query_var()
	 * @uses    self::valid_type()
	 *
	 * @return  array / false
	 */
	public static function get_connected_logs( $args = array() ) {

		$defaults = array(
			'post_parent'    => 0,
			'post_type'      => 'wp_log',
			'posts_per_page' => 10,
			'post_status'    => 'publish',
			'paged'          => get_query_var( 'paged' ),
			'log_type'       => false,
		);

		$query_args = wp_parse_args( $args, $defaults );

		if ( $query_args['log_type'] && self::valid_type( $query_args['log_type'] ) ) {

			$query_args['tax_query'] = array(
				array(
					'taxonomy' => 'wp_log_type',
					'field'    => 'slug',
					'terms'    => $query_args['log_type'],
				),
			);

		}

		$logs = get_posts( $query_args );

		if ( $logs ) {
			return $logs;
		}

		// no logs found
		return false;
	}


	/**
	 * Retrieves number of log entries connected to particular object ID
	 *
	 * @access  private
	 * @since   1.0
	 *
	 * @uses    WP_Query()
	 * @uses    self::valid_type()
	 *
	 * @return  int
	 */
	public static function get_log_count( $object_id = 0, $type = null, $meta_query = null ) {

		$query_args = array(
			'post_parent'    => $object_id,
			'post_type'      => 'wp_log',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
		);

		if ( ! empty( $type ) && self::valid_type( $type ) ) {

			$query_args['tax_query'] = array(
				array(
					'taxonomy' => 'wp_log_type',
					'field'    => 'slug',
					'terms'    => $type,
				),
			);

		}

		if ( ! empty( $meta_query ) ) {
			$query_args['meta_query'] = $meta_query;
		}

		$logs = new WP_Query( $query_args );

		return (int) $logs->post_count;
	}

	/**
	 * Erase all logs
	 *
	 * @return void
	 */
	public function erase_logs() {
		$allposts = get_posts(
			array(
				'post_type'   => 'wp_log',
				'numberposts' => -1,
			)
		);
		foreach ( $allposts as $eachpost ) {
			wp_delete_post( $eachpost->ID, true );
		}
		wp_die();
	}
}
$GLOBALS['wp_logs'] = new REFAIR_Logger();