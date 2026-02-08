<?php
/**
 * Navigation Menu functions
 *
 * @package WordPress
 * @subpackage Nav_Menus
 * @since 3.0.0
 */

/**
 * Converts a wp_menus row to a WP_Term-compatible object.
 *
 * @since 7.0.0
 * @access private
 *
 * @param object $row Database row from wp_menus.
 * @return WP_Term Menu object with term-compatible properties.
 */
function _wp_menu_row_to_term( $row ) {
	$obj                  = new stdClass();
	$obj->term_id         = (int) $row->id;
	$obj->name            = $row->name;
	$obj->slug            = $row->slug;
	$obj->term_group      = 0;
	$obj->term_taxonomy_id = (int) $row->id;
	$obj->taxonomy        = 'nav_menu';
	$obj->description     = '';
	$obj->parent          = 0;
	$obj->count           = 0;
	$obj->filter          = 'raw';

	return new WP_Term( $obj );
}

/**
 * Converts a wp_menu_items row to a WP_Post-compatible object with nav menu item properties.
 *
 * @since 7.0.0
 * @access private
 *
 * @param object $row Database row from wp_menu_items.
 * @return WP_Post Menu item object with all properties pre-populated.
 */
function _wp_menu_item_row_to_post( $row ) {
	$obj                      = new stdClass();
	$obj->ID                  = (int) $row->id;
	$obj->post_author         = 0;
	$obj->post_date           = current_time( 'mysql' );
	$obj->post_date_gmt       = current_time( 'mysql', 1 );
	$obj->post_content        = $row->description;
	$obj->post_title          = $row->title;
	$obj->post_excerpt        = $row->attr_title;
	$obj->post_status         = $row->status;
	$obj->comment_status      = 'closed';
	$obj->ping_status         = 'closed';
	$obj->post_password       = '';
	$obj->post_name           = '';
	$obj->to_ping             = '';
	$obj->pinged              = '';
	$obj->post_modified       = current_time( 'mysql' );
	$obj->post_modified_gmt   = current_time( 'mysql', 1 );
	$obj->post_content_filtered = '';
	$obj->post_parent         = 0;
	$obj->guid                = '';
	$obj->menu_order          = (int) $row->position;
	$obj->post_type           = 'nav_menu_item';
	$obj->post_mime_type      = '';
	$obj->comment_count       = 0;
	$obj->filter              = 'raw';

	$post = new WP_Post( $obj );

	// Pre-populate nav menu item properties so wp_setup_nav_menu_item() skips meta reads.
	$post->menu_item_parent = (string) $row->parent_id;
	$post->object_id        = (string) $row->object_id;
	$post->object           = $row->object_type;
	$post->type             = $row->item_type;
	$post->target           = $row->target;
	$post->classes          = array_filter( explode( ' ', $row->css_classes ) );
	$post->xfn              = $row->xfn;

	if ( 'custom' === $row->item_type ) {
		$post->url = $row->url;
	}

	return $post;
}

/**
 * Retrieves a single menu item from wp_menu_items.
 *
 * @since 7.0.0
 * @access private
 *
 * @param int $item_id Menu item ID.
 * @return WP_Post|false Menu item object or false.
 */
function _wp_get_menu_item( $item_id ) {
	global $wpdb;

	$item_id = (int) $item_id;
	if ( ! $item_id ) {
		return false;
	}

	$cached = wp_cache_get( $item_id, 'menu_items' );
	if ( false !== $cached ) {
		return $cached;
	}

	$row = $wpdb->get_row( $wpdb->prepare(
		"SELECT * FROM $wpdb->menu_items WHERE id = %d",
		$item_id
	) );

	if ( ! $row ) {
		return false;
	}

	$item = _wp_menu_item_row_to_post( $row );
	wp_cache_set( $item_id, $item, 'menu_items' );
	return $item;
}

/**
 * Retrieves the parent ID of a menu item.
 *
 * Used by nav-menu-template.php for walking up the ancestor chain.
 *
 * @since 7.0.0
 * @access private
 *
 * @param int $item_id Menu item ID.
 * @return int Parent menu item ID, or 0.
 */
function _wp_get_menu_item_parent( $item_id ) {
	global $wpdb;

	$item_id = (int) $item_id;
	if ( ! $item_id ) {
		return 0;
	}

	$item = _wp_get_menu_item( $item_id );
	if ( $item ) {
		return (int) $item->menu_item_parent;
	}

	return 0;
}

/**
 * Retrieves the menu ID that a menu item belongs to.
 *
 * @since 7.0.0
 * @access private
 *
 * @param int $item_id Menu item ID.
 * @return int Menu ID, or 0.
 */
function _wp_get_menu_id_for_item( $item_id ) {
	global $wpdb;

	$item_id = (int) $item_id;
	if ( ! $item_id ) {
		return 0;
	}

	return (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT menu_id FROM $wpdb->menu_items WHERE id = %d",
		$item_id
	) );
}

/**
 * Updates one or more fields of a menu item directly in wp_menu_items.
 *
 * @since 7.0.0
 * @access private
 *
 * @param int   $item_id Menu item ID.
 * @param array $data    Column => value pairs to update.
 */
function _wp_update_menu_item_fields( $item_id, $data ) {
	global $wpdb;

	$item_id = (int) $item_id;
	if ( ! $item_id || empty( $data ) ) {
		return;
	}

	$wpdb->update( $wpdb->menu_items, $data, array( 'id' => $item_id ) );
	wp_cache_delete( $item_id, 'menu_items' );
}

/**
 * Deletes a menu item from wp_menu_items.
 *
 * @since 7.0.0
 * @access private
 *
 * @param int $item_id Menu item ID.
 * @return bool True on success, false on failure.
 */
function _wp_delete_menu_item( $item_id ) {
	global $wpdb;

	$item_id = (int) $item_id;
	if ( ! $item_id ) {
		return false;
	}

	$result = $wpdb->delete( $wpdb->menu_items, array( 'id' => $item_id ), array( '%d' ) );
	wp_cache_delete( $item_id, 'menu_items' );
	return (bool) $result;
}

/**
 * Returns a navigation menu object.
 *
 * @since 3.0.0
 *
 * @param int|string|WP_Term $menu Menu ID, slug, name, or object.
 * @return WP_Term|false Menu object on success, false if $menu param isn't supplied or term does not exist.
 */
