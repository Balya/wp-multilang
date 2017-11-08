<?php

namespace WPM\Includes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Setup language params, locales, configs on load WordPress
 *
 * Class WPM_Setup
 * @package  WPM/Includes
 */
class WPM_Setup {

	/**
	 * Original url
	 *
	 * @var string
	 */
	private $original_home_url = '';

	/**
	 * Original uri
	 *
	 * @var string
	 */
	private $original_request_uri = '';

	/**
	 * Original uri
	 *
	 * @var string
	 */
	private $site_request_uri = '';

	/**
	 * Default locale
	 *
	 * @var string
	 */
	private $default_locale = '';

	/**
	 * Default site language
	 *
	 * @var string
	 */
	private $default_language = '';

	/**
	 * Languages
	 *
	 * @var array
	 */
	private $languages = array();

	/**
	 * Options
	 *
	 * @var array
	 */
	private $options = array();

	/**
	 * Installed languages
	 *
	 * @var array
	 */
	private $installed_languages = array();

	/**
	 * User language
	 *
	 * @var string
	 */
	private $user_language = '';

	/**
	 * Available translations
	 *
	 * @var array
	 */
	private $translations = array();

	/**
	 * Config
	 *
	 * @var array
	 */
	private $config = array();

	/**
	 * The single instance of the class.
	 *
	 * @var WPM_Setup
	 */
	protected static $_instance = null;

	/**
	 * Main WPM_Setup Instance.
	 *
	 * @static
	 * @return WPM_Setup - Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * WPM_Setup constructor.
	 */
	public function __construct() {
		add_filter( 'query_vars', array( $this, 'set_lang_var' ) );
		add_filter( 'option_home', array( $this, 'set_home_url' ), 99 );
		if ( defined( 'DOMAIN_MAPPING' ) ) {
			add_filter( 'pre_option_home', array( $this, 'set_home_url' ), 99 );
		}
		add_action( 'after_switch_theme', array( __NAMESPACE__ . '\WPM_Config', 'load_config_run' ) );
		add_action( 'activated_plugin', array( __NAMESPACE__ . '\WPM_Config', 'load_config_run' ) );
		add_action( 'upgrader_process_complete', array( __NAMESPACE__ . '\WPM_Config', 'load_config_run' ) );
		add_action( 'after_setup_theme', array( $this, 'redirect_default_url' ) );
		add_action( 'wpm_init', array( $this, 'load_integrations' ) );
//		add_action( 'parse_request', array( $this, 'setup_query_var' ), 0 );
		add_action( 'wp', array( $this, 'redirect_to_user_language' ) );
		add_filter( 'request', array( $this, 'set_home_page' ) );
		add_filter( 'rest_url', array( $this, 'fix_rest_url' ) );
		add_filter( 'option_date_format', array( $this, 'set_date_format' ) );
		add_filter( 'option_time_format', array( $this, 'set_time_format' ) );
		add_filter( 'locale', array( $this, 'set_locale' ) );
		add_filter( 'gettext', array( $this, 'set_html_locale' ), 10, 2 );
		add_filter( 'redirect_canonical', array( $this, 'fix_canonical_redirect' ), 10, 2 );
	}


	/**
	 * Load options from base
	 *
	 * @return array|string
	 */
	public function get_options() {
		if ( ! $this->options ) {
			$this->options = get_option( 'wpm_languages', array() );
		}

		return $this->options;
	}


	/**
	 * Get original home url
	 *
	 * @since 1.7.0
	 *
	 * @param bool $unslash
	 *
	 * @return string
	 */
	public function get_original_home_url( $unslash = true ) {
		if ( ! $this->original_home_url ) {
			$home_url = home_url();
			$this->original_home_url = $unslash ? untrailingslashit( $home_url ) : $home_url;
		}

		return $this->original_home_url;
	}


	/**
	 * Get original request url
	 *
	 * @since 1.7.0
	 *
	 * @return string
	 */
	public function get_original_request_uri() {
		return $this->original_request_uri ? $this->original_request_uri : '/';
	}


