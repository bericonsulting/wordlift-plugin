<?php
/**
 * Converters: Abstract Post to JSON-LD Converter.
 *
 * An abstract converter which provides basic post conversion.
 *
 * @since      3.10.0
 * @package    Wordlift
 * @subpackage Wordlift/includes
 */

/**
 * Define the {@link Wordlift_Abstract_Post_To_Jsonld_Converter} class.
 *
 * @since      3.10.0
 * @package    Wordlift
 * @subpackage Wordlift/includes
 */
abstract class Wordlift_Abstract_Post_To_Jsonld_Converter implements Wordlift_Post_Converter {

	/**
	 * The JSON-LD context.
	 *
	 * @since 3.10.0
	 */
	const CONTEXT = 'http://schema.org';

	/**
	 * A {@link Wordlift_Entity_Type_Service} instance.
	 *
	 * @since  3.10.0
	 * @access protected
	 * @var \Wordlift_Entity_Type_Service $entity_type_service A {@link Wordlift_Entity_Type_Service} instance.
	 */
	protected $entity_type_service;

	/**
	 * A {@link Wordlift_Entity_Service} instance.
	 *
	 * @since  3.10.0
	 * @access protected
	 * @var \Wordlift_Entity_Service $entity_type_service A {@link Wordlift_Entity_Service} instance.
	 */
	protected $entity_service;

	/**
	 * A {@link Wordlift_User_Service} instance.
	 *
	 * @since  3.10.0
	 * @access private
	 * @var \Wordlift_User_Service $user_service A {@link Wordlift_User_Service} instance.
	 */
	protected $user_service;

	/**
	 * A {@link Wordlift_Attachment_Service} instance.
	 *
	 * @since  3.10.0
	 * @access private
	 * @var \Wordlift_Attachment_Service $attachment_service A {@link Wordlift_Attachment_Service} instance.
	 */
	protected $attachment_service;

	/**
	 * A {@link Wordlift_Cache_Service} instance. Used to access cached data.
	 *
	 * @since  3.16.0
	 * @access private
	 * @var \Wordlift_Cache_Service $image_data_cache A {@link Wordlift_Cache_Service} instance.
	 */
	static protected $image_data_cache = null;

	/**
	 * Wordlift_Post_To_Jsonld_Converter constructor.
	 *
	 * @since 3.10.0
	 *
	 * @param \Wordlift_Entity_Type_Service $entity_type_service A {@link Wordlift_Entity_Type_Service} instance.
	 * @param \Wordlift_Entity_Service      $entity_service      A {@link Wordlift_Entity_Service} instance.
	 * @param \Wordlift_User_Service        $user_service        A {@link Wordlift_User_Service} instance.
	 * @param \Wordlift_Attachment_Service  $attachment_service  A {@link Wordlift_Attachment_Service} instance.
	 */
	public function __construct( $entity_type_service, $entity_service, $user_service, $attachment_service ) {

		$this->entity_type_service = $entity_type_service;
		$this->entity_service      = $entity_service;
		$this->user_service        = $user_service;
		$this->attachment_service  = $attachment_service;
		if ( is_null( self::$image_data_cache ) ) {
			self::$image_data_cache    = new Wordlift_Cache_Service( 'json_ld_images' );

			// Hook on post save to flush relevant cache.
			add_action( 'save_post', 'Wordlift_Abstract_Post_To_Jsonld_Converter::save_post' );

			// Hook on meta change to detect when featured image is change which might not
			/// always be when the post is saved.
			add_action( 'added_post_meta', 'Wordlift_Abstract_Post_To_Jsonld_Converter::updated_meta', 10, 4 );
			add_action( 'updated_post_meta', 'Wordlift_Abstract_Post_To_Jsonld_Converter::updated_meta', 10, 4 );
			add_action( 'deleted_post_meta', 'Wordlift_Abstract_Post_To_Jsonld_Converter::updated_meta', 10, 4 );
		}
	}

	/**
	 * Convert the provided {@link WP_Post} to a JSON-LD array. Any entity reference
	 * found while processing the post is set in the $references array.
	 *
	 * @since 3.10.0
	 *
	 *
	 * @param int   $post_id    The post id.
	 * @param array $references An array of entity references.
	 *
	 * @return array A JSON-LD array.
	 */
	public function convert( $post_id, &$references = array() ) {

		// Get the post instance.
		if ( null === $post = get_post( $post_id ) ) {
			// Post not found.
			return null;
		}

		// Get the post URI @id.
		$id = $this->entity_service->get_uri( $post->ID );

		// Get the entity @type. We consider `post` BlogPostings.
		$type = $this->entity_type_service->get( $post_id );

		// Prepare the response.
		$jsonld = array(
			'@context'    => self::CONTEXT,
			'@id'         => $id,
			'@type'       => $this->relative_to_context( $type['uri'] ),
			'description' => Wordlift_Post_Excerpt_Helper::get_text_excerpt( $post ),
		);

		// Set the `mainEntityOfPage` property if the post has some contents.
		if ( ! empty( $post->post_content ) ) {
			// We're setting the `mainEntityOfPage` to signal which one is the
			// main entity for the specified URL. It might be however that the
			// post/page is actually about another specific entity. How WL deals
			// with that hasn't been defined yet (see https://github.com/insideout10/wordlift-plugin/issues/451).
			//
			// See http://schema.org/mainEntityOfPage
			//
			// No need to specify `'@type' => 'WebPage'.
			//
			// See https://github.com/insideout10/wordlift-plugin/issues/451
			$jsonld['mainEntityOfPage'] = get_the_permalink( $post->ID );
		};

		$this->set_images( $post, $jsonld );

		// Get the entities referenced by this post and set it to the `references`
		// array so that the caller can do further processing, such as printing out
		// more of those references.
		$references = $this->entity_service->get_related_entities( $post->ID );

		return $jsonld;
	}