function wp_get_nav_menu_object( $menu ) {
	global $wpdb;

	$menu_obj = false;

	if ( is_object( $menu ) ) {
		$menu_obj = $menu;
	}

	if ( $menu && ! $menu_obj ) {
		if ( is_numeric( $menu ) ) {
			$row = $wpdb->get_row( $wpdb->prepare(
				"SELECT * FROM $wpdb->menus WHERE id = %d",
				(int) $menu
			) );
		} else {
			$row = null;
		}

		if ( ! $row && is_string( $menu ) ) {
			$row = $wpdb->get_row( $wpdb->prepare(
				"SELECT * FROM $wpdb->menus WHERE slug = %s",
				$menu
			) );
		}

		if ( ! $row && is_string( $menu ) ) {
			$row = $wpdb->get_row( $wpdb->prepare(
				"SELECT * FROM $wpdb->menus WHERE name = %s",
				$menu
			) );
		}

		if ( $row ) {
			$menu_obj = _wp_menu_row_to_term( $row );
		}
	}

	if ( ! $menu_obj || is_wp_error( $menu_obj ) ) {
		$menu_obj = false;
	}

	/**
	 * Filters the nav_menu term retrieved for wp_get_nav_menu_object().
	 *
	 * @since 4.3.0
	 *
	 * @param WP_Term|false      $menu_obj Term from nav_menu taxonomy, or false if nothing had been found.
	 * @param int|string|WP_Term $menu     The menu ID, slug, name, or object passed to wp_get_nav_menu_object().
	 */
	return apply_filters( 'wp_get_nav_menu_object', $menu_obj, $menu );
}

/**
 * Determines whether the given ID is a navigation menu.
 *
 * Returns true if it is; false otherwise.
 *
 * @since 3.0.0
 *
 * @param int|string|WP_Term $menu Menu ID, slug, name, or object of menu to check.
 * @return bool Whether the menu exists.
 */
function is_nav_menu( $menu ) {
	if ( ! $menu ) {
		return false;
	}

	$menu_obj = wp_get_nav_menu_object( $menu );

	if (
		$menu_obj &&
		! is_wp_error( $menu_obj ) &&
		! empty( $menu_obj->taxonomy ) &&
		'nav_menu' === $menu_obj->taxonomy
	) {
		return true;
	}

	return false;
}

/**
 * Registers navigation menu locations for a theme.
 *
 * @since 3.0.0
 *
 * @global array $_wp_registered_nav_menus
 *
 * @param string[] $locations Associative array of menu location identifiers (like a slug) and descriptive text.
 */
function register_nav_menus( $locations = array() ) {
	global $_wp_registered_nav_menus;

	add_theme_support( 'menus' );

	foreach ( $locations as $key => $value ) {
		if ( is_int( $key ) ) {
			_doing_it_wrong( __FUNCTION__, __( 'Nav menu locations must be strings.' ), '5.3.0' );
			break;
		}
	}

	$_wp_registered_nav_menus = array_merge( (array) $_wp_registered_nav_menus, $locations );
}

/**
 * Unregisters a navigation menu location for a theme.
 *
 * @since 3.1.0
 *
 * @global array $_wp_registered_nav_menus
 *
 * @param string $location The menu location identifier.
 * @return bool True on success, false on failure.
 */
function unregister_nav_menu( $location ) {
	global $_wp_registered_nav_menus;

	if ( is_array( $_wp_registered_nav_menus ) && isset( $_wp_registered_nav_menus[ $location ] ) ) {
		unset( $_wp_registered_nav_menus[ $location ] );
		if ( empty( $_wp_registered_nav_menus ) ) {
			_remove_theme_support( 'menus' );
		}
		return true;
	}
	return false;
}

/**
 * Registers a navigation menu location for a theme.
 *
 * @since 3.0.0
 *
 * @param string $location    Menu location identifier, like a slug.
 * @param string $description Menu location descriptive text.
 */
function register_nav_menu( $location, $description ) {
	register_nav_menus( array( $location => $description ) );
}
/**
 * Retrieves all registered navigation menu locations in a theme.
 *
 * @since 3.0.0
 *
 * @global array $_wp_registered_nav_menus
 *
 * @return string[] Associative array of registered navigation menu descriptions keyed
 *                  by their location. If none are registered, an empty array.
 */
function get_registered_nav_menus() {
	global $_wp_registered_nav_menus;
	return $_wp_registered_nav_menus ?? array();
}

/**
 * Retrieves all registered navigation menu locations and the menus assigned to them.
 *
 * @since 3.0.0
 *
 * @return int[] Associative array of registered navigation menu IDs keyed by their
 *               location name. If none are registered, an empty array.
 */
function get_nav_menu_locations() {
	$locations = get_theme_mod( 'nav_menu_locations' );
	return ( is_array( $locations ) ) ? $locations : array();
}

/**
 * Determines whether a registered nav menu location has a menu assigned to it.
 *
 * @since 3.0.0
 *
 * @param string $location Menu location identifier.
 * @return bool Whether location has a menu.
 */
function has_nav_menu( $location ) {
	$has_nav_menu = false;

	$registered_nav_menus = get_registered_nav_menus();
	if ( isset( $registered_nav_menus[ $location ] ) ) {
		$locations    = get_nav_menu_locations();
		$has_nav_menu = ! empty( $locations[ $location ] );
	}

	/**
	 * Filters whether a nav menu is assigned to the specified location.
	 *
	 * @since 4.3.0
	 *
	 * @param bool   $has_nav_menu Whether there is a menu assigned to a location.
	 * @param string $location     Menu location.
	 */
	return apply_filters( 'has_nav_menu', $has_nav_menu, $location );
}

/**
 * Returns the name of a navigation menu.
 *
 * @since 4.9.0
 *
 * @param string $location Menu location identifier.
 * @return string Menu name.
 */
function wp_get_nav_menu_name( $location ) {
	$menu_name = '';

	$locations = get_nav_menu_locations();

	if ( isset( $locations[ $location ] ) ) {
		$menu = wp_get_nav_menu_object( $locations[ $location ] );

		if ( $menu && $menu->name ) {
			$menu_name = $menu->name;
		}
	}

	/**
	 * Filters the navigation menu name being returned.
	 *
	 * @since 4.9.0
	 *
	 * @param string $menu_name Menu name.
	 * @param string $location  Menu location identifier.
	 */
	return apply_filters( 'wp_get_nav_menu_name', $menu_name, $location );
}

/**
 * Determines whether the given ID is a nav menu item.
 *
 * @since 3.0.0
 *
 * @param int $menu_item_id The ID of the potential nav menu item.
 * @return bool Whether the given ID is that of a nav menu item.
 */
function is_nav_menu_item( $menu_item_id = 0 ) {
	if ( is_wp_error( $menu_item_id ) ) {
		return false;
	}
	$item = _wp_get_menu_item( (int) $menu_item_id );
	return (bool) $item;
}

/**
 * Creates a navigation menu.
 *
 * Note that `$menu_name` is expected to be pre-slashed.
 *
 * @since 3.0.0
 *
 * @param string $menu_name Menu name.
 * @return int|WP_Error Menu ID on success, WP_Error object on failure.
 */
