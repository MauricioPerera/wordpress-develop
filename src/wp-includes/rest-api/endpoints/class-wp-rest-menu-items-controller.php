<?php
/**
 * REST API: WP_REST_Menu_Items_Controller class
 *
 * @package WordPress
 * @subpackage REST_API
 * @since 5.9.0
 */

/**
 * Core class to access nav items via the REST API.
 *
 * @since 5.9.0
 *
 * @see WP_REST_Posts_Controller
 */
class WP_REST_Menu_Items_Controller extends WP_REST_Posts_Controller {

	/**
	 * Gets the nav menu item, if the ID is valid.
	 *
	 * @since 5.9.0
	 *
	 * @param int $id Supplied ID.
	 * @return object|WP_Error Post object if ID is valid, WP_Error otherwise.
	 */
	protected function get_nav_menu_item( $id ) {
		$item = _wp_get_menu_item( (int) $id );
		if ( ! $item ) {
			return new WP_Error(
				'rest_post_invalid_id',
				__( 'Invalid menu item ID.' ),
				array( 'status' => 404 )
			);
		}

		return wp_setup_nav_menu_item( $item );
	}

	/**
	 * Gets the menu item post object, if the ID is valid.
	 *
	 * Overrides the parent to read from wp_menu_items instead of wp_posts.
	 *
	 * @since 7.0.0
	 *
	 * @param int $id Supplied ID.
	 * @return WP_Post|WP_Error Post object if ID is valid, WP_Error otherwise.
	 */
	protected function get_post( $id ) {
		$item = _wp_get_menu_item( (int) $id );
		if ( ! $item ) {
			return new WP_Error(
				'rest_post_invalid_id',
				__( 'Invalid menu item ID.' ),
				array( 'status' => 404 )
			);
		}

		return $item;
	}

	/**
	 * Checks if a given request has access to read menu items.
	 *
	 * @since 5.9.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error True if the request has read access, WP_Error object otherwise.
	 */
	public function get_items_permissions_check( $request ) {
		$has_permission = parent::get_items_permissions_check( $request );

		if ( true !== $has_permission ) {
			return $has_permission;
		}

		return $this->check_has_read_only_access( $request );
	}

