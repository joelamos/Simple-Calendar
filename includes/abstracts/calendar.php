<?php
/**
 * Calendar
 *
 * @package SimpleCalendar/Calendars
 */
namespace SimpleCalendar\Abstracts;

use Carbon\Carbon;
use SimpleCalendar\Events\Event;
use SimpleCalendar\Events\Event_Builder;
use SimpleCalendar\Events\Events;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Calendar.
 *
 * Displays events from events feed.
 */
abstract class Calendar {

	/**
	 * Calendar Id.
	 *
	 * @access public
	 * @var int
	 */
	public $id = 0;

	/**
	 * Calendar post object.
	 *
	 * @access public
	 * @var \WP_Post
	 */
	public $post = null;

	/**
	 * Calendar Type.
	 *
	 * @access public
	 * @var string
	 */
	public $type = '';

	/**
	 * Calendar Name.
	 *
	 * @access public
	 * @var string
	 */
	public $name = '';

	/**
	 * Feed type.
	 *
	 * @access public
	 * @var string
	 */
	public $feed = '';

	/**
	 * Calendar start.
	 *
	 * @access public
	 * @var int
	 */
	public $start = 0;

	/**
	 * Calendar end.
	 *
	 * @access public
	 * @var int
	 */
	public $end = 0;

	/**
	 * Static calendar.
	 *
	 * @access public
	 * @var bool
	 */
	public $static = false;

	/**
	 * Today.
	 *
	 * @access public
	 * @var int
	 */
	public $today = 0;

	/**
	 * Time now.
	 *
	 * @access public
	 * @var int
	 */
	public $now = 0;

	/**
	 * Timezone offset.
	 *
	 * @access public
	 * @var int
	 */
	public $offset = 0;

	/**
	 * Timezone
	 *
	 * @access public
	 * @var string
	 */
	public $timezone = 'UTC';

	/**
	 * Site timezone.
	 *
	 * @access public
	 * @var string
	 */
	public $site_timezone = 'UTC';

	/**
	 * Date format.
	 *
	 * @access public
	 * @var string
	 */
	public $date_format = '';

	/**
	 * Time format.
	 *
	 * @access public
	 * @var string
	 */
	public $time_format = '';

	/**
	 * Date-time separator.
	 *
	 * @access public
	 * @var string
	 */
	public $datetime_separator = '@';

	/**
	 * First day of the week.
	 *
	 * @access public
	 * @var int
	 */
	public $week_starts = 0;

	/**
	 * Events to display.
	 *
	 * @access public
	 * @var array
	 */
	public $events = array();

	/**
	 * Errors.
	 *
	 * @access public
	 * @var array
	 */
	protected $errors = array();

	/**
	 * Earliest event.
	 *
	 * @access public
	 * @var int
	 */
	public $earliest_event = 0;

	/**
	 * Latest event.
	 *
	 * @access public
	 * @var int
	 */
	public $latest_event = 0;

	/**
	 * Event builder content.
	 *
	 * @access public
	 * @var string
	 */
	public $events_template = '';

	/**
	 * Calendar Views.
	 *
	 * The calendar available views.
	 *
	 * @access public
	 * @var array
	 */
	public $views = array();

	/**
	 * Calendar View.
	 *
	 * The current view.
	 *
	 * @access public
	 * @var Calendar_View
	 */
	public $view = null;

	/**
	 * Calendar settings.
	 *
	 * @access protected
	 * @var array
	 */
	protected $settings = array();

