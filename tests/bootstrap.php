<?php
/**
 * Bootstrap.
 *
 * @package   Autoclose
 * @author    Ajay D'Souza
 * @license   GPL-2.0+
 * @link      https://ajaydsouza.com
 * @copyright 2008-2019 Ajay D'Souza
 */

$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
$_SERVER['SERVER_NAME']     = '';
$PHP_SELF                   = $GLOBALS['PHP_SELF'] = $_SERVER['PHP_SELF'] = '/index.php';

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = '/tmp/wordpress-tests-lib';
}

require_once $_tests_dir . '/includes/functions.php';

function _manually_load_plugin() {
	require dirname( dirname( __FILE__ ) ) . '/autoclose.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

require $_tests_dir . '/includes/bootstrap.php';

activate_plugin( 'autoclose/autoclose.php' );

echo "Installing Top 10...\n";

global $acc_settings, $current_user;

// acc_activation_hook( true );
$acc_settings = acc_get_settings();