	/**
	 * Checks if a given request has access to read a menu item if they have access to edit them.
	 *
	 * @since 5.9.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error True if the request has read access for the item, WP_Error object or false otherwise.
	 */
	public function get_item_permissions_check( $request ) {
		$menu_item = $this->get_nav_menu_item( $request['id'] );
		if ( is_wp_error( $menu_item ) ) {
			return $menu_item;
		}

		if ( 'edit' === $request['context'] && ! current_user_can( 'edit_theme_options' ) ) {
			return new WP_Error(
				'rest_forbidden_context',
				__( 'Sorry, you are not allowed to edit menu items.' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return $this->check_has_read_only_access( $request );
	}

	/**
	 * Checks if a given request has access to create a menu item.
	 *
	 * @since 7.0.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error True if the request has access to create items, WP_Error object otherwise.
	 */
	public function create_item_permissions_check( $request ) {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return new WP_Error(
				'rest_cannot_create',
				__( 'Sorry, you are not allowed to create menu items.' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * Checks if a given request has access to update a menu item.
	 *
	 * @since 7.0.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error True if the request has access to update the item, WP_Error object otherwise.
	 */
	public function update_item_permissions_check( $request ) {
		$menu_item = $this->get_nav_menu_item( $request['id'] );
		if ( is_wp_error( $menu_item ) ) {
			return $menu_item;
		}

		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return new WP_Error(
				'rest_cannot_update',
				__( 'Sorry, you are not allowed to update menu items.' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * Checks if a given request has access to delete a menu item.
	 *
	 * @since 7.0.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error True if the request has access to delete the item, WP_Error object otherwise.
	 */
	public function delete_item_permissions_check( $request ) {
		$menu_item = $this->get_nav_menu_item( $request['id'] );
		if ( is_wp_error( $menu_item ) ) {
			return $menu_item;
		}

		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return new WP_Error(
				'rest_cannot_delete',
				__( 'Sorry, you are not allowed to delete menu items.' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * Checks whether the current user has read permission for the endpoint.
	 *
	 * This allows for any user that can `edit_theme_options` or edit any REST API available post type.
	 *
	 * @since 5.9.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error True if the request has read access for the item, WP_Error object otherwise.
	 */
	protected function check_has_read_only_access( $request ) {
		/**
		 * Filters whether the current user has read access to menu items via the REST API.
		 *
		 * @since 6.8.0
		 *
		 * @param bool               $read_only_access Whether the current user has read access to menu items
		 *                                             via the REST API.
		 * @param WP_REST_Request    $request          Full details about the request.
		 * @param WP_REST_Controller $controller       The current instance of the controller.
		 */
		$read_only_access = apply_filters( 'rest_menu_read_access', false, $request, $this );
		if ( $read_only_access ) {
			return true;
		}

		if ( current_user_can( 'edit_theme_options' ) ) {
			return true;
		}

		if ( current_user_can( 'edit_posts' ) ) {
			return true;
		}

		foreach ( get_post_types( array( 'show_in_rest' => true ), 'objects' ) as $post_type ) {
			if ( current_user_can( $post_type->cap->edit_posts ) ) {
				return true;
			}
		}

		return new WP_Error(
			'rest_cannot_view',
			__( 'Sorry, you are not allowed to view menu items.' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}

	/**
	 * Retrieves a collection of nav menu items.
	 *
	 * Overrides the parent to query wp_menu_items directly instead of using WP_Query.
	 *
	 * @since 7.0.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_items( $request ) {
		global $wpdb;

		$where = array( '1=1' );

		// Filter by menu ID.
		if ( ! empty( $request['menus'] ) ) {
			$where[] = $wpdb->prepare( 'menu_id = %d', absint( $request['menus'] ) );
		}

		// Filter by status.
		if ( ! empty( $request['status'] ) ) {
			$statuses = wp_parse_list( $request['status'] );
		} else {
			$statuses = array( 'publish' );
		}
		$status_in = implode( "','", array_map( 'esc_sql', $statuses ) );
		$where[]   = "status IN ('$status_in')";

		// Filter by specific IDs.
		if ( ! empty( $request['include'] ) ) {
			$ids     = implode( ',', array_map( 'absint', $request['include'] ) );
			$where[] = "id IN ($ids)";
		}

		if ( ! empty( $request['exclude'] ) ) {
			$ids     = implode( ',', array_map( 'absint', $request['exclude'] ) );
			$where[] = "id NOT IN ($ids)";
		}

		// Search.
		if ( ! empty( $request['search'] ) ) {
			$where[] = $wpdb->prepare( 'title LIKE %s', '%' . $wpdb->esc_like( $request['search'] ) . '%' );
		}

		// Filter by menu_order.
		if ( isset( $request['menu_order'] ) ) {
			$where[] = $wpdb->prepare( 'position = %d', absint( $request['menu_order'] ) );
		}

		$where_clause = implode( ' AND ', $where );

		// Ordering.
		$orderby_map = array(
			'id'         => 'id',
			'title'      => 'title',
			'menu_order' => 'position',
		);
		$orderby = 'position';
		if ( ! empty( $request['orderby'] ) && isset( $orderby_map[ $request['orderby'] ] ) ) {
			$orderby = $orderby_map[ $request['orderby'] ];
		}
		$order = 'ASC';
		if ( ! empty( $request['order'] ) && 'desc' === strtolower( $request['order'] ) ) {
			$order = 'DESC';
		}

		// Pagination.
		$per_page = isset( $request['per_page'] ) ? (int) $request['per_page'] : 100;
		$page     = isset( $request['page'] ) ? (int) $request['page'] : 1;
		$offset   = ( $page - 1 ) * $per_page;

		// Total count.
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->menu_items} WHERE $where_clause" );

		// Fetch rows.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->menu_items} WHERE $where_clause ORDER BY $orderby $order LIMIT %d OFFSET %d",
				$per_page,
				$offset
			)
		);

		$items = array();
		foreach ( $rows as $row ) {
			$post   = _wp_menu_item_row_to_post( $row );
			$post   = wp_setup_nav_menu_item( $post );
			$data   = $this->prepare_item_for_response( $post, $request );
			$items[] = $this->prepare_response_for_collection( $data );
		}

		$response  = rest_ensure_response( $items );
		$max_pages = $per_page > 0 ? (int) ceil( $total / $per_page ) : 1;

		$response->header( 'X-WP-Total', $total );
		$response->header( 'X-WP-TotalPages', $max_pages );

		return $response;
	}

	/**
	 * Creates a single nav menu item.
	 *
	 * @since 5.9.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function create_item( $request ) {
		if ( ! empty( $request['id'] ) ) {
			return new WP_Error( 'rest_post_exists', __( 'Cannot create existing post.' ), array( 'status' => 400 ) );
		}

		$prepared_nav_item = $this->prepare_item_for_database( $request );

		if ( is_wp_error( $prepared_nav_item ) ) {
			return $prepared_nav_item;
		}
		$prepared_nav_item = (array) $prepared_nav_item;

		$nav_menu_item_id = wp_update_nav_menu_item( $prepared_nav_item['menu-id'], $prepared_nav_item['menu-item-db-id'], wp_slash( $prepared_nav_item ), false );
		if ( is_wp_error( $nav_menu_item_id ) ) {
			if ( 'db_insert_error' === $nav_menu_item_id->get_error_code() ) {
				$nav_menu_item_id->add_data( array( 'status' => 500 ) );
			} else {
				$nav_menu_item_id->add_data( array( 'status' => 400 ) );
			}

			return $nav_menu_item_id;
		}

		$nav_menu_item = $this->get_nav_menu_item( $nav_menu_item_id );
		if ( is_wp_error( $nav_menu_item ) ) {
			$nav_menu_item->add_data( array( 'status' => 404 ) );

			return $nav_menu_item;
		}

		/**
		 * Fires after a single menu item is created or updated via the REST API.
		 *
		 * @since 5.9.0
		 *
		 * @param object          $nav_menu_item Inserted or updated menu item object.
		 * @param WP_REST_Request $request       Request object.
		 * @param bool            $creating      True when creating a menu item, false when updating.
		 */
		do_action( 'rest_insert_nav_menu_item', $nav_menu_item, $request, true );

		$schema = $this->get_item_schema();

		if ( ! empty( $schema['properties']['meta'] ) && isset( $request['meta'] ) ) {
			$meta_update = $this->meta->update_value( $request['meta'], $nav_menu_item_id );

			if ( is_wp_error( $meta_update ) ) {
				return $meta_update;
			}
		}

		$nav_menu_item = $this->get_nav_menu_item( $nav_menu_item_id );
		$fields_update = $this->update_additional_fields_for_object( $nav_menu_item, $request );

		if ( is_wp_error( $fields_update ) ) {
			return $fields_update;
		}

		$request->set_param( 'context', 'edit' );

		/**
		 * Fires after a single menu item is completely created or updated via the REST API.
		 *
		 * @since 5.9.0
		 *
		 * @param object          $nav_menu_item Inserted or updated menu item object.
		 * @param WP_REST_Request $request       Request object.
		 * @param bool            $creating      True when creating a menu item, false when updating.
		 */
		do_action( 'rest_after_insert_nav_menu_item', $nav_menu_item, $request, true );

		$nav_menu_item = $this->get_nav_menu_item( $nav_menu_item_id );

		$response = $this->prepare_item_for_response( $nav_menu_item, $request );
		$response = rest_ensure_response( $response );

		$response->set_status( 201 );
		$response->header( 'Location', rest_url( sprintf( '%s/%s/%d', $this->namespace, $this->rest_base, $nav_menu_item_id ) ) );

		return $response;
	}

	/**
	 * Updates a single nav menu item.
	 *
	 * @since 5.9.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function update_item( $request ) {
		$valid_check = $this->get_nav_menu_item( $request['id'] );
		if ( is_wp_error( $valid_check ) ) {
			return $valid_check;
		}
		$prepared_nav_item = $this->prepare_item_for_database( $request );

		if ( is_wp_error( $prepared_nav_item ) ) {
			return $prepared_nav_item;
		}

		$prepared_nav_item = (array) $prepared_nav_item;

		$nav_menu_item_id = wp_update_nav_menu_item( $prepared_nav_item['menu-id'], $prepared_nav_item['menu-item-db-id'], wp_slash( $prepared_nav_item ), false );

		if ( is_wp_error( $nav_menu_item_id ) ) {
			if ( 'db_update_error' === $nav_menu_item_id->get_error_code() ) {
				$nav_menu_item_id->add_data( array( 'status' => 500 ) );
			} else {
				$nav_menu_item_id->add_data( array( 'status' => 400 ) );
			}

			return $nav_menu_item_id;
		}

		$nav_menu_item = $this->get_nav_menu_item( $nav_menu_item_id );
		if ( is_wp_error( $nav_menu_item ) ) {
			$nav_menu_item->add_data( array( 'status' => 404 ) );

			return $nav_menu_item;
		}

		/** This action is documented in wp-includes/rest-api/endpoints/class-wp-rest-menu-items-controller.php */
		do_action( 'rest_insert_nav_menu_item', $nav_menu_item, $request, false );

		$schema = $this->get_item_schema();

		if ( ! empty( $schema['properties']['meta'] ) && isset( $request['meta'] ) ) {
			$meta_update = $this->meta->update_value( $request['meta'], $nav_menu_item->ID );

			if ( is_wp_error( $meta_update ) ) {
				return $meta_update;
			}
		}

		$nav_menu_item = $this->get_nav_menu_item( $nav_menu_item_id );
		$fields_update = $this->update_additional_fields_for_object( $nav_menu_item, $request );

		if ( is_wp_error( $fields_update ) ) {
			return $fields_update;
		}

		$request->set_param( 'context', 'edit' );

		/** This action is documented in wp-includes/rest-api/endpoints/class-wp-rest-menu-items-controller.php */
		do_action( 'rest_after_insert_nav_menu_item', $nav_menu_item, $request, false );

		$nav_menu_item = $this->get_nav_menu_item( $nav_menu_item_id );

		$response = $this->prepare_item_for_response( $nav_menu_item, $request );

		return rest_ensure_response( $response );
	}

	/**
	 * Deletes a single nav menu item.
	 *
	 * @since 5.9.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error True on success, or WP_Error object on failure.
	 */
	public function delete_item( $request ) {
		$menu_item = $this->get_nav_menu_item( $request['id'] );
		if ( is_wp_error( $menu_item ) ) {
			return $menu_item;
		}

		// We don't support trashing for menu items.
		if ( ! $request['force'] ) {
			/* translators: %s: force=true */
			return new WP_Error( 'rest_trash_not_supported', sprintf( __( "Menu items do not support trashing. Set '%s' to delete." ), 'force=true' ), array( 'status' => 501 ) );
		}

		$previous = $this->prepare_item_for_response( $menu_item, $request );

		$result = _wp_delete_menu_item( $request['id'] );

		if ( ! $result ) {
			return new WP_Error( 'rest_cannot_delete', __( 'The menu item cannot be deleted.' ), array( 'status' => 500 ) );
		}

		$response = new WP_REST_Response();
		$response->set_data(
			array(
				'deleted'  => true,
				'previous' => $previous->get_data(),
			)
		);

		/**
		 * Fires immediately after a single menu item is deleted via the REST API.
		 *
		 * @since 5.9.0
		 *
		 * @param object          $nav_menu_item Inserted or updated menu item object.
		 * @param WP_REST_Response $response The response data.
		 * @param WP_REST_Request $request       Request object.
		 */
		do_action( 'rest_delete_nav_menu_item', $menu_item, $response, $request );

		return $response;
	}

	/**
	 * Prepares a single nav menu item for create or update.
	 *
	 * @since 5.9.0
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return object|WP_Error
	 */
	protected function prepare_item_for_database( $request ) {
		$menu_item_db_id = $request['id'];
		$menu_item_obj   = $this->get_nav_menu_item( $menu_item_db_id );
		// Need to persist the menu item data. See https://core.trac.wordpress.org/ticket/28138
		if ( ! is_wp_error( $menu_item_obj ) ) {
			// Correct the menu position if this was the first item. See https://core.trac.wordpress.org/ticket/28140
			$position = ( 0 === $menu_item_obj->menu_order ) ? 1 : $menu_item_obj->menu_order;

			$prepared_nav_item = array(
				'menu-item-db-id'       => $menu_item_db_id,
				'menu-item-object-id'   => $menu_item_obj->object_id,
				'menu-item-object'      => $menu_item_obj->object,
				'menu-item-parent-id'   => $menu_item_obj->menu_item_parent,
				'menu-item-position'    => $position,
				'menu-item-type'        => $menu_item_obj->type,
				'menu-item-title'       => $menu_item_obj->title,
				'menu-item-url'         => $menu_item_obj->url,
				'menu-item-description' => $menu_item_obj->description,
				'menu-item-attr-title'  => $menu_item_obj->attr_title,
				'menu-item-target'      => $menu_item_obj->target,
				'menu-item-classes'     => $menu_item_obj->classes,
				// Stored in the database as a string.
				'menu-item-xfn'         => explode( ' ', $menu_item_obj->xfn ),
				'menu-item-status'      => $menu_item_obj->post_status,
				'menu-id'               => $this->get_menu_id( $menu_item_db_id ),
			);
		} else {
			$prepared_nav_item = array(
				'menu-id'               => 0,
				'menu-item-db-id'       => 0,
				'menu-item-object-id'   => 0,
				'menu-item-object'      => '',
				'menu-item-parent-id'   => 0,
				'menu-item-position'    => 1,
				'menu-item-type'        => 'custom',
				'menu-item-title'       => '',
				'menu-item-url'         => '',
				'menu-item-description' => '',
				'menu-item-attr-title'  => '',
				'menu-item-target'      => '',
				'menu-item-classes'     => array(),
				'menu-item-xfn'         => array(),
				'menu-item-status'      => 'publish',
			);
		}

		$mapping = array(
			'menu-item-db-id'       => 'id',
			'menu-item-object-id'   => 'object_id',
			'menu-item-object'      => 'object',
			'menu-item-parent-id'   => 'parent',
			'menu-item-position'    => 'menu_order',
			'menu-item-type'        => 'type',
			'menu-item-url'         => 'url',
			'menu-item-description' => 'description',
			'menu-item-attr-title'  => 'attr_title',
			'menu-item-target'      => 'target',
			'menu-item-classes'     => 'classes',
			'menu-item-xfn'         => 'xfn',
			'menu-item-status'      => 'status',
		);

		$schema = $this->get_item_schema();

		foreach ( $mapping as $original => $api_request ) {
			if ( isset( $request[ $api_request ] ) ) {
				$prepared_nav_item[ $original ] = $request[ $api_request ];
			}
		}

		// If menus submitted, cast to int.
		if ( ! empty( $request['menus'] ) ) {
			$prepared_nav_item['menu-id'] = absint( $request['menus'] );
		}

		// Nav menu title.
		if ( ! empty( $schema['properties']['title'] ) && isset( $request['title'] ) ) {
			if ( is_string( $request['title'] ) ) {
				$prepared_nav_item['menu-item-title'] = $request['title'];
			} elseif ( ! empty( $request['title']['raw'] ) ) {
				$prepared_nav_item['menu-item-title'] = $request['title']['raw'];
			}
		}

		$error = new WP_Error();

		// Check if object id exists before saving.
		if ( ! $prepared_nav_item['menu-item-object'] ) {
			// If taxonomy, check if term exists.
			if ( 'taxonomy' === $prepared_nav_item['menu-item-type'] ) {
				$original = get_term( absint( $prepared_nav_item['menu-item-object-id'] ) );
				if ( empty( $original ) || is_wp_error( $original ) ) {
					$error->add( 'rest_term_invalid_id', __( 'Invalid term ID.' ), array( 'status' => 400 ) );
				} else {
					$prepared_nav_item['menu-item-object'] = get_term_field( 'taxonomy', $original );
				}
				// If post, check if post object exists.
			} elseif ( 'post_type' === $prepared_nav_item['menu-item-type'] ) {
				$original = get_post( absint( $prepared_nav_item['menu-item-object-id'] ) );
				if ( empty( $original ) ) {
					$error->add( 'rest_post_invalid_id', __( 'Invalid post ID.' ), array( 'status' => 400 ) );
				} else {
					$prepared_nav_item['menu-item-object'] = get_post_type( $original );
				}
			}
		}

		// If post type archive, check if post type exists.
		if ( 'post_type_archive' === $prepared_nav_item['menu-item-type'] ) {
			$post_type = $prepared_nav_item['menu-item-object'] ? $prepared_nav_item['menu-item-object'] : false;
			$original  = get_post_type_object( $post_type );
			if ( ! $original ) {
				$error->add( 'rest_post_invalid_type', __( 'Invalid post type.' ), array( 'status' => 400 ) );
			}
		}

		// Check if menu item is type custom, then title and url are required.
		if ( 'custom' === $prepared_nav_item['menu-item-type'] ) {
			if ( '' === $prepared_nav_item['menu-item-title'] ) {
				$error->add( 'rest_title_required', __( 'The title is required when using a custom menu item type.' ), array( 'status' => 400 ) );
			}
			if ( empty( $prepared_nav_item['menu-item-url'] ) ) {
				$error->add( 'rest_url_required', __( 'The url is required when using a custom menu item type.' ), array( 'status' => 400 ) );
			}
		}

		if ( $error->has_errors() ) {
			return $error;
		}

		// The xfn and classes properties are arrays, but passed to wp_update_nav_menu_item as a string.
		foreach ( array( 'menu-item-xfn', 'menu-item-classes' ) as $key ) {
			$prepared_nav_item[ $key ] = implode( ' ', $prepared_nav_item[ $key ] );
		}

		// Only draft / publish are valid post status for menu items.
		if ( 'publish' !== $prepared_nav_item['menu-item-status'] ) {
			$prepared_nav_item['menu-item-status'] = 'draft';
		}

		$prepared_nav_item = (object) $prepared_nav_item;

		/**
		 * Filters a menu item before it is inserted via the REST API.
		 *
		 * @since 5.9.0
		 *
		 * @param object          $prepared_nav_item An object representing a single menu item prepared
		 *                                           for inserting or updating the database.
		 * @param WP_REST_Request $request           Request object.
		 */
		return apply_filters( 'rest_pre_insert_nav_menu_item', $prepared_nav_item, $request );
	}

	/**
	 * Prepares a single nav menu item output for response.
	 *
	 * @since 5.9.0
	 *
	 * @param WP_Post         $item    Post object.
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function prepare_item_for_response( $item, $request ) {
		// Base fields for every post.
		$fields    = $this->get_fields_for_response( $request );
		$menu_item = $this->get_nav_menu_item( $item->ID );
		$data      = array();

		if ( rest_is_field_included( 'id', $fields ) ) {
			$data['id'] = $menu_item->ID;
		}

		if ( rest_is_field_included( 'title', $fields ) ) {
			$data['title'] = array();
		}

		if ( rest_is_field_included( 'title.raw', $fields ) ) {
			$data['title']['raw'] = $menu_item->title;
		}

		if ( rest_is_field_included( 'title.rendered', $fields ) ) {
			add_filter( 'protected_title_format', array( $this, 'protected_title_format' ) );
			add_filter( 'private_title_format', array( $this, 'protected_title_format' ) );

			/** This filter is documented in wp-includes/post-template.php */
			$title = apply_filters( 'the_title', $menu_item->title, $menu_item->ID );

			$data['title']['rendered'] = $title;

			remove_filter( 'protected_title_format', array( $this, 'protected_title_format' ) );
			remove_filter( 'private_title_format', array( $this, 'protected_title_format' ) );
		}

		if ( rest_is_field_included( 'status', $fields ) ) {
			$data['status'] = $menu_item->post_status;
		}

		if ( rest_is_field_included( 'url', $fields ) ) {
			$data['url'] = $menu_item->url;
		}

		if ( rest_is_field_included( 'attr_title', $fields ) ) {
			// Same as post_excerpt.
			$data['attr_title'] = $menu_item->attr_title;
		}

		if ( rest_is_field_included( 'description', $fields ) ) {
			// Same as post_content.
			$data['description'] = $menu_item->description;
		}

		if ( rest_is_field_included( 'type', $fields ) ) {
			$data['type'] = $menu_item->type;
		}

		if ( rest_is_field_included( 'type_label', $fields ) ) {
			$data['type_label'] = $menu_item->type_label;
		}

		if ( rest_is_field_included( 'object', $fields ) ) {
			$data['object'] = $menu_item->object;
		}

		if ( rest_is_field_included( 'object_id', $fields ) ) {
			// It is stored as a string, but should be exposed as an integer.
			$data['object_id'] = absint( $menu_item->object_id );
		}

		if ( rest_is_field_included( 'parent', $fields ) ) {
			// Same as post_parent, exposed as an integer.
			$data['parent'] = (int) $menu_item->menu_item_parent;
		}

		if ( rest_is_field_included( 'menu_order', $fields ) ) {
			// Same as post_parent, exposed as an integer.
			$data['menu_order'] = (int) $menu_item->menu_order;
		}

		if ( rest_is_field_included( 'target', $fields ) ) {
			$data['target'] = $menu_item->target;
		}

		if ( rest_is_field_included( 'classes', $fields ) ) {
			$data['classes'] = (array) $menu_item->classes;
		}

		if ( rest_is_field_included( 'xfn', $fields ) ) {
			$data['xfn'] = array_map( 'sanitize_html_class', explode( ' ', $menu_item->xfn ) );
		}

		if ( rest_is_field_included( 'invalid', $fields ) ) {
			$data['invalid'] = (bool) $menu_item->_invalid;
		}

		if ( rest_is_field_included( 'meta', $fields ) ) {
			$data['meta'] = $this->meta->get_value( $menu_item->ID, $request );
		}

		if ( rest_is_field_included( 'menus', $fields ) ) {
			$data['menus'] = _wp_get_menu_id_for_item( $menu_item->ID );
		}

		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data    = $this->add_additional_fields_to_object( $data, $request );
		$data    = $this->filter_response_by_context( $data, $context );

		// Wrap the data in a response object.
		$response = rest_ensure_response( $data );

		if ( rest_is_field_included( '_links', $fields ) || rest_is_field_included( '_embedded', $fields ) ) {
			$links = $this->prepare_links( $item );
			$response->add_links( $links );

			if ( ! empty( $links['self']['href'] ) ) {
				$actions = $this->get_available_actions( $item, $request );

				$self = $links['self']['href'];

				foreach ( $actions as $rel ) {
					$response->add_link( $rel, $self );
				}
			}
		}

		/**
		 * Filters the menu item data for a REST API response.
		 *
		 * @since 5.9.0
		 *
		 * @param WP_REST_Response $response  The response object.
		 * @param object           $menu_item Menu item setup by {@see wp_setup_nav_menu_item()}.
		 * @param WP_REST_Request  $request   Request object.
		 */
		return apply_filters( 'rest_prepare_nav_menu_item', $response, $menu_item, $request );
	}

	/**
	 * Prepares links for the request.
	 *
	 * @since 5.9.0
	 *
	 * @param WP_Post $post Post object.
	 * @return array Links for the given post.
	 */
	protected function prepare_links( $post ) {
		$menu_item = is_wp_error( $post ) ? $post : ( isset( $post->type ) ? $post : $this->get_nav_menu_item( $post->ID ) );
		$item_id   = isset( $post->ID ) ? $post->ID : 0;

		$links = array(
			'self'       => array(
				'href' => rest_url( sprintf( '%s/%s/%d', $this->namespace, $this->rest_base, $item_id ) ),
			),
			'collection' => array(
				'href' => rest_url( sprintf( '%s/%s', $this->namespace, $this->rest_base ) ),
			),
		);

		if ( is_wp_error( $menu_item ) || empty( $menu_item->object_id ) ) {
			return $links;
		}

		$path = '';
		$type = '';
		$key  = $menu_item->type;
		if ( 'post_type' === $menu_item->type ) {
			$path = rest_get_route_for_post( $menu_item->object_id );
			$type = get_post_type( $menu_item->object_id );
		} elseif ( 'taxonomy' === $menu_item->type ) {
			$path = rest_get_route_for_term( $menu_item->object_id );
			$type = get_term_field( 'taxonomy', $menu_item->object_id );
		}

		if ( $path && $type ) {
			$links['https://api.w.org/menu-item-object'][] = array(
				'href'       => rest_url( $path ),
				$key         => $type,
				'embeddable' => true,
			);
		}

		return $links;
	}

	/**
	 * Retrieves Link Description Objects that should be added to the Schema for the nav menu items collection.
	 *
	 * @since 5.9.0
	 *
	 * @return array
	 */
	protected function get_schema_links() {
		$href  = rest_url( "{$this->namespace}/{$this->rest_base}/{id}" );
		$links = array(
			array(
				'rel'          => 'https://api.w.org/menu-item-object',
				'title'        => __( 'Get linked object.' ),
				'href'         => $href,
				'targetSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'object' => array(
							'type' => 'integer',
						),
					),
				),
			),
		);

		return $links;
	}

	/**
	 * Retrieves the nav menu item's schema, conforming to JSON Schema.
	 *
	 * @since 5.9.0
	 *
	 * @return array Item schema data.
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$schema = array(
			'$schema' => 'http://json-schema.org/draft-04/schema#',
			'title'   => $this->post_type,
			'type'    => 'object',
		);

		$schema['properties']['title'] = array(
			'description' => __( 'The title for the object.' ),
			'type'        => array( 'string', 'object' ),
			'context'     => array( 'view', 'edit', 'embed' ),
			'properties'  => array(
				'raw'      => array(
					'description' => __( 'Title for the object, as it exists in the database.' ),
					'type'        => 'string',
					'context'     => array( 'edit' ),
				),
				'rendered' => array(
					'description' => __( 'HTML title for the object, transformed for display.' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
			),
		);

		$schema['properties']['id'] = array(
			'description' => __( 'Unique identifier for the object.' ),
			'type'        => 'integer',
			'default'     => 0,
			'minimum'     => 0,
			'context'     => array( 'view', 'edit', 'embed' ),
			'readonly'    => true,
		);

		$schema['properties']['type_label'] = array(
			'description' => __( 'The singular label used to describe this type of menu item.' ),
			'type'        => 'string',
			'context'     => array( 'view', 'edit', 'embed' ),
			'readonly'    => true,
		);

		$schema['properties']['type'] = array(
			'description' => __( 'The family of objects originally represented, such as "post_type" or "taxonomy".' ),
			'type'        => 'string',
			'enum'        => array( 'taxonomy', 'post_type', 'post_type_archive', 'custom' ),
			'context'     => array( 'view', 'edit', 'embed' ),
			'default'     => 'custom',
		);

		$schema['properties']['status'] = array(
			'description' => __( 'A named status for the object.' ),
			'type'        => 'string',
			'enum'        => array_keys( get_post_stati( array( 'internal' => false ) ) ),
			'default'     => 'publish',
			'context'     => array( 'view', 'edit', 'embed' ),
		);

		$schema['properties']['parent'] = array(
			'description' => __( 'The ID for the parent of the object.' ),
			'type'        => 'integer',
			'minimum'     => 0,
			'default'     => 0,
			'context'     => array( 'view', 'edit', 'embed' ),
		);

		$schema['properties']['attr_title'] = array(
			'description' => __( 'Text for the title attribute of the link element for this menu item.' ),
			'type'        => 'string',
			'context'     => array( 'view', 'edit', 'embed' ),
			'arg_options' => array(
				'sanitize_callback' => 'sanitize_text_field',
			),
		);

		$schema['properties']['classes'] = array(
			'description' => __( 'Class names for the link element of this menu item.' ),
			'type'        => 'array',
			'items'       => array(
				'type' => 'string',
			),
			'context'     => array( 'view', 'edit', 'embed' ),
			'arg_options' => array(
				'sanitize_callback' => static function ( $value ) {
					return array_map( 'sanitize_html_class', wp_parse_list( $value ) );
				},
			),
		);

		$schema['properties']['description'] = array(
			'description' => __( 'The description of this menu item.' ),
			'type'        => 'string',
			'context'     => array( 'view', 'edit', 'embed' ),
			'arg_options' => array(
				'sanitize_callback' => 'sanitize_text_field',
			),
		);

		$schema['properties']['menu_order'] = array(
			'description' => __( 'The DB ID of the nav_menu_item that is this item\'s menu parent, if any, otherwise 0.' ),
			'context'     => array( 'view', 'edit', 'embed' ),
			'type'        => 'integer',
			'minimum'     => 1,
			'default'     => 1,
		);

		$schema['properties']['object'] = array(
			'description' => __( 'The type of object originally represented, such as "category", "post", or "attachment".' ),
			'context'     => array( 'view', 'edit', 'embed' ),
			'type'        => 'string',
			'arg_options' => array(
				'sanitize_callback' => 'sanitize_key',
			),
		);

		$schema['properties']['object_id'] = array(
			'description' => __( 'The database ID of the original object this menu item represents, for example the ID for posts or the term_id for categories.' ),
			'context'     => array( 'view', 'edit', 'embed' ),
			'type'        => 'integer',
			'minimum'     => 0,
			'default'     => 0,
		);

		$schema['properties']['target'] = array(
			'description' => __( 'The target attribute of the link element for this menu item.' ),
			'type'        => 'string',
			'context'     => array( 'view', 'edit', 'embed' ),
			'enum'        => array(
				'_blank',
				'',
			),
		);

		$schema['properties']['url'] = array(
			'description' => __( 'The URL to which this menu item points.' ),
			'type'        => 'string',
			'format'      => 'uri',
			'context'     => array( 'view', 'edit', 'embed' ),
			'arg_options' => array(
				'validate_callback' => static function ( $url ) {
					if ( '' === $url ) {
						return true;
					}

					if ( sanitize_url( $url ) ) {
						return true;
					}

					return new WP_Error(
						'rest_invalid_url',
						__( 'Invalid URL.' )
					);
				},
			),
		);

		$schema['properties']['xfn'] = array(
			'description' => __( 'The XFN relationship expressed in the link of this menu item.' ),
			'type'        => 'array',
			'items'       => array(
				'type' => 'string',
			),
			'context'     => array( 'view', 'edit', 'embed' ),
			'arg_options' => array(
				'sanitize_callback' => static function ( $value ) {
					return array_map( 'sanitize_html_class', wp_parse_list( $value ) );
				},
			),
		);

		$schema['properties']['invalid'] = array(
			'description' => __( 'Whether the menu item represents an object that no longer exists.' ),
			'context'     => array( 'view', 'edit', 'embed' ),
			'type'        => 'boolean',
			'readonly'    => true,
		);

		$schema['properties']['menus'] = array(
			'description' => __( 'The ID of the menu this item belongs to.' ),
			'type'        => 'integer',
			'minimum'     => 0,
			'default'     => 0,
			'context'     => array( 'view', 'edit' ),
		);

		$schema['properties']['meta'] = $this->meta->get_field_schema();

		$schema_links = $this->get_schema_links();

		if ( $schema_links ) {
			$schema['links'] = $schema_links;
		}

		$this->schema = $schema;

		return $this->add_additional_fields_schema( $this->schema );
	}

	/**
	 * Retrieves the query params for the nav menu items collection.
	 *
	 * @since 5.9.0
	 *
	 * @return array Collection parameters.
	 */
	public function get_collection_params() {
		$query_params = parent::get_collection_params();

		$query_params['menu_order'] = array(
			'description' => __( 'Limit result set to items with a specific menu_order value.' ),
			'type'        => 'integer',
		);

		$query_params['order'] = array(
			'description' => __( 'Order sort attribute ascending or descending.' ),
			'type'        => 'string',
			'default'     => 'asc',
			'enum'        => array( 'asc', 'desc' ),
		);

		$query_params['orderby'] = array(
			'description' => __( 'Sort collection by object attribute.' ),
			'type'        => 'string',
			'default'     => 'menu_order',
			'enum'        => array(
				'id',
				'title',
				'menu_order',
			),
		);

		$query_params['menus'] = array(
			'description' => __( 'Limit result set to items assigned to a specific menu.' ),
			'type'        => 'integer',
		);

		// Change default to 100 items.
		$query_params['per_page']['default'] = 100;

		return $query_params;
	}

	/**
	 * Gets the id of the menu that the given menu item belongs to.
	 *
	 * @since 5.9.0
	 *
	 * @param int $menu_item_id Menu item id.
	 * @return int
	 */
	protected function get_menu_id( $menu_item_id ) {
		return _wp_get_menu_id_for_item( $menu_item_id );
	}
}
