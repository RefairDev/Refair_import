<?php

namespace XlsInventory;

/**
 * Attached to add_menu_page()
 */
function monitoring_menu_page() {
	global $plugin_page;

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$params = $_REQUEST; ?>
	<div>
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
		<a class="restart-operations">Relancer</a> <a class="remove-all-operations">Supprimer toutes les opérations</a>
		<p class="worker-status"></p>
		<p class="global-status"></p>
		<div class="batches">
		<?php
		$batches = get_batches();
		if ( count( $batches ) > 0 ) {
			foreach ( $batches as $key => $batch ) {
				?>
				<div class="batch-wrapper-inner">
					<h2><?php echo $batch->key; ?></h2>
					<a class="remove-operation" data-batch="<?php echo $batch->key; ?>">Supprimer l'opération</a>
					<span class="status-display"></span>	
					<table>
						<tbody>
							<?php
							foreach ( $batch->data as $raw_item ) {
								$item = json_decode( $raw_item, true );
								?>
								<tr>
									<?php
									foreach ( $item as $key => $field ) {
										?>
																				<td style="font-weight:bold"><?php echo $key; ?></td><td><?php echo $field; ?></td>
										<?php
									}
									?>
								</tr>
								<?php
							}
							?>
						</tbody>
					</table>
				</div>
				<?php
			}
		}
		if ( count( $batches ) < 1 ) {
			?>
			<p><?php esc_html_e( 'No task in queue', 'invimport' ); ?></p>
			<?php
		}
		?>
		</div>
	</div>
	<?php
}

/**
 * Undocumented function
 *
 * @return array
 */
function get_batches() {
	global $wpdb;

	$batches      = array();
	$table        = $wpdb->options;
	$column       = 'option_name';
	$key_column   = 'option_id';
	$value_column = 'option_value';

	if ( is_multisite() ) {
		$table        = $wpdb->sitemeta;
		$column       = 'meta_key';
		$key_column   = 'meta_id';
		$value_column = 'meta_value';
	}

	$key = $wpdb->esc_like( 'wp_generator_worker_batch_' ) . '%';

	$results = $wpdb->get_results(
		$wpdb->prepare(
			"
		SELECT *
		FROM {$table}
		WHERE {$column} LIKE %s
		ORDER BY {$key_column} ASC
	",
			$key
		)
	);

	if ( count( $results ) > 0 ) {
		foreach ( $results as $key => $result ) {
			$batch       = new \stdClass();
			$batch->key  = $result->$column;
			$batch->data = maybe_unserialize( $result->$value_column );
			$batches[]   = $batch;
		}
	}

	return $batches;
}


/**
 * add button to call an erase of all logs
 *
 * @return void
 */
