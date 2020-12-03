<?php
/**
 * The template for displaying testimonial items
 *
 * @package PhotoFocus
 */
?>

<?php
$number = get_theme_mod( 'photofocus_testimonial_number', 3 );

if ( ! $number ) {
	// If number is 0, then this section is disabled
	return;
}

$args = array(
	'ignore_sticky_posts' => 1 // ignore sticky posts
);

$photofocus_type = get_theme_mod( 'photofocus_testimonial_type', 'category' );

$post_list  = array();// list of valid post/page ids


if ( 'post' === $photofocus_type || 'jetpack-testimonial' === $photofocus_type || 'page' === $photofocus_type  ) {
	$args['post_type'] = $photofocus_type;

	for ( $i = 1; $i <= $number; $i++ ) {
		$photofocus_post_id = '';

		if ( 'post' === $photofocus_type ) {
			$photofocus_post_id = get_theme_mod( 'photofocus_testimonial_post_' . $i );
		} elseif ( 'page' === $photofocus_type ) {
			$photofocus_post_id = get_theme_mod( 'photofocus_testimonial_page_' . $i );
		} elseif ( 'jetpack-testimonial' === $photofocus_type ) {
			$photofocus_post_id =  get_theme_mod( 'photofocus_testimonial_cpt_' . $i );
		}

		if ( $photofocus_post_id && '' !== $photofocus_post_id ) {
			// Polylang Support.
			if ( class_exists( 'Polylang' ) ) {
				$photofocus_post_id = pll_get_post( $photofocus_post_id, pll_current_language() );
			}

			$post_list = array_merge( $post_list, array( $photofocus_post_id ) );

		}
	}

	$args['post__in'] = $post_list;
	$args['orderby'] = 'post__in';
} elseif ( 'category' === $photofocus_type ) {
	$no_of_post = $number;

	if ( get_theme_mod( 'photofocus_testimonial_select_category' ) ) {
		$args['category__in'] = (array) get_theme_mod( 'photofocus_testimonial_select_category' );
	}

	$args['post_type'] = 'post';
} elseif ( 'tag' === $photofocus_type ) {
	$no_of_post = $number;

	if ( get_theme_mod( 'photofocus_testimonial_select_tag' ) ) {
		$args['tag__in'] = (array) get_theme_mod( 'photofocus_testimonial_select_tag' );
	}

	$args['post_type'] = 'post';
}

$args['posts_per_page'] = $number;
$loop = new WP_Query( $args );

if ( $loop -> have_posts() ) :
	while ( $loop -> have_posts() ) :
		$loop -> the_post();

		get_template_part( 'template-parts/testimonial/content', 'testimonial' );
	endwhile;
	wp_reset_postdata();
endif;
