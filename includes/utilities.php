<?php
/**
 * General utilities
 **/


/**
 * Utility function that returns an image url by its thumbnail size.
 *
 * @since 0.0.0
 * @author Jo Dickson
 * @param int $id Attachment ID
 * @param string $size Image size name
 * @return string Attachment image URL
 **/
function ucfwp_get_attachment_src_by_size( $id, $size ) {
	$attachment = wp_get_attachment_image_src( $id, $size, false );
	if ( is_array( $attachment ) ) {
		return $attachment[0];
	}
	return $attachment;
}


/**
 * Returns a JSON object from the provided URL.  Detects undesirable status
 * codes and returns false if the response doesn't look valid.
 *
 * @since 0.0.0
 * @author Jo Dickson
 * @param string $url URL that points to a JSON object/feed
 * @return mixed JSON-decoded object or false on failure
 */
function ucfwp_fetch_json( $url ) {
	$response      = wp_remote_get( $url, array( 'timeout' => 15 ) );
	$response_code = wp_remote_retrieve_response_code( $response );
	$result        = false;

	if ( is_array( $response ) && is_int( $response_code ) && $response_code < 400 ) {
		$result = json_decode( wp_remote_retrieve_body( $response ) );
	}

	return $result;
}


/**
 * Returns a theme mod's default value from a constant.
 *
 * @since 0.2.2
 * @param string $theme_mod The name of the theme mod
 * @param string $defaults Serialized array of theme mod names + default values
 * @return mixed Theme mod default value, or false if a default is not set
 **/
function ucfwp_get_theme_mod_default( $theme_mod, $defaults=UCFWP_THEME_CUSTOMIZER_DEFAULTS ) {
	$defaults = unserialize( $defaults );
	if ( $defaults && isset( $defaults[$theme_mod] ) ) {
		return $defaults[$theme_mod];
	}
	return false;
}


/**
 * Returns a theme mod value or the default set in
 * $defaults if the theme mod value hasn't been set yet.
 *
 * @since 0.2.2
 * @param string $theme_mod The name of the theme mod
 * @param string $defaults Serialized array of theme mod names + default values
 * @return mixed Theme mod value or its default
 **/
function ucfwp_get_theme_mod_or_default( $theme_mod, $defaults=UCFWP_THEME_CUSTOMIZER_DEFAULTS ) {
	$default = ucfwp_get_theme_mod_default( $theme_mod, $defaults );
	return get_theme_mod( $theme_mod, $default );
}


/**
 * Check if the content is empty
 *
 * @since 0.2.2
 **/
function ucfwp_is_content_empty($str) {
	return trim( str_replace( '&nbsp;', '', strip_tags( $str ) ) ) === '';
}


/**
 * Filters outer markup generated by pagination-related functions,
 * like get_the_posts_pagination() and get_the_comments_navigation().
 * See _navigation_markup().
 *
 * @since 0.3.0
 * @author Jo Dickson
 *
 * @param string $template The default pagination markup.
 * @param string $class    The class passed by the calling function.
 * @return string Navigation template.
 */
function ucfwp_pagination_wrapper_markup( $template, $class ) {
	// Fix class incompatibility with Athena Framework pagination classes
	// when called by get_the_posts_pagination()
	$wrapper_class = '%1$s';
	if ( $class === 'pagination' ) {
		$wrapper_class = 'posts-pagination';
	}

	ob_start();
?>
	<nav class="loop-navigation <?php echo $wrapper_class; ?>" role="navigation" aria-label="%2$s">
        <div class="nav-links">%3$s</div>
    </nav>
<?php
	return ob_get_clean();
}

add_filter( 'navigation_markup_template', 'ucfwp_pagination_wrapper_markup', 10, 2 );


/**
 * Opinionated pagination rules for post loops.
 *
 * Moves the Previous Page link after the first page link, and the Next Page
 * link before the last page link, if the 'prev_next' arg is set to true.
 * Also adds FontAwesome icons for Previous/Next links to reduce
 * consumed space.
 *
 * @since 0.3.0
 * @author Jo Dickson
 * @param array $args Arguments to pass to paginate_links()
 * @return string Pagination markup
 */