	/**
	 * Constructor.
	 *
	 * @since 3.0.0
	 *
	 * @param int|object|\WP_Post|Calendar $calendar
	 * @param string $view
	 */
	public function __construct( $calendar, $view = '' ) {

		// Set the post object.
		$this->set_post_object( $calendar );

		if ( ! is_null( $this->post ) ) {

			// Set calendar type and events source.
			$this->set_taxonomies();

			// Set calendar default datetime properties.
			$this->set_timezone();
			$this->set_start_of_week();
			$this->set_static();

			// Set calendar start.
			$this->set_start();

			// Set the events template.
			$this->set_events_template();

			// Get events source data.
			$feed = simcal_get_feed( $this );
			if ( $feed instanceof Feed ) {
				if ( ! empty( $feed->events ) ) {
					if ( is_array( $feed->events ) ) {
						$this->set_events( $feed->events );
						if ( 'use_calendar' == get_post_meta( $this->id, '_feed_timezone_setting', true ) ) {
							$this->timezone = $feed->timezone;
							$this->set_start( $feed->timezone );
						}
					} elseif ( is_string( $feed->events ) ) {
						$this->errors[] = $feed->events;
					}
				}
			}

			// Set general purpose timestamps.
			$now = Carbon::now( $this->timezone );
			$this->now    = $now->getTimestamp();
			$this->today  = $now->startOfDay()->getTimestamp();
			$this->offset = $now->getOffset();

			// Set date time formatting.
			$this->set_date_format();
			$this->set_time_format();
			$this->set_datetime_separator();

			// Set earliest and latest event timestamps.
			if ( $this->events && is_array( $this->events ) ) {
				$this->earliest_event = intval( current( array_keys( $this->events ) ) );
				$this->latest_event   = intval( key( array_slice( $this->events, -1, 1, true ) ) );
			}

			// Set calendar end.
			$this->set_end();

			// Set view.
			if ( ! $view ) {

				$calendar_view = get_post_meta( $this->id, '_calendar_view', true );
				$calendar_view = isset( $calendar_view[ $this->type ] ) ? $calendar_view[ $this->type ] : '';

				$view = esc_attr( $calendar_view );
			}
		}

		// Get view.
		$this->view = $this->get_view( $view );
	}

	/**
	 * Overloading __isset function with post meta.
	 *
	 * @since  3.0.0
	 *
	 * @param  mixed $key Post meta key.
	 *
	 * @return bool
	 */
	public function __isset( $key ) {
		return metadata_exists( 'post', $this->id, '_' . $key );
	}

	/**
	 * Overloading __get function with post meta.
	 *
	 * @since  3.0.0
	 *
	 * @param  string $key Post meta key.
	 *
	 * @return mixed
	 */
	public function __get( $key ) {
		$value = get_post_meta( $this->id, '_' . $key, true );
		if ( ! empty( $value ) ) {
			$this->$key = $value;
		}
		return $value;
	}

	/**
	 * Set post object and id.
	 *
	 * @since 3.0.0
	 *
	 * @param int|object|\WP_Post|Calendar $calendar
	 */
	public function set_post_object( $calendar ) {
		if ( is_numeric( $calendar ) ) {
			$this->id   = absint( $calendar );
			$this->post = get_post( $this->id );
		} elseif ( $calendar instanceof Calendar ) {
			$this->id   = absint( $calendar->id );
			$this->post = $calendar->post;
		} elseif ( $calendar instanceof \WP_Post ) {
			$this->id   = absint( $calendar->ID );
			$this->post = $calendar;
		} elseif ( isset( $calendar->id ) && isset( $calendar->post ) ) {
			$this->id   = $calendar->id;
			$this->post = $calendar->post;
		}
	}

	/**
	 * Return the calendar title.
	 *
	 * @since  3.0.0
	 *
	 * @return string
	 */
	public function get_title() {
		$title = isset( $this->post->post_title ) ? $this->post->post_title : '';
		return apply_filters( 'simcal_calendar_title', $title );
	}

	/**
	 * Get the calendar post data.
	 *
	 * @since  3.0.0
	 *
	 * @return \WP_Post
	 */
	public function get_post_data() {
		return $this->post;
	}

	/**
	 * Set taxonomies.
	 *
	 * @since 3.0.0
	 * @access protected
	 */
	protected function set_taxonomies() {
		// Set calendar type.
		if ( $type = wp_get_object_terms( $this->id, 'calendar_type' ) ) {
			$this->type = sanitize_title( current( $type )->name );
		} else {
			$this->type = apply_filters( 'simcal_calendar_default_type', 'default-calendar' );
		}
		// Set feed type.
		if ( $feed_type = wp_get_object_terms( $this->id, 'calendar_feed' ) ) {
			$this->feed = sanitize_title( current( $feed_type )->name );
		} else {
			$this->feed = apply_filters( 'simcal_calendar_default_feed', 'google' );
		}
	}

	/**
	 * Get events.
	 *
	 * @since  3.0.0
	 *
	 * @return Events
	 */
	public function get_events() {
		return new Events( $this->events, $this->timezone );
	}