function wp_create_nav_menu( $menu_name ) {
	// expected_slashed ($menu_name)
	return wp_update_nav_menu_object( 0, array( 'menu-name' => $menu_name ) );
}

/**
 * Deletes a navigation menu.
 *
 * @since 3.0.0
 *
 * @param int|string|WP_Term $menu Menu ID, slug, name, or object.
 * @return bool|WP_Error True on success, false or WP_Error object on failure.
 */
function wp_delete_nav_menu( $menu ) {
	global $wpdb;

	$menu = wp_get_nav_menu_object( $menu );
	if ( ! $menu ) {
		return false;
	}

	// Delete all menu items belonging to this menu.
	$wpdb->delete( $wpdb->menu_items, array( 'menu_id' => $menu->term_id ), array( '%d' ) );

	// Delete the menu itself.
	$result = $wpdb->delete( $wpdb->menus, array( 'id' => $menu->term_id ), array( '%d' ) );

	// Remove this menu from any locations.
	$locations = get_nav_menu_locations();
	foreach ( $locations as $location => $menu_id ) {
		if ( $menu_id === $menu->term_id ) {
			$locations[ $location ] = 0;
		}
	}
	set_theme_mod( 'nav_menu_locations', $locations );

	if ( $result ) {

		/**
		 * Fires after a navigation menu has been successfully deleted.
		 *
		 * @since 3.0.0
		 *
		 * @param int $term_id ID of the deleted menu.
		 */
		do_action( 'wp_delete_nav_menu', $menu->term_id );
	}

	return (bool) $result;
}

/**
 * Saves the properties of a menu or create a new menu with those properties.
 *
 * Note that `$menu_data` is expected to be pre-slashed.
 *
 * @since 3.0.0
 *
 * @param int   $menu_id   The ID of the menu or "0" to create a new menu.
 * @param array $menu_data The array of menu data.
 * @return int|WP_Error Menu ID on success, WP_Error object on failure.
 */
function wp_update_nav_menu_object( $menu_id = 0, $menu_data = array() ) {
	global $wpdb;

	// expected_slashed ($menu_data)
	$menu_id = (int) $menu_id;

	$_menu = wp_get_nav_menu_object( $menu_id );

	$menu_name = $menu_data['menu-name'] ?? '';

	// Double-check that we're not going to have one menu take the name of another.
	$existing = $wpdb->get_row( $wpdb->prepare(
		"SELECT id FROM $wpdb->menus WHERE name = %s AND id != %d",
		$menu_name,
		$menu_id
	) );

	if ( $existing ) {
		return new WP_Error(
			'menu_exists',
			sprintf(
				/* translators: %s: Menu name. */
				__( 'The menu name %s conflicts with another menu name. Please try another.' ),
				'<strong>' . esc_html( $menu_name ) . '</strong>'
			)
		);
	}

	$slug = sanitize_title( $menu_name );

	// Menu doesn't already exist, so create a new menu.
	if ( ! $_menu || is_wp_error( $_menu ) ) {
		$result = $wpdb->insert(
			$wpdb->menus,
			array(
				'name' => $menu_name,
				'slug' => wp_unique_term_slug( $slug, (object) array( 'taxonomy' => 'nav_menu' ) ),
			),
			array( '%s', '%s' )
		);

		if ( false === $result ) {
			return new WP_Error( 'db_insert_error', __( 'Could not insert menu into the database.' ) );
		}

		$menu_id = (int) $wpdb->insert_id;

		/**
		 * Fires after a navigation menu is successfully created.
		 *
		 * @since 3.0.0
		 *
		 * @param int   $term_id   ID of the new menu.
		 * @param array $menu_data An array of menu data.
		 */
		do_action( 'wp_create_nav_menu', $menu_id, $menu_data );

		return $menu_id;
	}

	if ( ! $_menu || ! isset( $_menu->term_id ) ) {
		return 0;
	}

	$menu_id = (int) $_menu->term_id;

	$wpdb->update(
		$wpdb->menus,
		array(
			'name' => $menu_name,
			'slug' => $slug,
		),
		array( 'id' => $menu_id ),
		array( '%s', '%s' ),
		array( '%d' )
	);

	/**
	 * Fires after a navigation menu has been successfully updated.
	 *
	 * @since 3.0.0
	 *
	 * @param int   $menu_id   ID of the updated menu.
	 * @param array $menu_data An array of menu data.
	 */
	do_action( 'wp_update_nav_menu', $menu_id, $menu_data );
	return $menu_id;
}

/**
 * Saves the properties of a menu item or create a new one.
 *
 * The menu-item-title, menu-item-description and menu-item-attr-title are expected
 * to be pre-slashed since they are passed directly to APIs that expect slashed data.
 *
 * @since 3.0.0
 * @since 5.9.0 Added the `$fire_after_hooks` parameter.
 *
 * @param int   $menu_id          The ID of the menu. If 0, makes the menu item a draft orphan.
 * @param int   $menu_item_db_id  The ID of the menu item. If 0, creates a new menu item.
 * @param array $menu_item_data   The menu item's data.
 * @param bool  $fire_after_hooks Whether to fire the after insert hooks. Default true.
 * @return int|WP_Error The menu item's database ID or WP_Error object on failure.
 */
