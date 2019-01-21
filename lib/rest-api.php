<?php
/**
 * PHP and WordPress configuration compatibility functions for the Gutenberg
 * editor plugin changes related to REST API.
 *
 * @package gutenberg
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Silence is golden.' );
}

/**
 * Make sure oEmbed REST Requests apply the WP Embed security mechanism for WordPress embeds.
 *
 * @see  https://core.trac.wordpress.org/ticket/32522
 *
 * TODO: This is a temporary solution. Next step would be to edit the WP_oEmbed_Controller,
 * once merged into Core.
 *
 * @since 2.3.0
 *
 * @param  WP_HTTP_Response|WP_Error $response The REST Request response.
 * @param  WP_REST_Server            $handler  ResponseHandler instance (usually WP_REST_Server).
 * @param  WP_REST_Request           $request  Request used to generate the response.
 * @return WP_HTTP_Response|object|WP_Error    The REST Request response.
 */
function gutenberg_filter_oembed_result( $response, $handler, $request ) {
	if ( 'GET' !== $request->get_method() ) {
		return $response;
	}

	if ( is_wp_error( $response ) && 'oembed_invalid_url' !== $response->get_error_code() ) {
		return $response;
	}

	// External embeds.
	if ( '/oembed/1.0/proxy' === $request->get_route() ) {
		if ( is_wp_error( $response ) ) {
			// It's possibly a local post, so lets try and retrieve it that way.
			$post_id = url_to_postid( $_GET['url'] );
			$data    = get_oembed_response_data( $post_id, apply_filters( 'oembed_default_width', 600 ) );

			if ( $data ) {
				// It's a local post!
				$response = (object) $data;
			} else {
				// Try using a classic embed, instead.
				global $wp_embed;
				$html = $wp_embed->shortcode( array(), $_GET['url'] );
				if ( $html ) {
					global $wp_scripts;
					// Check if any scripts were enqueued by the shortcode, and
					// include them in the response.
					$enqueued_scripts = array();
					foreach ( $wp_scripts->queue as $script ) {
						$enqueued_scripts[] = $wp_scripts->registered[ $script ]->src;
					}
					return array(
						'provider_name' => __( 'Embed Handler', 'gutenberg' ),
						'html'          => $html,
						'scripts'       => $enqueued_scripts,
					);
				}
			}
		}

		// Make sure the HTML is run through the oembed sanitisation routines.
		$response->html = wp_oembed_get( $_GET['url'], $_GET );
	}

	return $response;
}
add_filter( 'rest_request_after_callbacks', 'gutenberg_filter_oembed_result', 10, 3 );