	/**
	 * Set events.
	 *
	 * @since 3.0.0
	 *
	 * @param array $array
	 */
	public function set_events( array $array ) {

		$events = array();

		if ( ! empty( $array ) ) {
			foreach ( $array as $tz => $e ) {
				foreach ( $e as $event ) {
					$events[ $tz ][] = $event instanceof Event ? $event : new Event( $event );
				}
			}
		}

		$this->events = $events;
	}

	/**
	 * Get the event builder template.
	 *
	 * @since  3.0.0
	 *
	 * @param  string $template
	 *
	 * @return string
	 */
	public function set_events_template( $template = '' ) {
		if ( empty( $template ) ) {
			$template = isset( $this->post->post_content ) ? $this->post->post_content : '';
		}

		// TODO: Removed wpautop() call.
		$this->events_template =  wp_kses_post( trim( $template ) );
	}

	/**
	 * Set the timezone.
	 *
	 * @since 3.0.0
	 *
	 * @param string $tz Timezone.
	 */
	public function set_timezone( $tz = '' ) {

		$site_tz = esc_attr( simcal_get_wp_timezone() );

		if ( empty( $tz ) ) {

			$timezone_setting = get_post_meta( $this->id, '_feed_timezone_setting', true );

			if ( 'use_site' == $timezone_setting ) {
				$tz = $site_tz;
			} elseif ( 'use_custom' == $timezone_setting ) {
				$custom_timezone = esc_attr( get_post_meta( $this->id, '_feed_timezone', true ) );
				// One may be using a non standard timezone in GMT (UTC) offset format.
				if ( ( strpos( $custom_timezone, 'UTC+' ) === 0 ) || ( strpos( $custom_timezone, 'UTC-' ) === 0 ) ) {
					$tz = simcal_get_timezone_from_gmt_offset( substr( $custom_timezone, 3 ) );
				} else {
					$tz = ! empty( $custom_timezone ) ? $custom_timezone : 'UTC';
				}
			}

			$this->timezone = empty( $tz ) ? 'UTC' : $tz;
			return;
		}

		$this->site_timezone = $site_tz;
		$this->timezone = simcal_esc_timezone( $tz, $this->timezone );
	}

	/**
	 * Set date format.
	 *
	 * @since 3.0.0
	 *
	 * @param string $format PHP datetime format.
	 */
	public function set_date_format( $format = '' ) {

		$date_format_custom = $date_format_default = $format;

		if ( empty( $date_format_custom ) ) {

			$date_format_option  = esc_attr( get_post_meta( $this->id, '_calendar_date_format_setting', true ) );
			$date_format_default = esc_attr( get_option( 'date_format' ) );
			$date_format_custom  = '';

			if ( 'use_custom' == $date_format_option ) {
				$date_format_custom = esc_attr( get_post_meta( $this->id, '_calendar_date_format', true ) );
			} elseif ( 'use_custom_php' == $date_format_option ) {
				$date_format_custom = esc_attr( get_post_meta( $this->id, '_calendar_date_format_php', true ) );
			}
		}

		$this->date_format = $date_format_custom ? $date_format_custom : $date_format_default;
	}

	/**
	 * Set time format.
	 *
	 * @since 3.0.0
	 *
	 * @param string $format PHP datetime format.
	 */
	public function set_time_format( $format = '' ) {

		$time_format_custom = $time_format_default = $format;

		if ( empty( $time_format_custom ) ) {

			$time_format_option  = esc_attr( get_post_meta( $this->id, '_calendar_time_format_setting', true ) );
			$time_format_default = esc_attr( get_option( 'time_format' ) );
			$time_format_custom  = '';

			if ( 'use_custom' == $time_format_option ) {
				$time_format_custom = esc_attr( get_post_meta( $this->id, '_calendar_time_format', true ) );
			} elseif ( 'use_custom_php' == $time_format_option ) {
				$time_format_custom = esc_attr( get_post_meta( $this->id, '_calendar_time_format_php', true ) );
			}
		}

		$this->time_format = $time_format_custom ? $time_format_custom : $time_format_default;
	}

