<?php
// Reuse the WordPress stubs and baseline regression checks.
require __DIR__ . '/php-behavior.php';
$stored_state = array();
function update_option( $name, $value, $autoload = null ) { global $stored_state; $stored_state[$name] = $value; return true; }
class ImportDB extends TestDB {
    public $posts = 'wp_posts';
    public $last_error = '';
    public $source = array();
    public $fail_write = false;
    public function prepare( $sql, ...$args ) {
        if ( count( $args ) === 1 && is_array( $args[0] ) ) { $args = $args[0]; }
        return vsprintf( $sql, $args );
    }
    public function get_results( $sql, $format ) {
        check( strpos( $sql, "v.type = 4 AND v.period = 'total'" ) !== false, 'lifetime aggregates only' );
        check( strpos( $sql, "p.post_type = 'post'" ) !== false, 'only article post type' );
        preg_match( '/v.id > (\d+) AND v.id <= (\d+)/', $sql, $match );
        $result = array();
        foreach ( $this->source as $id => $views ) {
            if ( $id <= (int) $match[1] || $id > (int) $match[2] ) { continue; }
            $result[] = array( 'id' => $id, 'views' => $views, 'imported' => $this->rows[$id]['imported'] ?? null );
            if ( count( $result ) === 200 ) { break; }
        }
        return $result;
    }
    public function query( $sql ) {
        if ( $this->fail_write ) { return false; }
        if ( strpos( $sql, 'adjustment, imported)' ) === false ) { return parent::query( $sql ); }
        check( strpos( $sql, 'actual = IF(imported IS NULL, actual + VALUES(imported), actual), imported = COALESCE(imported, VALUES(imported))' ) !== false, 'atomic idempotent SQL expression' );
        preg_match_all( '/\((\d+), (\d+), 0, (\d+)\)/', $sql, $matches, PREG_SET_ORDER );
        foreach ( $matches as $match ) {
            $id = (int) $match[1]; $views = (int) $match[2];
            if ( ! isset( $this->rows[$id] ) ) {
                $this->rows[$id] = array( 'actual' => $views, 'adjustment' => 0, 'imported' => $views );
            } elseif ( ! isset( $this->rows[$id]['imported'] ) ) {
                $this->rows[$id]['actual'] += $views;
                $this->rows[$id]['imported'] = $views;
            }
        }
        return count( $matches );
    }
}
$before = $passed;
$wpdb = new ImportDB();
for ( $i = 1; $i <= 2501; $i++ ) { $wpdb->source[$i] = $i * 10; }
$wpdb->source[1] = 0;
$wpdb->rows[42] = array( 'actual' => 5, 'adjustment' => 20 );
$state = array( 'run' => 'run-1', 'cursor' => 0, 'max_id' => 2501, 'total' => 2501, 'processed' => 0, 'copied' => 0, 'skipped' => 0, 'running' => true );
$start = $state;
$first = Zona_Simple_Views::import_batch( $state );
check( $first['processed'] === 200 && $first['running'], 'bounded batch processes 200 rows' );
check( $wpdb->rows[42]['actual'] === 425 && $wpdb->rows[42]['adjustment'] === 20, 'import keeps new traffic and manual offset' );
check( isset( $wpdb->rows[1]['imported'] ) && $wpdb->rows[1]['imported'] === 0, 'zero views marked as imported' );
$retry = Zona_Simple_Views::import_batch( $start );
check( $wpdb->rows[42]['actual'] === 425 && $retry['skipped'] === 200, 'lost response retry never duplicates views' );
$state = $first;
while ( $state['running'] ) { $state = Zona_Simple_Views::import_batch( $state ); }
check( $state['processed'] === 2501 && $state['copied'] === 2501 && count( $wpdb->rows ) === 2501, '2501 posts migrate to completion' );
check( $wpdb->rows[2501]['actual'] === 25010, 'last batch included' );
$reimport = $start;
while ( $reimport['running'] ) { $reimport = Zona_Simple_Views::import_batch( $reimport ); }
check( $reimport['copied'] === 0 && $reimport['skipped'] === 2501 && $wpdb->rows[2501]['actual'] === 25010, 'full repeated import is idempotent' );
$wpdb->fail_write = true;
$state_before_failure = $stored_state['zsv_import_state'];
try { Zona_Simple_Views::import_batch( $start ); check( false, 'failed write should throw' ); }
catch ( RuntimeException $e ) { check( $stored_state['zsv_import_state'] === $state_before_failure, 'failed write never advances persisted cursor' ); }
$wpdb->fail_write = false;
$wpdb->source = array();
$empty = Zona_Simple_Views::import_batch( $start );
check( ! $empty['running'], 'empty source finishes safely' );
function run_import_ajax() {
    try { Zona_Simple_Views::import_ajax(); } catch ( JsonResult $result ) { return $result; }
    throw new Exception( 'Expected import JSON result' );
}
function Post_Views_Counter() { return null; }
$role_allowed = false;
check( run_import_ajax()->status === 403, 'import requires administrator capability' );
$role_allowed = true; $nonce_valid = false;
check( run_import_ajax()->status === 403, 'import requires valid nonce' );
$nonce_valid = true;
check( run_import_ajax()->status === 400, 'import rejects active source counter' );
echo 'PASS: ' . ( $passed - $before ) . ' migration checks including 2501-post migration and complete rerun.' . PHP_EOL;