function wp_update_nav_menu_item( $menu_id = 0, $menu_item_db_id = 0, $menu_item_data = array(), $fire_after_hooks = true ) {
	global $wpdb;

	$menu_id         = (int) $menu_id;
	$menu_item_db_id = (int) $menu_item_db_id;

	// Make sure that we don't convert non-nav_menu_item objects into nav_menu_item objects.
	if ( ! empty( $menu_item_db_id ) && ! is_nav_menu_item( $menu_item_db_id ) ) {
		return new WP_Error( 'update_nav_menu_item_failed', __( 'The given object ID is not that of a menu item.' ) );
	}

	$menu = wp_get_nav_menu_object( $menu_id );

	if ( ! $menu && 0 !== $menu_id ) {
		return new WP_Error( 'invalid_menu_id', __( 'Invalid menu ID.' ) );
	}

	if ( is_wp_error( $menu ) ) {
		return $menu;
	}

	$defaults = array(
		'menu-item-db-id'         => $menu_item_db_id,
		'menu-item-object-id'     => 0,
		'menu-item-object'        => '',
		'menu-item-parent-id'     => 0,
		'menu-item-position'      => 0,
		'menu-item-type'          => 'custom',
		'menu-item-title'         => '',
		'menu-item-url'           => '',
		'menu-item-description'   => '',
		'menu-item-attr-title'    => '',
		'menu-item-target'        => '',
		'menu-item-classes'       => '',
		'menu-item-xfn'           => '',
		'menu-item-status'        => '',
	);

	$args = wp_parse_args( $menu_item_data, $defaults );

	if ( 0 === $menu_id ) {
		$args['menu-item-position'] = 1;
	} elseif ( 0 === (int) $args['menu-item-position'] ) {
		$menu_items = array();

		if ( 0 !== $menu_id ) {
			$menu_items = (array) wp_get_nav_menu_items( $menu_id, array( 'post_status' => 'publish,draft' ) );
		}

		$last_item = array_pop( $menu_items );

		if ( $last_item && isset( $last_item->menu_order ) ) {
			$args['menu-item-position'] = 1 + $last_item->menu_order;
		} else {
			$args['menu-item-position'] = count( $menu_items );
		}
	}

	if ( 'custom' === $args['menu-item-type'] ) {
		$args['menu-item-url'] = trim( $args['menu-item-url'] );
	} else {
		$args['menu-item-url'] = '';

		$original_title = '';

		if ( 'taxonomy' === $args['menu-item-type'] ) {
			$original_object = get_term( $args['menu-item-object-id'], $args['menu-item-object'] );

			if ( $original_object instanceof WP_Term ) {
				$original_title = get_term_field( 'name', $args['menu-item-object-id'], $args['menu-item-object'], 'raw' );
			}
		} elseif ( 'post_type' === $args['menu-item-type'] ) {
			$original_object = get_post( $args['menu-item-object-id'] );

			if ( $original_object instanceof WP_Post ) {
				$original_title = $original_object->post_title;
			}
		} elseif ( 'post_type_archive' === $args['menu-item-type'] ) {
			$original_object = get_post_type_object( $args['menu-item-object'] );

			if ( $original_object instanceof WP_Post_Type ) {
				$original_title = $original_object->labels->archives;
			}
		}

		if ( wp_unslash( $args['menu-item-title'] ) === wp_specialchars_decode( $original_title ) ) {
			$args['menu-item-title'] = '';
		}
	}

	$args['menu-item-classes'] = array_map( 'sanitize_html_class', explode( ' ', $args['menu-item-classes'] ) );
	$args['menu-item-xfn']    = implode( ' ', array_map( 'sanitize_html_class', explode( ' ', $args['menu-item-xfn'] ) ) );

	$update = 0 !== $menu_item_db_id;

	// Reset invalid `menu_item_parent`.
	if ( (int) $args['menu-item-parent-id'] === $menu_item_db_id ) {
		$args['menu-item-parent-id'] = 0;
	}

	if ( 'custom' === $args['menu-item-type'] && 0 === (int) $args['menu-item-object-id'] ) {
		// Will be set to the item's own ID after insert.
		$args['menu-item-object']    = 'custom';
	}

	$data = array(
		'menu_id'     => $menu_id,
		'parent_id'   => (int) $args['menu-item-parent-id'],
		'position'    => (int) $args['menu-item-position'],
		'item_type'   => sanitize_key( $args['menu-item-type'] ),
		'object_type' => sanitize_key( $args['menu-item-object'] ),
		'object_id'   => (int) $args['menu-item-object-id'],
		'title'       => $args['menu-item-title'],
		'url'         => sanitize_url( $args['menu-item-url'] ),
		'target'      => sanitize_key( $args['menu-item-target'] ),
		'css_classes' => implode( ' ', $args['menu-item-classes'] ),
		'xfn'         => $args['menu-item-xfn'],
		'description' => $args['menu-item-description'],
		'attr_title'  => $args['menu-item-attr-title'],
		'status'      => ( 'publish' === $args['menu-item-status'] ) ? 'publish' : 'draft',
	);

	$format = array( '%d', '%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' );

	if ( ! $update ) {
		$result = $wpdb->insert( $wpdb->menu_items, $data, $format );

		if ( false === $result ) {
			return new WP_Error( 'db_insert_error', __( 'Could not insert menu item into the database.' ) );
		}

		$menu_item_db_id = (int) $wpdb->insert_id;

		// For custom items, object_id is the item's own ID.
		if ( 'custom' === $args['menu-item-type'] && 0 === (int) $args['menu-item-object-id'] ) {
			$wpdb->update(
				$wpdb->menu_items,
				array( 'object_id' => $menu_item_db_id ),
				array( 'id' => $menu_item_db_id ),
				array( '%d' ),
				array( '%d' )
			);
		}

		wp_cache_delete( $menu_item_db_id, 'menu_items' );

		/**
		 * Fires immediately after a new navigation menu item has been added.
		 *
		 * @since 4.4.0
		 *
		 * @see wp_update_nav_menu_item()
		 *
		 * @param int   $menu_id         ID of the updated menu.
		 * @param int   $menu_item_db_id ID of the new menu item.
		 * @param array $args            An array of arguments used to update/add the menu item.
		 */
		do_action( 'wp_add_nav_menu_item', $menu_id, $menu_item_db_id, $args );
	} else {
		// Update existing menu item.
		if ( 'custom' === $args['menu-item-type'] && 0 === (int) $args['menu-item-object-id'] ) {
			$data['object_id'] = $menu_item_db_id;
		}

		$data['status'] = ( 'draft' === $args['menu-item-status'] ) ? 'draft' : 'publish';

		$wpdb->update(
			$wpdb->menu_items,
			$data,
			array( 'id' => $menu_item_db_id ),
			$format,
			array( '%d' )
		);

		wp_cache_delete( $menu_item_db_id, 'menu_items' );
	}

	/**
	 * Fires after a navigation menu item has been updated.
	 *
	 * @since 3.0.0
	 *
	 * @see wp_update_nav_menu_item()
	 *
	 * @param int   $menu_id         ID of the updated menu.
	 * @param int   $menu_item_db_id ID of the updated menu item.
	 * @param array $args            An array of arguments used to update a menu item.
	 */
	do_action( 'wp_update_nav_menu_item', $menu_id, $menu_item_db_id, $args );

	return $menu_item_db_id;
}

/**
 * Returns all navigation menu objects.
 *
 * @since 3.0.0
 * @since 4.1.0 Default value of the 'orderby' argument was changed from 'none'
 *              to 'name'.
 *
 * @param array $args Optional. Array of arguments passed on to get_terms().
 *                    Default empty array.
 * @return WP_Term[] An array of menu objects.
 */