	/**
	 * Set date-time separator.
	 *
	 * @since 3.0.0
	 *
	 * @param string $separator A UTF8 character used as separator.
	 */
	public function set_datetime_separator( $separator = '' ) {

		if ( empty( $separator ) ) {
			$separator = get_post_meta( $this->id, '_calendar_datetime_separator', true );
		}

		$this->datetime_separator = esc_attr( $separator );
	}

	/**
	 * Set start of week.
	 *
	 * @since 3.0.0
	 *
	 * @param int $weekday From 0 (Sunday) to 6 (Friday).
	 */
	public function set_start_of_week( $weekday = -1 ) {

		$week_starts = is_int( $weekday ) ? $weekday : -1;

		if ( $week_starts < 0 || $week_starts > 6 ) {

			$week_starts_setting = get_post_meta( $this->id, '_calendar_week_starts_on_setting', true );
			$week_starts         = intval( get_option( 'start_of_week' ) );

			if ( 'use_custom' == $week_starts_setting ) {
				$week_starts_on = get_post_meta( $this->id, '_calendar_week_starts_on', true );
				$week_starts    = is_numeric( $week_starts_on ) ? intval( $week_starts_on ) : $week_starts;
			}
		}

		$this->week_starts = $week_starts;
	}

	/**
	 * Set calendar start.
	 *
	 * @since 3.0.0
	 *
	 * @param int $timestamp
	 */
	public function set_start( $timestamp = 0 ) {

		if ( is_int( $timestamp ) && $timestamp !== 0 ) {
			$this->start = $timestamp;
			return;
		}

		$this->start = Carbon::now( $this->timezone )->getTimestamp();

		$calendar_begins = esc_attr( get_post_meta( $this->id, '_calendar_begins', true ) );
		$nth = max( absint( get_post_meta( $this->id, '_calendar_begins_nth', true ) ), 1 );

		if ( 'today' == $calendar_begins ) {
			$this->start = Carbon::today( $this->timezone )->getTimestamp();
		} elseif ( 'days_before' == $calendar_begins ) {
			$this->start = Carbon::today( $this->timezone )->subDays( $nth )->getTimestamp();
		} elseif ( 'days_after' == $calendar_begins ) {
			$this->start = Carbon::today( $this->timezone )->addDays( $nth )->getTimestamp();
		} elseif ( 'this_week' == $calendar_begins ) {
			$week = new Carbon( 'now', $this->timezone );
			$week->setWeekStartsAt( $this->week_starts );
			$this->start = $week->startOfWeek()->getTimestamp();
		} elseif ( 'weeks_before' == $calendar_begins ) {
			$week = new Carbon( 'now', $this->timezone );
			$week->setWeekStartsAt( $this->week_starts );
			$this->start = $week->startOfWeek()->subWeeks( $nth )->getTimestamp();
		} elseif ( 'weeks_after' == $calendar_begins ) {
			$week = new Carbon( 'now', $this->timezone );
			$week->setWeekStartsAt( $this->week_starts );
			$this->start = $week->startOfWeek()->addWeeks( $nth )->getTimestamp();
		} elseif ( 'this_month' == $calendar_begins ) {
			$this->start = Carbon::today( $this->timezone )->startOfMonth()->getTimeStamp();
		} elseif ( 'months_before' == $calendar_begins ) {
			$this->start = Carbon::today( $this->timezone )->subMonths( $nth )->startOfMonth()->getTimeStamp();
		} elseif ( 'months_after' == $calendar_begins ) {
			$this->start = Carbon::today( $this->timezone )->addMonths( $nth )->startOfMonth()->getTimeStamp();
		} elseif ( 'this_year' == $calendar_begins ) {
			$this->start = Carbon::today( $this->timezone )->startOfYear()->getTimestamp();
		} elseif ( 'years_before' == $calendar_begins ) {
			$this->start = Carbon::today( $this->timezone )->subYears( $nth )->startOfYear()->getTimeStamp();
		} elseif ( 'years_after' == $calendar_begins ) {
			$this->start = Carbon::today( $this->timezone )->addYears( $nth )->startOfYear()->getTimeStamp();
		} elseif ( 'custom_date' == $calendar_begins ) {
			if ( $date = get_post_meta( $this->id, '_calendar_begins_custom_date', true ) ) {
				$this->start = Carbon::createFromFormat( 'Y-m-d', esc_attr( $date ) )->setTimezone( $this->timezone )->getTimestamp();
			}
		}
	}

