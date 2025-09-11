<?php
/**
 * The back-end data processing functionality of the plugin.
 *
 * @link       pixelscodex.com
 * @since      1.0.0
 *
 * @package    Invimport
 * @author     Thomas Vias <t.vias@pixelscodex.com>
 */

namespace XlsInventory;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Class managing excel file data extraction
 */
class Xlsapi {

	/**
	 *  Rest Path
	 *
	 *  @var string $namespace  Rest url path
	 */
	private static $namespace = 'xlsinv/v1';

	/**
	 * Status of the import.
	 *
	 * @var array
	 */
	private $status = array();

	/**
	 * Status matrix correspondance.
	 *
	 * @var array
	 */
	private $status_level = array(
		'info'    => '[INFO]',
		'warning' => '[WARNING]',
		'error'   => '[ERROR]',
		'fatal'   => '[FATAL]',
	);

	/**
	 * During import is Quantity should be updated
	 *
	 * @var boolean
	 */
	private $update_qty = true;

	/**
	 * Routes stored before setting during concerned action hook callback.
	 *
	 * @var array
	 */
	protected $routes = array();

	/**
	 * Constructor of the XlsApi class, setting REST routes.
	 */
	public function __construct() {

		$this->routes = array(
			'/upload-deposit'    => array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_deposit' ),
				'permission_callback' => function () {
					return true;
				},
			),
			'/get-iris'          => array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_iris' ),
				'permission_callback' => function () {
					return true;
				},
			),
			'/get-insee-code'    => array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_insee_code' ),
				'permission_callback' => function () {
					return true;
				},
			),
			'/locality-geometry' => array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'set_locality_geometry' ),
				'permission_callback' => function () {
					return true;
				},
			),
			'/geocode'           => array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_geocode' ),
				'permission_callback' => function () {
					return true;
				},
			),
		);
	}

	/**
	 * Attached to rest-api-init by add_action()
	 */
	public function register_routes() {
		foreach ( $this->routes as $route => $args ) {
			register_rest_route(
				self::$namespace,
				$route,
				$args
			);
		}
	}

	/**
	 * Handle list an store it.
	 *
	 * @param  WP_REST_Request $request Converted JSON file.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_deposit( $request ) {

		try {
			$result = json_decode( $request->get_body() );
		} catch ( \Throwable $t ) {
			$result   = false;
			$response = new \WP_Error( 'malformed_data', "can't get data from request", array( 'status' => 400 ) );
		}

		if ( false !== $result ) {
			$this->set_should_update_qty( $result->update_qty );
			$site_deposit_record_return      = $this->manage_deposit_site( $result->siteData );
			$materials_deposit_record_return = $this->manage_deposit_materials( $result->depositData );
		}

		if ( ! isset( $response ) ) {
			$response = new \WP_REST_Response();
			if ( $this->status_has_error() ) {
				$response->set_status( 206 );
			} else {
				$response->set_status( 200 );
			}
			$response->set_data( $this->status );
		}

		return $response;
	}

	/**
	 * Setter of update_qty variable
	 *
	 * @param  boolean $update_qty Is quantity has to be updated.
	 * @return void
	 */
	protected function set_should_update_qty( $update_qty ) {
		$this->update_qty = $update_qty;
	}

	/**
	 * Getter of update_qty variable
	 *
	 * @return boolean update_qty value.
	 */
	protected function should_update_qty() {
		return $this->update_qty;
	}

	/**
	 * Set deposit according to input data (search existing and insert with correct input data ).
	 *
	 * @param  object $site_data input data to create deposit.
	 * @return int Id of the deposit on success or 0 on failure.
	 */
	protected function manage_deposit_site( $site_data ) {

		$id = $this->search_deposit_site( $site_data->deposit_name );
		return $this->insert_deposit( $site_data, $id );
	}

	/**
	 * Search deposit post id according to reference metadata
	 *
	 * @param  string $ref deposit reference.
	 * @return int deposit post id ( 0 if not found ).
	 */
	protected function search_deposit_site( $ref ) {
		$query_args_meta = array(
			'posts_per_page' => -1,
			'post_type'      => 'deposit',
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'     => 'reference',
					'value'   => $ref,
					'compare' => 'LIKE',
				),
			),
		);

		$posts = get_posts( $query_args_meta );
		if ( 0 === intval( count( $posts ) ) ) {
			$returned = 0;
		} else {
			$returned = $posts[0]->ID;
		}

		return $returned;
	}

	/**
	 * Undocumented function
	 *
	 * @param  [type]  $site_data
	 * @param  integer $site_id
	 * @return void
	 */
	protected function insert_deposit( $site_data, $site_id = 0 ) {

		$post_id           = 0;
		$thumbnail_picture = null;

		$pictures = array();

		if ( property_exists( $site_data, 'thumbnail' ) ) {
			$thumbnail_picture = $this->extract_deposit_pictures( $site_data->thumbnail, $site_data->deposit_name );
		} else {
			$this->store_status( $this->status_level['info'], 'deposit', 'Aucune photo de mise en avant à enregistrer' );
		}

		$pictures = array_merge( $pictures, $thumbnail_picture );

		if ( property_exists( $site_data, 'photos' ) ) {
			$gallery_pictures = $this->extract_deposit_pictures( $site_data->photos, $site_data->deposit_name );
		} else {
			$this->store_status( $this->status_level['info'], 'deposit', 'Aucune photo de galerie à enregistrer' );
		}

		$pictures = array_merge( $pictures, $gallery_pictures );

		$postarr = array(
			'post_title'  => $site_data->building_name,
			'post_type'   => 'deposit',
			'post_status' => 'auto-draft',
			'post_author' => get_current_user_id(),
		);

		if ( property_exists( $site_data, 'slug' ) && ! empty( $site_data->slug ) ) {
			$postarr['post_name'] = sanitize_title( $site_data->slug );
		}

		if ( property_exists( $site_data, 'content' ) ) {
			$postarr['post_content'] = $site_data->content;
		}

		if ( ! is_nan( $site_id ) && 0 !== $site_id ) {
			$postarr['ID'] = $site_id;
		}
		if ( isset( $pictures ) ) {
			if ( is_array( $pictures ) ) {
				$postarr['meta_input']['galery'] = $pictures;
			} else {
				$this->store_status( $this->status_level['error'], 'deposit', 'Une erreur est survenue lors de l\'enregistrement des images du gisement' );
			}
		}

		if ( 0 !== intval( $site_id ) ) {
			$post_id = wp_update_post( $postarr );
		} else {
			$post_id = wp_insert_post( $postarr );
		}
		if ( ! is_nan( $post_id ) && 0 !== $post_id ) {

			$meta_input = array(
				'location'             => json_decode( json_encode( $site_data->address ), true ),
				'reference'            => $site_data->deposit_name,
				'dismantle_date'       => $site_data->dismantle_date,
				'availability_details' => $site_data->availability_details,
				'plus_details'         => $site_data->plus_details,
				'insee_code'           => $site_data->insee_code,
			);

			if ( ! empty( $meta_input ) ) {
				foreach ( $meta_input as $field => $value ) {
					$meta_record_return = update_post_meta( $post_id, $field, $value );
					if ( false === $meta_record_return ) {
						$json_value = json_encode( $value );
						$this->store_status( $this->status_level['info'], 'deposit', "la donnée {$field} ({$json_value}) n'a pas était enregistrée ou modifiée" );
					}
				}
			}

			/**
			 *  Set commune term.
			 */

			$term_id = null;
			$term    = get_term_by( 'name', $site_data->city, 'city' );
			if ( is_object( $term ) && property_exists( $term, 'term_taxonomy_id' ) ) {
				$term_id = $term->term_taxonomy_id;
			}
			if ( null === $term_id ) {
				$this->store_status( $this->status_level['info'], 'deposit', "Aucune catégorie de Ville n'a été trouvé, une nouvelle a été créé" );
				$term_ids = wp_insert_term( $site_data->city, 'city' );
				$term_id  = array( $term_ids['term_taxonomy_id'] );
			}
			wp_set_post_terms( $post_id, $term_id, 'city' );

			/**
			 *  Set type term.
			 */

			$term_id = null;
			$term    = get_term_by( 'name', $site_data->provider, 'deposit_type' );
			if ( is_object( $term ) && property_exists( $term, 'term_taxonomy_id' ) ) {
				$term_id = $term->term_taxonomy_id;
			}
			if ( null === $term_id ) {
				$this->store_status( $this->status_level['info'], 'deposit', "Aucune catégorie de fournisseur n'a été trouvé, une nouvelle a été créé" );
				$term_ids = wp_insert_term( $site_data->provider, 'deposit_type' );
				update_post_meta( $term_ids['term_taxonomy_id'], 'color', 'blue' );
				$term_id = array( $term_ids['term_taxonomy_id'] );
			}
			wp_set_post_terms( $post_id, $term_id, 'deposit_type' );

			/**
			 *  Set images.
			 */

			if ( isset( $thumbnail_picture ) && is_array( $thumbnail_picture ) && count( $thumbnail_picture ) > 0 ) {
				set_post_thumbnail( $post_id, $thumbnail_picture[0]['id'] );
			}

			if ( ! isset( $thumbnail_picture ) && isset( $pictures ) && is_array( $pictures ) && count( $pictures ) > 0 ) {
				set_post_thumbnail( $post_id, $pictures[0]['id'] );
			} elseif ( -1 === intval( $pictures ) ) {
					$this->store_status( $this->status_level['error'], 'deposit', "Une erreur est survenue lors de l'enregistrement des images du gisement" );
			}

			$final_postarr = array(
				'ID'          => $post_id,
				'post_status' => 'publish',
			);
			$final_post_id = wp_update_post( $final_postarr );

			if ( $final_post_id !== $post_id ) {
				$this->store_status( $this->status_level['error'], 'deposit', 'Une erreur est survenue lors de la mise à jour finale du gisement' );
			}
		} else {
			$this->store_status( $this->status_level['error'], 'deposit', "Une erreur est survenue lors de l'enregistrement du gisement" );
		}

		return $post_id;
	}

	/**
	 * Extract deposit pictures from input data.
	 *
	 * @param  array  $pictures_refs Array of picture references or single string reference.
	 * @param  string $inv_ref Reference of the deposit.
	 * @return void
	 */
	protected function extract_deposit_pictures( $pictures_refs, $inv_ref ) {
		$returned   = array();
		$ref_inputs = array();

		if ( ! is_array( $pictures_refs ) ) {
			array_push( $ref_inputs, $pictures_refs );
		} else {
			$ref_inputs = $pictures_refs;
		}

		foreach ( $ref_inputs as $idx => $ref_input ) {
			$pic_to_extract = '';

			if ( ! empty( $ref_input ) ) {
				$pic_to_extract = $ref_input;
			}

			$extract_returned = $this->extract_deposit_picture( $pic_to_extract );
			if ( -1 !== $extract_returned ) {
				array_push( $returned, $extract_returned );
			} else {
				$this->store_status( $this->status_level['error'], 'deposit', "l'image du gisement '" . $ref_input . "' n'a pas pu être traité" );
			}
		}
		return $returned;
	}

	/**
	 * Get picture data from string picture filename reference
	 *
	 * @param  string $picture_ref filename of the attachment.
	 * @return mixed array | -1
	 */
	protected function extract_deposit_picture( $picture_ref ) {
		$returned = array();
		if ( empty( $picture_ref ) ) {
			return -1;
		}
		$id = $this->get_attachment_id_by_guid( $picture_ref );
		if ( -1 === $id ) {
			return -1;
		}
		$url             = wp_get_attachment_image_url( $id, 'full' );
		$returned['id']  = $id;
		$returned['url'] = $url;

		return $returned;
	}

	/**
	 * Undocumented function
	 *
	 * @param  [type] $materials_data
	 * @return void
	 */
	protected function manage_deposit_materials( $materials_data ) {
		$status = array();
		foreach ( $materials_data as $item ) {

			$item_record_return = 0;
			if ( count( $item->variations ) > 0 ) {
				$item_record_return = $this->create_variable_product_deposit_item( $item );
			} else {
				$item_record_return = $this->create_simple_product_deposit_item( $item );
			}

			if ( is_nan( $item_record_return ) ) {
				array_push( $status, $item_record_return );
			}
		}

		return $status;
	}

	public function manage_item_categories( $familly, $category ) {

		$cat_ids       = array();
		$family_id     = 0;
		$family_slug   = '';
		$family_term   = false;
		$category_term = false;

		$family_term = $this->manage_item_family( $familly );

		if ( false !== $family_term ) {
			array_push( $cat_ids, $family_term->term_id );
		}

		$category_term = $this->manage_item_category( $category, $family_term );

		if ( false !== $category_term ) {
			array_push( $cat_ids, $category_term->term_id );
		}

		return $cat_ids;
	}

	public function manage_item_family( $familly ) {

		$item_family_term = false;

		// Family name not empty.
		if ( strlen( $familly ) > 2 && ! empty( $familly ) ) {
			$item_family_term = get_term_by( 'slug', $familly, 'product_cat' );
			// No term already exist.
			if ( ! $item_family_term ) {
				// Insert term.
				$return = wp_insert_term( $familly, 'product_cat', array() );
				if ( is_array( $return ) ) {
					$item_family_term = get_term_by( 'id', $return['term_id'], 'product_cat' );
				}
			}
		}
		return $item_family_term;
	}

	public function manage_item_category( $category, $familly_term ) {

		$item_category_term = false;

		if ( strlen( $category ) > 2 && ! empty( $category ) ) {
			$item_category_term = get_term_by( 'slug', $category, 'product_cat' );

			if ( ! $item_category_term || ( is_a( $item_category_term, 'WP_Term' ) && is_a( $familly_term, 'WP_Term' ) && $item_category_term->parent !== $familly_term->term_id ) ) {

				$insert_args = array( 'parent' => $familly_term->term_id );

				if ( is_a( $item_category_term, 'WP_Term' ) && is_a( $familly_term, 'WP_Term' ) && $item_category_term->parent !== $familly_term->term_id ) {
					$insert_args['slug'] = $familly_term->slug . '-' . $item_category_term->slug;
					$item_category_term  = get_term_by( 'slug', $insert_args['slug'], 'product_cat' );
				}

				if ( ! $item_category_term ) {
					$return = wp_insert_term( $category, 'product_cat', $insert_args );
					if ( is_array( $return ) ) {
						$item_category_term = get_term_by( 'id', $return['term_id'], 'product_cat' );
					}
				}
			}
		}

		return $item_category_term;
	}


	public function manage_all_item_attributes( $attributes_data ) {
		$attributes = array();
		foreach ( $attributes_data as $attribute_data ) {
			if ( isset( $attribute_data['variation'] ) && $attribute_data['variation'] ) {
				$var = true;
			} else {
				$var = false;
			}
			$attributes[] = $this->manage_item_attribute( $attribute_data['name'], $attribute_data['options'], $var );
		}
		return $attributes;
	}


	public function manage_item_attribute( $name, $options, $variation = false ) {

		$attribute = new \WC_Product_Attribute();
		$attribute->set_id( 0 );
		$attribute->set_name( $name );
		$attribute->set_options( $options );
		$attribute->set_position( 0 );
		$attribute->set_visible( 1 );
		$attribute->set_variation( $variation );
		return $attribute;
	}

	public function manage_item_dimensions( $lng, $lrg, $htr ) {

		$dimensions           = array();
		$dimensions['length'] = $this->sanitize_dimension( $lng );
		$dimensions['width']  = $this->sanitize_dimension( $lrg );
		$dimensions['height'] = $this->sanitize_dimension( $htr );

		return $dimensions;
	}

	public function create_simple_product_deposit_item( $product_data ) {

		$return_id = 0;

		$product = $this->get_product_by_sku( $product_data->ref, '\WC_Product_Simple' );

		if ( null !== $product ) {

			$product->set_status( 'auto-draft' );
			$product->set_name( $product_data->designation );
			if ( empty( $product_data->designation ) ) {
				$this->store_status( $this->status_level['warning'], 'Matériau', "Le matériaux {$product_data->ref} n'a pas de nom" );
			}
			if ( $this->should_update_qty() ) {
				$product->set_manage_stock( true );
				if ( property_exists( $product_data, 'qty' ) ) {
					$product->set_stock_quantity( $this->set_sanitize_qty( $product_data->qty ) );
				} else {
					$product->set_stock_quantity( 1 );
				}
			}

			$price = 1;
			if ( isset( $product_data->price ) && '' !== $product_data->price ) {
				$price = $product_data->price;
			}
			$product->set_price( $price );
			$product->set_regular_price( $price );
			if ( isset( $product_data->description ) && ! empty( $product_data->description ) ) {
				$product->set_description( nl2br( $product_data->description ) );
			}

			$lng = '';
			$lrg = '';
			$htr = '';
			if ( property_exists( $product_data, 'lng' ) ) {
				$lng = $product_data->lng; }
			if ( property_exists( $product_data, 'lrg' ) ) {
				$lrg = $product_data->lrg; }
			if ( property_exists( $product_data, 'htr' ) ) {
				$htr = $product_data->htr; }
			$dimensions = $this->manage_item_dimensions( $lng, $lrg, $htr );
			$product->set_length( $dimensions['length'] );
			$product->set_width( $dimensions['width'] );
			$product->set_height( $dimensions['height'] );

			$id = $product->save();

			$familly  = '';
			$category = '';
			if ( property_exists( $product_data, 'familly' ) ) {
				$familly = $product_data->familly; }
			if ( property_exists( $product_data, 'category' ) ) {
				$category = $product_data->category; }

			$product_categories = $this->manage_item_categories( $familly, $category );
			if ( count( $product_categories ) > 0 ) {
				wp_set_object_terms( $id, $product_categories, 'product_cat' );
			}

			$product_metas = array(
				array(
					'key'   => 'remarques',
					'value' => 'rqs',
				),
				array(
					'key'   => 'deposit',
					'value' => 'deposit',
				),
				array(
					'key'   => 'material',
					'value' => 'type',
				),
				array(
					'key'   => 'condition',
					'value' => 'condition',
				),
				array(
					'key'   => 'unit',
					'value' => 'unit',
				),
				array(
					'key'   => 'code',
					'value' => 'PEMD_code',
				),
				array(
					'key'   => 'macrocat',
					'value' => 'PEMD_Macro',
				),
				array(
					'key'   => 'categorie',
					'value' => 'PEMD_Cat',
				),
				array(
					'key'   => 'pem',
					'value' => 'PEMD_PEM',
				),
			);

			foreach ( $product_metas as $product_meta ) {
				$value = '';
				if ( property_exists( $product_data, $product_meta['value'] ) ) {
					$value = $product_data->{$product_meta['value']};
				}
				update_post_meta( $id, $product_meta['key'], $value );
			}

			$availability_date = $this->get_product_availability_date( $id );

			if ( -1 !== $availability_date ) {
				update_post_meta( $id, 'availability_date', $availability_date );
			}

			property_exists( $product_data, 'picRefDetails' ) ? $pic_ref_details = $product_data->picRefDetails : $pic_ref_details = array();
			property_exists( $product_data, 'picRefGlob' ) ? $pic_ref_glob       = $product_data->picRefGlob : $pic_ref_glob = array();
			$this->set_product_imgs(
				array(
					'picRefDetails' => $pic_ref_details,
					'picRefGlob'    => $pic_ref_glob,
				),
				$product
			);

			$product->set_status( 'publish' );
			$product->save();
			$return_id = $id;
		}
		return $return_id;
	}

	public function create_variable_product_deposit_item( $product_data ) {

		$return_id = 0;
		$product   = $this->get_product_by_sku( $product_data->ref, '\WC_Product_Variable' );

		if ( null !== $product ) {
			$product->set_name( $product_data->designation );
			if ( empty( $product_data->designation ) ) {
				$this->store_status( $this->status_level['warning'], 'Matériaux à variations', "Le matériaux {$product_data->ref} n'a pas de nom" );
			}

			$attributes_slugs      = array();
			$variations_refs       = array();
			$variations_materials  = array();
			$variations_conditions = array();
			foreach ( $product_data->variations as $variation ) {
				if ( ! in_array( $variation->ref, $variations_refs ) ) {
					$variations_refs[] = $variation->ref;
				}
			}

			$attributes_data = array(
				array(
					'name'      => 'Variation',
					'options'   => $variations_refs,
					'variation' => true,
				),
			);
			$attributes      = $this->manage_all_item_attributes( $attributes_data );
			$product->set_attributes( $attributes );

			if ( isset( $product_data->description ) && ! empty( $product_data->description ) ) {
				$product->set_description( nl2br( $product_data->description ) );
			}

			$product->set_status( 'auto-draft' );

			$id = $product->save();

			$this->set_product_imgs(
				array(
					'picRefDetails' => $product_data->picRefDetails,
					'picRefGlob'    => $product_data->picRefGlob,
				),
				$product
			);

			$product_categories = $this->manage_item_categories( $product_data->familly, $product_data->category );
			if ( count( $product_categories ) > 0 ) {
				wp_set_object_terms( $id, $product_categories, 'product_cat' );
			}

			$product_metas = array(
				array(
					'key'   => 'remarques',
					'value' => 'rqs',
				),
				array(
					'key'   => 'deposit',
					'value' => 'deposit',
				),
			);

			foreach ( $product_metas as $product_meta ) {
				$value = '';
				if ( property_exists( $product_data, $product_meta['value'] ) ) {
					$value = $product_data->{$product_meta['value']};
				}
				update_post_meta( $id, $product_meta['key'], $value );
			}

			$availability_date = $this->get_product_availability_date( $id );

			if ( -1 !== $availability_date ) {
				update_post_meta( $id, 'availability_date', $availability_date );
			}

			if ( property_exists( $product_data, 'qty' ) ) {
				update_post_meta( $id, '_initial_stock', $product_data->qty );
			}

			$product->save();
			$return_id = $id;

			foreach ( $product_data->variations as $variation_data ) {

				$lng = '-';
				$lrg = '-';
				$htr = '-';
				if ( property_exists( $variation_data, 'lng' ) ) {
					$lng = $variation_data->lng; }
				if ( property_exists( $variation_data, 'lrg' ) ) {
					$lrg = $variation_data->lrg; }
				if ( property_exists( $variation_data, 'htr' ) ) {
					$htr = $variation_data->htr; }
				$dimensions = $this->manage_item_dimensions( $lng, $lrg, $htr );
				$slug       = $this->build_dimension_attr_slug( $lng, $lrg, $htr );

				$variation = $this->get_product_by_sku( $variation_data->ref, '\WC_Product_Variation' );

				if ( null !== $variation ) {
					$price = 1;
					if ( isset( $variation_data->price ) && '' !== $variation_data->price ) {
						$price = $variation_data->price;
					}

					$variation->set_regular_price( $price );
					$variation->set_price( $price );
					$variation->set_parent_id( $id );
					$variation->set_manage_stock( true );
					if ( $this->should_update_qty() ) {
						if ( property_exists( $variation_data, 'qty' ) ) {
							$variation->set_stock_quantity( $this->set_sanitize_qty( $variation_data->qty ) );
						} else {
							$product->set_stock_quantity( 1 );
						}
					}
					$variation->set_length( $dimensions['length'] );
					$variation->set_width( $dimensions['width'] );
					$variation->set_height( $dimensions['height'] );
					$variation->set_status( 'publish' );
					if ( property_exists( $variation_data, 'description' ) && ! empty( $variation_data->description ) ) {
						$variation->set_description( nl2br( $variation_data->description ) );
					}

					$v_attributes = array( sanitize_title( 'Variation' ) => $variation_data->ref );
					$variation->set_attributes( $v_attributes );
					$variation->set_stock_status();

					$variation->set_status( 'auto-draft' );
					$v_id = $variation->save();
					$product->save();
					$this->set_product_imgs(
						array(
							'picRefDetails' => $variation_data->picRefDetails,
							'picRefGlob'    => $variation_data->picRefGlob,
						),
						$variation
					);

					$variation_metas = array(
						array(
							'key'   => 'remarques',
							'value' => 'rqs',
						),
						array(
							'key'   => 'reference',
							'value' => 'ref',
						),
						array(
							'key'   => 'designation',
							'value' => 'designation',
						),
						array(
							'key'   => 'material',
							'value' => 'type',
						),
						array(
							'key'   => 'condition',
							'value' => 'condition',
						),
						array(
							'key'   => 'unit',
							'value' => 'unit',
						),
						array(
							'key'   => 'code',
							'value' => 'PEMD_code',
						),
						array(
							'key'   => 'macrocat',
							'value' => 'PEMD_Macro',
						),
						array(
							'key'   => 'categorie',
							'value' => 'PEMD_Cat',
						),
						array(
							'key'   => 'pem',
							'value' => 'PEMD_PEM',
						),
					);

					foreach ( $variation_metas as $variation_meta ) {
						$value = '';
						if ( property_exists( $variation_data, $variation_meta['value'] ) ) {
							$value = $variation_data->{$variation_meta['value']};
						}
						update_post_meta( $v_id, $variation_meta['key'], $value );
					}

					$availability_date = $this->get_product_availability_date( $v_id );

					if ( -1 !== $availability_date ) {
						update_post_meta( $v_id, 'availability_date', $availability_date );
					}

					if ( property_exists( $variation_data, 'qty' ) ) {
						update_post_meta( $v_id, '_initial_stock', $variation_data->qty );
					}

					$variation->set_status( 'publish' );
					$variation->save();
				}
			}
			$product->set_status( 'publish' );
			$product->save();
		}
		return $return_id;
	}

	/**
	 * Build atribute slug according to its dimensions.
	 *
	 * @param  string $lng Length.
	 * @param  string $lrg Width.
	 * @param  string $htr Hieght.
	 * @return string
	 */
	protected function build_dimension_attr_slug( $lng, $lrg, $htr ) {
		$slug = '';
		$slug = $this->wrap_dimension( $this->sanitize_dimension( $lng ), 'L', '' );
		$slug = $slug . $this->wrap_dimension( $this->sanitize_dimension( $lrg ), 'l', 'x' );
		$slug = $slug . $this->wrap_dimension( $this->sanitize_dimension( $htr ), 'h', 'x' );
		return $slug;
	}

	/**
	 * Undocumented function
	 *
	 * @param  [type] $dimension
	 * @return void
	 */
	protected function sanitize_dimension( $dimension ) {
		$re        = '/\D*(\d+)\D*/m';
		$matches   = array();
		$sanitized = '';
		if ( isset( $dimension ) ) {
			if ( '' !== $dimension ) {
				preg_match_all( $re, $dimension, $matches, PREG_SET_ORDER, 0 );
				if ( isset( $matches[0][1] ) ) {
					$sanitized = $matches[0][1];
				}
			}
		}
		return $sanitized;
	}

	/**
	 * Undocumented function
	 *
	 * @param  [type] $dimension
	 * @param  [type] $prefix
	 * @param  [type] $separator
	 * @return void
	 */
	protected function wrap_dimension( $dimension, $prefix, $separator ) {
		if ( '' !== $dimension ) {
			return $separator . $prefix . $dimension;
		} else {
			return '';
		}
	}

	/**
	 * Undocumented function
	 *
	 * @param  [type] $imgs
	 * @param  [type] $product
	 * @return void
	 */
	protected function set_product_imgs( $imgs, $product ) {

		$filtered_details = array();
		$filtered_global  = array();
		if ( is_array( $imgs ) ) {
			if ( array_key_exists( 'picRefDetails', $imgs ) && is_array( $imgs['picRefDetails'] ) ) {
				$filtered_details = array_filter(
					$imgs['picRefDetails'],
					function ( $elt ) {
						return ( isset( $elt ) && ( '' !== $elt ) );
					}
				);
			}
			if ( array_key_exists( 'picRefGlob', $imgs ) && is_array( $imgs['picRefGlob'] ) ) {
				$filtered_global = array_filter(
					$imgs['picRefGlob'],
					function ( $elt ) {
						return ( isset( $elt ) && ( '' !== $elt ) );
					}
				);
			}
		}

		if ( ! is_a( $product, 'WC_Product' ) ) {
			$this->store_status( $this->status_level['error'], "Gestion d'image", "Le matériau pointé n'est pas valide pour être assigné des images (set_product_imgs)" );
			return false;
		}

		$gallery_img_ids = array();
		foreach ( $filtered_details as $img_ref ) {
			$detailled_img_id = $this->get_attachment_id_by_guid( $img_ref );
			if ( -1 !== $detailled_img_id ) {
				array_push( $gallery_img_ids, $detailled_img_id );
			}
		}

		$global_img_id = -1;
		if ( is_array( $filtered_global ) && count( $filtered_global ) > 0 ) {
			$global_img_id = $this->get_attachment_id_by_guid( $filtered_global[0] );
		}

		if ( -1 !== $global_img_id ) {
			$product->set_image_id( $global_img_id );
			array_unshift( $gallery_img_ids, $global_img_id );
		}

		if ( is_array( $gallery_img_ids ) && count( $gallery_img_ids ) > 0 ) {
			$product->set_gallery_image_ids( $gallery_img_ids );
		}
	}

	/**
	 * Undocumented function
	 *
	 * @param  [type] $id
	 * @return void
	 */
	protected function get_product_availability_date( $id ) {

		if ( false === get_post_status( $id ) ) {
			return -1;
		}

		$product_deposit_ref = get_post_meta( $id, 'deposit', true );
		$format              = 'Y-m-d';
		$date_obj            = \DateTime::createFromFormat( $format, $product_deposit_ref );

		if ( $date_obj && $date_obj->format( $format ) !== $product_deposit_ref ) {
			return -1;
		}

		$args     = array(
			'post_type'   => 'deposit',
			'number_post' => -1,
			'meta_key'    => 'reference',
			'meta_value'  => $product_deposit_ref,
		);
		$deposits = get_posts( $args );

		if ( ! is_array( $deposits ) ) {
			return -1;
		}

		$availability_date = get_post_meta( $deposits[0]->ID, 'dismantle_date', true );

		if ( false === $availability_date ) {
			return -1;
		}

		return $availability_date;
	}


	/*---------------------------------------------------------------------------------------*/

	/**
	 * Save a new product attribute from his name (slug).
	 *
	 * @since 3.0.0
	 * @param string $name  The product attribute name (slug).
	 * @param string $label The product attribute label (name).
	 * @param string $set Is the product attribute value has to be set.
	 */
	public function save_product_attribute_from_name( $name, $label = '', $set = true ) {
		if ( ! function_exists( 'get_attribute_id_from_name' ) ) {
			return;
		}

		global $wpdb;

		$label        = '' === $label ? ucfirst( $name ) : $label;
		$attribute_id = $this->get_attribute_id_from_name( $name );

		if ( empty( $attribute_id ) ) {
			$attribute_id = null;
		} else {
			$set = false;
		}
		$args = array(
			'attribute_id'      => $attribute_id,
			'attribute_name'    => $name,
			'attribute_label'   => $label,
			'attribute_type'    => 'select',
			'attribute_orderby' => 'menu_order',
			'attribute_public'  => 0,
		);

		if ( empty( $attribute_id ) ) {
			$wpdb->insert( "{$wpdb->prefix}woocommerce_attribute_taxonomies", $args );
			set_transient( 'wc_attribute_taxonomies', false );
		}

		if ( $set ) {
			$attributes           = wc_get_attribute_taxonomies();
			$args['attribute_id'] = $this->get_attribute_id_from_name( $name );
			$attributes[]         = (object) $args;
			set_transient( 'wc_attribute_taxonomies', $attributes );
		} else {
			return;
		}
	}

	/**
	 * Get the product attribute ID from the name.
	 *
	 * @since 3.0.0
	 * @param string $name | The name (slug).
	 */
	protected function get_attribute_id_from_name( $name ) {
		global $wpdb;
		$attribute_id = $wpdb->get_col(
			"SELECT attribute_id
		FROM {$wpdb->prefix}woocommerce_attribute_taxonomies
		WHERE attribute_name LIKE '$name'"
		);
		return reset( $attribute_id );
	}

	/**
	 * Get attachement ID using its filename.
	 *
	 * @param  string $filename Filename of the supposed attachment.
	 * @return int ID on success or -1 on failure.
	 */
	protected function get_attachment_id_by_guid( $filename ) {
		global $wpdb;

		$ext            = array( '.png', '.jpg', '.gif', '.jpeg' );
		$filename       = str_ireplace( $ext, '', $filename );
		$clean_filename = trim( html_entity_decode( sanitize_title( $filename ) ) );

		if ( '' !== $clean_filename ) {
			$attachments = $wpdb->get_results( "SELECT ID FROM $wpdb->posts WHERE guid LIKE '%$clean_filename%' AND post_type = 'attachment' ", OBJECT );
			if ( $attachments ) {
				return $attachments[0]->ID;
			} else {
				return -1;
			}
		} else {
			return -1;
		}
	}

	/**
	 * Get product by sku and create one if not.
	 *
	 * @param  string $sku Id of the product.
	 * @param  string $product_type_class Class of product to search.
	 * @return mixed Product instance on success | null on failure.
	 */
	protected function get_product_by_sku( $sku, $product_type_class = '\WC_Product' ) {
		global $wpdb;

		try {
			$product_id = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key='_sku' AND meta_value='%s' LIMIT 1", $sku ) );

			if ( $product_id ) {
				return new $product_type_class( $product_id );
			} else {
				$new_product = new $product_type_class();
				$new_product->set_sku( $sku );
				return $new_product;
			}
		} catch ( \Throwable $t ) {
			$this->store_status( $this->status_level['error'], 'Gestion de produit', "Le matériaux {$sku} n'a pas pu être créé/retrouvé" );
			return null;
		}
	}

	/**
	 * Verify and correct quantity if needed.
	 *
	 * @param  int $qty Quantity value.
	 * @return int
	 */
	protected function set_sanitize_qty( $qty ): int {
		$int_qty = intval( $qty );

		if ( $int_qty < 1 ) {
			$int_qty = 0;}
		return $int_qty;
	}


	/**
	 * Store new status.
	 *
	 * @param  string $level Level of criticity of the status.
	 * @param  string $context Context linked to the status.
	 * @param  string $message Message explainning the status.
	 * @return void
	 */
	protected function store_status( $level, $context, $message ) {

		array_push(
			$this->status,
			array(
				'level'   => $level,
				'context' => $context,
				'message' => $message,
			)
		);
	}

	/**
	 * Clear all status stored.
	 *
	 * @return void
	 */
	protected function clear_status() {
		$this->status = array();
	}

	/**
	 * Check if errores status exists.
	 *
	 * @return boolean true on errors existing else false.
	 */
	protected function status_has_error() {
		$returned = array_count_values( array_column( $this->status, 'level' ) );
		return array_key_exists( $this->status_level['error'], $returned );
	}


	/**
	 * Get IRIS code according to request input data.
	 *
	 * @param  WP_REST_Request $request REST request from user.
	 * @return object WP_REST_Response to the request | WP_Error on failure.
	 *
	 * @throws \Exception Raised on multiple IRIS found.
	 */
	public function get_iris( $request ) {
		$iris          = array();
		$returned_iris = 0;
		$coords        = array( 0.0, 0.0 );
		try {
			$params = $request->get_params();
			if ( array_key_exists( 'coords', $params ) ) {
				$coords_str = explode( ',', $params['coords'] );
				foreach ( $coords_str as $idx => $coord ) {
					$coords[ $idx ] = floatval( $coord );
				}
			}
		} catch ( \Throwable $t ) {
			$response = new \WP_Error( 'malformed_data', "can't get data from request", array( 'status' => 400 ) );
		}

		if ( 0.0 !== $coords[0] && 0.0 !== $coords[1] ) {
			try {
				$iris_json = file_get_contents( geojson_FILE );

				if ( false !== $iris_json ) {
					$iris = json_decode( $iris_json, true );
				}
			} catch ( \Throwable $t ) {
				$iris     = false;
				$response = new \WP_Error( 'file_access_error', "can't get data from geojson iris file", array( 'status' => 500 ) );
			}
		}
		if ( 0.0 !== $coords[0] && 0.0 !== $coords[1] && ! empty( $iris ) ) {

			$point_location = new pointLocation();
			$point          = $point_location->point( $coords );
			$matching_iris  = array();
			foreach ( $iris['features'] as $iris_feature ) {
				$is_in = false;
				if ( $params['city'] === $iris_feature['properties']['NOM_COM'] ) {
					$is_in = $point_location->booleanPointInPolygon( $point, $iris_feature );

					if ( true === $is_in ) {
						array_push( $matching_iris, $iris_feature['properties']['CODE_IRIS'] );
					}
				}
			}
			if ( count( $matching_iris ) > 1 ) {
				throw new \Exception( 'Too many maching iris for one location', 1 );}

			$returned_iris = $matching_iris[0];

		}
		$response = new \WP_REST_Response();
		$response->set_status( 200 );
		$response->set_data( $returned_iris );
		return $response;
	}

	public function get_geocode( WP_REST_Request $request ) {

		$params  = $request->get_params();
		$address = $params['address'];
		if ( empty( $address ) ) {
			return new \WP_Error( 'missing_address', 'Address parameter is required', array( 'status' => 400 ) );
		}
		if ( ! is_string( $address ) ) {
			return new \WP_Error( 'invalid_address', 'Address parameter must be a string', array( 'status' => 400 ) );
		}

		$geocode_data = $this->get_geocode_from_google( $address );

		$rest_response = new WP_REST_Response();
		if ( is_wp_error( $geocode_data ) ) {
			$rest_response->set_status( 500 );
			$rest_response->set_data( array( 'error' => $geocode_data->get_error_message() ) );
			return $rest_response;
		}
		if ( empty( $geocode_data ) ) {
			$rest_response->set_status( 404 );
			$rest_response->set_data( array( 'error' => 'No geocode data found' ) );
			return $rest_response;
		}
		if ( ! is_array( $geocode_data ) ) {
			$rest_response->set_status( 500 );
			$rest_response->set_data( array( 'error' => 'Invalid geocode data format' ) );
			return $rest_response;
		}

		// Set the response data and status
		$rest_response->set_status( 200 );
		$rest_response->set_data( $geocode_data );
		$rest_response->set_headers( array( 'Content-Type' => 'application/json' ) );
		return $rest_response;
	}

	/**
	 * Call Google Maps Geocoding API to get geocode data.
	 *
	 * @param string $address The address to geocode.
	 * @return array|WP_Error Geocode data or error.
	 */
	private function get_geocode_from_google( $address ) {
		$api_key  = get_option( 'invimport_google_api_key' ); // Replace with your Google Maps API key
		$base_url = 'https://maps.googleapis.com/maps/api/geocode/json';

		// Build the request URL
		$url = $base_url . '?address=' . urlencode( $address ) . '&language=fr&key=' . $api_key;

		// Make the HTTP request
		$response = wp_remote_get( $url );

		// Check for errors
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Parse the response
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		// Check for a valid response
		if ( isset( $data['status'] ) && $data['status'] === 'OK' ) {
			return $data;
		} else {
			return new \WP_Error( 'geocode_error', 'Failed to retrieve geocode data: ' . $data['error_message'], $data );
		}
	}


	public function set_locality_geometry( WP_REST_Request $request ) {
		$body_raw = $request->get_body();

		if ( empty( $body_raw ) ) {
			return new \WP_Error( 'empty_body', 'Request body cannot be empty', array( 'status' => 400 ) );
		}
		$body = json_decode( $body_raw, true );
		if ( ! array_key_exists( 'locality_name', $body ) || ! array_key_exists( 'locality_code', $body ) || ! array_key_exists( 'geometry', $body ) ) {
			return new \WP_Error( 'missing_parameters', 'locality name locality code and geometry parameters are required', array( 'status' => 400 ) );
		}

		$locality_name = sanitize_text_field( $body['locality_name'] );
		$locality_code = sanitize_text_field( $body['locality_code'] );
		$geometry      = json_decode( $body['geometry'] );

		if ( empty( $locality_name ) || empty( $locality_code ) || empty( $geometry ) ) {
			return new \WP_Error( 'invalid_parameters', 'Locality and geometry cannot be empty', array( 'status' => 400 ) );
		}

		$this->insert_city_term_n_meta( $locality_name, $locality_code, $geometry );

		return new WP_REST_Response(
			array(
				'data' => array(
					'status'  => 200,
					'message' => 'Geometry set successfully for locality: ' . $locality_name,
				),
			),
			200
		);
	}

	/**
	 * Insert term and meta for a city locality.
	 *
	 * @param  string $locality Name of the locality to insert as term.
	 * @param  string $geometry Geometry data to associate with the term.
	 * @return mixed
	 * @throws \WP_Error If term insertion or meta addition fails.
	 */
	public function insert_city_term_n_meta( $locality_name, $locality_code, $geometry ) {

		$meta_rt        = false;
		$locality_terms = get_terms(
			array(
				'taxonomy'   => 'city',
				'hide_empty' => false,
				'name'       => $locality_name,
			)
		);

		if ( is_wp_error( $locality_terms ) || ( is_array( $locality_terms ) && empty( $locality_terms ) ) ) {
			$locality_term = wp_insert_term( $locality_name, 'city' );
		} else {
			$locality_term = $locality_terms[0];
		}

		$term_insee_code_meta = get_term_meta( $locality_term->term_id, 'insee_code', true );

		if ( empty( $term_insee_code_meta ) ) {

			$meta_rt = add_term_meta( $locality_term->term_id, 'insee_code', $locality_code, true );
			if ( is_wp_error( $meta_rt ) || ! $meta_rt ) {
				return new \WP_Error( 'geometry_error', 'Failed to set insee code for locality: ' . $locality_name, array( 'status' => 500 ) );
			}
		}

		$term_centroid_meta = get_term_meta( $locality_term->term_id, 'centroid', true );

		if ( empty( $term_centroid_meta ) ) {
			$locality_centroid = $this->wp_get_multipolygon_centroid( $geometry );

			$meta_rt = add_term_meta( $locality_term->term_id, 'centroid', wp_json_encode( $locality_centroid ), true );
			if ( is_wp_error( $meta_rt ) || ! $meta_rt ) {
				return new \WP_Error( 'geometry_error', 'Failed to set centroid for locality: ' . $locality, array( 'status' => 500 ) );
			}
		}

		$term_geometry_meta = get_term_meta( $locality_term->term_id, 'geometry', true );

		if ( empty( $term_geometry_meta ) ) {

			$meta_rt = add_term_meta( $locality_term->term_id, 'geometry', wp_json_encode( $this->invert_geometry_coordinates( $geometry['coordinates'] ) ), true );

			if ( is_wp_error( $meta_rt ) || ! $meta_rt ) {
				return new \WP_Error( 'geometry_error', 'Failed to set geometry for locality: ' . $locality, array( 'status' => 500 ) );
			}
		}

		return $meta_rt;
	}

	/**
	 * Calculate the centroid of a MultiPolygon from GeoJSON data
	 *
	 * @param array $geojson_multipolygon GeoJSON MultiPolygon feature
	 * @return array|false Centroid coordinates [longitude, latitude] or false on error
	 */
	private function calculate_multipolygon_centroid( $geojson_multipolygon ) {
		// Validate input structure
		if ( ! is_array( $geojson_multipolygon ) ||
			! isset( $geojson_multipolygon['type'] ) ||
			$geojson_multipolygon['type'] !== 'MultiPolygon' ||
			! isset( $geojson_multipolygon['coordinates'] ) ) {
			return false;
		}

		$total_area = 0;
		$weighted_x = 0;
		$weighted_y = 0;

		// Process each polygon in the multipolygon
		foreach ( $geojson_multipolygon['coordinates'] as $polygon ) {
			// Get the exterior ring (first ring) of each polygon
			$exterior_ring = $polygon[0];

			// Calculate polygon area and centroid
			$polygon_area     = $this->calculate_polygon_area( $exterior_ring );
			$polygon_centroid = $this->calculate_polygon_centroid( $exterior_ring );

			if ( $polygon_area > 0 && $polygon_centroid ) {
				$total_area += $polygon_area;
				$weighted_x += $polygon_centroid[0] * $polygon_area;
				$weighted_y += $polygon_centroid[1] * $polygon_area;
			}
		}

		// Calculate weighted average centroid
		if ( $total_area > 0 ) {
			return array(
				$weighted_y / $total_area,  // latitude
				$weighted_x / $total_area, // longitude
			);
		}

		return false;
	}

	/**
	 * Calculate the area of a polygon using the shoelace formula
	 *
	 * @param array $coordinates Array of coordinate pairs [[lng, lat], ...]
	 * @return float Polygon area (absolute value)
	 */
	private function calculate_polygon_area( $coordinates ) {
		$n = count( $coordinates );
		if ( $n < 3 ) {
			return 0;
		}

		$area = 0;

		// Apply shoelace formula
		for ( $i = 0; $i < $n - 1; $i++ ) {
			$area += ( $coordinates[ $i ][0] * $coordinates[ $i + 1 ][1] ) -
					( $coordinates[ $i + 1 ][0] * $coordinates[ $i ][1] );
		}

		return abs( $area ) / 2;
	}

	/**
	 * Calculate the centroid of a polygon using geometric center formula
	 *
	 * @param array $coordinates Array of coordinate pairs [[lng, lat], ...]
	 * @return array|false Centroid coordinates [longitude, latitude] or false on error
	 */
	private function calculate_polygon_centroid( $coordinates ) {
		$n = count( $coordinates );
		if ( $n < 3 ) {
			return false;
		}

		$area = $this->calculate_polygon_area( $coordinates );
		if ( $area == 0 ) {
			return false;
		}

		$cx = 0;
		$cy = 0;

		// Calculate centroid using the standard formula
		for ( $i = 0; $i < $n - 1; $i++ ) {
			$x0 = $coordinates[ $i ][0];
			$y0 = $coordinates[ $i ][1];
			$x1 = $coordinates[ $i + 1 ][0];
			$y1 = $coordinates[ $i + 1 ][1];

			$cross = ( $x0 * $y1 ) - ( $x1 * $y0 );
			$cx   += ( $x0 + $x1 ) * $cross;
			$cy   += ( $y0 + $y1 ) * $cross;
		}

		$factor = 1 / ( 6 * $area );

		return array(
			$cx * $factor, // longitude
			$cy * $factor,  // latitude
		);
	}

	/**
	 * WordPress helper function to get centroid from GeoJSON feature
	 *
	 * @param string $geojson_string JSON string containing GeoJSON data
	 * @return array|WP_Error Centroid coordinates or WP_Error on failure
	 */
	private function wp_get_multipolygon_centroid( $geojson_string ) {
		// Decode JSON
		$geojson = json_decode( $geojson_string, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new WP_Error( 'invalid_json', 'Invalid GeoJSON format' );
		}

		// Handle Feature or FeatureCollection
		$geometry = null;
		if ( isset( $geojson['type'] ) ) {
			switch ( $geojson['type'] ) {
				case 'Feature':
					$geometry = $geojson['geometry'];
					break;
				case 'MultiPolygon':
					$geometry = $geojson;
					break;
				case 'FeatureCollection':
					// Take the first MultiPolygon feature found
					foreach ( $geojson['features'] as $feature ) {
						if ( isset( $feature['geometry']['type'] ) &&
							$feature['geometry']['type'] === 'MultiPolygon' ) {
							$geometry = $feature['geometry'];
							break;
						}
					}
					break;
			}
		}

		if ( ! $geometry || $geometry['type'] !== 'MultiPolygon' ) {
			return new WP_Error( 'no_multipolygon', 'No MultiPolygon geometry found' );
		}

		$centroid = $this->calculate_multipolygon_centroid( $geometry );

		if ( false === $centroid ) {
			return new WP_Error( 'calculation_failed', 'Failed to calculate centroid' );
		}

		return $centroid;
	}

	protected function invert_geometry_coordinates( $geometry ) {
		if ( ! is_array( $geometry ) ) {
			return $geometry; // Return as is if not an array
		}

		// Invert coordinates for each polygon
		foreach ( $geometry as &$polygon ) {
			if ( is_array( $polygon ) ) {
				foreach ( $polygon as &$ring ) {
					if ( is_array( $ring ) ) {
						foreach ( $ring as &$point ) {
							if ( is_array( $point ) && count( $point ) === 2 ) {
								// Invert the point coordinates
								$point = array( $point[1], $point[0] );
							}
						}
					}
				}
			}
		}

		return $geometry;
	}
}