function wp_get_nav_menus( $args = array() ) {
	global $wpdb;

	$defaults = array(
		'orderby' => 'name',
		'order'   => 'ASC',
	);
	$args     = wp_parse_args( $args, $defaults );

	$orderby = ( 'id' === strtolower( $args['orderby'] ) ) ? 'id' : 'name';
	$order   = ( 'DESC' === strtoupper( $args['order'] ) ) ? 'DESC' : 'ASC';

	$rows  = $wpdb->get_results( "SELECT * FROM $wpdb->menus ORDER BY $orderby $order" );
	$menus = array();

	foreach ( $rows as $row ) {
		$menu = _wp_menu_row_to_term( $row );

		// Populate item count.
		$menu->count = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM $wpdb->menu_items WHERE menu_id = %d AND status = 'publish'",
			$menu->term_id
		) );

		$menus[] = $menu;
	}

	/**
	 * Filters the navigation menu objects being returned.
	 *
	 * @since 3.0.0
	 *
	 * @see get_terms()
	 *
	 * @param WP_Term[] $menus An array of menu objects.
	 * @param array     $args  An array of arguments used to retrieve menu objects.
	 */
	return apply_filters( 'wp_get_nav_menus', $menus, $args );
}

/**
 * Determines whether a menu item is valid.
 *
 * @link https://core.trac.wordpress.org/ticket/13958
 *
 * @since 3.2.0
 * @access private
 *
 * @param object $item The menu item to check.
 * @return bool False if invalid, otherwise true.
 */
function _is_valid_nav_menu_item( $item ) {
	return empty( $item->_invalid );
}

/**
 * Retrieves all menu items of a navigation menu.
 *
 * Note: Most arguments passed to the `$args` parameter – save for 'output_key' – are
 * specifically for retrieving nav_menu_item posts from get_posts() and may only
 * indirectly affect the ultimate ordering and content of the resulting nav menu
 * items that get returned from this function.
 *
 * @since 3.0.0
 *
 * @param int|string|WP_Term $menu Menu ID, slug, name, or object.
 * @param array              $args {
 *     Optional. Arguments to pass to get_posts().
 *
 *     @type string $order                  How to order nav menu items as queried with get_posts().
 *                                          Will be ignored if 'output' is ARRAY_A. Default 'ASC'.
 *     @type string $orderby                Field to order menu items by as retrieved from get_posts().
 *                                          Supply an orderby field via 'output_key' to affect the
 *                                          output order of nav menu items. Default 'menu_order'.
 *     @type string $post_type              Menu items post type. Default 'nav_menu_item'.
 *     @type string $post_status            Menu items post status. Default 'publish'.
 *     @type string $output                 How to order outputted menu items. Default ARRAY_A.
 *     @type string $output_key             Key to use for ordering the actual menu items that get
 *                                          returned. Note that that is not a get_posts() argument
 *                                          and will only affect output of menu items processed in
 *                                          this function. Default 'menu_order'.
 *     @type bool   $nopaging               Whether to retrieve all menu items (true) or paginate
 *                                          (false). Default true.
 *     @type bool   $update_menu_item_cache Whether to update the menu item cache. Default true.
 * }
 * @return array|false Array of menu items, otherwise false.
 */
function wp_get_nav_menu_items( $menu, $args = array() ) {
	global $wpdb;

	$menu = wp_get_nav_menu_object( $menu );

	if ( ! $menu ) {
		return false;
	}

	$defaults = array(
		'order'                  => 'ASC',
		'orderby'                => 'menu_order',
		'post_status'            => 'publish',
		'output'                 => ARRAY_A,
		'output_key'             => 'menu_order',
		'nopaging'               => true,
		'update_menu_item_cache' => true,
	);
	$args     = wp_parse_args( $args, $defaults );

	// Build WHERE clause for status filter.
	$statuses    = array_map( 'trim', explode( ',', $args['post_status'] ) );
	$placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );

	$query_args   = array_merge( array( $menu->term_id ), $statuses );
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM $wpdb->menu_items WHERE menu_id = %d AND status IN ($placeholders) ORDER BY position ASC",
		$query_args
	) );

	$items = array();
	foreach ( $rows as $row ) {
		$item = _wp_menu_item_row_to_post( $row );
		wp_cache_set( $item->ID, $item, 'menu_items' );
		$items[] = $item;
	}

	$items = array_map( 'wp_setup_nav_menu_item', $items );

	// Prime linked object caches.
	if ( $args['update_menu_item_cache'] ) {
		update_menu_item_cache( $items );
	}

	if ( ! is_admin() ) { // Remove invalid items only on front end.
		$items = array_filter( $items, '_is_valid_nav_menu_item' );
	}

	if ( ARRAY_A === $args['output'] ) {
		$items = wp_list_sort(
			$items,
			array(
				$args['output_key'] => 'ASC',
			)
		);

		$i = 1;

		foreach ( $items as $k => $item ) {
			$items[ $k ]->{$args['output_key']} = $i++;
		}
	}

	/**
	 * Filters the navigation menu items being returned.
	 *
	 * @since 3.0.0
	 *
	 * @param array  $items An array of menu item post objects.
	 * @param object $menu  The menu object.
	 * @param array  $args  An array of arguments used to retrieve menu item objects.
	 */
	return apply_filters( 'wp_get_nav_menu_items', $items, $menu, $args );
}

/**
 * Updates post and term caches for all linked objects for a list of menu items.
 *
 * @since 6.1.0
 *
 * @param WP_Post[] $menu_items Array of menu item post objects.
 */
function update_menu_item_cache( $menu_items ) {
	$post_ids = array();
	$term_ids = array();

	foreach ( $menu_items as $menu_item ) {
		// Properties are already populated on the object by _wp_menu_item_row_to_post().
		$object_id = isset( $menu_item->object_id ) ? (int) $menu_item->object_id : 0;
		$type      = isset( $menu_item->type ) ? $menu_item->type : '';

		if ( 'post_type' === $type ) {
			$post_ids[] = $object_id;
		} elseif ( 'taxonomy' === $type ) {
			$term_ids[] = $object_id;
		}
	}

	if ( ! empty( $post_ids ) ) {
		_prime_post_caches( $post_ids, false );
	}

	if ( ! empty( $term_ids ) ) {
		_prime_term_caches( $term_ids );
	}
}

