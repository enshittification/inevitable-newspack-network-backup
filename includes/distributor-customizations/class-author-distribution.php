<?php
/**
 * Newspack Network author distriburion.
 *
 * @package Newspack
 */

namespace Newspack_Network\Distributor_Customizations;

use Newspack\Data_Events;

/**
 * Class to handle author distribution.
 *
 * Every time a post is distributed, we also send all the information about the author (or authors if CAP is enabled)
 * On the target site, the plugin will create the authors if they don't exist, and override the byline
 */
class Author_Distribution {

	/**
	 * The user meta we watch for changes.
	 *
	 * @var array
	 */
	public static $watched_meta = [
		// Social Links.
		'facebook',
		'instagram',
		'linkedin',
		'myspace',
		'pinterest',
		'soundcloud',
		'tumblr',
		'twitter',
		'youtube',
		'wikipedia',

		// Core bio.
		'first_name',
		'last_name',
		'description',

		// Newspack.
		'newspack_job_title',
		'newspack_role',
		'newspack_employer',
		'newspack_phone_number',

		// Yoast SEO.
		'wpseo_title',
		'wpseo_metadesc',
		'wpseo_noindex_author',
		'wpseo_content_analysis_disable',
		'wpseo_keyword_analysis_disable',
		'wpseo_inclusive_language_analysis_disable',
	];

	/**
	 * The user properties we're watching and syncing.
	 *
	 * @var array
	 */
	public static $user_props = [
		'display_name',
		'user_email',
		'user_url',
		'website', // guest authors.
	];

	/**
	 * Initializes the class
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'dt_push_post_args', [ __CLASS__, 'add_author_data_to_push' ], 10, 2 );
	}

	/**
	 * Filters the post data sent on a push to add the author data.
	 *
	 * @param array   $post_body The post data.
	 * @param WP_Post $post The post object.
	 * @return array
	 */
	public static function add_author_data_to_push( $post_body, $post ) {
		$authors = self::get_authors_for_distribution( $post );
		if ( ! empty( $authors ) ) {
			$post_body['newspack_network_authors'] = $authors;
		}
		return $post_body;
	}

	/**
	 * Get the authors of a post to be added to the distribution payload.
	 *
	 * @param WP_Post $post The post object.
	 * @return array An array of authors.
	 */
	public static function get_authors_for_distribution( $post ) {
		if ( ! function_exists( 'get_coauthors' ) ) {
			return [ self::get_wp_user_for_distribution( $post->post_author ) ];
		}

		$co_authors = get_coauthors( $post->ID );
		if ( empty( $co_authors ) ) {
			return [ self::get_wp_user_for_distribution( $post->post_author ) ];
		}

		$authors = [];

		foreach ( $co_authors as $co_author ) {
			if ( is_a( $co_author, 'WP_User' ) ) {
				$authors[] = self::get_wp_user_for_distribution( $co_author );
			}
			$authors[] = self::get_guest_author_for_distribution( $co_author );
		}

		return $authors;

	}

	/**
	 * Gets the user data of a WP user to be distributed along with the post.
	 *
	 * @param int|WP_Post $user The user ID or object.
	 * @return array
	 */
	public static function get_wp_user_for_distribution( $user ) {
		if ( ! is_a( $user, 'WP_User' ) ) {
			$user = get_user_by( 'ID', $user );
		}

		if ( ! $user ) {
			return [];
		}

		$author = [
			'type' => 'wp_user',
			'id'   => $user->ID,
		];


		foreach ( self::$user_props as $prop ) {
			if ( isset( $user->$prop ) ) {
				$author[ $prop ] = $user->$prop;
			}
		}

		foreach ( self::$watched_meta as $meta_key ) {
			$author[ $meta_key ] = get_user_meta( $user->ID, $meta_key, true );
		}

		return $author;
	}

	/**
	 * Get the guest author data to be distributed along with the post.
	 *
	 * @param object $guest_author The Guest Author object.
	 * @return array
	 */
	public static function get_guest_author_for_distribution( $guest_author ) {

		if ( ! is_object( $guest_author ) || ! isset( $guest_author->type ) || 'guest-author' !== $guest_author->type ) {
			return [];
		}

		$author = [
			'type' => 'guest_author',
			'id'   => $guest_author->ID,
		];

		foreach ( self::$user_props as $prop ) {
			if ( isset( $guest_author->$prop ) ) {
				$author[ $prop ] = $guest_author->$prop;
			}
		}

		foreach ( self::$watched_meta as $meta_key ) {
			if ( isset( $guest_author->$meta_key ) ) {
				$author[ $meta_key ] = $guest_author->$meta_key;
			}
		}

		return $author;
	}

}
