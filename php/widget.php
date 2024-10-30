<?php
/**
 * Custom widget for WordPress to display the last lottoland data
 *
 * @version  /03/27/2014
 */
! defined( 'ABSPATH' ) and exit;


add_action(
	'widgets_init',
	create_function( '', 'register_widget( "Gewinnzahlen_Widget" );' )
);

class Gewinnzahlen_Widget extends WP_Widget {

	var $start_time;
	var $protocol;

	/**
	 * Url of the json
	 *
	 * @var string
	 */
	public static $jsonurl = 'https://media.lottoland.com/api/drawings';

	/**
	 * Url for the affiliate link
	 *
	 * @var string
	 */
	public static $affiliate_link = 'https://www.lottoland.co.uk/lottery-results';

	/**
	 * Define the allowed lotterings
	 * Would use for parse with content of json
	 *
	 * @var array
	 */
	public $validate_earnings = array(
		'german6aus49',
		'powerBall',
		'megaMillions',
		'euroJackpot',
		'euroMillions'
	);

	/**
	 * Time window for transient cache in seconds
	 *
	 * $type integer
	 */
	public static $expiration_time = '';

	/**
	 * Specifies the classname and description, instantiates the widget,
	 * loads localization files, and includes necessary stylesheets and JavaScript.
	 */
	public function __construct() {

		// Define the transient expiration time window
		self::$expiration_time = 60 * 60 * 2; //in seconds

		// load plugin text domain
		add_action( 'init', array( $this, 'widget_textdomain' ) );

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );

		// delete transients on deactivation
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

		$widget_ops = array(
			'classname'   => 'earningsfigures',
			'description' => __( 'Widget for the last earnings figures from Lottoland for WordPress.', 'lottololand' )
		);
		parent::__construct(
			get_class( $this ), // ID
			__( 'Earnings Figures Widget', 'lottololand' ), // Name
			$widget_ops
		);