/**
 * Decorates a menu item object with the shared navigation menu item properties.
 *
 * Properties:
 * - ID:               The term_id if the menu item represents a taxonomy term.
 * - attr_title:       The title attribute of the link element for this menu item.
 * - classes:          The array of class attribute values for the link element of this menu item.
 * - db_id:            The DB ID of this item as a nav_menu_item object, if it exists (0 if it doesn't exist).
 * - description:      The description of this menu item.
 * - menu_item_parent: The DB ID of the nav_menu_item that is this item's menu parent, if any. 0 otherwise.
 * - object:           The type of object originally represented, such as 'category', 'post', or 'attachment'.
 * - object_id:        The DB ID of the original object this menu item represents, e.g. ID for posts and term_id for categories.
 * - post_parent:      The DB ID of the original object's parent object, if any (0 otherwise).
 * - post_title:       A "no title" label if menu item represents a post that lacks a title.
 * - target:           The target attribute of the link element for this menu item.
 * - title:            The title of this menu item.
 * - type:             The family of objects originally represented, such as 'post_type' or 'taxonomy'.
 * - type_label:       The singular label used to describe this type of menu item.
 * - url:              The URL to which this menu item points.
 * - xfn:              The XFN relationship expressed in the link of this menu item.
 * - _invalid:         Whether the menu item represents an object that no longer exists.
 *
 * @since 3.0.0
 *
 * @param object $menu_item The menu item to modify.
 * @return object The menu item with standard menu item properties.
 */
function wp_setup_nav_menu_item( $menu_item ) {

	/**
	 * Filters whether to short-circuit the wp_setup_nav_menu_item() output.
	 *
	 * Returning a non-null value from the filter will short-circuit wp_setup_nav_menu_item(),
	 * returning that value instead.
	 *
	 * @since 6.3.0
	 *
	 * @param object|null $modified_menu_item Modified menu item. Default null.
	 * @param object      $menu_item          The menu item to modify.
	 */
	$pre_menu_item = apply_filters( 'pre_wp_setup_nav_menu_item', null, $menu_item );

	if ( null !== $pre_menu_item ) {
		return $pre_menu_item;
	}

	if ( isset( $menu_item->post_type ) ) {
		if ( 'nav_menu_item' === $menu_item->post_type ) {
			$menu_item->db_id            = (int) $menu_item->ID;
			$menu_item->menu_item_parent = ! isset( $menu_item->menu_item_parent ) ? get_post_meta( $menu_item->ID, '_menu_item_menu_item_parent', true ) : $menu_item->menu_item_parent;
			$menu_item->object_id        = ! isset( $menu_item->object_id ) ? get_post_meta( $menu_item->ID, '_menu_item_object_id', true ) : $menu_item->object_id;
			$menu_item->object           = ! isset( $menu_item->object ) ? get_post_meta( $menu_item->ID, '_menu_item_object', true ) : $menu_item->object;
			$menu_item->type             = ! isset( $menu_item->type ) ? get_post_meta( $menu_item->ID, '_menu_item_type', true ) : $menu_item->type;

			if ( 'post_type' === $menu_item->type ) {
				$object = get_post_type_object( $menu_item->object );
				if ( $object ) {
					$menu_item->type_label = $object->labels->singular_name;
					// Denote post states for special pages (only in the admin).
					if ( function_exists( 'get_post_states' ) ) {
						$menu_post = get_post( $menu_item->object_id );
						if ( $menu_post instanceof WP_Post ) {
							$post_states = get_post_states( $menu_post );
							if ( $post_states ) {
								$menu_item->type_label = wp_strip_all_tags( implode( ', ', $post_states ) );
							}
						}
					}
				} else {
					$menu_item->type_label = $menu_item->object;
					$menu_item->_invalid   = true;
				}

				if ( 'trash' === get_post_status( $menu_item->object_id ) ) {
					$menu_item->_invalid = true;
				}

				$original_object = get_post( $menu_item->object_id );

				if ( $original_object ) {
					$menu_item->url = get_permalink( $original_object->ID );
					/** This filter is documented in wp-includes/post-template.php */
					$original_title = apply_filters( 'the_title', $original_object->post_title, $original_object->ID );
				} else {
					$menu_item->url      = '';
					$original_title      = '';
					$menu_item->_invalid = true;
				}

				if ( '' === $original_title ) {
					/* translators: %d: ID of a post. */
					$original_title = sprintf( __( '#%d (no title)' ), $menu_item->object_id );
				}

				$menu_item->title = ( '' === $menu_item->post_title ) ? $original_title : $menu_item->post_title;

			} elseif ( 'post_type_archive' === $menu_item->type ) {
				$object = get_post_type_object( $menu_item->object );
				if ( $object ) {
					$menu_item->title      = ( '' === $menu_item->post_title ) ? $object->labels->archives : $menu_item->post_title;
					$post_type_description = $object->description;
				} else {
					$post_type_description = '';
					$menu_item->_invalid   = true;
				}

				$menu_item->type_label = __( 'Post Type Archive' );
				$post_content          = wp_trim_words( $menu_item->post_content, 200 );
				$post_type_description = ( '' === $post_content ) ? $post_type_description : $post_content;
				$menu_item->url        = get_post_type_archive_link( $menu_item->object );

			} elseif ( 'taxonomy' === $menu_item->type ) {
				$object = get_taxonomy( $menu_item->object );
				if ( $object ) {
					$menu_item->type_label = $object->labels->singular_name;
				} else {
					$menu_item->type_label = $menu_item->object;
					$menu_item->_invalid   = true;
				}

				$original_object = get_term( (int) $menu_item->object_id, $menu_item->object );

				if ( $original_object && ! is_wp_error( $original_object ) ) {
					$menu_item->url = get_term_link( (int) $menu_item->object_id, $menu_item->object );
					$original_title = $original_object->name;
				} else {
					$menu_item->url      = '';
					$original_title      = '';
					$menu_item->_invalid = true;
				}

				if ( '' === $original_title ) {
					/* translators: %d: ID of a term. */
					$original_title = sprintf( __( '#%d (no title)' ), $menu_item->object_id );
				}

				$menu_item->title = ( '' === $menu_item->post_title ) ? $original_title : $menu_item->post_title;

			} else {
				$menu_item->type_label = __( 'Custom Link' );
				$menu_item->title      = $menu_item->post_title;
				$menu_item->url        = ! isset( $menu_item->url ) ? get_post_meta( $menu_item->ID, '_menu_item_url', true ) : $menu_item->url;
			}

			$menu_item->target = ! isset( $menu_item->target ) ? get_post_meta( $menu_item->ID, '_menu_item_target', true ) : $menu_item->target;

			/**
			 * Filters a navigation menu item's title attribute.
			 *
			 * @since 3.0.0
			 *
			 * @param string $item_title The menu item title attribute.
			 */
			$menu_item->attr_title = ! isset( $menu_item->attr_title ) ? apply_filters( 'nav_menu_attr_title', $menu_item->post_excerpt ) : $menu_item->attr_title;

			if ( ! isset( $menu_item->description ) ) {
				/**
				 * Filters a navigation menu item's description.
				 *
				 * @since 3.0.0
				 *
				 * @param string $description The menu item description.
				 */
				$menu_item->description = apply_filters( 'nav_menu_description', wp_trim_words( $menu_item->post_content, 200 ) );
			}

			$menu_item->classes = ! isset( $menu_item->classes ) ? (array) get_post_meta( $menu_item->ID, '_menu_item_classes', true ) : $menu_item->classes;
			$menu_item->xfn     = ! isset( $menu_item->xfn ) ? get_post_meta( $menu_item->ID, '_menu_item_xfn', true ) : $menu_item->xfn;
		} else {
			$menu_item->db_id            = 0;
			$menu_item->menu_item_parent = 0;
			$menu_item->object_id        = (int) $menu_item->ID;
			$menu_item->type             = 'post_type';

			$object                = get_post_type_object( $menu_item->post_type );
			$menu_item->object     = $object->name;
			$menu_item->type_label = $object->labels->singular_name;

			if ( '' === $menu_item->post_title ) {
				/* translators: %d: ID of a post. */
				$menu_item->post_title = sprintf( __( '#%d (no title)' ), $menu_item->ID );
			}

			$menu_item->title  = $menu_item->post_title;
			$menu_item->url    = get_permalink( $menu_item->ID );
			$menu_item->target = '';

			/** This filter is documented in wp-includes/nav-menu.php */
			$menu_item->attr_title = apply_filters( 'nav_menu_attr_title', '' );

			/** This filter is documented in wp-includes/nav-menu.php */
			$menu_item->description = apply_filters( 'nav_menu_description', '' );
			$menu_item->classes     = array();
			$menu_item->xfn         = '';
		}
	} elseif ( isset( $menu_item->taxonomy ) ) {
		$menu_item->ID               = $menu_item->term_id;
		$menu_item->db_id            = 0;
		$menu_item->menu_item_parent = 0;
		$menu_item->object_id        = (int) $menu_item->term_id;
		$menu_item->post_parent      = (int) $menu_item->parent;
		$menu_item->type             = 'taxonomy';

		$object                = get_taxonomy( $menu_item->taxonomy );
		$menu_item->object     = $object->name;
		$menu_item->type_label = $object->labels->singular_name;

		$menu_item->title       = $menu_item->name;
		$menu_item->url         = get_term_link( $menu_item, $menu_item->taxonomy );
		$menu_item->target      = '';
		$menu_item->attr_title  = '';
		$menu_item->description = get_term_field( 'description', $menu_item->term_id, $menu_item->taxonomy );
		$menu_item->classes     = array();
		$menu_item->xfn         = '';

	}

	/**
	 * Filters a navigation menu item object.
	 *
	 * @since 3.0.0
	 *
	 * @param object $menu_item The menu item object.
	 */
	return apply_filters( 'wp_setup_nav_menu_item', $menu_item );
}

