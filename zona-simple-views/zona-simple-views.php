<?php
/**
 * Plugin Name: Zona Simple Views
 * Description: Penghitung kunjungan artikel dengan penyesuaian manual, filter kategori, dan shortcode.
 * Version: 1.2.0
 * Author: Zonautara
 * License: GPL-2.0-or-later
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: zona-simple-views
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Zona_Simple_Views {
    const VERSION = '1.2.0';
    const OPTION = 'zsv_settings';
    const COOKIE = 'zsv_seen';

    private static function cookie_name() {
        return self::COOKIE . '_' . get_current_blog_id();
    }

    public static function init() {
        add_action( 'init', array( __CLASS__, 'maybe_install' ) );
        add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
        add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_assets' ) );
        add_action( 'wp_ajax_zsv_import', array( __CLASS__, 'import_ajax' ) );
        add_action( 'wp_ajax_zsv_quick_data', array( __CLASS__, 'quick_data' ) );
        add_action( 'quick_edit_custom_box', array( __CLASS__, 'quick_box' ), 10, 2 );
        add_action( 'add_meta_boxes_post', array( __CLASS__, 'meta_box' ) );
        add_action( 'save_post_post', array( __CLASS__, 'save_views' ) );
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
        add_action( 'wp_ajax_zsv_boot', array( __CLASS__, 'boot' ) );
        add_action( 'wp_ajax_nopriv_zsv_boot', array( __CLASS__, 'boot' ) );
        add_action( 'wp_ajax_zsv_count', array( __CLASS__, 'count' ) );
        add_action( 'wp_ajax_nopriv_zsv_count', array( __CLASS__, 'count' ) );
        add_filter( 'the_content', array( __CLASS__, 'content' ), 20 );
        add_shortcode( 'zsv_views', array( __CLASS__, 'shortcode' ) );
        add_filter( 'manage_post_posts_columns', array( __CLASS__, 'columns' ) );
        add_action( 'manage_post_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
        add_action( 'before_delete_post', array( __CLASS__, 'delete_post' ) );
    }

    private static function table() {
        global $wpdb;
        return $wpdb->prefix . 'zsv_views';
    }

    public static function install() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = self::table();
        $collate = $wpdb->get_charset_collate();
        dbDelta( "CREATE TABLE $table (
            post_id bigint(20) unsigned NOT NULL,
            actual bigint(20) unsigned NOT NULL DEFAULT 0,
            adjustment bigint(20) NOT NULL DEFAULT 0,
            imported bigint(20) unsigned DEFAULT NULL,
            PRIMARY KEY  (post_id)
        ) $collate;" );
        update_option( 'zsv_db_version', self::VERSION, false );
    }

    public static function activate( $network_wide = false ) {
        // Each multisite blog installs its own prefixed table on first use.
        self::install();
    }

    public static function maybe_install() {
        if ( get_option( 'zsv_db_version' ) !== self::VERSION ) {
            self::install();
        }
    }

    public static function settings() {
        return wp_parse_args( (array) get_option( self::OPTION, array() ), array(
            'position' => 'after',
            'categories' => array(),
            'label' => 'Dibaca:',
            'interval' => 30,
            'exclude_logged_in' => 1,
        ) );
    }

    public static function numbers( $post_id ) {
        global $wpdb;
        $table = self::table();
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT actual, adjustment, imported FROM $table WHERE post_id = %d", $post_id
        ), ARRAY_A );
        $actual = $row ? (int) $row['actual'] : 0;
        $adjustment = $row ? (int) $row['adjustment'] : 0;
        $imported = $row && isset( $row['imported'] ) ? (int) $row['imported'] : 0;
        return array( 'actual' => $actual, 'adjustment' => $adjustment, 'imported' => $imported, 'total' => max( 0, $actual + $adjustment ) );
    }

    public static function menu() {
        add_options_page( 'Zona Simple Views', 'Zona Simple Views', 'manage_options', 'zona-simple-views', array( __CLASS__, 'settings_page' ) );
    }

    public static function register_settings() {
        register_setting( 'zsv_group', self::OPTION, array( 'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ) ) );
    }

    public static function sanitize_settings( $input ) {
        $input = is_array( $input ) ? $input : array();
        $position = isset( $input['position'] ) && is_string( $input['position'] ) ? $input['position'] : 'after';
        $label = isset( $input['label'] ) && is_string( $input['label'] ) ? sanitize_text_field( $input['label'] ) : 'Dibaca:';
        $categories = isset( $input['categories'] ) && is_array( $input['categories'] ) ? array_filter( array_map( 'absint', $input['categories'] ) ) : array();
        return array(
            'position' => in_array( $position, array( 'before', 'after', 'manual' ), true ) ? $position : 'after',
            'categories' => array_values( array_unique( $categories ) ),
            'label' => $label,
            'interval' => min( 1440, max( 1, absint( $input['interval'] ?? 30 ) ) ),
            'exclude_logged_in' => empty( $input['exclude_logged_in'] ) ? 0 : 1,
        );
    }

    public static function settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) { return; }
        $s = self::settings();
        $categories = get_categories( array( 'hide_empty' => false ) );
        ?>
        <div class="wrap">
            <h1>Zona Simple Views</h1>
            <p>Penghitungan berlaku untuk semua artikel terbit. Pilihan kategori hanya membatasi tampilan kepada pembaca.</p>
            <form method="post" action="options.php">
                <?php settings_fields( 'zsv_group' ); ?>
                <table class="form-table" role="presentation">
                    <tr><th scope="row"><label for="zsv-position">Posisi tampilan</label></th><td>
                        <select id="zsv-position" name="zsv_settings[position]">
                            <?php foreach ( array( 'before' => 'Sebelum isi artikel', 'after' => 'Setelah isi artikel', 'manual' => 'Manual melalui shortcode' ) as $value => $label ) : ?>
                                <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $s['position'], $value ); ?>><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">Untuk Elementor, gunakan widget Shortcode dengan <code>[zsv_views]</code> jika posisi otomatis tidak sesuai.</p>
                    </td></tr>
                    <tr><th scope="row"><label for="zsv-label">Label</label></th><td><input class="regular-text" id="zsv-label" name="zsv_settings[label]" value="<?php echo esc_attr( $s['label'] ); ?>"><p class="description">Contoh: Dibaca: atau Views:. Kosongkan untuk hanya menampilkan angka.</p></td></tr>
                    <tr><th scope="row">Kategori yang menampilkan views</th><td>
                        <fieldset><legend class="screen-reader-text">Kategori yang menampilkan views</legend>
                            <?php foreach ( $categories as $category ) : ?>
                                <label style="display:block;margin-bottom:6px"><input type="checkbox" name="zsv_settings[categories][]" value="<?php echo esc_attr( $category->term_id ); ?>" <?php checked( in_array( (int) $category->term_id, array_map( 'intval', $s['categories'] ), true ) ); ?>> <?php echo esc_html( $category->name ); ?></label>
                            <?php endforeach; ?>
                        </fieldset><p class="description">Tidak ada yang dicentang = semua kategori. Subkategori harus dipilih sendiri. Artikel cukup memiliki salah satu kategori yang dipilih.</p>
                    </td></tr>
                    <tr><th scope="row"><label for="zsv-interval">Jeda penghitungan ulang</label></th><td><input type="number" min="1" max="1440" id="zsv-interval" name="zsv_settings[interval]" value="<?php echo esc_attr( $s['interval'] ); ?>"> menit<p class="description">Browser yang sama dihitung kembali setelah jeda ini. Default 30 menit, bergantung pada cookie browser.</p></td></tr>
                    <tr><th scope="row">Pengguna login</th><td><label><input type="checkbox" name="zsv_settings[exclude_logged_in]" value="1" <?php checked( $s['exclude_logged_in'], 1 ); ?>> Jangan hitung kunjungan pengguna yang sedang login</label></td></tr>
                </table>
                <?php submit_button(); ?>
            </form>
            <h2>Mengubah jumlah views</h2><p>Buka editor artikel, lalu cari kotak <strong>Zona Views</strong>. Administrator dan editor dapat mengubah angka tampil. Views otomatis tetap tersimpan terpisah.</p>
            <p>Shortcode: <code>[zsv_views]</code> untuk artikel saat ini, atau <code>[zsv_views id="123"]</code> untuk artikel tertentu. Filter kategori tetap berlaku.</p>
            <?php self::import_panel(); ?>
        </div>
        <?php
    }

    private static function can_adjust( $post_id ) {
        return current_user_can( 'edit_others_posts' ) && current_user_can( 'edit_post', $post_id );
    }

    public static function meta_box( $post ) {
        if ( self::can_adjust( $post->ID ) ) {
            add_meta_box( 'zsv-box', 'Zona Views', array( __CLASS__, 'box' ), 'post', 'side', 'default' );
        }
    }

    public static function box( $post ) {
        $n = self::numbers( $post->ID );
        wp_nonce_field( 'zsv_save_' . $post->ID, 'zsv_nonce' );
        ?>
        <p>Views otomatis: <strong><?php echo esc_html( number_format_i18n( $n['actual'] ) ); ?></strong><br>Penyesuaian manual: <strong><?php echo esc_html( number_format_i18n( $n['adjustment'] ) ); ?></strong></p>
        <?php if ( $n['imported'] > 0 ) : ?>
            <p class="description">Termasuk <?php echo esc_html( number_format_i18n( $n['imported'] ) ); ?> views dari Post Views Counter.</p>
        <?php endif; ?>
        <p><label for="zsv-total">Jumlah yang ditampilkan</label><br><input style="width:100%" type="number" min="0" max="1000000000" step="1" id="zsv-total" name="zsv_total" value="<?php echo esc_attr( $n['total'] ); ?>"></p>
        <input type="hidden" name="zsv_baseline" value="<?php echo esc_attr( $n['actual'] ); ?>">
        <input type="hidden" name="zsv_original" value="<?php echo esc_attr( $n['total'] ); ?>">
        <p class="description">Ubah angka lalu klik Perbarui/Simpan artikel. Kunjungan baru tetap menambah angka ini. Views yang masuk selama Anda mengedit tetap dipertahankan.</p>
        <?php
        $audit = get_post_meta( $post->ID, '_zsv_last_adjustment', true );
        if ( is_array( $audit ) && isset( $audit['user'], $audit['time'] ) ) {
            $user = get_userdata( $audit['user'] );
            echo '<p class="description">Terakhir disesuaikan: ' . esc_html( $user ? $user->display_name : 'Pengguna terhapus' ) . ', ' . esc_html( wp_date( 'd/m/Y H:i', (int) $audit['time'] ) ) . '.</p>';
        }
    }

    public static function save_views( $post_id ) {
        if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! self::can_adjust( $post_id ) ) { return; }
        if ( ! isset( $_POST['zsv_nonce'] ) || ! is_string( $_POST['zsv_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['zsv_nonce'] ) ), 'zsv_save_' . $post_id ) ) { return; }
        foreach ( array( 'zsv_total', 'zsv_baseline', 'zsv_original' ) as $field ) {
            if ( ! isset( $_POST[$field] ) || ! is_scalar( $_POST[$field] ) || ! preg_match( '/^\d{1,10}$/', (string) $_POST[$field] ) || (int) $_POST[$field] > 1000000000 ) { return; }
        }
        $total = (int) $_POST['zsv_total'];
        if ( $total === (int) $_POST['zsv_original'] ) { return; }
        // Baseline from the editor preserves traffic arriving while the form was open.
        $adjustment = $total - (int) $_POST['zsv_baseline'];
        global $wpdb;
        $table = self::table();
        $result = $wpdb->query( $wpdb->prepare(
            "INSERT INTO $table (post_id, actual, adjustment) VALUES (%d, 0, %d) ON DUPLICATE KEY UPDATE adjustment = VALUES(adjustment)",
            $post_id, $adjustment
        ) );
        if ( false !== $result ) {
            update_post_meta( $post_id, '_zsv_last_adjustment', array( 'user' => get_current_user_id(), 'time' => time(), 'target' => $total ) );
        }
    }

    private static function public_post( $post_id ) {
        $post = get_post( $post_id );
        return $post && 'post' === $post->post_type && 'publish' === $post->post_status && '' === $post->post_password;
    }

    private static function allowed( $post_id ) {
        $s = self::settings();
        return self::public_post( $post_id ) && ( empty( $s['categories'] ) || has_category( array_map( 'intval', $s['categories'] ), $post_id ) );
    }

    public static function enqueue() {
        // Register assets before wp_head, including shortcode use on other pages.
        // The script makes no request on pages without a post or views shortcode.
        self::assets();
    }

    private static function assets() {
        wp_enqueue_style( 'zona-simple-views', plugins_url( 'assets/views.css', __FILE__ ), array(), self::VERSION );
        wp_enqueue_script( 'zona-simple-views', plugins_url( 'assets/views.js', __FILE__ ), array(), self::VERSION, true );
        $post_id = is_singular( 'post' ) && ! is_preview() && self::public_post( get_queried_object_id() ) ? (int) get_queried_object_id() : 0;
        wp_localize_script( 'zona-simple-views', 'ZonaViews', array( 'url' => admin_url( 'admin-ajax.php' ), 'postId' => $post_id ) );
    }

    public static function render( $post_id ) {
        $post_id = absint( $post_id );
        if ( ! self::allowed( $post_id ) ) { return ''; }
        self::assets();
        $s = self::settings();
        $n = self::numbers( $post_id );
        $label = '' !== $s['label'] ? '<span class="zsv-label">' . esc_html( $s['label'] ) . '</span> ' : '';
        return '<div class="zsv-views" data-zsv-post="' . esc_attr( $post_id ) . '">' . $label . '<span class="zsv-count">' . esc_html( number_format_i18n( $n['total'] ) ) . '</span></div>';
    }

    public static function shortcode( $atts ) {
        $atts = shortcode_atts( array( 'id' => 0 ), $atts, 'zsv_views' );
        return self::render( absint( $atts['id'] ) ?: get_the_ID() );
    }

    public static function content( $content ) {
        if ( is_admin() || ! is_singular( 'post' ) || is_preview() || ! in_the_loop() || ! is_main_query() || (int) get_the_ID() !== (int) get_queried_object_id() ) { return $content; }
        $s = self::settings();
        if ( 'manual' === $s['position'] || has_shortcode( get_post_field( 'post_content', get_the_ID() ), 'zsv_views' ) ) { return $content; }
        $html = self::render( get_the_ID() );
        return 'before' === $s['position'] ? $html . $content : $content . $html;
    }

    private static function request_post() {
        $id = isset( $_POST['post_id'] ) && is_scalar( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        if ( ! self::public_post( $id ) ) { wp_send_json_error( array( 'message' => 'Artikel tidak tersedia.' ), 404 ); }
        return $id;
    }

    public static function boot() {
        nocache_headers();
        $id = self::request_post();
        $n = self::numbers( $id );
        wp_send_json_success( array( 'total' => $n['total'], 'formatted' => number_format_i18n( $n['total'] ), 'nonce' => wp_create_nonce( 'zsv_count_' . $id ) ) );
    }

    private static function same_origin() {
        $source = $_SERVER['HTTP_ORIGIN'] ?? ( $_SERVER['HTTP_REFERER'] ?? '' );
        if ( ! is_string( $source ) || '' === $source ) { return false; }
        $host = strtolower( (string) wp_parse_url( $source, PHP_URL_HOST ) );
        return $host === strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
    }

    private static function seen() {
        $name = self::cookie_name();
        $raw = isset( $_COOKIE[$name] ) && is_string( $_COOKIE[$name] ) ? wp_unslash( $_COOKIE[$name] ) : '';
        $parts = explode( '.', $raw, 2 );
        if ( count( $parts ) !== 2 || ! hash_equals( hash_hmac( 'sha256', $parts[0], wp_salt( 'auth' ) ), $parts[1] ) ) { return array(); }
        $map = json_decode( base64_decode( $parts[0], true ), true );
        if ( ! is_array( $map ) || count( $map ) > 30 ) { return array(); }
        return array_filter( $map, static function ( $timestamp ) { return is_int( $timestamp ) && $timestamp <= time() && $timestamp > time() - DAY_IN_SECONDS; } );
    }

    private static function remember( $map, $id ) {
        $map[$id] = time();
        asort( $map, SORT_NUMERIC );
        $map = array_slice( $map, -30, null, true );
        $encoded = base64_encode( wp_json_encode( $map ) );
        $value = $encoded . '.' . hash_hmac( 'sha256', $encoded, wp_salt( 'auth' ) );
        setcookie( self::cookie_name(), $value, array( 'expires' => time() + DAY_IN_SECONDS, 'path' => '/', 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax' ) );
    }

    public static function count() {
        nocache_headers();
        if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! self::same_origin() ) { wp_send_json_error( array( 'message' => 'Permintaan tidak diizinkan.' ), 403 ); }
        $id = self::request_post();
        if ( ! check_ajax_referer( 'zsv_count_' . $id, 'nonce', false ) ) { wp_send_json_error( array( 'message' => 'Token kedaluwarsa.' ), 403 ); }
        $s = self::settings();
        $ua = is_string( $_SERVER['HTTP_USER_AGENT'] ?? null ) ? $_SERVER['HTTP_USER_AGENT'] : '';
        $excluded = ( ! empty( $s['exclude_logged_in'] ) && is_user_logged_in() ) || '' === $ua || preg_match( '/bot|crawler|spider|slurp|headless|facebookexternalhit|preview|curl|wget/i', $ua );
        $map = self::seen();
        $duplicate = isset( $map[$id] ) && time() - (int) $map[$id] < (int) $s['interval'] * MINUTE_IN_SECONDS;
        $counted = false;
        if ( ! $excluded && ! $duplicate ) {
            global $wpdb;
            $table = self::table();
            // Atomic increment prevents lost updates from concurrent visitors.
            $result = $wpdb->query( $wpdb->prepare( "INSERT INTO $table (post_id, actual, adjustment) VALUES (%d, 1, 0) ON DUPLICATE KEY UPDATE actual = actual + 1", $id ) );
            if ( false === $result ) { wp_send_json_error( array( 'message' => 'Views belum tersimpan.' ), 500 ); }
            self::remember( $map, $id );
            $counted = true;
        }
        $n = self::numbers( $id );
        wp_send_json_success( array( 'total' => $n['total'], 'formatted' => number_format_i18n( $n['total'] ), 'counted' => $counted ) );
    }

    public static function columns( $columns ) {
        $columns['zsv_views'] = 'Zona Views';
        return $columns;
    }

    public static function column( $column, $post_id ) {
        if ( 'zsv_views' !== $column ) { return; }
        $n = self::numbers( $post_id );
        echo '<span title="' . esc_attr( 'Otomatis: ' . $n['actual'] . '; penyesuaian: ' . $n['adjustment'] ) . '">' . esc_html( number_format_i18n( $n['total'] ) ) . '</span>';
        if ( self::can_adjust( $post_id ) ) {
            echo '<span class="zsv-row-data" hidden data-zsv-nonce="' . esc_attr( wp_create_nonce( 'zsv_save_' . $post_id ) ) . '"></span>';
        }
    }

    public static function delete_post( $post_id ) {
        global $wpdb;
        $wpdb->delete( self::table(), array( 'post_id' => $post_id ), array( '%d' ) );
    }

    private static function source_table() {
        global $wpdb;
        return $wpdb->prefix . 'post_views';
    }

    private static function source_exists() {
        global $wpdb;
        $source = self::source_table();
        return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $source ) ) ) === $source;
    }

    public static function admin_assets( $hook ) {
        if ( 'edit.php' === $hook ) {
            $screen = get_current_screen();
            if ( $screen && 'post' === $screen->post_type && current_user_can( 'edit_others_posts' ) ) {
                wp_enqueue_script( 'zona-views-quick-edit', plugins_url( 'assets/quick-edit.js', __FILE__ ), array( 'inline-edit-post' ), self::VERSION, true );
                wp_localize_script( 'zona-views-quick-edit', 'ZonaViewsQuickEdit', array( 'url' => admin_url( 'admin-ajax.php' ) ) );
            }
            return;
        }
        if ( 'settings_page_zona-simple-views' !== $hook ) { return; }
        wp_enqueue_script( 'zona-views-import', plugins_url( 'assets/import.js', __FILE__ ), array(), self::VERSION, true );
        wp_localize_script( 'zona-views-import', 'ZonaViewsImport', array( 'url' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'zsv_import' ) ) );
    }

    public static function quick_box( $column, $post_type ) {
        if ( 'zsv_views' !== $column || 'post' !== $post_type || ! current_user_can( 'edit_others_posts' ) ) { return; }
        ?>
        <fieldset class="inline-edit-col-right zsv-quick-box">
            <div class="inline-edit-col">
                <label><span class="title">Zona Views</span><span class="input-text-wrap"><input type="number" name="zsv_total" min="0" max="1000000000" step="1" value="" disabled></span></label>
                <input type="hidden" name="zsv_nonce" value="">
                <input type="hidden" name="zsv_baseline" value="">
                <input type="hidden" name="zsv_original" value="">
                <p class="zsv-quick-status" role="status" aria-live="polite">Memuat views terbaru…</p>
                <p class="description">Ubah angka tampil lalu klik Perbarui. Views otomatis tetap tersimpan dan kunjungan baru tetap ditambahkan.</p>
            </div>
        </fieldset>
        <?php
    }

    public static function quick_data() {
        nocache_headers();
        $id = isset( $_POST['post_id'] ) && is_scalar( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        if ( ! $id || ! self::can_adjust( $id ) || 'post' !== get_post_type( $id ) ) {
            wp_send_json_error( array( 'message' => 'Anda tidak memiliki izin mengubah views artikel ini.' ), 403 );
        }
        if ( ! check_ajax_referer( 'zsv_save_' . $id, 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => 'Sesi kedaluwarsa. Muat ulang daftar artikel.' ), 403 );
        }
        $n = self::numbers( $id );
        $n['nonce'] = wp_create_nonce( 'zsv_save_' . $id );
        $n['description'] = 'Views otomatis: ' . number_format_i18n( $n['actual'] ) . '; penyesuaian: ' . number_format_i18n( $n['adjustment'] ) . '.';
        wp_send_json_success( $n );
    }

    private static function import_panel() {
        $state = get_option( 'zsv_import_state', array() );
        $exists = self::source_exists();
        ?>
        <hr>
        <h2>Impor dari Post Views Counter</h2>
        <p>Salin total views semua artikel dalam kelompok 200 artikel. Data asal tetap tersimpan dan impor ulang tidak menggandakan angka.</p>
        <p>Nonaktifkan Post Views Counter lama sebelum memulai agar kunjungan tidak dihitung oleh dua plugin selama perpindahan. Cukup Deactivate, jangan hapus datanya.</p>
        <p>Views lama ditambahkan ke views otomatis Zona yang sudah tercatat. Penyesuaian manual yang pernah Anda buat tetap dipertahankan.</p>
        <?php if ( ! $exists ) : ?>
            <p><strong>Tabel data Post Views Counter belum ditemukan pada situs ini.</strong> Jika plugin lama sudah dihapus beserta datanya, pulihkan data dari cadangan terlebih dahulu.</p>
        <?php endif; ?>
        <p><button type="button" class="button button-primary" id="zsv-import" <?php disabled( ! $exists ); ?>><?php echo ! empty( $state['running'] ) ? 'Lanjutkan impor' : 'Impor views lama'; ?></button></p>
        <p id="zsv-import-status" role="status" aria-live="polite">
            <?php if ( ! empty( $state['run'] ) ) : ?>
                <?php echo esc_html( sprintf( '%s: %s dari %s artikel diperiksa; %s artikel disalin, %s sudah pernah diimpor.', ! empty( $state['running'] ) ? 'Impor belum selesai' : 'Impor selesai', number_format_i18n( (int) $state['processed'] ), number_format_i18n( (int) $state['total'] ), number_format_i18n( (int) $state['copied'] ), number_format_i18n( (int) $state['skipped'] ) ) ); ?>
            <?php else : ?>Belum ada proses impor.<?php endif; ?>
        </p>
        <p class="description">Biarkan halaman ini terbuka sampai selesai. Jika terputus, buka kembali halaman ini dan klik Lanjutkan impor. Setelah selesai, bersihkan cache situs.</p>
        <?php
    }

    // An option with a unique name provides a database-backed exclusive lock.
    private static function import_lock() {
        $token = wp_generate_uuid4();
        $old = get_option( 'zsv_import_lock' );
        if ( is_array( $old ) && (int) ( $old['time'] ?? 0 ) < time() - 120 ) {
            // Conditional deletion prevents removing a newer process's lock.
            global $wpdb;
            $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", 'zsv_import_lock', maybe_serialize( $old ) ) );
            wp_cache_delete( 'zsv_import_lock', 'options' );
        }
        if ( ! add_option( 'zsv_import_lock', array( 'token' => $token, 'time' => time() ), '', false ) ) {
            throw new RuntimeException( 'Impor sedang diproses. Coba lagi beberapa saat lagi.' );
        }
        return $token;
    }

    private static function import_unlock( $token ) {
        $lock = get_option( 'zsv_import_lock' );
        if ( is_array( $lock ) && ( $lock['token'] ?? '' ) === $token ) {
            delete_option( 'zsv_import_lock' );
        }
    }

    public static function import_ajax() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array( 'message' => 'Hanya administrator yang dapat mengimpor.' ), 403 ); }
        if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! check_ajax_referer( 'zsv_import', 'nonce', false ) ) { wp_send_json_error( array( 'message' => 'Sesi tidak valid. Muat ulang halaman pengaturan.' ), 403 ); }
        if ( function_exists( 'Post_Views_Counter' ) ) { wp_send_json_error( array( 'message' => 'Nonaktifkan Post Views Counter lama (Deactivate), lalu muat ulang halaman ini dan mulai impor. Jangan hapus datanya.' ), 400 ); }
        $token = null;
        try {
            $token = self::import_lock();
            if ( ! self::source_exists() ) { throw new RuntimeException( 'Tabel Post Views Counter tidak ditemukan.' ); }
            global $wpdb;
            $source = self::source_table();
            $state = get_option( 'zsv_import_state', array() );
            $mode = isset( $_POST['mode'] ) && is_string( $_POST['mode'] ) ? $_POST['mode'] : '';
            if ( 'start' === $mode ) {
                if ( empty( $state['running'] ) ) {
                    $summary = $wpdb->get_row( "SELECT COUNT(*) AS total, COALESCE(MAX(v.id), 0) AS max_id FROM $source v INNER JOIN {$wpdb->posts} p ON p.ID = v.id WHERE v.type = 4 AND v.period = 'total' AND p.post_type = 'post'", ARRAY_A );
                    if ( null === $summary || $wpdb->last_error ) { throw new RuntimeException( 'Data sumber tidak dapat dibaca. Periksa struktur tabel Post Views Counter.' ); }
                    $state = array( 'run' => wp_generate_uuid4(), 'cursor' => 0, 'max_id' => (int) $summary['max_id'], 'total' => (int) $summary['total'], 'processed' => 0, 'copied' => 0, 'skipped' => 0, 'running' => (int) $summary['total'] > 0 );
                    update_option( 'zsv_import_state', $state, false );
                }
            } elseif ( 'step' === $mode ) {
                $run = isset( $_POST['run'] ) && is_string( $_POST['run'] ) ? $_POST['run'] : '';
                if ( empty( $state['run'] ) || ! hash_equals( $state['run'], $run ) ) { throw new RuntimeException( 'Proses impor berubah. Muat ulang halaman dan lanjutkan.' ); }
                if ( ! empty( $state['running'] ) ) { $state = self::import_batch( $state ); }
            } else { throw new RuntimeException( 'Perintah impor tidak dikenal.' ); }
        } catch ( RuntimeException $error ) {
            if ( $token ) { self::import_unlock( $token ); }
            wp_send_json_error( array( 'message' => $error->getMessage() ), 400 );
            return;
        }
        self::import_unlock( $token );
        wp_send_json_success( $state );
    }

    public static function import_batch( $state ) {
        global $wpdb;
        $source = self::source_table();
        $target = self::table();
        // Import the lifetime aggregate only; daily/monthly rows overlap it.
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT v.id, v.count AS views, z.imported FROM $source v INNER JOIN {$wpdb->posts} p ON p.ID = v.id LEFT JOIN $target z ON z.post_id = v.id WHERE v.type = 4 AND v.period = 'total' AND p.post_type = 'post' AND v.id > %d AND v.id <= %d ORDER BY v.id ASC LIMIT 200",
            $state['cursor'], $state['max_id']
        ), ARRAY_A );
        if ( $wpdb->last_error || ! is_array( $rows ) ) { throw new RuntimeException( 'Tidak dapat membaca kelompok artikel. Klik Lanjutkan impor untuk mencoba lagi.' ); }
        if ( empty( $rows ) ) {
            $state['running'] = false;
            update_option( 'zsv_import_state', $state, false );
            return $state;
        }
        $values = array();
        $args = array();
        $copied = 0;
        foreach ( $rows as $row ) {
            $views = max( 0, (int) $row['views'] );
            $values[] = '(%d, %d, 0, %d)';
            array_push( $args, (int) $row['id'], $views, $views );
            if ( null === $row['imported'] ) { $copied++; }
        }
        // The nullable imported column is a per-post idempotency marker.
        // If an AJAX response is lost, retrying the batch cannot add views twice.
        $sql = "INSERT INTO $target (post_id, actual, adjustment, imported) VALUES " . implode( ', ', $values ) . ' ON DUPLICATE KEY UPDATE actual = IF(imported IS NULL, actual + VALUES(imported), actual), imported = COALESCE(imported, VALUES(imported))';
        if ( false === $wpdb->query( $wpdb->prepare( $sql, $args ) ) ) { throw new RuntimeException( 'Tidak dapat menyimpan kelompok artikel. Klik Lanjutkan impor untuk mencoba lagi.' ); }
        $state['cursor'] = (int) end( $rows )['id'];
        $state['processed'] += count( $rows );
        $state['copied'] += $copied;
        $state['skipped'] += count( $rows ) - $copied;
        $state['running'] = count( $rows ) === 200 && $state['cursor'] < $state['max_id'];
        update_option( 'zsv_import_state', $state, false );
        return $state;
    }
}

register_activation_hook( __FILE__, array( 'Zona_Simple_Views', 'activate' ) );
Zona_Simple_Views::init();
