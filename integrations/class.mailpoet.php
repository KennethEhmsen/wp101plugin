<?php

class WP101_MailPoet_Videos {
	public static $instance;

	public function __construct() {
		self::$instance = $this;
		add_action( 'init', array( $this, 'init' ) );
	}

	public function init() {
		add_filter( 'wp101_get_document'          , array( self::$instance, 'get_document' ), 10, 3 );
		add_action( 'wp101_after_help_topics'     , array( self::$instance, 'mailpoet_help_topics_html' ) );
		add_action( 'wp101_after_edit_help_topics', array( self::$instance, 'mailpoet_edit_help_topics_html' ) );
		add_action( 'wp101_ajax_handler_mailpoet'    , array( self::$instance, 'change_topic_visiblity' ), 10, 2 );
	}

	public function get_mailpoet_help_topics( $wp_101 ) {

		$help_topics = false;

		if ( 'valid' == $wp_101->validate_api_key() ) {

			if ( $topics = get_transient( 'wp101_mailpoet_topics' ) ) {
				$help_topics = $topics;
			} else {
				$api_base = $wp_101->get_api_base();
				$result = wp_remote_get( $api_base . 'action=get_mailpoet_topics&api_key=' . $wp_101->get_key(), array( 'timeout' => 45, 'sslverify' => false, 'user-agent' => 'WP101Plugin' ) );
				$result = json_decode( $result['body'], true );
				if ( ! $result['error'] && count( $result['data'] ) ) {
					set_transient( 'wp101_mailpoet_topics', $result['data'], 30 ); // Good for a day.
					$help_topics = $result['data'];
				}
			}
		}

		return apply_filters( 'wp101_get_mailpoet_help_topics', $help_topics, self::$instance );
	}

	public function mailpoet_help_topics_html( $wp_101 ) {

		if ( 'valid' !== $wp_101->validate_api_key() ) {
			return;
		}

		echo self::$instance->get_mailpoet_help_topics_html( $wp_101 );
	}

	public function mailpoet_edit_help_topics_html( $wp_101 ) {
		echo self::$instance->get_mailpoet_help_topics_html( $wp_101, true );
	}

	public function get_mailpoet_help_topics_html( $wp_101, $edit_mode = false ) {

		$topics = $this->get_mailpoet_help_topics( $wp_101 );

		if ( ! $topics ) {
			return false;
		}

		$hidden_topics = $wp_101->get_hidden_topics();
		$topics_diff   = array_diff( array_keys( $topics ), $hidden_topics );

		if ( empty( $topics_diff ) && ! $edit_mode ) {
			return false;
		}

		$output = $this->mailpoet_help_topics_title( $edit_mode );

		$output .= '<div><ol class="wp101-topic-ol">';

		foreach ( $topics as $topic ) {
			if ( $edit_mode ) {
				$edit_links = '&nbsp;&nbsp;<small class="wp101-show">[<a data-nonce="' . wp_create_nonce( 'wp101-showhide-' . $topic['id'] ) . '" data-topic-id="' . $topic['id'] . '" href="#">show</a>]</small><small class="wp101-hide">[<a data-nonce="' . wp_create_nonce( 'wp101-showhide-' . $topic['id'] ) . '" data-topic-id="' . $topic['id'] . '" href="#">hide</a>]</small>';
				if ( $wp_101->is_hidden( $topic['id'] ) ) {
					$addl_class = 'wp101-hidden';
				} else {
					$addl_class = 'wp101-shown';
				}
			} else {
				if ( $wp_101->is_hidden( $topic['id'] ) ) {
					continue;
				}
				$edit_links = $addl_class = '';
			}
			$output .= '<li class="' . $addl_class . ' page-item-' . $topic['id'] . '"><span><a href="' . esc_url( admin_url( 'admin.php?page=wp101&document=' . $topic['id'] ) ) . '">' . esc_html( $topic['title'] ) . '</a></span>' . $edit_links . '</li>';
		}

		$output .= '</ol></div>';

		return $output;
	}

	public function mailpoet_help_topics_title( $edit_mode ) {

		$output = '<h3 class="title">' . __( 'MailPoet Tutorial Videos', 'wp101' );

		if ( $edit_mode ) {
			$nonce   = wp_create_nonce( 'wp101-showhide-allmailpoet' );
			$output .= '&nbsp;&nbsp;<small class="wp101-show-all">[<a data-nonce="' . $nonce . '" data-topic="mailpoet" href="#">show all</a>]</small><small class="wp101-hide-all">[<a data-nonce="' . $nonce. '" data-topic="mailpoet" href="#">hide all</a>]</small>';
		}

		$output .= '</h3>';

		return $output;
	}

	public function change_topic_visiblity( $wp_101, $direction ) {

		if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'wp101-showhide-allmailpoet' ) ) {
			die( '-1' );
		}

		foreach ( $this->get_mailpoet_help_topics( $wp_101 ) as $topic_id => $value ) {

			if ( 'hide-all' === $direction ) {
				$wp_101->hide_topic( $topic_id );
			} else {
				$wp_101->show_topic( $topic_id );
			}
		}
		die( '1' );
	}

	public function get_document( $document, $id, $wp_101 ) {

		if ( ! $document ) {

			$topics = $this->get_mailpoet_help_topics( $wp_101 );

			if ( isset( $topics[ $id ] ) ) {
				$document = $topics[ $id ];
			}
		}

		return apply_filters( 'wp101_mailpoet_get_document', $document, $id, self::$instance );
	}

}

add_action( 'plugins_loaded', 'wp101_maybe_activate_mailpoet_videos' );

function wp101_maybe_activate_mailpoet_videos() {
	if ( is_plugin_active( 'mailpoet/mailpoet.php' ) ) {
		return new WP101_MailPoet_Videos;
	}
}