	/**
	 * Get site request url
	 *
	 * @since 2.0.1
	 *
	 * @return string
	 */
	public function get_site_request_uri() {
		if ( ! $this->site_request_uri ) {
			$original_uri = $this->get_original_request_uri();

			if ( isset( $_GET['lang'] ) ) {
				$site_request_uri = $original_uri;
				if ( $url_lang = $this->get_lang_from_url() ) {
					$site_request_uri = str_replace( '/' . $url_lang . '/', '/', $original_uri );
				}
			} else {
				$site_request_uri = str_replace( home_url(), '', $this->get_original_home_url() . $original_uri );
			}

			$this->site_request_uri = $site_request_uri ? $site_request_uri : '/';
		}

		return $this->site_request_uri ;
	}


	/**
	 * Get installed languages
	 *
	 * @return array
	 */
	public function get_installed_languages() {
		if ( ! $this->installed_languages ) {
			$this->installed_languages = wp_parse_args( get_available_languages(), array( 'en_US' ) );
		}

		return $this->installed_languages;
	}


	/**
	 * Get enables languages. Add installed languages to options.
	 *
	 * @return array
	 */
	public function get_languages() {
		if ( ! $this->languages ) {
			$options   = $this->get_options();
			$languages = array();

			foreach ( $options as $slug => $language ) {
				if ( $language['enable'] ) {
					$languages[ $slug ] = $language;
				}
			}

			$this->languages = $languages;
		}

		return $this->languages;
	}

	/**
	 * Get site locale from options
	 *
	 * @return string
	 */
	public function get_default_locale() {
		if ( ! $this->default_locale ) {
			$option_lang          = get_option( 'WPLANG' );
			$this->default_locale = $option_lang ? $option_lang : 'en_US';
		}

		return $this->default_locale;
	}

	/**
	 * Get site language
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function get_default_language() {
		if ( ! $this->default_language ) {
			$default_language = get_option( 'wpm_site_language' );

			if ( ! $default_language ) {
				$locale           = explode( '_', $this->get_default_locale() );
				$default_language = $locale[0];
			}

			$this->default_language = $default_language;
		}

		return $this->default_language;
	}


	/**
	 * Set site locale
	 *
	 * @since 2.0.0
	 *
	 * @param $locale
	 *
	 * @return mixed
	 */
	public function set_locale( $locale ) {

		$languages = $this->get_languages();

		if ( ! $languages ) {
			return $locale;
		}

		return $languages[ $this->get_user_language() ]['translation'];
	}


	/**
	 * Get user language
	 *
	 * @return string
	 */
	public function get_user_language() {
		if ( ! $this->user_language ) {
			$this->user_language = $this->set_user_language();
		}

		return $this->user_language;
	}

	/**
	 * Set user language for frontend from url or browser
	 * Set admin language from cookie or url
	 */
	public function set_user_language() {

		$languages     = $this->get_languages();
		$url           = '';
		$user_language = '';

		require_once( ABSPATH . WPINC . '/pluggable.php' );

		if ( ! is_admin() ) {
			$url = wpm_get_current_url();
		}

		if ( wp_doing_ajax() ) {
			if ( $referrer = wp_get_raw_referer() ) {
				if ( strpos( $referrer, 'wp-admin/' ) === false ) {
					$url = $referrer;
					add_filter( 'get_user_metadata', array( $this, 'set_user_locale' ), 10, 4 );
				}
			} else {
				add_filter( 'get_user_metadata', array( $this, 'set_user_locale' ), 10, 4 );
			}
		}

		if ( $url ) {
			$this->original_request_uri = str_replace( $this->get_original_home_url(), '', $url );

			if ( $url_lang = $this->get_lang_from_url() ) {
				$user_language = $url_lang;
			}

			if ( $user_language && ! is_admin() && ! isset( $languages[ $user_language ] ) ) {
				add_action( 'template_redirect', array( $this, 'set_not_found' ) );
			}
		}

		if ( isset( $_REQUEST['lang'] ) ) {
			$lang = wpm_clean( $_REQUEST['lang'] );
			if ( isset( $languages[ $lang ] ) ) {
				$user_language = $lang;

				if ( is_admin() && ! wp_doing_ajax() ) {
					update_user_meta( get_current_user_id(), 'user_lang', $lang );
					update_user_meta( get_current_user_id(), 'locale', $languages[ $lang ]['translation'] );
				}
			} else {
				if ( ! is_admin() ) {
					add_action( 'template_redirect', array( $this, 'set_not_found' ) );
				}
			}
		} else {
			if ( is_admin() && ! wp_doing_ajax() ) {
				if ( $user_meta_language = get_user_meta( get_current_user_id(), 'user_lang', true ) ) {
					if ( isset( $languages[ $user_meta_language ] ) ) {
						$user_language = $user_meta_language;
					}
				} else {
					update_user_meta( get_current_user_id(), 'user_lang', $this->get_default_language() );
					update_user_meta( get_current_user_id(), 'locale', $this->get_default_locale() );
				}
			} elseif ( ! is_admin() && preg_match( '/^.*\.php$/i', wp_parse_url( $url, PHP_URL_PATH ) ) ) {
				if ( isset( $_COOKIE['language'] ) ) {
					$user_language = wpm_clean( $_COOKIE['language'] );
				}
			}
		}

		if ( ! $user_language || ! isset( $languages[ $user_language ] ) ) {
			$user_language = $this->get_default_language();
		}

		return $user_language;
	}