/**
 * Returns the menu items associated with a particular object.
 *
 * @since 3.0.0
 *
 * @param int    $object_id   Optional. The ID of the original object. Default 0.
 * @param string $object_type Optional. The type of object, such as 'post_type' or 'taxonomy'.
 *                            Default 'post_type'.
 * @param string $taxonomy    Optional. If $object_type is 'taxonomy', $taxonomy is the name
 *                            of the tax that $object_id belongs to. Default empty.
 * @return int[] The array of menu item IDs; empty array if none.
 */
function wp_get_associated_nav_menu_items( $object_id = 0, $object_type = 'post_type', $taxonomy = '' ) {
	global $wpdb;

	$object_id = (int) $object_id;

	if ( 'taxonomy' === $object_type && $taxonomy ) {
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT id FROM $wpdb->menu_items WHERE object_id = %d AND item_type = 'taxonomy' AND object_type = %s",
			$object_id,
			$taxonomy
		) );
	} else {
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT id FROM $wpdb->menu_items WHERE object_id = %d AND item_type = %s",
			$object_id,
			$object_type
		) );
	}

	$menu_item_ids = array();
	foreach ( $rows as $row ) {
		$menu_item_ids[] = (int) $row->id;
	}

	return $menu_item_ids;
}

/**
 * Callback for handling a menu item when its original object is deleted.
 *
 * @since 3.0.0
 * @access private
 *
 * @param int $object_id The ID of the original object being trashed.
 */
function _wp_delete_post_menu_item( $object_id ) {
	global $wpdb;

	$object_id = (int) $object_id;

	$menu_item_ids = wp_get_associated_nav_menu_items( $object_id, 'post_type' );

	foreach ( (array) $menu_item_ids as $menu_item_id ) {
		$wpdb->delete( $wpdb->menu_items, array( 'id' => $menu_item_id ), array( '%d' ) );
		wp_cache_delete( $menu_item_id, 'menu_items' );
	}
}

/**
 * Serves as a callback for handling a menu item when its original object is deleted.
 *
 * @since 3.0.0
 * @access private
 *
 * @param int    $object_id The ID of the original object being trashed.
 * @param int    $tt_id     Term taxonomy ID. Unused.
 * @param string $taxonomy  Taxonomy slug.
 */
function _wp_delete_tax_menu_item( $object_id, $tt_id, $taxonomy ) {
	global $wpdb;

	$object_id = (int) $object_id;

	$menu_item_ids = wp_get_associated_nav_menu_items( $object_id, 'taxonomy', $taxonomy );

	foreach ( (array) $menu_item_ids as $menu_item_id ) {
		$wpdb->delete( $wpdb->menu_items, array( 'id' => $menu_item_id ), array( '%d' ) );
		wp_cache_delete( $menu_item_id, 'menu_items' );
	}
}

/**
 * Automatically add newly published page objects to menus with that as an option.
 *
 * @since 3.0.0
 * @access private
 *
 * @param string  $new_status The new status of the post object.
 * @param string  $old_status The old status of the post object.
 * @param WP_Post $post       The post object being transitioned from one status to another.
 */
function _wp_auto_add_pages_to_menu( $new_status, $old_status, $post ) {
	if ( 'publish' !== $new_status || 'publish' === $old_status || 'page' !== $post->post_type ) {
		return;
	}
	if ( ! empty( $post->post_parent ) ) {
		return;
	}
	$auto_add = get_option( 'nav_menu_options' );
	if ( empty( $auto_add ) || ! is_array( $auto_add ) || ! isset( $auto_add['auto_add'] ) ) {
		return;
	}
	$auto_add = $auto_add['auto_add'];
	if ( empty( $auto_add ) || ! is_array( $auto_add ) ) {
		return;
	}

	$args = array(
		'menu-item-object-id' => $post->ID,
		'menu-item-object'    => $post->post_type,
		'menu-item-type'      => 'post_type',
		'menu-item-status'    => 'publish',
	);

	foreach ( $auto_add as $menu_id ) {
		$items = wp_get_nav_menu_items( $menu_id, array( 'post_status' => 'publish,draft' ) );
		if ( ! is_array( $items ) ) {
			continue;
		}
		foreach ( $items as $item ) {
			if ( $post->ID === (int) $item->object_id ) {
				continue 2;
			}
		}
		wp_update_nav_menu_item( $menu_id, 0, $args );
	}
}

