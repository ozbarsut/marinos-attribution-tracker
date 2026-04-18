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
		$where   = '1=1';
		$args    = array();

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

		$per_page = 30;
		$paged    = max( 1, $filters['paged'] );
		$offset   = ( $paged - 1 ) * $per_page;

		$sql_total = "SELECT COUNT(*) FROM {$this->table_name} WHERE {$where}";
		if ( ! empty( $args ) ) {
			$sql_total = $wpdb->prepare( $sql_total, $args );
		}
		$total_rows = (int) $wpdb->get_var( $sql_total );

		$sql_events = "SELECT * FROM {$this->table_name} WHERE {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d";
		$args_rows  = $args;
		$args_rows[] = $per_page;
		$args_rows[] = $offset;
		$sql_events = $wpdb->prepare( $sql_events, $args_rows );
		$events     = $wpdb->get_results( $sql_events );

		$stats = $this->fetch_stats();
		$total_pages = max( 1, (int) ceil( $total_rows / $per_page ) );
		?>
		<div class="wrap">
			<h1>Attribution Tracker</h1>
			<p>
				Siteye gelen kisinin kaynak bilgisini, tikladigi linkleri ve arama motoru anahtar kelimesini
				(eger referrer bunu veriyorsa) listeler.
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
						<button type="submit" class="button button-primary">Filtrele</button>
						<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=mat-tracker' ) ); ?>">Temizle</a>
					</div>
				</div>
			</form>

			<table class="widefat striped">
				<thead>
					<tr>
						<th style="width:140px;">Zaman</th>
						<th style="width:90px;">Event</th>
						<th style="width:120px;">Kaynak</th>
						<th style="width:110px;">Medium</th>
						<th style="width:120px;">Arama Motoru</th>
						<th>Arama Kelimesi</th>
						<th>Sayfa</th>
						<th>Hedef URL</th>
						<th style="width:120px;">IP</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $events ) ) : ?>
						<tr>
							<td colspan="9">Kayit bulunamadi.</td>
						</tr>
					<?php else : ?>
						<?php foreach ( $events as $event ) : ?>
							<tr>
								<td><?php echo esc_html( $event->created_at ); ?></td>
								<td><code><?php echo esc_html( $event->event_type ); ?></code></td>
								<td><?php echo esc_html( $event->source ); ?></td>
								<td><?php echo esc_html( $event->medium ); ?></td>
								<td><?php echo esc_html( $event->search_engine ); ?></td>
								<td><?php echo esc_html( $event->search_keyword ); ?></td>
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
								<td><?php echo esc_html( $event->visitor_ip ); ?></td>
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

	private function fetch_stats() {
		global $wpdb;

		$total_events_sql = "SELECT COUNT(*) FROM {$this->table_name}";
		$unique_sql       = "SELECT COUNT(DISTINCT session_id) FROM {$this->table_name}";
		$keyword_sql      = "SELECT COUNT(*) FROM {$this->table_name} WHERE search_keyword <> ''";

		return array(
			'total_events'   => (int) $wpdb->get_var( $total_events_sql ),
			'unique_sessions'=> (int) $wpdb->get_var( $unique_sql ),
			'keyword_rows'   => (int) $wpdb->get_var( $keyword_sql ),
		);
	}

	private function read_filters() {
		return array(
			'event_type' => isset( $_GET['event_type'] ) ? sanitize_text_field( wp_unslash( $_GET['event_type'] ) ) : '',
			'source'     => isset( $_GET['source'] ) ? sanitize_text_field( wp_unslash( $_GET['source'] ) ) : '',
			'keyword'    => isset( $_GET['keyword'] ) ? sanitize_text_field( wp_unslash( $_GET['keyword'] ) ) : '',
			'paged'      => isset( $_GET['paged'] ) ? (int) $_GET['paged'] : 1,
		);
	}

	private function trim_text( $value, $length ) {
		if ( mb_strlen( $value ) <= $length ) {
			return $value;
		}
		return mb_substr( $value, 0, $length ) . '...';
	}
}
