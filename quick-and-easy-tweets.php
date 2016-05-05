<?php
/**
 * Plugin Name:       Quick and Easy Tweets
 * Plugin URI:        https://github.com/saqibsarwar/quick-and-easy-tweets
 * Description:       A quick and easy way to to display tweets on your website using widget.
 * Version:           1.0.0
 * Author:            M Saqib Sarwar
 * Author URI:        http://saqibsarwar.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       qaet
 * Domain Path:       /languages
 */

class Quick_And_Easy_Tweets extends WP_Widget {

	/**
	 * Register widget with WordPress.
	 */
	public function __construct() {
		parent::__construct(
			'quick-and-easy-tweets',
			__( 'Quick and Easy Tweets', 'qaet' ),
			array ( 'description' => __( 'A widget to display tweets.', 'qaet' ) ) );
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
		$limit                     = $instance['update_count'];
		$oauth_access_token        = $instance['oauth_access_token'];
		$oauth_access_token_secret = $instance['oauth_access_token_secret'];
		$consumer_key              = $instance['consumer_key'];
		$consumer_secret           = $instance['consumer_secret'];

		echo $args['before_widget'];

		if ( ! empty( $title ) ) {
			echo $args['before_title'] . $title . $args['after_title'];
		}

		// Get the tweets.
		$tweets = $this->get_tweets( $username, $limit, $oauth_access_token, $oauth_access_token_secret, $consumer_key, $consumer_secret );

		if ( $tweets ) {

			// Add links to URL and username mention in tweets.
			$patterns = array( '@(https?://([-\w\.]+)+(:\d+)?(/([\w/_\.]*(\?\S+)?)?)?)@', '/@([A-Za-z0-9_]{1,15})/' );
			$replace = array( '<a href="$1">$1</a>', '<a href="http://twitter.com/$1">@$1</a>' );

			ob_start();

			?>
			<style type="text/css">
				.qaet-tweets { }
			</style>
			<?php

			echo ob_get_clean();

			echo '<div class="qaet-tweets">';
			foreach ( $tweets as $tweet ) {
				$result = preg_replace( $patterns, $replace, $tweet->text );
				echo '<div class="tweet">';
					echo '<p class="text">' . $result . '</p>';
					echo '<div class="time">' . $this->tweet_time( $tweet->created_at ) . '</div>';
				echo '</div>';
			}
			echo '</div>';

		} else {
			_e( 'Problem in getting tweets. Kindly verify the twitter configurations provided in the widget.', 'qaet' );
		}


		echo $args['after_widget'];
	}

