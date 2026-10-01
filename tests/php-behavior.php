<?php
define( 'ABSPATH', '/tmp/' );
define( 'ARRAY_A', 'ARRAY_A' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'DAY_IN_SECONDS', 86400 );
$settings = array();
$role_allowed = true;
$logged_in = false;
$nonce_valid = true;
$nonce_saved = true;
$audit = array();
$posts = array(
    42 => (object) array( 'ID' => 42, 'post_type' => 'post', 'post_status' => 'publish', 'post_password' => '' ),
    99 => (object) array( 'ID' => 99, 'post_type' => 'post', 'post_status' => 'draft', 'post_password' => '' ),
    77 => (object) array( 'ID' => 77, 'post_type' => 'post', 'post_status' => 'publish', 'post_password' => 'secret' ),
);
function add_action() {} function add_filter() {} function add_shortcode() {} function register_activation_hook() {}
function get_option( $key, $default = false ) { global $settings; return $key === 'zsv_settings' ? $settings : $default; }
function wp_parse_args( $values, $defaults ) { return array_merge( $defaults, $values ); }
function absint( $value ) { return abs( (int) $value ); }
function sanitize_text_field( $text ) { return strip_tags( $text ); }
function wp_unslash( $value ) { return stripslashes( $value ); }
function current_user_can() { global $role_allowed; return $role_allowed; }
function wp_is_post_revision() { return false; }
function wp_verify_nonce() { global $nonce_saved; return $nonce_saved; }
function update_post_meta( $id, $key, $value ) { global $audit; $audit[$id] = $value; }
function get_current_user_id() { return 7; }
function get_current_blog_id() { return 1; }
function get_post( $id ) { global $posts; return $posts[$id] ?? null; }
function has_category( $categories, $id ) { return $id === 42 && in_array( 10, $categories, true ); }
function nocache_headers() {}
function wp_create_nonce( $action ) { return 'fresh-' . $action; }
function check_ajax_referer() { global $nonce_valid; return $nonce_valid; }
function number_format_i18n( $value ) { return number_format( $value, 0, ',', '.' ); }
function is_user_logged_in() { global $logged_in; return $logged_in; }
function wp_parse_url( $url, $component ) { return parse_url( $url, $component ); }
function home_url() { return 'https://example.test'; }
function wp_salt() { return 'test-salt'; }
function wp_json_encode( $value ) { return json_encode( $value ); }
function is_ssl() { return true; }
class JsonResult extends Exception {
    public $payload; public $status;
    public function __construct( $payload, $status ) { $this->payload = $payload; $this->status = $status; }
}
function wp_send_json_error( $data, $status = 400 ) { throw new JsonResult( $data, $status ); }
function wp_send_json_success( $data ) { throw new JsonResult( $data, 200 ); }
class TestDB {
    public $prefix = 'wp_'; public $rows = array(); public $queries = 0;
    public function prepare( $sql, ...$args ) { return vsprintf( $sql, $args ); }
    public function get_row( $sql, $mode ) {
        preg_match( '/post_id = (\d+)/', $sql, $match );
        return $this->rows[(int) $match[1]] ?? null;
    }
    public function query( $sql ) {
        $this->queries++;
        preg_match( '/VALUES \((\d+), (\d+), (-?\d+)\)/', $sql, $match );
        $id = (int) $match[1];
        if ( ! isset( $this->rows[$id] ) ) {
            $this->rows[$id] = array( 'actual' => (int) $match[2], 'adjustment' => (int) $match[3] );
        } elseif ( strpos( $sql, 'actual = actual + 1' ) !== false ) {
            $this->rows[$id]['actual']++;
        } else { $this->rows[$id]['adjustment'] = (int) $match[3]; }
        return 1;
    }
}
$wpdb = new TestDB();
require __DIR__ . '/../zona-simple-views/zona-simple-views.php';
$passed = 0;
function check( $condition, $name ) { global $passed; if ( ! $condition ) { throw new Exception( 'FAIL: ' . $name ); } $passed++; }
function run_count() {
    try { Zona_Simple_Views::count(); } catch ( JsonResult $result ) { return $result; }
    throw new Exception( 'Expected JSON result' );
}
check( Zona_Simple_Views::numbers( 42 )['total'] === 0, 'new post starts at zero' );
$wpdb->rows[42] = array( 'actual' => 123, 'adjustment' => 0 );
$_POST = array( 'zsv_nonce' => 'ok', 'zsv_total' => '1000', 'zsv_baseline' => '120', 'zsv_original' => '120' );
Zona_Simple_Views::save_views( 42 );
check( Zona_Simple_Views::numbers( 42 )['total'] === 1003, 'manual adjustment preserves three concurrent arrivals' );
check( Zona_Simple_Views::numbers( 42 )['actual'] === 123, 'manual adjustment preserves actual traffic' );
check( $audit[42]['user'] === 7, 'last editor saved' );
$q = $wpdb->queries;
$_POST['zsv_total'] = '120';
Zona_Simple_Views::save_views( 42 );
check( $wpdb->queries === $q, 'unchanged field does not overwrite offset' );
$_POST['zsv_total'] = '500'; $role_allowed = false;
Zona_Simple_Views::save_views( 42 );
check( $wpdb->queries === $q, 'unauthorized adjustment rejected' );
$role_allowed = true; $nonce_saved = false;
Zona_Simple_Views::save_views( 42 );
check( $wpdb->queries === $q, 'invalid editor nonce rejected' );
$nonce_saved = true;
$_POST['zsv_total'] = '-5'; Zona_Simple_Views::save_views( 42 );
check( $wpdb->queries === $q, 'negative target rejected' );
$_POST['zsv_total'] = '1000000001'; Zona_Simple_Views::save_views( 42 );
check( $wpdb->queries === $q, 'oversized target rejected' );
$_POST['zsv_total'] = '0'; $_POST['zsv_baseline'] = '123'; Zona_Simple_Views::save_views( 42 );
check( Zona_Simple_Views::numbers( 42 )['total'] === 0, 'manual lowering supported' );
$_POST = array( 'post_id' => '42', 'nonce' => 'fresh' );
$_SERVER = array( 'REQUEST_METHOD' => 'POST', 'HTTP_ORIGIN' => 'https://example.test', 'HTTP_USER_AGENT' => 'Mozilla/5.0 Chrome/130' );
$result = run_count();
check( $result->status === 200 && $result->payload['counted'] && $result->payload['total'] === 1, 'new visit increments both actual and adjusted total' );
$encoded = base64_encode( json_encode( array( 42 => time() ) ) );
$_COOKIE['zsv_seen_1'] = $encoded . '.' . hash_hmac( 'sha256', $encoded, 'test-salt' );
$result = run_count();
check( ! $result->payload['counted'], 'signed cookie prevents repeat counting' );
$_COOKIE = array(); $logged_in = true;
$result = run_count(); check( ! $result->payload['counted'], 'logged-in user excluded' );
$logged_in = false; $_SERVER['HTTP_USER_AGENT'] = 'Googlebot';
$result = run_count(); check( ! $result->payload['counted'], 'known bot excluded' );
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0'; $_SERVER['HTTP_ORIGIN'] = 'https://attacker.test';
check( run_count()->status === 403, 'cross-origin count rejected' );
$_SERVER['HTTP_ORIGIN'] = 'https://example.test'; $nonce_valid = false;
check( run_count()->status === 403, 'count nonce required' );
$nonce_valid = true; $_POST['post_id'] = '99';
check( run_count()->status === 404, 'draft not publicly counted' );
$_POST['post_id'] = '77'; check( run_count()->status === 404, 'protected post not counted' );
$settings = array( 'categories' => array( 10 ) );
$method = new ReflectionMethod( 'Zona_Simple_Views', 'allowed' ); if ( PHP_VERSION_ID < 80100 ) { $method->setAccessible( true ); }
check( $method->invoke( null, 42 ), 'matching category allowed' );
$settings = array( 'categories' => array( 20 ) );
check( ! $method->invoke( null, 42 ), 'other category hidden' );
$settings = array( 'categories' => array() );
check( $method->invoke( null, 42 ), 'empty category selection allows all published posts' );
$sanitized = Zona_Simple_Views::sanitize_settings( array( 'position' => 'invalid', 'label' => '<b>Views</b>', 'interval' => '99999', 'categories' => array( '10', '10', '0' ) ) );
check( $sanitized['position'] === 'after' && $sanitized['interval'] === 1440 && $sanitized['label'] === 'Views' && $sanitized['categories'] === array( 10 ), 'settings sanitized and bounded' );
echo 'PASS: ' . $passed . ' PHP behavior checks.' . PHP_EOL;
