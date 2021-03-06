<?php
/*
Plugin Name: Google Docs Shortcode
Plugin URI: https://github.com/cuny-academic-commons/google-docs-shortcode
Description: Easily embed a Google Doc into your blog posts
Author: r-a-y
Author URI: http://profiles.wordpress.org/r-a-y
Version: 0.5-bleeding
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Initializer.
 *
 * @since 0.5.0 Added Shortcake support.
 */
function ray_gdoc_shortcode_init() {
	add_shortcode( 'gdoc', 'ray_google_docs_shortcode' );

	// Add Shortcake support.
	if ( function_exists( 'shortcode_ui_register_for_shortcode' ) ) {
		require_once __DIR__ . '/shortcake.php';
	}
}
add_action( 'init', 'ray_gdoc_shortcode_init' );

/**
 * Initialize Gutenberg support.
 *
 * @since 0.5.0
 */
function ray_google_docs_gutenberg_init() {
	// Bail if Gutenberg isn't available.
	if ( ! function_exists( 'register_block_type' ) ) {
		return;
	}

	// Register JS.
	wp_register_script( 'ray-gdoc-block', plugins_url( basename( dirname( __FILE__ ) ) ) . '/block.js', array(
		'wp-blocks', 'wp-i18n', 'wp-element'
	), '20181120' );

	// Register block type.
	register_block_type( 'ray/google-drive', array(
		'editor_script'   => 'ray-gdoc-block',
		'render_callback' => 'ray_google_docs_shortcode',
	) );
}
add_action( 'init', 'ray_google_docs_gutenberg_init' );

/**
 * Shortcode to embed a Google Doc.
 *
 * eg. [gdoc link="https://docs.google.com/document/pub?id=XXX"]
 */