function ucfwp_get_the_posts_pagination( $args=array() ) {
	$pagination    = '';
	$defaults      = array(
		'mid_size'           => 1,
		'prev_text'          => '<span class="fa fa-angle-double-left" aria-hidden="true"></span><span class="sr-only">Previous</span>',
		'next_text'          => '<span class="fa fa-angle-double-right" aria-hidden="true"></span><span class="sr-only">Next</span>',
		'screen_reader_text' => __( 'Posts navigation' )
	);
	$forced_values = array(
		'prev_next' => false,
		'type'      => 'array'
	);

	// Apply opinionated default values (are still overridable)
	$args = array_merge( $defaults, $args );

	// Store values we need to override, but fix later
	$prev_next = isset( $args['prev_next'] ) ? $args['prev_next'] : true; // use default val for paginate_links() as fallback
	$type      = isset( $args['type'] ) ? $args['type'] : 'plain'; // use default val for paginate_links() as fallback

	// Generate paginated link markup based on our defaults and forced values:
	$args  = array_merge( $args, $forced_values );
	$links = paginate_links( $args );

	// If there's no links to return, exit now
	if ( ! $links ) return '';

	// If the user wanted previous/next links, re-add them:
	if ( $prev_next ) {
		// A left-most ellipses list item will always be in $links[1]
		// if present.  Insert a Previous link immediately after it:
		if (
			isset( $links[1] )
			&& strpos( $links[1], 'class="page-numbers dots"' )
			&& $prev_url = previous_posts( false )
		) {
			$prev_link = '<a class="page-numbers prev" href="' . $prev_url . '">' . $args['prev_text'] . '</a>';
			array_splice( $links, 2, 0, $prev_link );
		}

		// A right-most ellipses list item will always be in the next-to-last
		// array position, if present.  Insert a Next link immediately
		// before it:
		$next_to_last = count( $links ) - 2; // -2 to account for 0 index in count() total
		if (
			isset( $links[$next_to_last] )
			&& strpos( $links[$next_to_last], 'class="page-numbers dots"' )
			&& $next_url = next_posts( 0, false )
		) {
			$next_link = '<a class="page-numbers next" href="' . $next_url . '">' . $args['next_text'] . '</a>';
			array_splice( $links, -2, 0, $next_link );
		}
	}

	// Apply the pagination 'type' the user originally requested.
	// Apply sane fallback of 'plain' if the user set the type to 'array':
	$links_formatted = null;
	switch ( $type ) {
		case 'list':
			$links_formatted = "<ul class='page-numbers'>\n\t<li>";
			$links_formatted .= join( "</li>\n\t<li>", $links );
			$links_formatted .= "</li>\n</ul>\n";
			break;
		case 'array':
		default:
			$links_formatted = join( "\n", $links );
			break;
	}

	// Put it all back together:
	$pagination_class   = 'posts-pagination';
	$pagination_wrapper = apply_filters( 'navigation_markup_template', '', $pagination_class );
	$pagination         = sprintf( $pagination_wrapper, $pagination_class, $args['screen_reader_text'], $links_formatted );

	return $pagination;
}


/**
 * WordPress Loop-friendly function to echo opinionated pagination markup.
 *
 * @since 0.3.0
 * @author Jo Dickson
 * @param array $args Arguments to pass to paginate_links()
 */
function ucfwp_the_posts_pagination( $args=array() ) {
	echo ucfwp_get_the_posts_pagination( $args );
}


/**
 * Shim that provides backward compatibility for header markup functions
 * while still utilizing get_template_part() whenever possible.
 *
 * Ideally in a next major release, this function will be replaceable
 * with get_template_part().
 *
 * @since 0.4.0
 * @author Jo Dickson
 * @internal
 * @param string $template_part_slug The template part slug to fetch
 * @param string $template_part_name The template part name to fetch
 */
function _ucfwp_get_template_part( $template_part_slug, $template_part_name ) {
	$shim_retval = '';
	$obj = ucfwp_get_queried_object();

	$videos = ucfwp_get_header_videos( $obj );
	$images = ucfwp_get_header_images( $obj );

	switch ( $template_part_slug ) {
		case ucfwp_get_template_part_slug( 'header' ):
			switch ( $template_part_name ) {
				case '':
					$shim_retval = ucfwp_get_header_default_markup( $obj );
					break;
				case 'media':
					$shim_retval = ucfwp_get_header_media_markup( $obj, $videos, $images );
					break;
				default:
					break;
			}
		case ucfwp_get_template_part_slug( 'header_content' ):
			switch ( $template_part_name ) {
				case 'title_subtitle':
					$shim_retval = ucfwp_get_header_content_title_subtitle( $obj );
					break;
				case 'custom':
					$shim_retval = ucfwp_get_header_content_custom( $obj );
					break;
				default:
					break;
			}
		default:
			break;
	}

	if ( $shim_retval ) {
		echo $shim_retval;
	}
	else {
		get_template_part( $template_part_slug, $template_part_name );
	}
}


/**
 * Returns a template part slug suitable for use as the
 * $slug param in get_template_part().
 *
 * @author Jo Dickson
 * @since 0.4.0
 * @param string $subpath An optional subdirectory within the template parts directory
 * @return string The desired template part slug (for this theme and child themes)
 */
if ( ! function_exists( 'ucfwp_get_template_part_slug' ) ) {
	function ucfwp_get_template_part_slug( $subpath='' ) {
		if ( $subpath ) {
			$subpath = '/' . $subpath;
		}
		return UCFWP_THEME_TEMPLATE_PARTS_PATH . $subpath;
	}
}


/**
 * Wrapper for get_queried_object() with opinionated overrides for this theme.
 * Sets a `ucfwp_obj` query var on the global $wp_query object for fast
 * reference in subsequent requests for the queried object.
 *
 * @see https://codex.wordpress.org/Function_Reference/get_queried_object
 *
 * @since 0.4.0
 * @author Jo Dickson
 * @return mixed The queried object, or null if no valid object was queried
 */
function ucfwp_get_queried_object() {
	// If ucfwp_obj is already a set query param, return it.
	// Note that a set value may still be null, but valid.
	//
	// We reference $wp_query here directly because we have no
	// other means of determining the difference between an
	// unset value and a set, but empty/null, value.
	global $wp_query;
	if ( $wp_query && array_key_exists( 'ucfwp_obj', $wp_query->query_vars ) ) {
		return $wp_query->query_vars['ucfwp_obj'];
	}

	$obj = get_queried_object();

	if ( !$obj && is_404() ) {
		$page = get_page_by_title( '404' );
		if ( $page && $page->post_status === 'publish' ) {
			$obj = $page;
		}
	}

	// Store as a query var on $wp_query for reference in
	// subsequent queried object requests:
	set_query_var( 'ucfwp_obj', $obj );

	return $obj;
}
