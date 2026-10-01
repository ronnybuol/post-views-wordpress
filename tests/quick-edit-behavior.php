<?php
require __DIR__ . '/php-behavior.php';
function get_post_type( $id ) { $post = get_post( $id ); return $post ? $post->post_type : false; }
function run_quick_data() {
    try { Zona_Simple_Views::quick_data(); } catch ( JsonResult $result ) { return $result; }
    throw new Exception( 'Expected quick-edit JSON response' );
}
$before = $passed;
$wpdb->rows[42] = array( 'actual' => 5005, 'adjustment' => 100, 'imported' => 5000 );
$_POST = array( 'post_id' => '42', 'nonce' => 'ok' );
$result = run_quick_data();
check( $result->status === 200 && $result->payload['total'] === 5105 && $result->payload['actual'] === 5005, 'quick edit reads fresh database values' );
check( $result->payload['imported'] === 5000 && $result->payload['nonce'] === 'fresh-zsv_save_42', 'fresh nonce and imported baseline retained' );
$role_allowed = false;
check( run_quick_data()->status === 403, 'author without editor privileges rejected' );
$role_allowed = true; $nonce_valid = false;
check( run_quick_data()->status === 403, 'invalid nonce rejected' );
$nonce_valid = true; $_POST['post_id'] = 0;
check( run_quick_data()->status === 403, 'missing post rejected' );
$_POST['post_id'] = 123456;
check( run_quick_data()->status === 403, 'nonexistent post rejected' );
$_POST = array( 'action' => 'inline-save', 'zsv_nonce' => 'ok', 'zsv_total' => '10000', 'zsv_baseline' => '5005', 'zsv_original' => '5105' );
$wpdb->rows[42]['actual'] += 4;
Zona_Simple_Views::save_views( 42 );
check( Zona_Simple_Views::numbers( 42 )['total'] === 10004, 'quick-edit adjustment preserves traffic arriving after form open' );
check( Zona_Simple_Views::numbers( 42 )['actual'] === 5009 && Zona_Simple_Views::numbers( 42 )['imported'] === 5000, 'automatic and imported counts remain intact' );
$q = $wpdb->queries;
$_POST['zsv_total'] = $_POST['zsv_original'];
Zona_Simple_Views::save_views( 42 );
check( $q === $wpdb->queries, 'saving other quick-edit fields never resets views' );
echo 'PASS: ' . ( $passed - $before ) . ' quick-edit server checks.' . PHP_EOL;
