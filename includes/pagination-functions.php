<?php
/**
 * Pagination-related functions and overrides
 **/

/**
 * Filters outer markup generated by pagination-related functions,
 * like get_the_posts_pagination() and get_the_comments_navigation().
 * See _navigation_markup().
 *
 * @since 0.5.0
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
 * @since 0.5.0
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
 * @since 0.5.0
 * @author Jo Dickson
 * @param array $args Arguments to pass to paginate_links()
 */
function ucfwp_the_posts_pagination( $args=array() ) {
	echo ucfwp_get_the_posts_pagination( $args );
}