	/**
	 * Redirect to default language
	 */
	public function redirect_default_url() {
		$user_language    = $this->get_user_language();
		$default_language = $this->get_default_language();
		$languages        = $this->get_languages();
		$url_lang         = $this->get_lang_from_url();

		if ( ! isset( $_REQUEST['lang'] ) ) {
			if ( get_option( 'wpm_use_prefix' ) ) {
				if ( ! $url_lang && ! is_admin() && ! preg_match( '/^.*\.php$/i', wp_parse_url( $this->get_original_request_uri(), PHP_URL_PATH ) ) ) {
					wp_redirect( home_url( '/' . $default_language . $this->get_original_request_uri() ) );
					exit;
				}
			} else {
				if ( $url_lang && isset( $languages[ $url_lang ] ) && $user_language === $default_language ) {
					wp_redirect( home_url( str_replace( '/' . $user_language . '/', '/', $this->get_original_request_uri() ) ) );
					exit;
				}
			}
		}
	}

	/**
	 * Get available translations
	 *
	 * @return array
	 */
	public function get_translations() {

		if ( ! $this->translations ) {
			require_once( ABSPATH . 'wp-admin/includes/translation-install.php' );
			$available_translations          = wp_get_available_translations();
			$available_translations['en_US'] = array(
				'native_name' => 'English (US)',
				'iso'         => array( 'en' ),
			);

			$this->translations = $available_translations;
		}

		return $this->translations;
	}


	/**
	 * Get config from options
	 *
	 * @return array
	 */
	public function get_config() {

		if ( ! $this->config ) {
			$config       = get_option( 'wpm_config', array() );
			$theme_config = WPM_Config::load_theme_config();
			$this->config = wpm_array_merge_recursive( $config, $theme_config );
		}

		$config = apply_filters( 'wpm_load_config', $this->config );

		$posts_config = apply_filters( 'wpm_posts_config', $config['post_types'] );
		$post_types   = get_post_types( '', 'names' );

		foreach ( $post_types as $post_type ) {
			$posts_config[ $post_type ] = apply_filters( "wpm_post_{$post_type}_config", isset( $posts_config[ $post_type ] ) ? $posts_config[ $post_type ] : null );
		}

		$config['post_types'] = $posts_config;

		$taxonomies_config = apply_filters( 'wpm_taxonomies_config', $config['taxonomies'] );
		$taxonomies        = get_taxonomies();

		foreach ( $taxonomies as $taxonomy ) {
			$taxonomies_config[ $taxonomy ] = apply_filters( "wpm_taxonomy_{$taxonomy}_config", isset( $taxonomies_config[ $taxonomy ] ) ? $taxonomies_config[ $taxonomy ] : null );
		}

		$config['taxonomies'] = $taxonomies_config;

		$config['options'] = apply_filters( 'wpm_options_config', $config['options'] );

		if ( is_multisite() ) {
			$config['site_options'] = apply_filters( 'wpm_site_options_config', $config['site_options'] );
		} else {
			unset( $config['site_options'] );
		}

		$config['widgets'] = apply_filters( 'wpm_widgets_config', $config['widgets'] );

		return $config;
	}


	/**
	 * Add lang slug to home url
	 *
	 * @param $value
	 *
	 * @return string
	 */
	public static function set_home_url( $value ) {

		if ( ( is_admin() && ! wp_doing_ajax() ) || ! did_action( 'wpm_init' ) || ! $value ) {
			return $value;
		}

		$user_language    = wpm_get_user_language();
		$default_language = wpm_get_default_language();


		if ( $user_language !== $default_language || get_option( 'wpm_use_prefix' ) ) {
			$value .= '/' . $user_language;
		}

		return $value;
	}