	/**
	 * Set calendar end.
	 *
	 * @since 3.0.0
	 *
	 * @param int $timestamp
	 */
	public function set_end( $timestamp = 0 ) {
		$latest = is_int( $timestamp ) && $timestamp !== 0 ? $timestamp : $this->latest_event;
		$this->end = $latest > $this->start ? $latest : $this->start;
	}

	/**
	 * Set the calendar to static.
	 *
	 * @since 3.0.0
	 *
	 * @param string|bool $static
	 */
	public function set_static( $static = '' ) {

		if ( ! empty( $static ) && is_bool( $static ) ) {
			$this->static = $static;
			return;
		}

		if ( 'yes' == get_post_meta( $this->id, '_calendar_is_static', true ) ) {
			$this->static = true;
			return;
		}

		$this->static = false;
	}

	/**
	 * Input fields for settings page.
	 *
	 * @since 3.0.0
	 *
	 * @return array
	 */
	public function settings_fields() {
		return $this->settings;
	}

	/**
	 * Get a calendar view.
	 *
	 * @since 3.0.0
	 *
	 * @param  string $view
	 *
	 * @return Calendar_View
	 */
	abstract public function get_view( $view = '' );

	/**
	 * Get event HTML parsed by template.
	 *
	 * @since  3.0.0
	 *
	 * @param  Event  $event    Event object to be parsed.
	 * @param  string $template (optional) To use another template or a partial.
	 *
	 * @return string
	 */
	public function get_event_html( Event $event, $template = '' ) {
		$event_builder = new Event_Builder( $event, $this );
		// Use the event template to parse tags; if empty, fallback to calendar post content.
		$template = empty( $template ) ? ( empty( $event->template ) ? $this->events_template : $event->template ) : $template;
		return $event_builder->parse_event_template_tags( $template );
	}

	/**
	 * Output the calendar markup.
	 *
	 * @since 3.0.0
	 *
	 * @param string $view The calendar view to display.
	 */
	public function html( $view = '' ) {

		$view = empty( $view ) ? $this->view : $this->get_view( $view );

		if ( $view instanceof Calendar_View ) {

			if ( ! empty( $this->errors ) ) {

				if ( current_user_can( 'manage_options' )  ) {
					echo '<pre><code>';
					foreach ( $this->errors as $error ) { echo $error; }
					echo '</code></pre>';
				}

			} else {

				// Get a CSS class from the class name of the calendar view (minus namespace part).
				$view_name  = implode( '-', array_map( 'lcfirst', explode( '_', strtolower( get_class( $view ) ) ) ) );
				$view_class = substr( $view_name, strrpos( $view_name, '\\' ) + 1 );

				$calendar_class = trim( implode( ' simcal-', apply_filters( 'simcal_calendar_class', array(
					'simcal-calendar',
					$this->type,
					$view_class,
				), $this->id ) ) );

				echo '<div class="' . $calendar_class . '" '
									. 'data-calendar-id="'    . $this->id . '" '
									. 'data-timezone="'       . $this->timezone . '" '
									. 'data-offset="'         . $this->offset . '" '
									. 'data-week-start="'     . $this->week_starts . '" '
									. 'data-calendar-start="' . $this->start .'" '
									. 'data-calendar-end="'   . $this->end . '" '
									. 'data-events-first="'   . $this->earliest_event .'" '
									. 'data-events-last="'    . $this->latest_event . '"'
									. '>';

				date_default_timezone_set( $this->timezone );
				do_action( 'simcal_calendar_html_before', $this->id );

				$view->html();

				do_action( 'simcal_calendar_html_after', $this->id );
				date_default_timezone_set( $this->site_timezone );

				$settings = get_option( 'simple-calendar_settings_calendars' );
				$poweredby = isset( $settings['poweredby']['opt_in'] ) ? $settings['poweredby']['opt_in'] : '';

				if ( 'yes' == $poweredby ) {
					$align = is_rtl() ? 'left' : 'right';
					echo '<small class="simcal-powered simcal-align-' . $align .'">' .
					     sprintf( __( 'Powered by <a href="%s" target="_blank">Simple Calendar</a>', 'google-calendar-events' ), simcal_get_url( 'home' ) ) .
					     '</small>';
				}

				echo '</div>';

			}

		}

	}

}
