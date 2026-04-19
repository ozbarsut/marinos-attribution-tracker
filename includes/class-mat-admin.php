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

		$this->handle_export_action();

		global $wpdb;

		$filters = $this->read_filters( $_GET );
		list( $where, $args ) = $this->build_where_clause( $filters );

		$per_page = 30;
		$paged    = max( 1, $filters['paged'] );
		$offset   = ( $paged - 1 ) * $per_page;

		$sql_total  = "SELECT COUNT(*) FROM {$this->table_name} WHERE {$where}";
		$sql_total  = $this->prepare_sql( $sql_total, $args );
		$total_rows = (int) $wpdb->get_var( $sql_total );

		$sql_events  = "SELECT * FROM {$this->table_name} WHERE {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d";
		$args_rows   = $args;
		$args_rows[] = $per_page;
		$args_rows[] = $offset;
		$sql_events  = $wpdb->prepare( $sql_events, $args_rows );
		$events      = $wpdb->get_results( $sql_events );

		$stats       = $this->fetch_stats( $where, $args );
		$ip_overview = $this->fetch_ip_overview( $where, $args );
		$total_pages = max( 1, (int) ceil( $total_rows / $per_page ) );
		$notice      = $this->read_notice();
		?>
		<div class="wrap">
			<h1>Attribution Tracker</h1>
			<?php if ( $notice['message'] ) : ?>
				<div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible">
					<p><?php echo esc_html( $notice['message'] ); ?></p>
				</div>
			<?php endif; ?>
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
				<div style="background:#fff; border:1px solid #dcdcde; padding:12px 14px; border-radius:8px; min-width:220px;">
					<div style="font-size:12px; color:#646970;">Bugun Giren (Tekil)</div>
					<div style="font-size:24px; font-weight:600;"><?php echo esc_html( number_format_i18n( $stats['today_sessions'] ) ); ?></div>
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
						<label for="mat_date_from" style="display:block; margin-bottom:4px;">Baslangic Tarihi</label>
						<input id="mat_date_from" name="date_from" type="date" value="<?php echo esc_attr( $filters['date_from'] ); ?>">
					</div>
					<div>
						<label for="mat_date_to" style="display:block; margin-bottom:4px;">Bitis Tarihi</label>
						<input id="mat_date_to" name="date_to" type="date" value="<?php echo esc_attr( $filters['date_to'] ); ?>">
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
						<div style="display:block; margin-bottom:4px;">Tarih Hizli Secim</div>
						<label for="mat_today_only" style="display:flex; align-items:center; gap:6px;">
							<input id="mat_today_only" name="today_only" type="checkbox" value="1" <?php checked( $filters['today_only'], 1 ); ?>>
							Sadece bugun
						</label>
					</div>
					<div>
						<button type="submit" class="button button-primary">Filtrele</button>
						<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=mat-tracker' ) ); ?>">Temizle</a>
					</div>
				</div>
			</form>

			<form method="post" style="background:#fff; border:1px solid #dcdcde; padding:12px; border-radius:8px; margin-bottom:12px;">
				<?php wp_nonce_field( 'mat_export_action', 'mat_export_nonce' ); ?>
				<input type="hidden" name="event_type" value="<?php echo esc_attr( $filters['event_type'] ); ?>">
				<input type="hidden" name="source" value="<?php echo esc_attr( $filters['source'] ); ?>">
				<input type="hidden" name="keyword" value="<?php echo esc_attr( $filters['keyword'] ); ?>">
				<input type="hidden" name="bot_mode" value="<?php echo esc_attr( $filters['bot_mode'] ); ?>">
				<input type="hidden" name="ad_only" value="<?php echo esc_attr( $filters['ad_only'] ); ?>">
				<input type="hidden" name="today_only" value="<?php echo esc_attr( $filters['today_only'] ); ?>">
				<input type="hidden" name="date_from" value="<?php echo esc_attr( $filters['date_from'] ); ?>">
				<input type="hidden" name="date_to" value="<?php echo esc_attr( $filters['date_to'] ); ?>">
				<div style="display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap;">
					<div>
						<label for="mat_export_email" style="display:block; margin-bottom:4px;">E-posta Adresi</label>
						<input id="mat_export_email" name="export_email" type="email" value="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>" style="min-width:280px;">
					</div>
					<div>
						<button type="submit" name="mat_action" value="download_csv" class="button">Excel (CSV) indir</button>
						<button type="submit" name="mat_action" value="email_csv" class="button button-secondary">Excel (CSV) e-posta gonder</button>
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

		$total_events_sql  = "SELECT COUNT(*) FROM {$this->table_name} WHERE {$where}";
		$unique_sql        = "SELECT COUNT(DISTINCT session_id) FROM {$this->table_name} WHERE {$where}";
		$keyword_sql       = "SELECT COUNT(*) FROM {$this->table_name} WHERE {$where} AND search_keyword <> ''";
		$ad_sql            = "SELECT COUNT(*) FROM {$this->table_name} WHERE {$where} AND (medium = 'cpc' OR metadata LIKE '%\"ad_click\":true%' OR referrer_url LIKE '%gclid=%' OR referrer_url LIKE '%msclkid=%')";
		$bot_sql           = "SELECT COUNT(*) FROM {$this->table_name} WHERE {$where} AND metadata LIKE '%\"is_bot\":1%'";
		$today_sessions_sql = "SELECT COUNT(DISTINCT session_id) FROM {$this->table_name} WHERE DATE(created_at) = CURDATE()";

		return array(
			'total_events'   => (int) $wpdb->get_var( $this->prepare_sql( $total_events_sql, $args ) ),
			'unique_sessions'=> (int) $wpdb->get_var( $this->prepare_sql( $unique_sql, $args ) ),
			'keyword_rows'   => (int) $wpdb->get_var( $this->prepare_sql( $keyword_sql, $args ) ),
			'ad_rows'        => (int) $wpdb->get_var( $this->prepare_sql( $ad_sql, $args ) ),
			'bot_rows'       => (int) $wpdb->get_var( $this->prepare_sql( $bot_sql, $args ) ),
			'today_sessions' => (int) $wpdb->get_var( $today_sessions_sql ),
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
		if ( $filters['date_from'] ) {
			$where  .= ' AND DATE(created_at) >= %s';
			$args[] = $filters['date_from'];
		}
		if ( $filters['date_to'] ) {
			$where  .= ' AND DATE(created_at) <= %s';
			$args[] = $filters['date_to'];
		}
		if ( $filters['today_only'] ) {
			$where .= ' AND DATE(created_at) = CURDATE()';
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

	private function read_filters( $source ) {
		$bot_mode = isset( $source['bot_mode'] ) ? sanitize_key( wp_unslash( $source['bot_mode'] ) ) : '';
		if ( ! in_array( $bot_mode, array( '', 'exclude', 'only' ), true ) ) {
			$bot_mode = '';
		}

		$date_from = isset( $source['date_from'] ) ? sanitize_text_field( wp_unslash( $source['date_from'] ) ) : '';
		$date_to   = isset( $source['date_to'] ) ? sanitize_text_field( wp_unslash( $source['date_to'] ) ) : '';

		return array(
			'event_type' => isset( $source['event_type'] ) ? sanitize_text_field( wp_unslash( $source['event_type'] ) ) : '',
			'source'     => isset( $source['source'] ) ? sanitize_text_field( wp_unslash( $source['source'] ) ) : '',
			'keyword'    => isset( $source['keyword'] ) ? sanitize_text_field( wp_unslash( $source['keyword'] ) ) : '',
			'bot_mode'   => $bot_mode,
			'ad_only'    => isset( $source['ad_only'] ) ? 1 : 0,
			'today_only' => isset( $source['today_only'] ) ? 1 : 0,
			'date_from'  => preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_from ) ? $date_from : '',
			'date_to'    => preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_to ) ? $date_to : '',
			'paged'      => isset( $source['paged'] ) ? max( 1, (int) $source['paged'] ) : 1,
		);
	}

	private function handle_export_action() {
		if ( 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$action = isset( $_POST['mat_action'] ) ? sanitize_key( wp_unslash( $_POST['mat_action'] ) ) : '';
		if ( ! in_array( $action, array( 'download_csv', 'email_csv' ), true ) ) {
			return;
		}
		if ( ! isset( $_POST['mat_export_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mat_export_nonce'] ) ), 'mat_export_action' ) ) {
			$this->set_notice( 'error', 'Guvenlik dogrulamasi basarisiz oldu.' );
			return;
		}

		$filters = $this->read_filters( $_POST );
		$rows    = $this->fetch_export_rows( $filters );

		if ( empty( $rows ) ) {
			$this->set_notice( 'warning', 'Secilen filtrelerle disa aktarilacak veri bulunamadi.' );
			return;
		}

		if ( 'download_csv' === $action ) {
			$this->download_csv( $rows );
		}

		$email = isset( $_POST['export_email'] ) ? sanitize_email( wp_unslash( $_POST['export_email'] ) ) : '';
		if ( ! is_email( $email ) ) {
			$this->set_notice( 'error', 'Gecerli bir e-posta adresi girin.' );
			return;
		}

		$file_path = $this->create_csv_file( $rows );
		if ( ! $file_path ) {
			$this->set_notice( 'error', 'CSV dosyasi olusturulamadi.' );
			return;
		}

		$subject = sprintf( 'Attribution Tracker Raporu - %s', wp_date( 'Y-m-d H:i' ) );
		$message = 'Filtrelenmis trafik raporu ekte yer aliyor.';
		$sent    = wp_mail( $email, $subject, $message, array(), array( $file_path ) );

		@unlink( $file_path );

		if ( $sent ) {
			$this->set_notice( 'success', 'Excel (CSV) raporu e-posta ile gonderildi.' );
		} else {
			$this->set_notice( 'error', 'E-posta gonderimi basarisiz oldu. Sunucuda mail ayari gerekli olabilir.' );
		}

		wp_safe_redirect( $this->build_admin_url_with_filters( $filters ) );
		exit;
	}

	private function fetch_export_rows( $filters ) {
		global $wpdb;

		list( $where, $args ) = $this->build_where_clause( $filters );
		$sql = "SELECT created_at, event_type, source, medium, search_keyword, page_url, target_url, referrer_url, visitor_ip, metadata
			FROM {$this->table_name}
			WHERE {$where}
			ORDER BY created_at DESC";
		$sql = $this->prepare_sql( $sql, $args );

		return $wpdb->get_results( $sql, ARRAY_A );
	}

	private function download_csv( $rows ) {
		$file_name = 'mat-report-' . wp_date( 'Ymd-His' ) . '.csv';
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $file_name . '"' );

		$output = fopen( 'php://output', 'w' );
		if ( false === $output ) {
			exit;
		}

		fwrite( $output, "\xEF\xBB\xBF" );
		$this->write_csv_rows( $output, $rows );
		fclose( $output );
		exit;
	}

	private function create_csv_file( $rows ) {
		$upload = wp_upload_dir();
		if ( ! empty( $upload['error'] ) ) {
			return '';
		}

		$dir = trailingslashit( $upload['basedir'] ) . 'mat-exports';
		if ( ! wp_mkdir_p( $dir ) ) {
			return '';
		}

		$file_path = trailingslashit( $dir ) . 'mat-report-' . wp_generate_uuid4() . '.csv';
		$handle    = fopen( $file_path, 'w' );
		if ( false === $handle ) {
			return '';
		}

		fwrite( $handle, "\xEF\xBB\xBF" );
		$this->write_csv_rows( $handle, $rows );
		fclose( $handle );

		return $file_path;
	}

	private function write_csv_rows( $handle, $rows ) {
		$headers = array(
			'Zaman',
			'Event',
			'Kaynak / Medium',
			'Arama Kelimesi',
			'Sayfa',
			'Hedef URL',
			'IP',
			'Trafik',
		);
		fputcsv( $handle, $headers );

		foreach ( $rows as $row ) {
			$metadata = $this->decode_metadata( isset( $row['metadata'] ) ? $row['metadata'] : '' );
			$is_bot   = $this->is_bot_event( $metadata );
			$is_ad    = $this->is_ad_event( (object) $row, $metadata );

			$data = array(
				isset( $row['created_at'] ) ? (string) $row['created_at'] : '',
				isset( $row['event_type'] ) ? (string) $row['event_type'] : '',
				$this->format_source_medium( $row ),
				$this->format_keyword_for_display( $row, $metadata, $is_ad ),
				isset( $row['page_url'] ) ? (string) $row['page_url'] : '',
				isset( $row['target_url'] ) ? (string) $row['target_url'] : '',
				isset( $row['visitor_ip'] ) ? (string) $row['visitor_ip'] : '',
				$is_bot ? 'Bot' : 'Insan',
			);
			fputcsv( $handle, $data );
		}
	}

	private function format_source_medium( $row ) {
		$source = isset( $row['source'] ) && '' !== (string) $row['source'] ? (string) $row['source'] : '-';
		$medium = isset( $row['medium'] ) && '' !== (string) $row['medium'] ? (string) $row['medium'] : '-';

		return $source . ' / ' . $medium;
	}

	private function format_keyword_for_display( $row, $metadata, $is_ad_event ) {
		$keyword = isset( $row['search_keyword'] ) ? (string) $row['search_keyword'] : '';
		$note    = isset( $metadata['note'] ) ? (string) $metadata['note'] : '';

		if ( '' === $keyword ) {
			$keyword = $is_ad_event ? 'Gizli / URLde yok' : '-';
		}

		if ( '' !== $note ) {
			return $keyword . ' | ' . $note;
		}

		return $keyword;
	}

	private function set_notice( $type, $message ) {
		set_transient(
			'mat_admin_notice',
			array(
				'type'    => $type,
				'message' => $message,
			),
			60
		);
	}

	private function read_notice() {
		$notice = get_transient( 'mat_admin_notice' );
		if ( ! is_array( $notice ) ) {
			return array(
				'type'    => '',
				'message' => '',
			);
		}
		delete_transient( 'mat_admin_notice' );

		$type = isset( $notice['type'] ) ? sanitize_key( $notice['type'] ) : '';
		if ( ! in_array( $type, array( 'success', 'error', 'warning', 'info' ), true ) ) {
			$type = 'info';
		}

		return array(
			'type'    => $type,
			'message' => isset( $notice['message'] ) ? sanitize_text_field( $notice['message'] ) : '',
		);
	}

	private function build_admin_url_with_filters( $filters ) {
		$params = array(
			'page'       => 'mat-tracker',
			'event_type' => $filters['event_type'],
			'source'     => $filters['source'],
			'keyword'    => $filters['keyword'],
			'bot_mode'   => $filters['bot_mode'],
			'ad_only'    => $filters['ad_only'] ? '1' : '',
			'today_only' => $filters['today_only'] ? '1' : '',
			'date_from'  => $filters['date_from'],
			'date_to'    => $filters['date_to'],
		);

		return add_query_arg( $params, admin_url( 'admin.php' ) );
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