	/**
	 * Add 'lang' param to allow params
	 *
	 * @param $public_query_vars
	 *
	 * @return array
	 */
	public function set_lang_var( $public_query_vars ) {

		if ( ! isset( $_GET['lang'] ) ) {
			$public_query_vars[] = 'lang';
		}


		return $public_query_vars;
	}


	/**
	 * Load integration classes
	 */
	public function load_integrations() {
		$integrations_path = WPM_ABSPATH . 'includes/integrations/';
		foreach ( glob( $integrations_path . '*.php' ) as $integration_file ) {
			if ( apply_filters( 'wpm_load_integration_' . str_replace( '-', '_', basename( $integration_file, '.php' ) ), $integration_file ) ) {
				if ( $integration_file && is_readable( $integration_file ) ) {
					include_once( $integration_file );
				}
			}
		}
	}

	/**
	 * Set 404 headers for not available language
	 */
	public function set_not_found() {
		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
	}

	/**
	 * Add query var 'lang' in global request
	 *
	 * @param $request
	 *
	 * @return object WP
	 */
	public function setup_query_var( $request ) {
		if ( ( '/' == wp_parse_url( $this->get_site_request_uri(), PHP_URL_PATH ) ) || ( isset( $request->query_vars['paged'] ) && count( $request->query_vars ) == 1 ) || isset( $_GET['lang'] ) ) {
			return $request;
		}

		$request->query_vars['lang'] = $this->get_user_language();

		return $request;
	}

	/**
	 * Redirect to browser language
	 */
	public function redirect_to_user_language() {

		if ( ! is_admin() && ! defined( 'WP_CLI' ) ) {
			$user_language = $this->get_user_language();

			if ( ! isset( $_COOKIE['language'] ) ) {

				wpm_setcookie( 'language', $user_language, time() + YEAR_IN_SECONDS );
				$redirect_to_browser_language = get_option( 'wpm_use_redirect', false );

				if ( $redirect_to_browser_language ) {

					$browser_language = $this->get_browser_language();

					if ( $browser_language && ( $browser_language !== $user_language ) ) {
						wp_redirect( wpm_translate_url( wpm_get_current_url(), $browser_language ) );
						exit;
					}
				}
			} else {
				if ( wpm_clean( $_COOKIE['language'] ) != $user_language ) {
					wpm_setcookie( 'language', $user_language, time() + YEAR_IN_SECONDS );
					do_action( 'wpm_changed_language' );
				}
			} // End if().
		} // End if().
	}


	/**
	 * Detect browser language
	 *
	 * @return string
	 */
	private function get_browser_language() {

		if ( ! isset( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) || ! $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) {
			return '';
		}

		if ( ! preg_match_all( '#([^;,]+)(;[^,0-9]*([0-9\.]+)[^,]*)?#i', $_SERVER['HTTP_ACCEPT_LANGUAGE'], $matches, PREG_SET_ORDER ) ) {
			return '';
		}

		$detect             = '';
		$prefered_languages = array();
		$priority           = 1.0;

		foreach ( $matches as $match ) {
			if ( ! isset( $match[3] ) ) {
				$pr       = $priority;
				$priority -= 0.001;
			} else {
				$pr = floatval( $match[3] );
			}
			$prefered_languages[ str_replace( '-', '_', $match[1] ) ] = $pr;
		}

		arsort( $prefered_languages, SORT_NUMERIC );

		$browser_languages = array_keys( $prefered_languages );
		$languages         = $this->get_languages();

		foreach ( $browser_languages as $browser_language ) {
			foreach ( $languages as $key => $value ) {
				if ( ! $locale = $value['locale'] ) {
					$locale = $value['translation'];
				}

				$locale = str_replace( '-', '_', $locale );

				if ( $browser_language == $locale || strtolower( str_replace( '_', '-', $browser_language ) ) == $key ) {
					$detect = $key;
					break 2;
				}
			}
		}

		return $detect;
	}

	/**
	 * Fix home page if isset 'lang' GET parameter
	 *
	 * @param $query_vars
	 *
	 * @return array
	 */
	public function set_home_page( $query_vars ) {
		d($query_vars);
		/*$url_lang = $this->get_lang_from_url();
		d($query_vars);

		if ( isset( $_GET['lang'] ) && ( ( '/' == wp_parse_url( $this->get_site_request_uri(), PHP_URL_PATH ) ) || ( count( $query_vars ) == 2 && isset( $query_vars['paged'] ) ) || $url_lang ) ) {
			unset( $query_vars['lang'] );
		}*/

		/*if ( isset( $_GET['lang'] ) && in_array( $url_lang, $query_vars ) ) {
			$key = array_search( $url_lang, $query_vars );
			unset( $query_vars[ $key ] );
		}*/

		return $query_vars;
	}