/**
 * Deletes auto-draft posts associated with the supplied changeset.
 *
 * @since 4.8.0
 * @access private
 *
 * @param int $post_id Post ID for the customize_changeset.
 */
function _wp_delete_customize_changeset_dependent_auto_drafts( $post_id ) {
	$post = get_post( $post_id );

	if ( ! $post || 'customize_changeset' !== $post->post_type ) {
		return;
	}

	$data = json_decode( $post->post_content, true );
	if ( empty( $data['nav_menus_created_posts']['value'] ) ) {
		return;
	}
	remove_action( 'delete_post', '_wp_delete_customize_changeset_dependent_auto_drafts' );
	foreach ( $data['nav_menus_created_posts']['value'] as $stub_post_id ) {
		if ( empty( $stub_post_id ) ) {
			continue;
		}
		if ( 'auto-draft' === get_post_status( $stub_post_id ) ) {
			wp_delete_post( $stub_post_id, true );
		} elseif ( 'draft' === get_post_status( $stub_post_id ) ) {
			wp_trash_post( $stub_post_id );
			delete_post_meta( $stub_post_id, '_customize_changeset_uuid' );
		}
	}
	add_action( 'delete_post', '_wp_delete_customize_changeset_dependent_auto_drafts' );
}

/**
 * Handles menu config after theme change.
 *
 * @access private
 * @since 4.9.0
 */
function _wp_menus_changed() {
	$old_nav_menu_locations    = get_option( 'theme_switch_menu_locations', array() );
	$new_nav_menu_locations    = get_nav_menu_locations();
	$mapped_nav_menu_locations = wp_map_nav_menu_locations( $new_nav_menu_locations, $old_nav_menu_locations );

	set_theme_mod( 'nav_menu_locations', $mapped_nav_menu_locations );
	delete_option( 'theme_switch_menu_locations' );
}

/**
 * Maps nav menu locations according to assignments in previously active theme.
 *
 * @since 4.9.0
 *
 * @param array $new_nav_menu_locations New nav menu locations assignments.
 * @param array $old_nav_menu_locations Old nav menu locations assignments.
 * @return array Nav menus mapped to new nav menu locations.
 */
function wp_map_nav_menu_locations( $new_nav_menu_locations, $old_nav_menu_locations ) {
	$registered_nav_menus   = get_registered_nav_menus();
	$new_nav_menu_locations = array_intersect_key( $new_nav_menu_locations, $registered_nav_menus );

	// Short-circuit if there are no old nav menu location assignments to map.
	if ( empty( $old_nav_menu_locations ) ) {
		return $new_nav_menu_locations;
	}

	// If old and new theme have just one location, map it and we're done.
	if ( 1 === count( $old_nav_menu_locations ) && 1 === count( $registered_nav_menus ) ) {
		$new_nav_menu_locations[ key( $registered_nav_menus ) ] = array_pop( $old_nav_menu_locations );
		return $new_nav_menu_locations;
	}

	$old_locations = array_keys( $old_nav_menu_locations );

	// Map locations with the same slug.
	foreach ( $registered_nav_menus as $location => $name ) {
		if ( in_array( $location, $old_locations, true ) ) {
			$new_nav_menu_locations[ $location ] = $old_nav_menu_locations[ $location ];
			unset( $old_nav_menu_locations[ $location ] );
		}
	}

	// If there are no old nav menu locations left, then we're done.
	if ( empty( $old_nav_menu_locations ) ) {
		return $new_nav_menu_locations;
	}

	/*
	 * If old and new theme both have locations that contain phrases
	 * from within the same group, make an educated guess and map it.
	 */
	$common_slug_groups = array(
		array( 'primary', 'menu-1', 'main', 'header', 'navigation', 'top' ),
		array( 'secondary', 'menu-2', 'footer', 'subsidiary', 'bottom' ),
		array( 'social' ),
	);

	// Go through each group...
	foreach ( $common_slug_groups as $slug_group ) {

		// ...and see if any of these slugs...
		foreach ( $slug_group as $slug ) {

			// ...and any of the new menu locations...
			foreach ( $registered_nav_menus as $new_location => $name ) {

				// ...actually match!
				if ( is_string( $new_location ) && false === stripos( $new_location, $slug ) && false === stripos( $slug, $new_location ) ) {
					continue;
				} elseif ( is_numeric( $new_location ) && $new_location !== $slug ) {
					continue;
				}

				// Then see if any of the old locations...
				foreach ( $old_nav_menu_locations as $location => $menu_id ) {

					// ...and any slug in the same group...
					foreach ( $slug_group as $slug ) {

						// ... have a match as well.
						if ( is_string( $location ) && false === stripos( $location, $slug ) && false === stripos( $slug, $location ) ) {
							continue;
						} elseif ( is_numeric( $location ) && $location !== $slug ) {
							continue;
						}

						// Make sure this location wasn't mapped and removed previously.
						if ( ! empty( $old_nav_menu_locations[ $location ] ) ) {

							// We have a match that can be mapped!
							$new_nav_menu_locations[ $new_location ] = $old_nav_menu_locations[ $location ];

							// Remove the mapped location so it can't be mapped again.
							unset( $old_nav_menu_locations[ $location ] );

							// Go back and check the next new menu location.
							continue 3;
						}
					} // End foreach ( $slug_group as $slug ).
				} // End foreach ( $old_nav_menu_locations as $location => $menu_id ).
			} // End foreach foreach ( $registered_nav_menus as $new_location => $name ).
		} // End foreach ( $slug_group as $slug ).
	} // End foreach ( $common_slug_groups as $slug_group ).

	return $new_nav_menu_locations;
}

/**
 * Prevents menu items from being their own parent.
 *
 * Resets menu_item_parent to 0 when the parent is set to the item itself.
 * For use before saving `_menu_item_menu_item_parent` in nav-menus.php.
 *
 * @since 6.2.0
 * @access private
 *
 * @param array $menu_item_data The menu item data array.
 * @return array The menu item data with reset menu_item_parent.
 */
function _wp_reset_invalid_menu_item_parent( $menu_item_data ) {
	if ( ! is_array( $menu_item_data ) ) {
		return $menu_item_data;
	}

	if (
		! empty( $menu_item_data['ID'] ) &&
		! empty( $menu_item_data['menu_item_parent'] ) &&
		(int) $menu_item_data['ID'] === (int) $menu_item_data['menu_item_parent']
	) {
		$menu_item_data['menu_item_parent'] = 0;
	}

	return $menu_item_data;
}
