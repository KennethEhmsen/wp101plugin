<?php
/**
 * Shortcode handling for WP101 on the front-end of a site.
 *
 * @package WP101
 */

namespace WP101\Shortcode;

use WP101\API as API;
use WP101\TemplateTags as TemplateTags;

/**
 * Register scripts used for front-end display.
 */
function register_scripts_styles() {
	wp_register_style(
		'wp101',
		WP101_URL . '/assets/css/wp101.css',
		null,
		WP101_VERSION
	);

	wp_register_script(
		'wp101',
		WP101_URL . '/assets/js/wp101.js',
		null,
		WP101_VERSION,
		true
	);

	wp_localize_script( 'wp101', 'wp101', [
		'apiKey' => TemplateTags\api()->get_public_api_key(),
	] );
}
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\register_scripts_styles' );

/**
 * Handle requests for the [wp101] shortcode.
 *
 * @param array $atts {
 *   Shortcode attributes.
 *
 *   @var string $series The series slug, to display a whole list of videos. Takes precedence over
 *                       a single video slug.
 *   @var string $video  The video/topic slug.
 * }
 * @return string The rendered shortcode content.
 */
function render_shortcode( $atts ) {
	$atts = shortcode_atts( [
		'series' => null,
		'video'  => null,
	], $atts, 'wp101' );
	$api  = TemplateTags\api();

	if ( ! $api->account_can( 'embed-on-front-end' ) ) {
		return shortcode_debug( __( 'Your WP101 subscription does not permit embedding on the front-end of a site.', 'wp101' ) );

	} elseif ( 'private' !== get_post_status() ) {
		return shortcode_debug( __( 'You may not use WP101 shortcodes on public pages.', 'wp101' ) );
	}

	// Load the requisite files.
	wp_enqueue_style( 'wp101' );
	wp_enqueue_script( 'wp101' );

	if ( $atts['series'] ) {
		$series = TemplateTags\get_series( $atts['series'] );

		return render_shortcode_playlist( $series );

	} elseif ( $atts['video'] ) {
		$topic = TemplateTags\get_topic( $atts['video'] );

		return render_shortcode_single( $topic );
	}

	return shortcode_debug( __( 'No WP101 series or video were specified.', 'wp101' ) );
}
add_shortcode( 'wp101', __NAMESPACE__ . '\render_shortcode' );

/**
 * Render the shortcode for a single topic.
 *
 * Note that this function should not be called directly, but through render_shortcode().
 *
 * @param array $topic The topic to display.
 *
 * @return string The rendered shortcode content.
 */
function render_shortcode_single( $topic ) {
	ob_start();
?>

	<figure class="wp101-video">
		<div class="wp101-video-wrapper">
			<iframe id="wp101-video-player-<?php echo esc_attr( $topic['slug'] ); ?>" class="wp101-video-player" data-media-src="<?php echo esc_attr( $topic['url'] ); ?>" border="0" allowfullscreen></iframe>
		</div>
		<figcaption class="wp101-video-details">
			<h2 class="wp101-video-title"><?php echo esc_html( $topic['title'] ); ?></h2>
			<?php if ( $topic['description'] ) : ?>
				<?php echo wp_kses_post( apply_filters( 'the_content', $topic['description'] ) ); ?>
			<?php endif; ?>
		</figcaption>
	</figure>

<?php
	$output = ob_get_clean();

	/**
	 * Filter the generated markup for a WP101 video on the front-end.
	 *
	 * @param string $markup The HTML for displaying a video.
	 * @param array  $topic  The video information.
	 */
	return apply_filters( 'wp101_render_shortcode_single', $output, $topic );
}

/**
 * Render an entire series in a grid.
 *
 * Note that this function should not be called directly, but through render_shortcode().
 *
 * @param array $series The series to display.
 *
 * @return string The rendered shortcode content.
 */
function render_shortcode_playlist( $series ) {
	ob_start();
?>

	<section class="wp101-video-grid">
		<header class="wp101-video-grid-header">
			<h2 class="wp101-video-grid-heading"><?php echo esc_html( $series['title'] ); ?></h2>
		</header>
		<div class="wp101-video-grid-contents">
			<?php foreach ( $series['topics'] as $topic ) : ?>

				<figure class="wp101-video">
					<div class="wp101-video-wrapper">
						<iframe id="wp101-video-player-<?php echo esc_attr( $topic['slug'] ); ?>" class="wp101-video-player" data-media-src="<?php echo esc_attr( $topic['url'] ); ?>" border="0" allowfullscreen></iframe>
					</div>
					<figcaption class="wp101-video-details">
						<h3 class="wp101-video-title"><?php echo esc_html( $topic['title'] ); ?></h3>
					</figcaption>
				</figure>

			<?php endforeach; ?>
		</div>
	</section>

<?php
	$output = ob_get_clean();

	/**
	 * Filter the generated markup for a collection of WP101 videos on the front-end.
	 *
	 * @param string $markup The HTML for displaying a grid of videos.
	 * @param array  $series  The series information.
	 */
	return apply_filters( 'wp101_render_shortcode_playlist', $output, $series );
}

/**
 * If the current user is logged in and has the ability to edit_posts, print a debug message in the
 * form of an HTML comment. Otherwise, return an empty string.
 *
 * @param string $message The debug message to *maybe* display.
 * @return string Either an HTML comment with the $message or an empty string.
 */
function shortcode_debug( $message ) {
	$output = '';

	if ( current_user_can( 'edit_posts' ) ) {
		$output = sprintf( '<!-- %1$s -->', esc_html( $message ) );
	}

	return $output;
}
