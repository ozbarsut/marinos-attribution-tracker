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

		$query_params = $this->get_request_query_params();
		$referrer     = $this->get_referrer();
		$user_agent   = $this->get_user_agent();
		$bot_info     = $this->detect_bot( $user_agent );
		$attribution  = $this->extract_attribution( $query_params, $referrer );
		$this->insert_event(
			array(
				'session_id'    => $session_id,
				'event_type'    => 'session_start',
				'event_label'   => 'landing_page',
				'page_url'      => $page_url,
				'target_url'    => '',
				'referrer_url'  => $referrer,
				'source'        => $attribution['source'],
				'medium'        => $attribution['medium'],
				'campaign'      => $attribution['campaign'],
				'search_engine' => $attribution['search_engine'],
				'search_keyword'=> $attribution['search_keyword'],
				'visitor_ip'    => $this->get_ip(),
				'user_agent'    => $user_agent,
				'metadata'      => wp_json_encode(
					array(
						'query_params' => $query_params,
						'note'         => $attribution['note'],
						'is_bot'       => $bot_info['is_bot'],
						'bot_reason'   => $bot_info['reason'],
						'ad_platform'  => $attribution['ad_platform'],
						'ad_click'     => $attribution['is_ad_click'],
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

		$user_agent = $this->get_user_agent();
		$bot_info   = $this->detect_bot( $user_agent );
		$session_context = $this->get_session_context( $session_id );

		$this->insert_event(
			array(
				'session_id'    => $session_id,
				'event_type'    => 'click',
				'event_label'   => $label,
				'page_url'      => $page_url,
				'target_url'    => $target_url,
				'referrer_url'  => $this->get_referrer(),
				'source'        => $session_context['source'],
				'medium'        => $session_context['medium'],
				'campaign'      => $session_context['campaign'],
				'search_engine' => $session_context['search_engine'],
				'search_keyword'=> $session_context['search_keyword'],
				'visitor_ip'    => $this->get_ip(),
				'user_agent'    => $user_agent,
				'metadata'      => wp_json_encode(
					array(
						'element'    => isset( $_POST['element'] ) ? sanitize_text_field( wp_unslash( $_POST['element'] ) ) : '',
						'is_bot'     => $bot_info['is_bot'],
						'bot_reason' => $bot_info['reason'],
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

	private function extract_attribution( $query_params, $referrer ) {
		$source   = '';
		$medium   = '';
		$campaign = '';
		$engine   = '';
		$keyword  = '';
		$note     = '';
		$is_ad_click = false;
		$ad_platform = '';

		if ( ! empty( $query_params['utm_source'] ) ) {
			$source = $query_params['utm_source'];
			$medium = isset( $query_params['utm_medium'] ) ? $query_params['utm_medium'] : '';
			$campaign = isset( $query_params['utm_campaign'] ) ? $query_params['utm_campaign'] : '';
		}
		$keyword = $this->extract_keyword_from_query_params( $query_params );
		if ( $keyword && empty( $query_params['utm_term'] ) ) {
			$note = 'Keyword URL parametresinden alindi';
		}

		if ( $this->is_google_ads_click( $query_params ) ) {
			$source = $source ? $source : 'google';
			$medium = $medium ? $medium : 'cpc';
			$engine = $engine ? $engine : 'google_ads';
			$is_ad_click = true;
			$ad_platform = 'google_ads';
			if ( ! $note ) {
				$note = $keyword
					? 'Google Ads kelimesi URL parametresinden alindi'
					: 'Google Ads tiki bulundu. Anahtar kelimeyi gormek icin reklam URLsine utm_term={keyword} veya keyword={keyword} ekleyin.';
			}
		}

		if ( ! empty( $query_params['fbclid'] ) ) {
			$source = $source ? $source : 'facebook';
			$medium = $medium ? $medium : 'paid_social';
			$note   = 'fbclid bulundu';
			$is_ad_click = true;
			$ad_platform = 'facebook_ads';
		}
		if ( ! empty( $query_params['msclkid'] ) ) {
			$source = $source ? $source : 'bing';
			$medium = $medium ? $medium : 'cpc';
			$note   = 'msclkid bulundu';
			$is_ad_click = true;
			$ad_platform = 'microsoft_ads';
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
					if ( ! $is_ad_click ) {
						$engine = $domain;
						$medium = $medium ? $medium : 'organic';
					}
					$referrer_keyword = $this->extract_keyword_from_referrer( $referrer, $query_key );
					if ( ! $keyword && $referrer_keyword ) {
						$keyword = $referrer_keyword;
					}
					if ( ! $keyword && ! $is_ad_click && ! $note ) {
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
			'is_ad_click'    => $is_ad_click,
			'ad_platform'    => $ad_platform,
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

		return $this->normalize_keyword_candidate( $params[ $query_key ] );
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
		$keyword_keys = array( 'utm_term', 'keyword', 'searchterm', 'search_term', 'term', 'query', 'q', 'utm_keyword', 'ad_keyword', 'kw', 'adquery', '_kwd' );

		foreach ( $keyword_keys as $key ) {
			if ( empty( $query_params[ $key ] ) ) {
				continue;
			}

			$keyword = $this->normalize_keyword_candidate( $query_params[ $key ] );
			if ( '' !== $keyword ) {
				return $keyword;
			}
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
			'kw',
			'ad_keyword',
			'adquery',
			'_kwd',
			'matchtype',
			'device',
			'network',
			'adgroupid',
			'campaignid',
		);
		$data = array();

		foreach ( $keys as $key ) {
			if ( isset( $_GET[ $key ] ) ) {
				$data[ $key ] = sanitize_text_field( wp_unslash( $_GET[ $key ] ) );
			}
		}

		return $data;
	}

	private function is_google_ads_click( $query_params ) {
		return ! empty( $query_params['gclid'] ) || ! empty( $query_params['gbraid'] ) || ! empty( $query_params['wbraid'] ) || ! empty( $query_params['gad_source'] );
	}

	private function normalize_keyword_candidate( $value ) {
		$keyword = sanitize_text_field( $value );
		if ( '' === $keyword ) {
			return '';
		}

		$normalized = strtolower( trim( $keyword ) );
		$invalid    = array( '{keyword}', '(not set)', '(not provided)', 'not set', 'not provided', 'n/a', 'na' );
		if ( in_array( $normalized, $invalid, true ) ) {
			return '';
		}

		return $keyword;
	}

	private function detect_bot( $user_agent ) {
		$user_agent = trim( (string) $user_agent );
		if ( '' === $user_agent ) {
			return array(
				'is_bot' => 1,
				'reason' => 'empty_user_agent',
			);
		}

		$user_agent_lower = strtolower( $user_agent );
		$bot_patterns     = array(
			'bot',
			'crawl',
			'spider',
			'slurp',
			'crawler',
			'headless',
			'python-requests',
			'curl',
			'wget',
			'facebookexternalhit',
			'whatsapp',
			'telegrambot',
		);

		foreach ( $bot_patterns as $pattern ) {
			if ( false !== strpos( $user_agent_lower, $pattern ) ) {
				return array(
					'is_bot' => 1,
					'reason' => 'ua_pattern:' . $pattern,
				);
			}
		}

		return array(
			'is_bot' => 0,
			'reason' => '',
		);
	}

	private function get_session_context( $session_id ) {
		global $wpdb;

		$sql = $wpdb->prepare(
			"SELECT source, medium, campaign, search_engine, search_keyword
			FROM {$this->table_name}
			WHERE session_id = %s
				AND ( source <> '' OR medium <> '' OR campaign <> '' OR search_engine <> '' OR search_keyword <> '' )
			ORDER BY id DESC
			LIMIT 1",
			$session_id
		);
		$row = $wpdb->get_row( $sql, ARRAY_A );
		if ( ! is_array( $row ) ) {
			return array(
				'source'         => '',
				'medium'         => '',
				'campaign'       => '',
				'search_engine'  => '',
				'search_keyword' => '',
			);
		}

		return array(
			'source'         => isset( $row['source'] ) ? (string) $row['source'] : '',
			'medium'         => isset( $row['medium'] ) ? (string) $row['medium'] : '',
			'campaign'       => isset( $row['campaign'] ) ? (string) $row['campaign'] : '',
			'search_engine'  => isset( $row['search_engine'] ) ? (string) $row['search_engine'] : '',
			'search_keyword' => isset( $row['search_keyword'] ) ? (string) $row['search_keyword'] : '',
		);
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