	/**
	 * If the provided value starts with the schema.org context, we remove the schema.org
	 * part since it is set with the '@context'.
	 *
	 * @since 3.10.0
	 *
	 * @param string $value The property value.
	 *
	 * @return string The property value without the context.
	 */
	public function relative_to_context( $value ) {

		return 0 === strpos( $value, self::CONTEXT . '/' ) ? substr( $value, strlen( self::CONTEXT ) + 1 ) : $value;
	}

	/**
	 * Set the images, by looking for embedded images, for images loaded via the
	 * gallery and for the featured image.
	 *
	 * Uses the cache service to store the results of this function for a day.
	 *
	 * @since 3.10.0
	 *
	 * @param WP_Post $post   The target {@link WP_Post}.
	 * @param array   $jsonld The JSON-LD array.
	 */
	protected function set_images( $post, &$jsonld ) {

		// First lets check if we have this cached, and if so return it.
		$cache = self::$image_data_cache->get( $post->ID );
		if ( $cache ) {
			if (0 !== $cache) { // do nothing if we have no images cached.
				return $cache;
			}
		}

		// Prepare the attachment ids array.
		$ids = array();

		// Set the thumbnail id as first attachment id, if any.
		if ( '' !== $thumbnail_id = get_post_thumbnail_id( $post->ID ) ) {
			$ids[] = $thumbnail_id;
		}

		// Get the embeds, removing existing ids.
		$embeds = array_diff( $this->attachment_service->get_image_embeds( $post->post_content ), $ids );

		// Get the gallery, removing existing ids.
		$gallery = array_diff( $this->attachment_service->get_gallery( $post ), $ids, $embeds );

		// Map the attachment ids to images' data structured for schema.org use.
		$images = array_map( function ( $item ) {

			// @todo: we're not sure that we're getting attachment data here, we
			// should filter `false`s.

			// Get the attachment data.
			$attachment = wp_get_attachment_image_src( $item, 'full' );

			// Refactor data as per schema.org specifications.
			return Wordlift_Abstract_Post_To_Jsonld_Converter::set_image_size( array(
				'@type' => 'ImageObject',
				'url'   => $attachment[0],
			), $attachment );
		}, array_merge( $ids, $embeds, $gallery ) );

		if ( 0 < sizeof( $images ) ) {
			$jsonld['image'] = $images;
			self::$image_data_cache->set( $post->ID, $images, DAY_IN_SECONDS );
		} else {
			self::$image_data_cache->set( $post->ID, 0, DAY_IN_SECONDS );
		}

	}

	/**
	 * Process the provided array by adding the width / height if the values
	 * are available and are greater than 0.
	 *
	 * @since 3.14.0
	 *
	 * @param array $image      The `ImageObject` array.
	 * @param array $attachment The attachment array.
	 *
	 * @return array The enriched `ImageObject` array.
	 */
	public static function set_image_size( $image, $attachment ) {

		// If you specify a "width" or "height" value you should leave out
		// 'px'. For example: "width":"4608px" should be "width":"4608".
		//
		// See https://github.com/insideout10/wordlift-plugin/issues/451
		if ( isset( $attachment[1] ) && is_numeric( $attachment[1] ) && 0 < $attachment[1] ) {
			$image['width'] = $attachment[1];
		}

		if ( isset( $attachment[2] ) && is_numeric( $attachment[2] ) && 0 < $attachment[2] ) {
			$image['height'] = $attachment[2];
		}

		return $image;
	}

	/**
	 * Delete relevant cache when a post content is changed.
	 *
	 * @since 3.16.0
	 *
	 * @param int     $post_id The id of the post.
	 * @param WP_Post $post    The post.
	 */
	static public function save_post( $post_id, $post ) {
		self::$image_data_cache->delete( $post_id );
	}

	/**
	 * Delete the relevant post cache when a featured image is changed.
	 *
	 * @since 3.16.0
	 *
	 * @param int    $meta_id    ID of updated metadata entry.
	 * @param int    $object_id  Object ID.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value Meta value.
	 */
	static public function updated_meta( $meta_id, $object_id, $meta_key, $meta_value ) {
		if ( '_thumbnail_id' === $meta_key ) {
			self::$image_data_cache->delete( $object_id );
		}
	}

	/**
	 * Delete all of the posts cache.
	 *
	 * @since 3.16.0
	 *
	 */
	static public function flush_cache( ) {
		if ( ! is_null( self::$image_data_cache ) ) {
			self::$image_data_cache->flush( );
		} else {
			$t = new Wordlift_Cache_Service( 'json_ld_images' );
			$t->flush();
		}
	}
}