	/**
	 * Output widget form
	 */
	public function form( $instance ) {
		$title = ! empty( $instance['title'] ) ? $instance['title'] : __( 'Recent Tweets', 'qaet' );
		$twitter_username = ! empty( $instance['twitter_username'] ) ? $instance['twitter_username'] : '';
		$update_count = ! empty( $instance['update_count'] ) ? $instance['update_count'] : '';
		$oauth_access_token = ! empty( $instance['oauth_access_token'] ) ? $instance['oauth_access_token'] : '';
		$oauth_access_token_secret = ! empty( $instance['oauth_access_token_secret'] ) ? $instance['oauth_access_token_secret'] : '';
		$consumer_key = ! empty( $instance['consumer_key'] ) ? $instance['consumer_key'] : '';
		$consumer_secret = ! empty( $instance['consumer_secret'] ) ? $instance['consumer_secret'] : '';
		?>
		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>">
				<?php echo __( 'Title', 'qaet' ) . ':'; ?>
			</label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php if(isset($title)){echo esc_attr( $title );} ?>" />
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'twitter_username' ); ?>">
				<?php echo __( 'Twitter Username', 'qaet' ) . ':'; ?>
			</label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'twitter_username' ); ?>" name="<?php echo $this->get_field_name( 'twitter_username' ); ?>" type="text" value="<?php if(isset($twitter_username)){echo esc_attr( $twitter_username );} ?>" />
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'update_count' ); ?>">
				<?php echo __( 'Number of Tweets to Display', 'qaet' ) . ':'; ?>
			</label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'update_count' ); ?>" name="<?php echo $this->get_field_name( 'update_count' ); ?>" type="number" value="<?php if(isset($update_count)){echo esc_attr( $update_count );} ?>" />
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'consumer_key' ); ?>">
				<?php echo __( 'Consumer Key', 'qaet' ) . ':'; ?>
			</label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'consumer_key' ); ?>" name="<?php echo $this->get_field_name( 'consumer_key' ); ?>" type="text" value="<?php if(isset($consumer_key)){echo esc_attr( $consumer_key );} ?>" />
			<small> <a target="_blank" href="https://apps.twitter.com/app/new">Click here to create new twitter application</a> to get your Consumer Key, Consumer Secret, Access Token and Access Token Secret.</small>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'consumer_secret' ); ?>">
				<?php echo __( 'Consumer Secret', 'qaet' ) . ':'; ?>
			</label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'consumer_secret' ); ?>" name="<?php echo $this->get_field_name( 'consumer_secret' ); ?>" type="text" value="<?php if(isset($consumer_secret)){echo esc_attr( $consumer_secret );} ?>" />
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'oauth_access_token' ); ?>">
				<?php echo __( 'Access Token', 'qaet' ) . ':'; ?>
			</label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'oauth_access_token' ); ?>" name="<?php echo $this->get_field_name( 'oauth_access_token' ); ?>" type="text" value="<?php if(isset($oauth_access_token)){echo esc_attr( $oauth_access_token );} ?>" />
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'oauth_access_token_secret' ); ?>">
				<?php echo __( 'Access Token Secret', 'qaet' ) . ':'; ?>
			</label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'oauth_access_token_secret' ); ?>" name="<?php echo $this->get_field_name( 'oauth_access_token_secret' ); ?>" type="text" value="<?php if(isset($oauth_access_token_secret)){echo esc_attr( $oauth_access_token_secret );} ?>" />
		</p>
		<?php
	}

	/**
	 * Sanitize widget form values as they are saved.
	 *
	 * @see WP_Widget::update()
	 *
	 * @param array $new_instance Values just sent to be saved.
	 * @param array $old_instance Previously saved values from database.
	 *
	 * @return array Updated safe values to be saved.
	 */
	public function update( $new_instance, $old_instance ) {
		$instance = array();

		$instance['title'] = ( ! empty( $new_instance['title'] ) ) ? strip_tags( $new_instance['title'] ) : '';
		$instance['twitter_username'] = ( ! empty( $new_instance['twitter_username'] ) ) ? strip_tags( $new_instance['twitter_username'] ) : '';
		$instance['update_count'] = ( ! empty( $new_instance['update_count'] ) ) ? strip_tags( $new_instance['update_count'] ) : '';
		$instance['oauth_access_token'] = ( ! empty( $new_instance['oauth_access_token'] ) ) ? strip_tags( $new_instance['oauth_access_token'] ) : '';
		$instance['oauth_access_token_secret'] = ( ! empty( $new_instance['oauth_access_token_secret'] ) ) ? strip_tags( $new_instance['oauth_access_token_secret'] ) : '';
		$instance['consumer_key'] = ( ! empty( $new_instance['consumer_key'] ) ) ? strip_tags( $new_instance['consumer_key'] ) : '';
		$instance['consumer_secret'] = ( ! empty( $new_instance['consumer_secret'] ) ) ? strip_tags( $new_instance['consumer_secret'] ) : '';

		return $instance;
	}


	public function get_tweets( $username, $limit, $oauth_access_token, $oauth_access_token_secret,  $consumer_key, $consumer_secret ) {

		$cacheKey = $username . ' -recent-' . $limit . '-tweets';

		$cached = get_transient( $cacheKey );
		if ( false !== $cached ) {
			return $cached;
		}

		include( plugin_dir_path( __FILE__ ) . 'twitteroauth/twitteroauth.php' );

		$twitterOAuth = new TwitterOAuth( $consumer_key, $consumer_secret, $oauth_access_token, $oauth_access_token_secret );

		$tweets = $twitterOAuth->get( 'statuses/user_timeline', array( 'screen_name' => $username, 'count' => $limit, 'exclude_replies' => true ) );

		set_transient( $cacheKey, $tweets, 3600 );

		return $tweets;
	}

	/**
	 * To make the tweet time more user-friendly
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
		$hour = $minute * 60;
		$day = $hour * 24;
		$week = $day * 7;

		if ( is_numeric( $difference ) && $difference > 0 ) {

			// If less than 3 seconds.
			if ( $difference < 3 ) {
				return __( 'right now', 'qaet' );
			}

			// If less than minute.
			if ( $difference < $minute ) {
				return floor( $difference ) . ' ' . __( 'seconds ago', 'qaet' );;
			}

			// If less than 2 minutes.
			if ( $difference < $minute * 2 ) {
				return __( 'about 1 minute ago', 'qaet' );
			}

			// If less than hour.
			if ( $difference < $hour ) {
				return floor( $difference / $minute ) . ' ' . __( 'minutes ago', 'qaet' );
			}

			// If less than 2 hours.
			if ( $difference < $hour * 2 ) {
				return __( 'about 1 hour ago', 'qaet' );
			}

			// If less than day.
			if ( $difference < $day ) {
				return floor( $difference / $hour ) . ' ' . __( 'hours ago', 'qaet' );
			}

			// If more than day, but less than 2 days.
			if ( $difference > $day && $difference < $day * 2 ) {
				return __( 'yesterday', 'qaet' );;
			}

			// If less than year.
			if ( $difference < $day * 365 ) {
				return floor( $difference / $day ) . ' ' . __( 'days ago', 'qaet' );
			}

			// Else return more than a year.
			return __( 'over a year ago', 'qaet' );
		}
	}

} // class Quick_And_Easy_Tweets



if ( ! function_exists( 'register_quick_and_easy_tweets_widget' ) ) {
	/**
	 * Register Quick and Easy Tweets Widget
	 */
	function register_quick_and_easy_tweets_widget() {
		register_widget( 'Quick_And_Easy_Tweets' );
	}

	add_action( 'widgets_init', 'register_quick_and_easy_tweets_widget' );
}