function add_operations_btns() {
	if ( ! isset( $_GET['page'] ) || ( false === strpos( $_GET['page'], 'monitoring' ) ) ) {
		return false;
	}
	$ajax_url = admin_url( 'admin-ajax.php' );
	?>
	<script type="text/javascript">
		document.addEventListener("DOMContentLoaded",function(){

			/* Restart operations */
			let restartOpe = document.querySelector(".restart-operations");
			restartOpe.addEventListener('click',function requestRestartOperation(params) {
				let statusDisplay = params.target.parentNode.querySelector(".status-display");
				statusDisplay.classList.add("in-progress");					
				statusDisplay.classList.remove("success","error");
				statusDisplay.innerText = "Redémarrage en cours";
				

				const data = new FormData();	
				data.append( 'action', 'restart_operation' );
	
				fetch("<?php echo $ajax_url; ?>", {
				method: "POST",
				credentials: 'same-origin',
				body: data
				})
				.then((response) => {
					if ( 200 === response.status ) {
						statusDisplay.classList.replace("in-progress","success");
						statusDisplay.innerText = "Relance effectuée";
						setTimeout(() => {
							window.location.reload();
						}, 1000);
					}
				})
				.catch(function(error) {
					let status = document.querySelector(".status-display");
					statusDisplay.classList.replace("in-progress","error");
					status.innerText = "Une erreur est survenue pendant la relance"+error.msg;
				});
			});

			/* Remove All operations */

			let removeAllOpe = document.querySelectorAll(".remove-all-operations");
			removeAllOpe.forEach(node => node.addEventListener('click',function requestRemoveAllOperations(params) {

				let statusDisplay = document.querySelector(".global-status");
				statusDisplay.classList.add("in-progress");					
				statusDisplay.classList.remove("success","error");
				statusDisplay.innerText = "Suppression en cours";
				
				let removeOpe = document.querySelectorAll(".remove-operation");

				let opeKeys = Array.from(removeOpe).map(elt => elt.attributes['data-batch'].value);

				const data = new FormData();	
				data.append( 'action', 'remove_all_operations' );
				data.append( 'keys', JSON.stringify(opeKeys) );
	
				fetch("<?php echo $ajax_url; ?>", {
				method: "POST",
				credentials: 'same-origin',
				body: data
				})
				.then((response) => {
					if ( 200 === response.status ) {
						statusDisplay.classList.replace("in-progress","success");
						statusDisplay.innerText = "Suppressions effectuées";
						setTimeout(() => {
							window.location.reload();
						}, 1000);
					}
				})
				.catch(function(error) {
					let status = document.querySelector(".status-display");
					statusDisplay.classList.replace("in-progress","error");
					status.innerText = "Une erreur est survenue pendant l'annulation'"+error.msg;
				});
			})
			);

			/* Remove one operation */

			let removeOpe = document.querySelectorAll(".remove-operation");
			removeOpe.forEach(node => node.addEventListener('click',function requestRemoveOperation(params) {

				let statusDisplay = params.target.parentNode.querySelector(".status-display");
				statusDisplay.classList.add("in-progress");					
				statusDisplay.classList.remove("success","error");
				statusDisplay.innerText = "Suppression en cours";
				

				const data = new FormData();	
				data.append( 'action', 'remove_operation' );
				data.append( 'key', params.target.attributes['data-batch'].value );
	
				fetch("<?php echo $ajax_url; ?>", {
				method: "POST",
				credentials: 'same-origin',
				body: data
				})
				.then((response) => {
					if ( 200 === response.status ) {
						statusDisplay.classList.replace("in-progress","success");
						statusDisplay.innerText = "Suppression effectuée";
						setTimeout(() => {
							window.location.reload();
						}, 1000);
					}
				})
				.catch(function(error) {
					let status = document.querySelector(".status-display");
					statusDisplay.classList.replace("in-progress","error");
					status.innerText = "Une erreur est survenue pendant l'annulation'"+error.msg;
				});
			})
			);

			

			const data = new FormData();	
				data.append( 'action', 'worker_status' );

			fetch("<?php echo $ajax_url; ?>", {
				method: "POST",
				credentials: 'same-origin',
				body: data
				})
				.then((response) => response.json())
				.then((data) => {
					let workerStatus = document.querySelector(".worker-status");
					workerStatus.innerText ="Aucune opération en cours";
					if (data['processing'] || data['processing'] == "true") {workerStatus.innerText ="Opération en cours"}
				})
				.catch(function(error) {
					let workerStatus = document.querySelector(".worker-status");
					workerStatus.innerText ="Erreur sur l'acquisition de statut d'opération'";
				});
		})
		;
	</script>
	<style>

		</style>
	<?php
}

function add_monitoring_page_styles() {
	?>
	<style>
		.batches{
			display: flex;
			flex-wrap: wrap;
			flex-direction: column;
			row-gap: 0.5rem;
		}
		.batch-wrapper-inner{
			display: inline-block;
			border: 1px black solid;
			padding:0.5rem;
			display:inline-block;
		}
		.remove-operation, .restart-operations, .remove-all-operations {
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
		.remove-operation, .restart-operations, .remove-all-operations{
			margin-left:5px;
		}
		.remove-operation:hover, .restart-operations:hover, .remove-all-operations:hover{
			background: #f1f1f1;
			border-color: #016087;
			color: #015080;
		}

		.status-display{
			margin-left: 5px;
		}
		.status-display.success{
			color: green;
		}
		.status-display.in-progress{
			color: orange;
		}
		.status-display.error{
			color: red;
		}
	</style>
	<?php
}


add_action( 'admin_footer', '\XlsInventory\add_operations_btns' );
add_action( 'admin_footer', '\XlsInventory\add_monitoring_page_styles' );