function ray_google_docs_shortcode( $atts ) {
	global $content_width;

	$r = shortcode_atts( array(
		'link'     => false,

		// type
		'type'     => false,

		// dimensions
		'width'    => ! empty( $content_width ) ? $content_width : '100%',
		'height'   => 300,   // default height is set to 300

		// only for documents
		'seamless' => 1,  // if set to 'true', this will not show the Google Docs header / footer.
		                  // if set to 'false', this will show the Google Docs header / footer.

		// only for presentations
		'size'     => false, // preset presentation size, either 'small', 'medium' or 'large';
		                     // preset dimensions are as follows: small (480x389), medium (960x749), large (1440x1109)
		                     // to set custom size, set the 'width' and 'height' params instead,

		// links
		'downloadlink' => false, // add a download link after the embedded content; default: false.
		'hideiframe'   => false, // hide the iframe, useful if you set 'downloadlink' to true; default: false.

	), $atts );

	// if no link or link is not from Google Docs, stop now!
	if ( ! $r['link'] || ( strpos( $r['link'], '://docs.google.com' ) === false && strpos( $r['link'], '://drive.google.com' ) === false ) ) {
		return;
	}

	$type = $extra = false;
	$output = '';

	// set the doc type by looking at the URL

	// document
	if ( strpos( $r['link'], '/document/' ) !== false ) {
		$type = 'doc';
		$base = 'document';

	// presentation
	} elseif ( strpos( $r['link'], '/presentation/' ) !== false || strpos( $r['link'], '/present/' ) !== false ) {
		$type = $base = 'presentation';

	// form
	} elseif ( strpos( $r['link'], '/forms/' ) !== false || strpos( $r['link'], 'form?formkey' ) !== false ) {
		$type = 'form';

	// spreadsheet
	} elseif ( strpos( $r['link'], '/spreadsheets/' ) !== false || strpos( $r['link'], '/spreadsheet/' ) !== false ) {
		$type = $base = 'spreadsheet';
		$base .= 's';

	// non-google doc
	} elseif ( ! empty( $r['type'] ) ) {
		$type = $r['type'];

	// nada!
	} else {
		return;
	}

	// add query args depending on doc type
	switch ( $type ) {
		case 'doc' :
			if ( false !== strpos( $r['link'], '/pub' ) && wp_validate_boolean( $r['seamless'] ) ) {
				$r['link'] = add_query_arg( 'embedded', 'true', $r['link'] );
			}

			break;

		case 'presentation' :
			$is_old_doc = strpos( $r['link'], '/present/' ) !== false || strpos( $r['link'], '?id=' ) !== false;

			// alter the link so we're in embed mode
			// (older docs)
			$r['link'] = str_replace( '/view', '/embed', $r['link'] );

			// alter the link so we're in embed mode
			$r['link'] = str_replace( 'pub?', 'embed?', $r['link'] );

			// dimensions
			switch ( $r['size'] ) {
				case 'medium' :
					$r['width']  = 960;

					if ( $is_old_doc ) {
						$r['height'] = 749;
					} else {
						$r['height'] = 559;
					}

					break;

				case 'large' :
					$r['width']  = 1440;

					if ( $is_old_doc ) {
						$r['height'] = 1109;
					} else {
						$r['height'] = 839;
					}

					break;

				case 'small' :
				default :
					$r['width']  = 480;

					if ( $is_old_doc ) {
						$r['height'] = 389;
					} else {
						$r['height'] = 299;
					}

					break;
			}

			// extra iframe args
			// i'm aware that these are non-standard attributes in XHTML / HTML5,
			// but these are the attributes given by Google's embed code!
			// don't like this? use the 'ray_google_docs_shortcode_output' filter to remove it!
			$extra = ' frameborder="0" allowfullscreen="true" mozallowfullscreen="true" webkitallowfullscreen="true"';

			break;

		case 'form' :
			// new form format
			if ( strpos( $r['link'], '/forms/' ) !== false ) {
				$r['link'] = add_query_arg( 'embedded', true, $r['link'] );

			// older form format
			} else {
				$r['link'] = str_replace( 'viewform?', 'embeddedform?', $r['link'] );
			}

			// extra iframe args
			// i'm aware that these are non-standard attributes in XHTML / HTML5,
			// but these are the attributes given by Google's embed code!
			// don't like this? use the 'ray_google_docs_shortcode_output' filter to remove it!
			$extra = ' frameborder="0" marginheight="0" marginwidth="0"';

			break;

		case 'spreadsheet' :
			$r['link'] = add_query_arg( 'widget', 'true', $r['link'] );

			// extra iframe args
			// i'm aware that these are non-standard attributes in XHTML / HTML5,
			// but these are the attributes given by Google's embed code!
			// don't like this? use the 'ray_google_docs_shortcode_output' filter to remove it!
			$extra = ' frameborder="0"';

			break;

		// http://webapps.stackexchange.com/a/84399
		case 'audio' :
		case 'other' :
			$id = str_replace( 'https://drive.google.com/file/d/', '', $r['link'] );
			$id = str_replace( '/view?usp=sharing', '', $id );
			$id = esc_attr( $id );

			$link = esc_url( "http://docs.google.com/uc?export=open&id={$id}" );
			break;
	}

	// set up link info
	if ( true === (bool) $r['downloadlink'] ) {
		switch ( $type ) {
			case 'doc' :
			case 'presentation' :
			case 'spreadsheet' :
				$id = str_replace( "https://docs.google.com/{$base}/d/", '', $r['link'] );
				$id = substr( $id, 0, strrpos( $id, '/' ) );

				// ugh... URL formats are different!
				switch ( $type ) {
					case 'doc' :
						$link = "https://docs.google.com/feeds/download/documents/export/Export?id={$id}&exportFormat=docx";
						break;
					case 'presentation' :
						$link = "https://docs.google.com/feeds/download/presentations/Export?id={$id}&exportFormat=pptx";
						break;
					case 'spreadsheet' :
						$link = "https://docs.google.com/spreadsheets/export?id={$id}&exportFormat=xlsx";
						break;
				}
				break;
		}
	}

	// support "anyone with link" functionality
	if ( false !== strpos( $r['link'], '/edit?usp=sharing' ) ) {
		$r['link'] = str_replace( '/edit?usp=sharing', '/preview', $r['link'] );
		$r['link'] = str_replace( '&widget=true', '', $r['link'] );
		$r['link'] = str_replace( '&embedded=true', '', $r['link'] );
	} elseif ( false !== strpos( $r['link'], '/view?usp=sharing' ) ) {
		$r['link'] = str_replace( '/view?usp=sharing', '/preview', $r['link'] );
	}

	// set width
	$r['width'] = ' width="' . esc_attr( $r['width'] ) . '"';

	// set height
	$r['height'] = ' height="' . esc_attr( $r['height'] ) . '"';

	// audio uses HTML5
	if ( 'audio' === $r['type'] ) {
		$output = "<audio controls>
		<source src='{$link}'>
		<p>" . __( 'Your browser does not support HTML5 audio', 'google-docs-shortcode' ) . "</p>
		</audio>";

	// Use iframe if we're not hiding it.
	} elseif ( ! wp_validate_boolean( $r['hideiframe'] ) ) {
		$output = '<iframe id="gdoc-' . md5( $r['link'] ) . '" class="gdocs_shortcode gdocs_' . esc_attr( $type ) . '" src="' .  esc_url( $r['link'] ) . '"' . $r['width'] . $r['height'] . $extra . '></iframe>';
	}

	// add links if enabled
	if ( true === (bool) $r['downloadlink'] && 'form' !== $type ) {
		$link = '<p class="gdoc-download gdoc-type-' . esc_attr( $type ) . '"><span class="dashicons dashicons-download"></span><a href="' . esc_url( $link ) . '">' . __( 'Download', 'google-docs-shortcode' ). '</a></p>';
		$output .= $link;
	}

	return apply_filters( 'ray_google_docs_shortcode_output', $output, $type );
}