		// Flush cache on the theme switch
		add_action( 'switch_theme', array( $this, 'flush_widget_cache' ) );
	}

	/**
	 * Get the formated date of the last figures
	 *
	 * @param $type
	 * @param $data
	 *
	 * @return int
	 */
	public function getDrawingDate( $type, $data ) {

		$date = new DateTime();
		$date->setTimezone( new DateTimeZone( "Europe/Berlin" ) );
		$date->setDate(
			$data->{$type}->{'last'}->{'date'}->{'year'},
			$data->{$type}->{'last'}->{'date'}->{'month'},
			$data->{$type}->{'last'}->{'date'}->{'day'}
		);

		$date = strtotime(
			$data->{$type}->{'last'}->{'date'}->{'month'} . '/' .
			$data->{$type}->{'last'}->{'date'}->{'day'} . '-' .
			$data->{$type}->{'last'}->{'date'}->{'year'}
		);

		return date_i18n( get_option( 'date_format' ), $date );
		/*
		return $data->{$type}->{'last'}->{'date'}->{'dayOfWeek'}
			. ', ' . $data->{$type}->{'last'}->{'date'}->{'day'}
			. '. ' . translate( $date->format( 'M' ) );
		*/
	}

	/**
	 * Get data, Decodes a JSON string
	 * Validate the json object and get only values, there are inside the setting var 'validate_earnings'
	 *
	 * @return array|mixed
	 */
	public function get_lotterings() {

		$json       = file_get_contents( self::$jsonurl, 0, NULL, NULL );
		$lotterings = json_decode( utf8_encode( $json ), TRUE );

		$unset_queue = '';
		// Difference, all keys inside the object and validate to the settings
		foreach ( $lotterings as $name => $data ) {

			if ( ! in_array( $name, $this->validate_earnings ) ) {
				$unset_queue[ ] = $name;
			}

		}

		// unset the wrong keys
		foreach ( $unset_queue as $index ) {
			unset( $lotterings[ $index ] );
		}

		return $lotterings;
	}

	/**
	 * Format the lotterings strings from Json to readable string
	 *
	 * @param $string
	 *
	 * @return mixed
	 */
	public function format_lotterings( $string ) {

		switch ( $string ) {
			case 'german6aus49':
				$string = __( 'LOTTO 6 aus 49', 'lottololand' );
				break;
			case 'powerBall':
				$string = __( 'PowerBall', 'lottololand' );
				break;
			case 'megaMillions':
				$string = __( 'MegaMillions', 'lottololand' );
				break;
			case 'euroJackpot':
				$string = __( 'Eurojackpot', 'lottololand' );
				break;
			case 'euroMillions':
				$string = __( 'Euromillionen', 'lottololand' );
				break;
			default:
				$string = esc_attr( $string );
		}

		return $string;
	}

	/**
	 * Return data, dependet from type
	 *
	 * @param $type
	 *
	 * @return mixed
	 */
	public function get_drawings( $type ) {

		$json        = file_get_contents( self::$jsonurl, 0, NULL, NULL );
		$json_output = json_decode( $json );

		//*
		// For debugging purpose
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && function_exists( 'debug_to_console' ) ) {
			debug_to_console( $json_output->{$type}->{'last'} );
		}
		/**/

		// Set winners value
		$winners = '';
		// Get data
		$data[ 'date' ] = $this->getDrawingDate( $type, $json_output );
		switch ( $type ) {
			case 'irishLotto':
				$data[ 'title' ]            = sprintf( __( 'Lotto earnings from %s.', 'lottololand' ), $data[ 'date' ] );
				$data[ 'numbers' ]          = $json_output->{$type}->{'last'}->{'numbers'};
				$data[ 'numbers_add' ][ 0 ] = $json_output->{$type}->{'last'}->{'bonus'}[ 0 ];
				$data[ 'numbers_add_name' ] = __( 'Bonus', 'lottololand' );
				$data[ 'img' ]              = 'il';
				if ( isset( $json_output->{$type}->{'last'}->{'Winners'} )
					 && isset( $json_output->{$type}->{'last'}->{'lottoPlus1Winners'} )
					 && isset( $json_output->{$type}->{'last'}->{'lottoPlus2Winners'} )
				) {
					$winners = $json_output->{$type}->{'last'}->{'Winners'}
							   + $json_output->{$type}->{'last'}->{'lottoPlus1Winners'}
							   + $json_output->{$type}->{'last'}->{'lottoPlus2Winners'};
				}
				$data[ 'winners' ] = $winners;
				$data[ 'link' ]    = 'https://www.lottoland.com/lottozahlen?utm_medium=widget_results&utm_content=textlink&utm_campaign=L649';
				$data[ 'lottery' ] = __( 'Irish Lottery', 'lottololand' );
				break;

			case 'german6aus49':
				$data[ 'title' ]            = sprintf( __( 'Lotto earnings from %s.', 'lottololand' ), $data[ 'date' ] );
				$data[ 'numbers' ]          = $json_output->{$type}->{'last'}->{'numbers'};
				$data[ 'numbers_add' ][ 0 ] = $json_output->{$type}->{'last'}->{'superzahl'};
				$data[ 'numbers_add_name' ] = __( 'super speed', 'lottololand' );
				$data[ 'img' ]              = '649';
				if ( isset( $json_output->{$type}->{'last'}->{'Winners'} )
					 && isset( $json_output->{$type}->{'last'}->{'spiel77Winners'} )
					 && isset( $json_output->{$type}->{'last'}->{'super6Winners'} )
				) {
					$winners = $json_output->{$type}->{'last'}->{'Winners'}
							   + $json_output->{$type}->{'last'}->{'spiel77Winners'}
							   + $json_output->{$type}->{'last'}->{'super6Winners'};
				}
				$data[ 'winners' ] = $winners;
				$data[ 'link' ]    = 'https://www.lottoland.com/lottozahlen?utm_medium=widget_results&utm_content=textlink&utm_campaign=L649';
				$data[ 'lottery' ] = __( 'LOTTO 6 aus 49', 'lottololand' );
				break;

			case 'powerBall':
				$data[ 'title' ]            = sprintf( __( 'Earnings figures from %s.', 'lottololand' ), $data[ 'date' ] );
				$data[ 'numbers' ]          = $json_output->{$type}->{'last'}->{'numbers'};
				$data[ 'numbers_add' ]      = $json_output->{$type}->{'last'}->{'powerballs'};
				$data[ 'numbers_add_name' ] = __( 'PowerBall', 'lottololand' );
				$data[ 'img' ]              = 'pb';
				if ( isset( $json_output->{$type}->{'last'}->{'Winners'} ) ) {
					$winners = $json_output->{$type}->{'last'}->{'Winners'};
				}
				$data[ 'winners' ] = $winners;
				$data[ 'link' ]    = 'https://www.lottoland.com/powerball-lottozahlen?utm_medium=widget_results&utm_content=textlink&utm_campaign=PB';
				$data[ 'lottery' ] = __( 'PowerBall', 'lottololand' );
				break;

			case 'megaMillions':
				$data[ 'title' ]            = sprintf( __( 'Earnings figures from %s.', 'lottololand' ), $data[ 'date' ] );
				$data[ 'numbers' ]          = $json_output->{$type}->{'last'}->{'numbers'};
				$data[ 'numbers_add' ]      = $json_output->{$type}->{'last'}->{'megaballs'};
				$data[ 'numbers_add_name' ] = __( 'MegaBall &nbsp;', 'lottololand' );
				$data[ 'img' ]              = 'mm';
				if ( isset( $json_output->{$type}->{'last'}->{'Winners'} ) ) {
					$winners = $json_output->{$type}->{'last'}->{'Winners'};
				}
				$data[ 'winners' ]   = $winners;
				$data[ 'link' ]      = 'https://www.lottoland.com/megamillions-lottozahlen?utm_medium=widget_results&utm_content=textlink&utm_campaign=MM';
				$data[ 'lottery' ]   = __( 'MegaMillions', 'lottololand' );
				$data[ 'megaplier' ] = 'x' . $json_output->{$type}->{'last'}->{'megaplier'};
				break;

			case 'euroJackpot':
				$data[ 'title' ]            = sprintf( __( 'Earnings figures from %s.', 'lottololand' ), $data[ 'date' ] );
				$data[ 'numbers' ]          = $json_output->{$type}->{'last'}->{'numbers'};
				$data[ 'numbers_add' ]      = $json_output->{$type}->{'last'}->{'euroNumbers'};
				$data[ 'numbers_add_name' ] = __( 'Eurozahlen', 'lottololand' );
				$data[ 'img' ]              = 'ej';
				if ( isset( $json_output->{$type}->{'last'}->{'Winners'} ) ) {
					$winners = $json_output->{$type}->{'last'}->{'Winners'};
				}
				$data[ 'winners' ] = $winners;
				$data[ 'link' ]    = 'https://www.lottoland.com/eurojackpot-lottozahlen?utm_medium=widget_results&utm_content=textlink&utm_campaign=EJ';
				$data[ 'lottery' ] = __( 'EuroJackpot', 'lottololand' );
				break;

			case 'euroMillions':
				$data[ 'title' ]            = sprintf( __( 'Earnings figures from %s.', 'lottololand' ), $data[ 'date' ] );
				$data[ 'numbers' ]          = $json_output->{$type}->{'last'}->{'numbers'};
				$data[ 'numbers_add' ]      = $json_output->{$type}->{'last'}->{'stars'};
				$data[ 'numbers_add_name' ] = __( 'Sternzahlen', 'lottololand' );
				$data[ 'img' ]              = 'em';
				if ( isset( $json_output->{$type}->{'last'}->{'Winners'} ) ) {
					$winners = $json_output->{$type}->{'last'}->{'Winners'};
				}
				$data[ 'winners' ] = $winners;
				$data[ 'link' ]    = 'https://www.lottoland.com/euromillions-gewinnzahlen?utm_medium=widget_results&utm_content=textlink&utm_campaign=EM';
				$data[ 'lottery' ] = __( 'EuroMillionen', 'lottololand' );
				break;

			default :
				break;
		}

		return $data;
	}

	/**
	 * Enqueue stylesheet to format inside the widget
	 *
	 * @return  void
	 */
	public function enqueue_styles() {

		// Get settings
		$settings = $this->get_settings();

		// Check settings
		foreach ( $settings as $instance => $data ) {
			// If is not sett
			if ( ! isset( $data[ 'stylesheet' ] ) ) {
				$data[ 'stylesheet' ] = 1;
				// If is inactive, return
			}
			elseif ( $data[ 'stylesheet' ] === 0 ) {
				return FALSE;
			}
		}

		wp_register_style(
			'lottololand',
			str_replace( '/php', '', plugin_dir_url( __FILE__ ) ) . 'css/widget_style.css',
			array(),
			'22/03/2014',
			'screen'
		);

		wp_enqueue_style(
			'lottololand'
		);

	}

	/**
	 * Outputs the content of the widget.
	 *
	 * @param array $args     The array of form elements
	 * @param array $instance The current instance of the widget
	 */
	public function widget( $args, $instance ) {

		$before_widget = $after_widget = $before_title = $after_title = '';

		extract( $args );

		// get title
		$title = apply_filters( 'widget_title', $instance[ 'title' ] );

		echo $before_widget;

		echo $before_title;
		if ( ! empty( $title ) ) {
			echo $title . '<br />';
		}

		$type = $instance[ 'lotterie' ];
		/*
		if ( has_tag( 'PowerBall' ) || is_category( 'PowerBall' ) ) {
			$type = 'powerBall';
		} elseif ( has_tag( 'EuroMillionen' ) || is_category( 'EuroMillionen' ) ) {
			$type = 'euroMillions';
		} elseif ( has_tag( '6aus49' ) || is_category( 'LOTTO 6aus49' ) ) {
			$type = 'german6aus49';
		} elseif ( has_tag( 'EuroJackpot' ) || is_category( 'EuroJackpot' ) ) {
			$type = 'euroJackpot';
		} elseif ( has_tag( 'MegaMillions' ) || is_category( 'MegaMillions' ) ) {
			$type = 'megaMillions';
		} else {
			$type = 'german6aus49';
		}
		*/

		$transient_name  = 'zahlen' . $type;
		$transient_value = get_transient( $transient_name );

		// For Debugging set cache to false
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$transient_value = FALSE;
		}

		if ( FALSE !== $transient_value ) {

			$lotto = $transient_value;

			if ( ! $lotto[ 'numbers' ] ) {
				delete_transient( $transient_name );
			}

			$lotto = $this->get_drawings( $type );
			if ( '' != $lotto[ 'date' ] ) {
				set_transient( $transient_name, $lotto, self::$expiration_time );
			}

		}
		else {

			$lotto = $this->get_drawings( $type );

			if ( empty( $lotto ) ) {
				_e( 'No data available.', 'lottololand' );
			}

			if ( '' != $lotto[ 'numbers' ] ) {
				set_transient( $transient_name, $lotto, self::$expiration_time );
			}

		}

		echo $lotto[ 'title' ];
		echo $after_title;
		?>
		<div id="earningsfigures">
			<div class="quoten">
				<?php
				$img_path = str_replace( '/php', '', plugin_dir_path( __FILE__ ) ) . 'images/ll_logo_' . $lotto[ 'img' ] . '_45x45.png';
				$img_url  = str_replace( '/php', '', plugin_dir_url( __FILE__ ) ) . 'images/ll_logo_' . $lotto[ 'img' ] . '_45x45.png';
				if ( file_exists( $img_path ) ) {
					?>
					<img src="<?php echo $img_url; ?>" alt="<?php echo $lotto[ 'lottery' ]; ?>" width="45" height="45" class="woo-image" />
				<?php } ?>
				<span class="lottery">
					<a href="<?php echo $lotto[ 'link' ]; ?>" title="<?php echo $lotto[ 'title' ]; ?>" target="_blank"><?php echo $lotto[ 'lottery' ]; ?></a>
				</span><br />
				<span class="meta">
					<a href="<?php echo $lotto[ 'link' ]; ?>" title="<?php echo $lotto[ 'title' ]; ?>" target="_blank"><?php _e( '» to the earnings rates', 'lottololand' ); ?></a>
				</span>
			</div>
			<div class="numbers-container">
				<div class="lotto_numbers">
					<div class="label"><?php _e( 'Winning numbers', 'lottololand' ); ?> </div>
					<?php
					for ( $i = 0; $i < count( $lotto[ 'numbers' ] ); $i ++ ) {
						echo '<span class="lotto_number_ball">' . $lotto[ 'numbers' ][ $i ] . '</span>';
					}
					?>
				</div>
				<div class="lotto_numbers lotto_numbers_<?php echo $lotto[ 'img' ]; ?>">
					<div class="label"><?php echo $lotto[ 'numbers_add_name' ]; ?> </div>
					<?php
					for ( $i = 0; $i < count( $lotto[ 'numbers_add' ] ); $i ++ ) {
						echo '<span class="lotto_number_ball lotto_number_ball_extra lotto_number_ball_' . $lotto[ 'img' ] . '">' . $lotto[ 'numbers_add' ][ $i ] . '</span>';
					}
					?>
				</div>
				<?php
				if ( 'mm' === $lotto[ 'img' ] && isset( $lotto[ 'megaplier' ] ) ) {
					?>
					<div class="lotto_numbers lotto_numbers_mp">
						<span class="label"><?php _e( 'MegaPlier', 'lottololand' ); ?></span>
						<span class="lotto_number_ball lotto_number_ball_mp"><?php echo $lotto[ 'megaplier' ]; ?></span>
					</div>
				<?php
				}
				?>
			</div>
			<div class="ll-likebox">
				<span class="like-text"><?php if ( 0 != $lotto[ 'winners' ] ) {
						echo number_format( $lotto[ 'winners' ], 0, '', '.' ) . ' ' . __( 'Winner', 'lottololand' );
					} ?></span>

				<div class="fb-like" data-href="https://www.facebook.com/lottoland" data-send="false" data-layout="button_count" data-width="292" data-show_faces="false"></div>
				<div class="fix"></div>
			</div>
			<div style="text-align: right;">
				<?php echo '<a href="' . __( 'https://www.lottoland.co.uk/lottery-results', 'lottololand' ) . '" target="_blank">'
						   . sprintf( __( 'Powered by %s', 'lottololand' ), '<img src="' . str_replace( '/php', '', plugin_dir_url( __FILE__ ) ) . 'images/LL_68x25px.png" alt="Lottoland" />' )
						   . '</a>';
				?>
			</div>
			<div class="fix"></div>
		</div>
		<?php

		echo $after_widget;
	}

	/**
	 * Processes the widget's options to be saved.
	 *
	 * @param array $new_instance The new instance of values to be generated via the update.
	 * @param array $old_instance The previous instance of values before the update.
	 *
	 * @return array
	 */
	public function update( $new_instance, $old_instance ) {

		// delete all transients on update
		foreach ( $this->get_lotterings() as $name => $data ) {
			$transient_name = 'zahlen' . $name;
			delete_transient( $transient_name );
		}

		$instance[ 'title' ]      = strip_tags( stripslashes( $new_instance[ 'title' ] ) );
		$instance[ 'lotterie' ]   = stripslashes( $new_instance[ 'lotterie' ] );
		$instance[ 'stylesheet' ] = (int) $new_instance[ 'stylesheet' ];

		return $instance;
	} // end widget

	/**
	 * Generates the administration form for the widget.
	 *
	 * @param array $instance The array of keys and values for the widget.
	 *
	 * @return string|void
	 */
	public function form( $instance ) {

		// Get title
		$title = array_key_exists( 'title', $instance ) ? $instance[ 'title' ] : __( 'Current winning numbers', 'lottololand' );
		// Get lotterie
		$lotterie = array_key_exists( 'lotterie', $instance ) ? $instance[ 'lotterie' ] : 'german6aus49';
		// Get stylesheet settings
		$stylesheet = array_key_exists( 'stylesheet', $instance ) ? (int) $instance[ 'stylesheet' ] : 1;
		?>

		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:', 'lottololand' ); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>"
				   name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo $title; ?>" />
		</p>

		<p>
			<label for="<?php echo $this->get_field_id( 'lotterie' ); ?>"><?php _e( 'Lotteries:', 'lottololand' ) ?></label>
			<select class="widefat" id="<?php echo $this->get_field_id( 'lotterie' ); ?>" name="<?php echo $this->get_field_name( 'lotterie' ); ?>">
				<?php
				foreach ( $this->get_lotterings() as $name => $data ) {
					?>
					<option value="<?php echo esc_attr( $name ) ?>" <?php selected( $name, $lotterie ) ?>><?php echo $this->format_lotterings( $name ); ?></option>
				<?php } ?>
			</select>
		</p>

		<p>
			<label for="<?php echo $this->get_field_id( 'stylesheet' ); ?>">
				<input id="<?php echo $this->get_field_id( 'stylesheet' ); ?>" name="<?php echo $this->get_field_name( 'stylesheet' ); ?>" type="checkbox" value="1" <?php checked( $stylesheet, 1 ); ?> />
				<?php _e( 'Include stylesheet?', 'lottololand' ); ?></label>
		</p>

		<p style="text-align: right;">
			<?php echo '<a href="' . __( 'https://www.lottoland.co.uk/lottery-results', 'lottololand' ) . '" target="_blank">'
					   . sprintf( __( 'Powered by %s', 'lottololand' ), '<img src="' . str_replace( '/php', '', plugin_dir_url( __FILE__ ) ) . 'images/LL_68x25px.png" alt="Lottoland" />' )
					   . '</a>';
			?>
		</p>
	<?php
	}

	/**
	 * Loads the Widget's text domain for localization and translation.
	 */
	public function widget_textdomain() {

		$path = str_replace( 'php', '', dirname( plugin_basename( __FILE__ ) ) ) . 'languages';
		load_plugin_textdomain( 'lottololand', FALSE, $path );
	} // end widget_textdomain

	/**
	 * Fired when the plugin is deactivated.
	 *
	 * @param boolean $network_wide
	 *        True if WPMU superadmin uses "Network Activate" action,
	 *        False if WPMU is disabled or plugin is activated on an individual blog
	 */
	public function deactivate( $network_wide ) {

		// delete all transients
		foreach ( $this->get_lotterings() as $name => $data ) {
			$transient_name = 'zahlen' . $name;
			delete_transient( $transient_name );
		}

		delete_option( 'widget_gewinnzahlen-widget' );

		// Flush widget cache
		array( $this, 'flush_widget_cache' );
	} // end deactivate

} // end class
