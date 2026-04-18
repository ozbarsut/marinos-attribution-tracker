<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MAT_Tracker {

	private $table_name;

	public function __construct() {
		$this->table_name = MAT_Database::table_name();

		add_action( 'init', array( $this, 'ensure_session_cookie' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_script' ) );
		add_action( 'template_redirect', array( $this, 'track_page_entry' ) );

		add_action( 'wp_ajax_mat_track_click', array( $this, 'handle_click_event' ) );
		add_action( 'wp_ajax_nopriv_mat_track_click', array( $this, 'handle_click_event' ) );
	}

	public function ensure_session_cookie() {
		if ( is_admin() || wp_doing_cron() ) {
			return;
		}

		if ( ! empty( $_COOKIE['mat_sid'] ) ) {
			return;
		}

		$session_id = wp_generate_uuid4();
		$secure     = is_ssl();
		setcookie( 'mat_sid', $session_id, time() + DAY_IN_SECONDS * 30, COOKIEPATH, COOKIE_DOMAIN, $secure, true );
		$_COOKIE['mat_sid'] = $session_id;
	}

	public function enqueue_script() {
		if ( is_admin() ) {
			return;
		}

		wp_enqueue_script(
			'mat-tracker',
			MAT_PLUGIN_URL . 'assets/js/tracker.js',
			array(),
			MAT_VERSION,
			true
		);

		wp_localize_script(
			'mat-tracker',
			'MATTracker',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'mat_track_click' ),
			)
		);
	}

	public function track_page_entry() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}

		$session_id = $this->get_session_id();
		if ( ! $session_id ) {
			return;
		}

		$already_logged = isset( $_COOKIE['mat_entry_logged'] );
		if ( $already_logged ) {
			return;
		}

		$page_url = $this->current_page_url();
		if ( ! $page_url ) {
			return;
		}

		$attribution = $this->extract_attribution();
		$this->insert_event(
			array(
				'session_id'    => $session_id,
				'event_type'    => 'session_start',
				'event_label'   => 'landing_page',
				'page_url'      => $page_url,
				'target_url'    => '',
				'referrer_url'  => $this->get_referrer(),
				'source'        => $attribution['source'],
				'medium'        => $attribution['medium'],
				'campaign'      => $attribution['campaign'],
				'search_engine' => $attribution['search_engine'],
				'search_keyword'=> $attribution['search_keyword'],
				'visitor_ip'    => $this->get_ip(),
				'user_agent'    => $this->get_user_agent(),
				'metadata'      => wp_json_encode(
					array(
						'query_params' => $this->get_request_query_params(),
						'note'         => $attribution['note'],
					)
				),
			)
		);

		$secure = is_ssl();
		setcookie( 'mat_entry_logged', '1', time() + DAY_IN_SECONDS * 30, COOKIEPATH, COOKIE_DOMAIN, $secure, true );
	}

	public function handle_click_event() {
		check_ajax_referer( 'mat_track_click', 'nonce' );

		$session_id = $this->get_session_id();
		if ( ! $session_id ) {
			wp_send_json_error( array( 'message' => 'Session yok' ), 400 );
		}

		$page_url   = isset( $_POST['page_url'] ) ? esc_url_raw( wp_unslash( $_POST['page_url'] ) ) : '';
		$target_url = isset( $_POST['target_url'] ) ? esc_url_raw( wp_unslash( $_POST['target_url'] ) ) : '';
		$label      = isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( $_POST['label'] ) ) : '';

		if ( ! $page_url && ! $target_url ) {
			wp_send_json_error( array( 'message' => 'Eksik veri' ), 400 );
		}

		$this->insert_event(
			array(
				'session_id'    => $session_id,
				'event_type'    => 'click',
				'event_label'   => $label,
				'page_url'      => $page_url,
				'target_url'    => $target_url,
				'referrer_url'  => $this->get_referrer(),
				'source'        => '',
				'medium'        => '',
				'campaign'      => '',
				'search_engine' => '',
				'search_keyword'=> '',
				'visitor_ip'    => $this->get_ip(),
				'user_agent'    => $this->get_user_agent(),
				'metadata'      => wp_json_encode(
					array(
						'element' => isset( $_POST['element'] ) ? sanitize_text_field( wp_unslash( $_POST['element'] ) ) : '',
					)
				),
			)
		);

		wp_send_json_success( array( 'saved' => true ) );
	}

	private function insert_event( $data ) {
		global $wpdb;

		$wpdb->insert(
			$this->table_name,
			array(
				'session_id'     => $data['session_id'],
				'event_type'     => $data['event_type'],
				'event_label'    => $data['event_label'],
				'page_url'       => $data['page_url'],
				'target_url'     => $data['target_url'],
				'referrer_url'   => $data['referrer_url'],
				'source'         => $data['source'],
				'medium'         => $data['medium'],
				'campaign'       => $data['campaign'],
				'search_engine'  => $data['search_engine'],
				'search_keyword' => $data['search_keyword'],
				'visitor_ip'     => $data['visitor_ip'],
				'user_agent'     => $data['user_agent'],
				'metadata'       => $data['metadata'],
				'created_at'     => current_time( 'mysql' ),
			),
			array(
				'%s', '%s', '%s', '%s', '%s', '%s', '%s',
				'%s', '%s', '%s', '%s', '%s', '%s', '%s',
				'%s',
			)
		);
	}

	private function extract_attribution() {
		$query_params = $this->get_request_query_params();
		$referrer     = $this->get_referrer();

		$source   = '';
		$medium   = '';
		$campaign = '';
		$engine   = '';
		$keyword  = '';
		$note     = '';

		if ( ! empty( $query_params['utm_source'] ) ) {
			$source = $query_params['utm_source'];
			$medium = isset( $query_params['utm_medium'] ) ? $query_params['utm_medium'] : '';
			$campaign = isset( $query_params['utm_campaign'] ) ? $query_params['utm_campaign'] : '';
		}
		$keyword = $this->extract_keyword_from_query_params( $query_params );
		if ( $keyword && empty( $query_params['utm_term'] ) ) {
			$note = 'Keyword URL parametresinden alindi';
		}

		if ( ! empty( $query_params['gclid'] ) || ! empty( $query_params['gbraid'] ) || ! empty( $query_params['wbraid'] ) || ! empty( $query_params['gad_source'] ) ) {
			$source = $source ? $source : 'google';
			$medium = $medium ? $medium : 'cpc';
			if ( ! $note ) {
				$note = 'Google Ads parametresi bulundu';
			}
		}

		if ( ! empty( $query_params['fbclid'] ) ) {
			$source = $source ? $source : 'facebook';
			$medium = $medium ? $medium : 'paid_social';
			$note   = 'fbclid bulundu';
		}
		if ( ! empty( $query_params['msclkid'] ) ) {
			$source = $source ? $source : 'bing';
			$medium = $medium ? $medium : 'cpc';
			$note   = 'msclkid bulundu';
		}

		if ( $referrer ) {
			$ref_host = wp_parse_url( $referrer, PHP_URL_HOST );
			$ref_host = $ref_host ? strtolower( $ref_host ) : '';

			if ( ! $source ) {
				$source = $ref_host ? $ref_host : 'direct';
			}

			$search_map = $this->search_engine_map();
			foreach ( $search_map as $domain => $query_key ) {
				if ( $ref_host && false !== strpos( $ref_host, $domain ) ) {
					$engine  = $domain;
					$medium  = $medium ? $medium : 'organic';
					$referrer_keyword = $this->extract_keyword_from_referrer( $referrer, $query_key );
					if ( ! $keyword && $referrer_keyword ) {
						$keyword = $referrer_keyword;
					}
					if ( ! $keyword ) {
						$note = 'Arama motoru bulundu ama kelime gizli olabilir';
					}
					break;
				}
			}
		} elseif ( ! $source ) {
			$source = 'direct';
		}

		return array(
			'source'         => $source,
			'medium'         => $medium,
			'campaign'       => $campaign,
			'search_engine'  => $engine,
			'search_keyword' => $keyword,
			'note'           => $note,
		);
	}

	private function extract_keyword_from_referrer( $referrer, $query_key ) {
		$query = wp_parse_url( $referrer, PHP_URL_QUERY );
		if ( ! $query ) {
			return '';
		}

		parse_str( $query, $params );
		if ( empty( $params[ $query_key ] ) ) {
			return '';
		}

		return sanitize_text_field( $params[ $query_key ] );
	}

	private function search_engine_map() {
		return array(
			'google.'      => 'q',
			'bing.com'     => 'q',
			'yandex.'      => 'text',
			'duckduckgo.'  => 'q',
			'yahoo.'       => 'p',
			'baidu.com'    => 'wd',
		);
	}

	private function extract_keyword_from_query_params( $query_params ) {
		$keyword_keys = array( 'utm_term', 'keyword', 'searchterm', 'search_term', 'term', 'query', 'q', 'utm_keyword' );

		foreach ( $keyword_keys as $key ) {
			if ( empty( $query_params[ $key ] ) ) {
				continue;
			}

			return sanitize_text_field( $query_params[ $key ] );
		}

		return '';
	}

	private function get_request_query_params() {
		$keys = array(
			'utm_source',
			'utm_medium',
			'utm_campaign',
			'utm_term',
			'utm_keyword',
			'keyword',
			'searchterm',
			'search_term',
			'term',
			'query',
			'q',
			'gclid',
			'gbraid',
			'wbraid',
			'gad_source',
			'fbclid',
			'msclkid',
		);
		$data = array();

		foreach ( $keys as $key ) {
			if ( isset( $_GET[ $key ] ) ) {
				$data[ $key ] = sanitize_text_field( wp_unslash( $_GET[ $key ] ) );
			}
		}

		return $data;
	}

	private function get_session_id() {
		$cookie = isset( $_COOKIE['mat_sid'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['mat_sid'] ) ) : '';
		return $cookie;
	}

	private function get_referrer() {
		return isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
	}

	private function get_user_agent() {
		return isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
	}

	private function get_ip() {
		$headers = array(
			'HTTP_CF_CONNECTING_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_CLIENT_IP',
			'REMOTE_ADDR',
		);

		foreach ( $headers as $header ) {
			if ( empty( $_SERVER[ $header ] ) ) {
				continue;
			}

			$value = wp_unslash( $_SERVER[ $header ] );
			$parts = explode( ',', $value );
			$ip    = trim( $parts[0] );
			if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				return $ip;
			}
		}

		return '';
	}

	private function current_page_url() {
		if ( empty( $_SERVER['HTTP_HOST'] ) || empty( $_SERVER['REQUEST_URI'] ) ) {
			return '';
		}

		$scheme = is_ssl() ? 'https://' : 'http://';
		$url    = $scheme . wp_unslash( $_SERVER['HTTP_HOST'] ) . wp_unslash( $_SERVER['REQUEST_URI'] );

		return esc_url_raw( $url );
	}
}
