<?php
/**
 * Services: Content Filter Service.
 *
 * Define the Wordlift_Content_Filter_Service class. This file is included from
 * the main class-wordlift.php file.
 *
 * @since   3.8.0
 * @package Wordlift
 */

/**
 * Define the Wordlift_Content_Filter_Service class which intercepts the
 * 'the_content' WP filter and mangles the content accordingly.
 *
 * @since   3.8.0
 * @package Wordlift
 */
class Wordlift_Content_Filter_Service {

	/**
	 * The pattern to find entities in text.
	 *
	 * @since 3.8.0
	 */
	const PATTERN = '/<(\\w+)[^<]*class="([^"]*)"\\sitemid=\"([^"]+)\"[^>]*>([^<]*)<\\/\\1>/i';

	/**
	 * A {@link Wordlift_Entity_Service} instance.
	 *
	 * @since  3.8.0
	 * @access private
	 * @var \Wordlift_Entity_Service $entity_service A {@link Wordlift_Entity_Service} instance.
	 */
	private $entity_service;

	/**
	 * The {@link Wordlift_Configuration_Service} instance.
	 *
	 * @since  3.13.0
	 * @access private
	 * @var \Wordlift_Configuration_Service $configuration_service The {@link Wordlift_Configuration_Service} instance.
	 */
	private $configuration_service;

	/**
	 * The `link by default` setting.
	 *
	 * @since  3.13.0
	 * @access private
	 * @var bool True if link by default is enabled otherwise false.
	 */
	private $is_link_by_default;

	/**
	 * The {@link Wordlift_Content_Filter_Service} singleton instance.
	 *
	 * @since  3.14.2
	 * @access private
	 * @var \Wordlift_Content_Filter_Service $instance The {@link Wordlift_Content_Filter_Service} singleton instance.
	 */
	private static $instance;

	/**
	 * Create a {@link Wordlift_Content_Filter_Service} instance.
	 *
	 * @since 3.8.0
	 *
	 * @param \Wordlift_Entity_Service        $entity_service        The {@link Wordlift_Entity_Service} instance.
	 * @param \Wordlift_Configuration_Service $configuration_service The {@link Wordlift_Configuration_Service} instance.
	 */
	public function __construct( $entity_service, $configuration_service ) {

		$this->entity_service        = $entity_service;
		$this->configuration_service = $configuration_service;

		self::$instance = $this;

	}

	/**
	 * Get the {@link Wordlift_Content_Filter_Service} singleton instance.
	 *
	 * @since 3.14.2
	 * @return \Wordlift_Content_Filter_Service The {@link Wordlift_Content_Filter_Service} singleton instance.
	 */
	public static function get_instance() {

		return self::$instance;
	}

	/**
	 * Mangle the content by adding links to the entity pages. This function is
	 * hooked to the 'the_content' WP's filter.
	 *
	 * @since 3.8.0
	 *
	 * @param string $content The content being filtered.
	 *
	 * @return string The filtered content.
	 */
	public function the_content( $content ) {

		// Links should be added only on the front end and not for RSS.
		if ( is_feed() ) {
			return $content;
		}

		// Preload the `link by default` setting.
		$this->is_link_by_default = $this->configuration_service->is_link_by_default();

		// Replace each match of the entity tag with the entity link. If an error
		// occurs fail silently returning the original content.
		return preg_replace_callback( self::PATTERN, array(
			$this,
			'link',
		), $content ) ?: $content;
	}

	/**
	 * Get the entity match and replace it with a page link.
	 *
	 * @since 3.8.0
	 *
	 * @param array $matches An array of matches.
	 *
	 * @return string The replaced text with the link to the entity page.
	 */
	private function link( $matches ) {

		// Get the entity itemid URI and label.
		$css_class = $matches[2];
		$uri       = $matches[3];
		$label     = $matches[4];

		// Get the entity post by URI.
		$post = $this->entity_service->get_entity_post_by_uri( $uri );
		if ( null === $post ) {

			// If the entity post is not found return the label w/o the markup
			// around it.
			//
			// See https://github.com/insideout10/wordlift-plugin/issues/461.
			return $label;
		}

		$no_link = - 1 < strpos( $css_class, 'wl-no-link' );
		$link    = - 1 < strpos( $css_class, 'wl-link' );

		// Don't link if links are disabled and the entity is not link or the
		// entity is do not link.
		$dont_link = ( ! $this->is_link_by_default && ! $link ) || $no_link;

		// Return the label if it's don't link.
		if ( $dont_link ) {
			return $label;
		}

		// Get the link.
		$href = get_permalink( $post );

		// Get an alternative title attribute.
		$title_attribute = $this->get_title_attribute( $post->ID, $label );

		// Return the link.
		return "<a class='wl-entity-page-link' $title_attribute href='$href'>$label</a>";
	}

	/**
	 * Get a `title` attribute with an alternative label for the link.
	 *
	 * If an alternative title isn't available an empty string is returned.
	 *
	 * @since 3.15.0
	 *
	 * @param int    $post_id The {@link WP_Post}'s id.
	 * @param string $label   The main link label.
	 *
	 * @return string A `title` attribute with an alternative label or an empty
	 *                string if none available.
	 */
	private function get_title_attribute( $post_id, $label ) {

		// Get an alternative title.
		$title = $this->get_link_title( $post_id, $label );
		if ( ! empty( $title ) ) {
			return 'title="' . esc_attr( $title ) . '"';
		}

		return '';
	}

	/**
	 * Get a string to be used as a title attribute in links to a post
	 *
	 * @since 3.15.0
	 *
	 * @param int    $post_id      The post id of the post being linked.
	 * @param string $ignore_label A label to ignore.
	 *
	 * @return string    The title to be used in the link. An empty string when
	 *                    there is no alternative that is not the $ignore_label.
	 */
	function get_link_title( $post_id, $ignore_label ) {

		// Get possible alternative labels we can select from.
		$labels = $this->entity_service->get_alternative_labels( $post_id );

		/*
		 * Since the original text might use an alternative label than the
		 * Entity title, add the title itself which is not returned by the api.
		 */
		$labels[] = get_the_title( $post_id );

		// Add some randomness to the label selection.
		shuffle( $labels );

		// Select the first label which is not to be ignored.
		$title = '';
		foreach ( $labels as $label ) {
			if ( 0 !== strcasecmp( $label, $ignore_label ) ) {
				$title = $label;
				break;
			}
		}

		return $title;
	}

	/**
	 * Get the entity URIs (configured in the `itemid` attribute) contained in
	 * the provided content.
	 *
	 * @since 3.14.2
	 *
	 * @param string $content The content.
	 *
	 * @return array An array of URIs.
	 */
	public function get_entity_uris( $content ) {

		$matches = array();
		preg_match_all( Wordlift_Content_Filter_Service::PATTERN, $content, $matches );

		// We need to use `array_values` here in order to avoid further `json_encode`
		// to turn it into an object (since if the 3rd match isn't found the index
		// is not sequential.
		//
		// See https://github.com/insideout10/wordlift-plugin/issues/646.
		return array_values( array_unique( $matches[3] ) );
	}

}
