<?php
if ( !defined('ABSPATH') && !defined('WP_UNINSTALL_PLUGIN') ) {
    exit();
}

if ( wp_next_scheduled( 'ald_acc_hook' ) ) {
	wp_clear_scheduled_hook( 'ald_acc_hook' );
}

delete_option('ald_acc_settings');
?>