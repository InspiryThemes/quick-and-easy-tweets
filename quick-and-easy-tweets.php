<?php
/**
 * Plugin Name:       Quick and Easy Tweets
 * Plugin URI:        https://github.com/saqibsarwar/quick-and-easy-tweets
 * Description:       A quick and easy way to display tweets on your website via widget.
 * Version:           1.0.9
 * Tested up to:      6.9
 * Requires PHP:      8.3
 * Author:            InspiryThemes
 * Author URI:        https://inspirythemes.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       qaet
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Plugin text domain.
define( 'QE_TWEETS_TEXT_DOMAIN', 'qaet' );

class Quick_And_Easy_Tweets extends WP_Widget {

	/**
	 * Register widget with WordPress.
	 */
	public function __construct() {
		parent::__construct(
			'quick-and-easy-tweets',
			esc_html__( 'Quick and Easy Tweets', QE_TWEETS_TEXT_DOMAIN ),
			array( 'description' => esc_html__( 'A widget to display tweets.', QE_TWEETS_TEXT_DOMAIN ) ) );
	}


	/**
	 * Outputs the tweets
	 *
	 * @param array $args
	 * @param array $instance
	 */
	public function widget( $args, $instance ) {
		$title                     = apply_filters( 'widget_title', $instance['title'] );
		$username                  = $instance['twitter_username'];
		$limit                     = isset( $instance['update_count'] ) ? $instance['update_count'] : 5;
		$oauth_access_token        = $instance['oauth_access_token'];
		$oauth_access_token_secret = $instance['oauth_access_token_secret'];
		$consumer_key              = $instance['consumer_key'];
		$consumer_secret           = $instance['consumer_secret'];
		// Default to 'api' for backward compatibility
		$display_mode = isset( $instance['display_mode'] ) ? $instance['display_mode'] : 'api';
		$tweet_urls   = isset( $instance['tweet_urls'] ) ? $instance['tweet_urls'] : '';

		echo $args['before_widget'];

		if ( ! empty( $title ) ) {
			echo $args['before_title'] . $title . $args['after_title'];
		}

		if ( 'oembed' === $display_mode ) {
			// oEmbed Mode (Free - Manual Tweet URLs)
			$this->render_oembed_tweets( $tweet_urls );

		} else if ( 'embed' === $display_mode ) {
			// Embed Mode (Free Timeline - Often Restricted)
			// Enqueue Twitter Widgets JS properly
			wp_enqueue_script( 'twitter-widgets', 'https://platform.twitter.com/widgets.js', array(), null, true );

			// "data-tweet-limit" is often rate-limited or deprecated. Using "data-height" is safer.
			// Estimate height based on tweet count (approx 120px to 150px per tweet).
			$height = $limit * 150;
			if ( $height < 300 ) {
				$height = 300;
			} // Min height
			if ( $height > 1000 ) {
				$height = 1000;
			} // Max height
			?>
			<a class="twitter-timeline" data-height="<?php echo esc_attr( $height ); ?>" href="https://twitter.com/<?php echo esc_attr( $username ); ?>?ref_src=twsrc%5Etfw">Tweets
				by <?php echo esc_html( $username ); ?></a>
			<?php

		} else {
			// API Mode (Paid)
			/* Get Tweets */
			$tweets_data = $this->get_tweets( $username, $limit, $oauth_access_token, $oauth_access_token_secret, $consumer_key, $consumer_secret );

			if ( $tweets_data && isset( $tweets_data->data ) ) {
				$tweets = $tweets_data->data;

				// Add links to URL and username mention in tweets.
				$patterns = array(
					'@(https?://([-\w\.]+)+(:\d+)?(/([\w/_\.]*(\?\S+)?)?)?)@',
					'/@([A-Za-z0-9_]{1,15})/'
				);
				$replace  = array( '<a href="$1">$1</a>', '<a href="http://twitter.com/$1">@$1</a>' );

				ob_start();

				echo ob_get_clean();

				echo '<div class="qaet-tweets">';
				foreach ( $tweets as $tweet ) {
					$result = preg_replace( $patterns, $replace, $tweet->text );
					?>
					<div class="qaet-tweet">
						<p class="text">
							<?php echo $result; ?>
							<br>
							<small>
								<a target="_blank" href="<?php echo esc_url( 'http://twitter.com/' . $username ); ?>"><?php echo esc_html( $this->tweet_time( $tweet->created_at ) ); ?></a>
							</small>
						</p>
					</div>
					<?php
				}
				echo '</div>';

			} else {
				if ( isset( $tweets_data->errors ) ) {
					echo '<p>' . esc_html__( 'Error:', QE_TWEETS_TEXT_DOMAIN ) . ' ' . esc_html( $tweets_data->errors[0]->detail ) . '</p>';
				} else {
					esc_html_e( 'Problem in getting tweets. Kindly verify the twitter configurations provided in the widget.', QE_TWEETS_TEXT_DOMAIN );
				}
			}
		}

		echo $args['after_widget'];
	}

	/**
	 * Render tweets using oEmbed API (Free, no auth required)
	 *
	 * @param string $tweet_urls Newline-separated tweet URLs
	 */
	private function render_oembed_tweets( $tweet_urls ) {
		if ( empty( $tweet_urls ) ) {
			echo '<p>' . esc_html__( 'Please add tweet URLs in the widget settings.', QE_TWEETS_TEXT_DOMAIN ) . '</p>';

			return;
		}

		// Split URLs by newline
		$urls = array_filter( array_map( 'trim', explode( "\n", $tweet_urls ) ) );

		if ( empty( $urls ) ) {
			echo '<p>' . esc_html__( 'No valid tweet URLs found.', QE_TWEETS_TEXT_DOMAIN ) . '</p>';

			return;
		}

		echo '<div class="qaet-oembed-tweets">';

		foreach ( $urls as $url ) {
			// Validate URL format
			if ( ! preg_match( '#^https?://(twitter\.com|x\.com)/.+/status/\d+#i', $url ) ) {
				continue;
			}

			// Cache key for this specific tweet
			$cache_key   = 'qaet_oembed_' . md5( $url );
			$cached_html = get_transient( $cache_key );

			if ( false !== $cached_html ) {
				echo $cached_html;
			} else {
				// Call oEmbed API
				$oembed_url = 'https://publish.twitter.com/oembed?url=' . urlencode( $url );
				$response   = wp_remote_get( $oembed_url );

				if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
					$body = wp_remote_retrieve_body( $response );
					$data = json_decode( $body );

					if ( isset( $data->html ) ) {
						$html = '<div class="qaet-oembed-tweet">' . $data->html . '</div>';
						// Cache for 1 hour
						set_transient( $cache_key, $html, 3600 );
						echo $html;
					}
				}
			}
		}

		echo '</div>';

		// Enqueue widgets.js to make the embeds interactive
		wp_enqueue_script( 'twitter-widgets', 'https://platform.twitter.com/widgets.js', array(), null, true );
	}

	/**
	 * Output widget form
	 */
	public function form( $instance ) {
		$title                     = ! empty( $instance['title'] ) ? $instance['title'] : esc_html__( 'Recent Tweets', QE_TWEETS_TEXT_DOMAIN );
		$twitter_username          = ! empty( $instance['twitter_username'] ) ? $instance['twitter_username'] : '';
		$update_count              = ! empty( $instance['update_count'] ) ? $instance['update_count'] : '';
		$oauth_access_token        = ! empty( $instance['oauth_access_token'] ) ? $instance['oauth_access_token'] : '';
		$oauth_access_token_secret = ! empty( $instance['oauth_access_token_secret'] ) ? $instance['oauth_access_token_secret'] : '';
		$consumer_key              = ! empty( $instance['consumer_key'] ) ? $instance['consumer_key'] : '';
		$consumer_secret           = ! empty( $instance['consumer_secret'] ) ? $instance['consumer_secret'] : '';
		$display_mode              = ! empty( $instance['display_mode'] ) ? $instance['display_mode'] : 'api';
		$tweet_urls                = ! empty( $instance['tweet_urls'] ) ? $instance['tweet_urls'] : '';
		?>
		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>">
				<?php esc_html_e( 'Title:', QE_TWEETS_TEXT_DOMAIN ); ?>
			</label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php if ( isset( $title ) ) {
				echo esc_attr( $title );
			} ?>" />
		</p>

		<p>
			<label for="<?php echo $this->get_field_id( 'display_mode' ); ?>"><?php esc_html_e( 'Display Mode:', QE_TWEETS_TEXT_DOMAIN ); ?></label>
			<select class="widefat" id="<?php echo $this->get_field_id( 'display_mode' ); ?>" name="<?php echo $this->get_field_name( 'display_mode' ); ?>">
				<option value="api" <?php selected( $display_mode, 'api' ); ?>><?php esc_html_e( 'API (Paid - Custom Timeline)', QE_TWEETS_TEXT_DOMAIN ); ?></option>
				<option value="oembed" <?php selected( $display_mode, 'oembed' ); ?>><?php esc_html_e( 'oEmbed (Free - Manual URLs)', QE_TWEETS_TEXT_DOMAIN ); ?></option>
				<option value="embed" <?php selected( $display_mode, 'embed' ); ?>><?php esc_html_e( 'Embed Timeline (Restricted/Broken)', QE_TWEETS_TEXT_DOMAIN ); ?></option>
			</select>
			<small><?php esc_html_e( 'oEmbed mode is recommended for free usage.', QE_TWEETS_TEXT_DOMAIN ); ?></small>
		</p>

		<div id="<?php echo $this->get_field_id( 'oembed_section' ); ?>" style="<?php echo ( 'oembed' !== $display_mode ) ? 'display:none;' : ''; ?>">
			<p>
				<label for="<?php echo $this->get_field_id( 'tweet_urls' ); ?>">
					<?php esc_html_e( 'Tweet URLs (one per line):', QE_TWEETS_TEXT_DOMAIN ); ?>
				</label>
				<textarea class="widefat" id="<?php echo $this->get_field_id( 'tweet_urls' ); ?>" name="<?php echo $this->get_field_name( 'tweet_urls' ); ?>" rows="5"><?php echo esc_textarea( $tweet_urls ); ?></textarea>
				<small><?php esc_html_e( 'Example: https://twitter.com/SpaceX/status/1234567890', QE_TWEETS_TEXT_DOMAIN ); ?></small>
			</p>
		</div>

		<div id="<?php echo $this->get_field_id( 'timeline_section' ); ?>" style="<?php echo ( 'oembed' === $display_mode ) ? 'display:none;' : ''; ?>">
			<p>
				<label for="<?php echo $this->get_field_id( 'twitter_username' ); ?>">
					<?php esc_html_e( 'Twitter Username:', QE_TWEETS_TEXT_DOMAIN ); ?>
				</label>
				<input class="widefat" id="<?php echo $this->get_field_id( 'twitter_username' ); ?>" name="<?php echo $this->get_field_name( 'twitter_username' ); ?>" type="text" value="<?php if ( isset( $twitter_username ) ) {
					echo esc_attr( $twitter_username );
				} ?>" />
			</p>
			<p>
				<label for="<?php echo $this->get_field_id( 'update_count' ); ?>">
					<?php esc_html_e( 'Number of Tweets to Display:', QE_TWEETS_TEXT_DOMAIN ); ?>
				</label>
				<input class="widefat" id="<?php echo $this->get_field_id( 'update_count' ); ?>" name="<?php echo $this->get_field_name( 'update_count' ); ?>" type="number" value="<?php if ( isset( $update_count ) ) {
					echo esc_attr( $update_count );
				} ?>" />
			</p>
		</div>

		<div id="<?php echo $this->get_field_id( 'api_section' ); ?>" style="<?php echo ( 'api' !== $display_mode ) ? 'display:none;' : ''; ?>border-top: 1px solid #ddd; padding-top: 10px; margin-top: 10px;">
			<p>
				<strong><?php esc_html_e( 'API Configuration (Required for API Mode only)', QE_TWEETS_TEXT_DOMAIN ); ?></strong>
			</p>
			<p>
				<label for="<?php echo $this->get_field_id( 'consumer_key' ); ?>">
					<?php esc_html_e( 'Consumer Key:', QE_TWEETS_TEXT_DOMAIN ); ?>
				</label>
				<input class="widefat" id="<?php echo $this->get_field_id( 'consumer_key' ); ?>" name="<?php echo $this->get_field_name( 'consumer_key' ); ?>" type="text" value="<?php if ( isset( $consumer_key ) ) {
					echo esc_attr( $consumer_key );
				} ?>" />
				<small> <a target="_blank" href="https://developer.twitter.com/en/apps">Click here to create new twitter
						application</a> to get your Consumer Key, Consumer Secret, Access Token and Access Token Secret.</small>
			</p>
			<p>
				<label for="<?php echo $this->get_field_id( 'consumer_secret' ); ?>">
					<?php esc_html_e( 'Consumer Secret:', QE_TWEETS_TEXT_DOMAIN ); ?>
				</label>
				<input class="widefat" id="<?php echo $this->get_field_id( 'consumer_secret' ); ?>" name="<?php echo $this->get_field_name( 'consumer_secret' ); ?>" type="text" value="<?php if ( isset( $consumer_secret ) ) {
					echo esc_attr( $consumer_secret );
				} ?>" />
			</p>
			<p>
				<label for="<?php echo $this->get_field_id( 'oauth_access_token' ); ?>">
					<?php esc_html_e( 'Access Token:', QE_TWEETS_TEXT_DOMAIN ); ?>
				</label>
				<input class="widefat" id="<?php echo $this->get_field_id( 'oauth_access_token' ); ?>" name="<?php echo $this->get_field_name( 'oauth_access_token' ); ?>" type="text" value="<?php if ( isset( $oauth_access_token ) ) {
					echo esc_attr( $oauth_access_token );
				} ?>" />
			</p>
			<p>
				<label for="<?php echo $this->get_field_id( 'oauth_access_token_secret' ); ?>">
					<?php esc_html_e( 'Access Token Secret:', QE_TWEETS_TEXT_DOMAIN ); ?>
				</label>
				<input class="widefat" id="<?php echo $this->get_field_id( 'oauth_access_token_secret' ); ?>" name="<?php echo $this->get_field_name( 'oauth_access_token_secret' ); ?>" type="text" value="<?php if ( isset( $oauth_access_token_secret ) ) {
					echo esc_attr( $oauth_access_token_secret );
				} ?>" />
			</p>
		</div>
		<?php
	}

	/**
	 * Sanitize widget form values as they are saved.
	 *
	 * @param array $new_instance Values just sent to be saved.
	 * @param array $old_instance Previously saved values from database.
	 *
	 * @return array Updated safe values to be saved.
	 * @see WP_Widget::update()
	 *
	 */
	public function update( $new_instance, $old_instance ) {
		$instance = array();

		$instance['display_mode']              = ( ! empty( $new_instance['display_mode'] ) ) ? strip_tags( $new_instance['display_mode'] ) : 'api';
		$instance['title']                     = ( ! empty( $new_instance['title'] ) ) ? strip_tags( $new_instance['title'] ) : '';
		$instance['twitter_username']          = ( ! empty( $new_instance['twitter_username'] ) ) ? strip_tags( $new_instance['twitter_username'] ) : '';
		$instance['update_count']              = ( ! empty( $new_instance['update_count'] ) ) ? strip_tags( $new_instance['update_count'] ) : '';
		$instance['oauth_access_token']        = ( ! empty( $new_instance['oauth_access_token'] ) ) ? strip_tags( $new_instance['oauth_access_token'] ) : '';
		$instance['oauth_access_token_secret'] = ( ! empty( $new_instance['oauth_access_token_secret'] ) ) ? strip_tags( $new_instance['oauth_access_token_secret'] ) : '';
		$instance['consumer_key']              = ( ! empty( $new_instance['consumer_key'] ) ) ? strip_tags( $new_instance['consumer_key'] ) : '';
		$instance['consumer_secret']           = ( ! empty( $new_instance['consumer_secret'] ) ) ? strip_tags( $new_instance['consumer_secret'] ) : '';
		$instance['tweet_urls']                = ( ! empty( $new_instance['tweet_urls'] ) ) ? sanitize_textarea_field( $new_instance['tweet_urls'] ) : '';


		// Clear cache when settings update
		$cacheKey = $instance['twitter_username'] . ' -recent-' . $instance['update_count'] . '-tweets';
		delete_transient( $cacheKey );

		// Also clear cache for User ID if it exists (though we don't cache ID in this simplistic update yet, good practice)

		return $instance;
	}

	/**
	 * Get Tweets using OAuth Twitter API v2
	 *
	 * @param $username
	 * @param $limit
	 * @param $oauth_access_token
	 * @param $oauth_access_token_secret
	 * @param $consumer_key
	 * @param $consumer_secret
	 *
	 * @return API|array|mixed|object
	 */
	public function get_tweets( $username, $limit, $oauth_access_token, $oauth_access_token_secret, $consumer_key, $consumer_secret ) {
		$cacheKey = $username . ' -recent-' . $limit . '-tweets';
		$cached   = get_transient( $cacheKey );
		if ( false !== $cached ) {
			return $cached;
		}

		include_once( plugin_dir_path( __FILE__ ) . 'twitteroauth/twitteroauth.php' );
		$twitterOAuth = new TwitterOAuth( $consumer_key, $consumer_secret, $oauth_access_token, $oauth_access_token_secret );

		// 1. Get User ID from Username
		$user_url      = 'https://api.twitter.com/2/users/by/username/' . $username;
		$user_response = $twitterOAuth->get( $user_url );

		if ( isset( $user_response->data ) && isset( $user_response->data->id ) ) {
			$user_id = $user_response->data->id;

			// 2. Get User Timeline
			$timeline_url = "https://api.twitter.com/2/users/{$user_id}/tweets";
			$params       = [
				'max_results'  => $limit,
				'tweet.fields' => 'created_at,text',
				'exclude'      => 'replies' // Only exclude replies to match original behavior (retweets included)
			];
			// Adjust limit constraint for v2 (min 5, max 100).
			if ( $limit < 5 ) {
				$params['max_results'] = 5;
			}
			if ( $limit > 100 ) {
				$params['max_results'] = 100;
			}

			// Since TwitterOAuth->get puts params in query string for GET, this works.
			$tweets = $twitterOAuth->get( $timeline_url, $params );

			if ( isset( $tweets->data ) ) {
				set_transient( $cacheKey, $tweets, 3600 );

				return $tweets;
			} else {
				return $tweets; // Return error response if any
			}

		} else {
			return $user_response; // Return error from user lookup
		}
	}


	/**
	 * To make the tweet time more human friendly
	 *
	 * @param $time
	 *
	 * @return string|void
	 */
	public function tweet_time( $time ) {
		// Get current timestamp.
		$now = strtotime( 'now' );

		// Get timestamp when tweet created.
		$created = strtotime( $time );

		// Get difference.
		$difference = $now - $created;

		// Calculate different time values.
		$minute = 60;
		$hour   = $minute * 60;
		$day    = $hour * 24;
		$week   = $day * 7;

		if ( is_numeric( $difference ) && $difference > 0 ) {

			// If less than 3 seconds.
			if ( $difference < 3 ) {
				return esc_html__( 'right now', QE_TWEETS_TEXT_DOMAIN );
			}

			// If less than minute.
			if ( $difference < $minute ) {
				return floor( $difference ) . ' ' . esc_html__( 'seconds ago', QE_TWEETS_TEXT_DOMAIN );
			}

			// If less than 2 minutes.
			if ( $difference < $minute * 2 ) {
				return esc_html__( 'about 1 minute ago', QE_TWEETS_TEXT_DOMAIN );
			}

			// If less than hour.
			if ( $difference < $hour ) {
				return floor( $difference / $minute ) . ' ' . esc_html__( 'minutes ago', QE_TWEETS_TEXT_DOMAIN );
			}

			// If less than 2 hours.
			if ( $difference < $hour * 2 ) {
				return esc_html__( 'about 1 hour ago', QE_TWEETS_TEXT_DOMAIN );
			}

			// If less than day.
			if ( $difference < $day ) {
				return floor( $difference / $hour ) . ' ' . esc_html__( 'hours ago', QE_TWEETS_TEXT_DOMAIN );
			}

			// If more than day, but less than 2 days.
			if ( $difference > $day && $difference < $day * 2 ) {
				return esc_html__( 'yesterday', QE_TWEETS_TEXT_DOMAIN );
			}

			// If less than year.
			if ( $difference < $day * 365 ) {
				return floor( $difference / $day ) . ' ' . esc_html__( 'days ago', QE_TWEETS_TEXT_DOMAIN );
			}

			// Else return more than a year.
			return esc_html__( 'over a year ago', QE_TWEETS_TEXT_DOMAIN );
		}
	}

} // class Quick_And_Easy_Tweets


/**
 * Register Quick and Easy Tweets Widget
 */
function register_quick_and_easy_tweets_widget() {
	register_widget( 'Quick_And_Easy_Tweets' );
}

add_action( 'widgets_init', 'register_quick_and_easy_tweets_widget' );


/**
 * Load plugin text domain.
 */
function qaet_load_textdomain() {
	load_plugin_textdomain( QE_TWEETS_TEXT_DOMAIN, false, plugin_basename( dirname( __FILE__ ) ) . '/languages/' );
}

add_action( 'plugins_loaded', 'qaet_load_textdomain' );


/**
 * Plugin Styles
 */
function qaet_styles() {
	?>
	<style type="text/css">
		.qaet-tweet small {
			opacity: 0.5;
		}

		.qaet-tweet small:hover {
			opacity: 0.8;
		}
	</style>
	<?php
}

add_action( 'wp_head', 'qaet_styles' );