<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MAT_Admin {

	private $table_name;

	public function __construct() {
		$this->table_name = MAT_Database::table_name();
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
	}

	public function register_menu() {
		add_menu_page(
			'Attribution Tracker',
			'Attribution Tracker',
			'manage_options',
			'mat-tracker',
			array( $this, 'render_page' ),
			'dashicons-chart-line',
			81
		);
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		global $wpdb;

		$filters = $this->read_filters();
		list( $where, $args ) = $this->build_where_clause( $filters );

		$per_page = 30;
		$paged    = max( 1, $filters['paged'] );
		$offset   = ( $paged - 1 ) * $per_page;

		$sql_total = "SELECT COUNT(*) FROM {$this->table_name} WHERE {$where}";
		$sql_total = $this->prepare_sql( $sql_total, $args );
		$total_rows = (int) $wpdb->get_var( $sql_total );

		$sql_events = "SELECT * FROM {$this->table_name} WHERE {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d";
		$args_rows  = $args;
		$args_rows[] = $per_page;
		$args_rows[] = $offset;
		$sql_events = $wpdb->prepare( $sql_events, $args_rows );
		$events     = $wpdb->get_results( $sql_events );

		$stats       = $this->fetch_stats( $where, $args );
		$ip_overview = $this->fetch_ip_overview( $where, $args );
		$total_pages = max( 1, (int) ceil( $total_rows / $per_page ) );
		?>
		<div class="wrap">
			<h1>Attribution Tracker</h1>
			<p>
				Reklamdan gelen anahtar kelimeyi gorebilmek icin ziyaret URL'sinde
				<code>utm_term</code> veya <code>keyword</code> parametresinin tasinmasi gerekir.
			</p>

			<div style="display:flex; gap:12px; margin:16px 0; flex-wrap:wrap;">
				<div style="background:#fff; border:1px solid #dcdcde; padding:12px 14px; border-radius:8px; min-width:220px;">
					<div style="font-size:12px; color:#646970;">Toplam Olay</div>
					<div style="font-size:24px; font-weight:600;"><?php echo esc_html( number_format_i18n( $stats['total_events'] ) ); ?></div>
				</div>
				<div style="background:#fff; border:1px solid #dcdcde; padding:12px 14px; border-radius:8px; min-width:220px;">
					<div style="font-size:12px; color:#646970;">Tekil Oturum</div>
					<div style="font-size:24px; font-weight:600;"><?php echo esc_html( number_format_i18n( $stats['unique_sessions'] ) ); ?></div>
				</div>
				<div style="background:#fff; border:1px solid #dcdcde; padding:12px 14px; border-radius:8px; min-width:220px;">
					<div style="font-size:12px; color:#646970;">Arama Kelimesi Bulunan</div>
					<div style="font-size:24px; font-weight:600;"><?php echo esc_html( number_format_i18n( $stats['keyword_rows'] ) ); ?></div>
				</div>
				<div style="background:#fff; border:1px solid #dcdcde; padding:12px 14px; border-radius:8px; min-width:220px;">
					<div style="font-size:12px; color:#646970;">Reklam Tiklamasi</div>
					<div style="font-size:24px; font-weight:600;"><?php echo esc_html( number_format_i18n( $stats['ad_rows'] ) ); ?></div>
				</div>
				<div style="background:#fff; border:1px solid #dcdcde; padding:12px 14px; border-radius:8px; min-width:220px;">
					<div style="font-size:12px; color:#646970;">Bot Olaylari</div>
					<div style="font-size:24px; font-weight:600;"><?php echo esc_html( number_format_i18n( $stats['bot_rows'] ) ); ?></div>
				</div>
			</div>

			<form method="get" style="background:#fff; border:1px solid #dcdcde; padding:12px; border-radius:8px; margin-bottom:12px;">
				<input type="hidden" name="page" value="mat-tracker">
				<div style="display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap;">
					<div>
						<label for="mat_event_type" style="display:block; margin-bottom:4px;">Event</label>
						<select id="mat_event_type" name="event_type">
							<option value="">Tum eventler</option>
							<option value="session_start" <?php selected( $filters['event_type'], 'session_start' ); ?>>session_start</option>
							<option value="click" <?php selected( $filters['event_type'], 'click' ); ?>>click</option>
						</select>
					</div>
					<div>
						<label for="mat_source" style="display:block; margin-bottom:4px;">Kaynak</label>
						<input id="mat_source" name="source" type="text" value="<?php echo esc_attr( $filters['source'] ); ?>" placeholder="google, facebook, direct">
					</div>
					<div>
						<label for="mat_keyword" style="display:block; margin-bottom:4px;">Arama Kelimesi</label>
						<input id="mat_keyword" name="keyword" type="text" value="<?php echo esc_attr( $filters['keyword'] ); ?>" placeholder="ornek: beyaz esya servisi">
					</div>
					<div>
						<label for="mat_bot_mode" style="display:block; margin-bottom:4px;">Bot Filtresi</label>
						<select id="mat_bot_mode" name="bot_mode">
							<option value="" <?php selected( $filters['bot_mode'], '' ); ?>>Tum trafik</option>
							<option value="exclude" <?php selected( $filters['bot_mode'], 'exclude' ); ?>>Botlari gizle</option>
							<option value="only" <?php selected( $filters['bot_mode'], 'only' ); ?>>Sadece botlar</option>
						</select>
					</div>
					<div>
						<div style="display:block; margin-bottom:4px;">Reklam</div>
						<label for="mat_ad_only" style="display:flex; align-items:center; gap:6px;">
							<input id="mat_ad_only" name="ad_only" type="checkbox" value="1" <?php checked( $filters['ad_only'], 1 ); ?>>
							Sadece reklam trafigi
						</label>
					</div>
					<div>
						<button type="submit" class="button button-primary">Filtrele</button>
						<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=mat-tracker' ) ); ?>">Temizle</a>
					</div>
				</div>
			</form>

			<div style="background:#fff; border:1px solid #dcdcde; padding:12px; border-radius:8px; margin-bottom:12px;">
				<h2 style="margin-top:0;">IP Ozeti (ilk 12)</h2>
				<table class="widefat striped" style="margin-top:8px;">
					<thead>
						<tr>
							<th>IP</th>
							<th style="width:120px;">Toplam Olay</th>
							<th style="width:120px;">Oturum</th>
							<th style="width:120px;">Bot Orani</th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $ip_overview ) ) : ?>
							<tr>
								<td colspan="4">IP verisi bulunamadi.</td>
							</tr>
						<?php else : ?>
							<?php foreach ( $ip_overview as $row ) : ?>
								<?php
								$bot_rate = 0;
								if ( (int) $row->total_events > 0 ) {
									$bot_rate = (int) round( ( (int) $row->bot_events * 100 ) / (int) $row->total_events );
								}
								?>
								<tr>
									<td><code><?php echo esc_html( $row->visitor_ip ); ?></code></td>
									<td><?php echo esc_html( number_format_i18n( (int) $row->total_events ) ); ?></td>
									<td><?php echo esc_html( number_format_i18n( (int) $row->unique_sessions ) ); ?></td>
									<td><?php echo esc_html( $bot_rate ); ?>%</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>

			<table class="widefat striped">
				<thead>
					<tr>
						<th style="width:140px;">Zaman</th>
						<th style="width:90px;">Event</th>
						<th style="width:180px;">Kaynak / Medium</th>
						<th>Arama Kelimesi</th>
						<th>Sayfa</th>
						<th>Hedef URL</th>
						<th style="width:130px;">IP</th>
						<th style="width:110px;">Trafik</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $events ) ) : ?>
						<tr>
							<td colspan="8">Kayit bulunamadi.</td>
						</tr>
					<?php else : ?>
						<?php foreach ( $events as $event ) : ?>
							<?php
							$metadata     = $this->decode_metadata( $event->metadata );
							$note         = isset( $metadata['note'] ) ? (string) $metadata['note'] : '';
							$is_bot       = $this->is_bot_event( $metadata );
							$is_ad_event  = $this->is_ad_event( $event, $metadata );
							$keyword_text = $event->search_keyword
								? (string) $event->search_keyword
								: ( $is_ad_event ? 'Gizli / URLde yok' : '' );
							?>
							<tr>
								<td><?php echo esc_html( $event->created_at ); ?></td>
								<td><code><?php echo esc_html( $event->event_type ); ?></code></td>
								<td>
									<div><strong><?php echo esc_html( $event->source ? $event->source : '-' ); ?></strong></div>
									<div style="color:#646970; font-size:12px;"><?php echo esc_html( $event->medium ? $event->medium : '-' ); ?></div>
								</td>
								<td>
									<div><?php echo esc_html( $keyword_text ? $keyword_text : '-' ); ?></div>
									<?php if ( $note ) : ?>
										<div style="color:#646970; font-size:12px;"><?php echo esc_html( $note ); ?></div>
									<?php endif; ?>
								</td>
								<td>
									<?php if ( $event->page_url ) : ?>
										<a href="<?php echo esc_url( $event->page_url ); ?>" target="_blank" rel="noopener noreferrer">
											<?php echo esc_html( $this->trim_text( $event->page_url, 70 ) ); ?>
										</a>
									<?php endif; ?>
								</td>
								<td>
									<?php if ( $event->target_url ) : ?>
										<a href="<?php echo esc_url( $event->target_url ); ?>" target="_blank" rel="noopener noreferrer">
											<?php echo esc_html( $this->trim_text( $event->target_url, 70 ) ); ?>
										</a>
									<?php endif; ?>
								</td>
								<td><code><?php echo esc_html( $event->visitor_ip ? $event->visitor_ip : '-' ); ?></code></td>
								<td>
									<span style="display:inline-block; padding:2px 8px; border-radius:999px; font-size:12px; background:<?php echo esc_attr( $is_bot ? '#fbeaea' : '#e8f5e9' ); ?>; color:<?php echo esc_attr( $is_bot ? '#a12a2a' : '#1b5e20' ); ?>;">
										<?php echo esc_html( $is_bot ? 'Bot' : 'Insan' ); ?>
									</span>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
			<?php if ( $total_pages > 1 ) : ?>
				<div class="tablenav">
					<div class="tablenav-pages">
						<?php
						echo wp_kses_post(
							paginate_links(
								array(
									'base'      => add_query_arg( 'paged', '%#%' ),
									'format'    => '',
									'current'   => $paged,
									'total'     => $total_pages,
									'prev_text' => '&laquo;',
									'next_text' => '&raquo;',
								)
							)
						);
						?>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	private function fetch_stats( $where, $args ) {
		global $wpdb;

		$total_events_sql = "SELECT COUNT(*) FROM {$this->table_name} WHERE {$where}";
		$unique_sql       = "SELECT COUNT(DISTINCT session_id) FROM {$this->table_name} WHERE {$where}";
		$keyword_sql      = "SELECT COUNT(*) FROM {$this->table_name} WHERE {$where} AND search_keyword <> ''";
		$ad_sql           = "SELECT COUNT(*) FROM {$this->table_name} WHERE {$where} AND (medium = 'cpc' OR metadata LIKE '%\"ad_click\":true%' OR referrer_url LIKE '%gclid=%' OR referrer_url LIKE '%msclkid=%')";
		$bot_sql          = "SELECT COUNT(*) FROM {$this->table_name} WHERE {$where} AND metadata LIKE '%\"is_bot\":1%'";

		return array(
			'total_events'    => (int) $wpdb->get_var( $this->prepare_sql( $total_events_sql, $args ) ),
			'unique_sessions' => (int) $wpdb->get_var( $this->prepare_sql( $unique_sql, $args ) ),
			'keyword_rows'    => (int) $wpdb->get_var( $this->prepare_sql( $keyword_sql, $args ) ),
			'ad_rows'         => (int) $wpdb->get_var( $this->prepare_sql( $ad_sql, $args ) ),
			'bot_rows'        => (int) $wpdb->get_var( $this->prepare_sql( $bot_sql, $args ) ),
		);
	}

	private function fetch_ip_overview( $where, $args ) {
		global $wpdb;

		$sql = "SELECT
				visitor_ip,
				COUNT(*) AS total_events,
				COUNT(DISTINCT session_id) AS unique_sessions,
				SUM(CASE WHEN metadata LIKE '%\"is_bot\":1%' THEN 1 ELSE 0 END) AS bot_events
			FROM {$this->table_name}
			WHERE {$where}
				AND visitor_ip <> ''
			GROUP BY visitor_ip
			ORDER BY total_events DESC
			LIMIT 12";
		$sql = $this->prepare_sql( $sql, $args );

		return $wpdb->get_results( $sql );
	}

	private function build_where_clause( $filters ) {
		global $wpdb;

		$where = '1=1';
		$args  = array();

		if ( $filters['event_type'] ) {
			$where  .= ' AND event_type = %s';
			$args[] = $filters['event_type'];
		}
		if ( $filters['source'] ) {
			$where  .= ' AND source LIKE %s';
			$args[] = '%' . $wpdb->esc_like( $filters['source'] ) . '%';
		}
		if ( $filters['keyword'] ) {
			$where  .= ' AND search_keyword LIKE %s';
			$args[] = '%' . $wpdb->esc_like( $filters['keyword'] ) . '%';
		}
		if ( $filters['ad_only'] ) {
			$where  .= " AND (medium = %s OR metadata LIKE %s OR referrer_url LIKE %s OR referrer_url LIKE %s)";
			$args[] = 'cpc';
			$args[] = '%"ad_click":true%';
			$args[] = '%gclid=%';
			$args[] = '%msclkid=%';
		}
		if ( 'exclude' === $filters['bot_mode'] ) {
			$where  .= " AND (metadata NOT LIKE %s OR metadata = '' OR metadata IS NULL)";
			$args[] = '%"is_bot":1%';
		} elseif ( 'only' === $filters['bot_mode'] ) {
			$where  .= ' AND metadata LIKE %s';
			$args[] = '%"is_bot":1%';
		}

		return array( $where, $args );
	}

	private function read_filters() {
		$bot_mode = isset( $_GET['bot_mode'] ) ? sanitize_key( wp_unslash( $_GET['bot_mode'] ) ) : '';
		if ( ! in_array( $bot_mode, array( '', 'exclude', 'only' ), true ) ) {
			$bot_mode = '';
		}

		return array(
			'event_type' => isset( $_GET['event_type'] ) ? sanitize_text_field( wp_unslash( $_GET['event_type'] ) ) : '',
			'source'     => isset( $_GET['source'] ) ? sanitize_text_field( wp_unslash( $_GET['source'] ) ) : '',
			'keyword'    => isset( $_GET['keyword'] ) ? sanitize_text_field( wp_unslash( $_GET['keyword'] ) ) : '',
			'bot_mode'   => $bot_mode,
			'ad_only'    => isset( $_GET['ad_only'] ) ? 1 : 0,
			'paged'      => isset( $_GET['paged'] ) ? (int) $_GET['paged'] : 1,
		);
	}

	private function prepare_sql( $sql, $args ) {
		global $wpdb;

		if ( empty( $args ) ) {
			return $sql;
		}

		return $wpdb->prepare( $sql, $args );
	}

	private function decode_metadata( $raw_metadata ) {
		if ( '' === (string) $raw_metadata ) {
			return array();
		}

		$decoded = json_decode( (string) $raw_metadata, true );
		return is_array( $decoded ) ? $decoded : array();
	}

	private function is_bot_event( $metadata ) {
		return ! empty( $metadata['is_bot'] );
	}

	private function is_ad_event( $event, $metadata ) {
		if ( ! empty( $metadata['ad_click'] ) || ! empty( $metadata['ad_platform'] ) ) {
			return true;
		}

		$medium = isset( $event->medium ) ? strtolower( (string) $event->medium ) : '';
		if ( 'cpc' === $medium ) {
			return true;
		}

		$referrer = isset( $event->referrer_url ) ? (string) $event->referrer_url : '';
		if ( '' === $referrer ) {
			return false;
		}

		return false !== strpos( $referrer, 'gclid=' ) || false !== strpos( $referrer, 'msclkid=' );
	}

	private function trim_text( $value, $length ) {
		if ( mb_strlen( $value ) <= $length ) {
			return $value;
		}
		return mb_substr( $value, 0, $length ) . '...';
	}
}