	/**
	 * Set user locale for AJAX front requests
	 *
	 * @param $check
	 * @param $object_id
	 * @param $meta_key
	 * @param $single
	 *
	 * @return array|string
	 */
	public function set_user_locale( $check, $object_id, $meta_key, $single ) {
		if ( 'locale' == $meta_key ) {
			if ( $single ) {
				$check = get_locale();
			} else {
				$check = array( get_locale() );
			}
		}

		return $check;
	}


	/**
	 * Fix REST url
	 *
	 * @param $url
	 *
	 * @return string
	 */
	public function fix_rest_url( $url ) {
		if ( ! get_option( 'wpm_use_prefix' ) && get_locale() != wpm_get_default_locale() ) {
			$url = str_replace( '/' . wpm_get_language() . '/', '/', $url );
		}

		return $url;
	}


	/**
	 * Set date format for current language
	 *
	 * @since 1.8.0
	 *
	 * @param $value
	 *
	 * @return string
	 */
	public function set_date_format( $value ) {

		require_once( ABSPATH . 'wp-admin/includes/screen.php' );

		if ( is_admin() && ! wp_doing_ajax() ) {
			$screen = get_current_screen();
			if ( $screen && 'options-general' == $screen->id ) {
				return $value;
			}
		}

		if ( defined( 'REST_REQUEST' ) && ( '/wp/v2/settings' == $GLOBALS['wp']->query_vars['rest_route'] ) ) {
			return $value;
		}

		$languages     = $this->get_languages();
		$user_language = $this->get_user_language();

		if ( isset( $languages[ $user_language ]['date'] ) && $languages[ $user_language ]['date'] ) {
			return $languages[ $user_language ]['date'];
		}

		return $value;
	}


	/**
	 * Set time format for current language
	 *
	 * @since 1.8.0
	 *
	 * @param $value
	 *
	 * @return string
	 */
	public function set_time_format( $value ) {

		require_once( ABSPATH . 'wp-admin/includes/screen.php' );

		if ( is_admin() && ! wp_doing_ajax() ) {
			$screen = get_current_screen();
			if ( $screen && 'options-general' == $screen->id ) {
				return $value;
			}
		}

		if ( defined( 'REST_REQUEST' ) && ( '/wp/v2/settings' == $GLOBALS['wp']->query_vars['rest_route'] ) ) {
			return $value;
		}

		$languages     = $this->get_languages();
		$user_language = $this->get_user_language();

		if ( isset( $languages[ $user_language ]['time'] ) && $languages[ $user_language ]['time'] ) {
			return $languages[ $user_language ]['time'];
		}

		return $value;
	}


	/**
	 * Set locale for html
	 *
	 * @since 2.0.0
	 *
	 * @param $translation
	 * @param $text
	 *
	 * @return mixed
	 */
	public function set_html_locale( $translation, $text ) {

		if ( 'html_lang_attribute' == $text ) {
			$languages     = $this->get_languages();
			$user_language = $this->get_user_language();

			if ( $languages[ $user_language ]['locale'] ) {
				$translation = $languages[ $user_language ]['locale'];
			}
		}

		return $translation;
	}

	/**
	 * Fix redirect when using lang param in $_GET
	 *
	 * @since 2.0.1
	 *
	 * @param string $redirect_url
	 * @param string $requested_url
	 *
	 * @return string
	 */
	public function fix_canonical_redirect( $redirect_url, $requested_url ) {
		if ( isset( $_GET['lang'] ) && ! empty( $_GET['lang'] ) ) {
			return $requested_url;
		}

		return $redirect_url;
	}

	/**
	 * Get lang from url
	 *
	 * @since 2.0.3
	 *
	 * @return string
	 */
	public function get_lang_from_url() {
		$url_lang = '';

		if ( preg_match( '!^/([a-z]{2})(-[a-z]{2})?(/|$)!i', $this->get_original_request_uri(), $match ) ) {
			$url_lang = $match[1];
		}

		return $url_lang;
	}
}
