<?php
/**********************************************************************
*					Admin Page										*
*********************************************************************/
if ( ! defined( 'ABSPATH' ) ) die( "Aren't you supposed to come here via WP-Admin?" );

/**
 * Plugin settings page.
 * 
 * @access public
 * @return void
 */
function acc_options() {
	
    global $wpdb;
    $poststable = $wpdb->posts;
	$acc_settings = acc_read_options();

	if ( ( isset( $_POST['acc_save'] ) || isset( $_POST['run_once'] ) ) && ( check_admin_referer( 'acc-plugin' ) ) ) {
	
		$acc_settings['comment_age'] = intval( $_POST['comment_age'] );
		$acc_settings['pbtb_age'] = intval( $_POST['pbtb_age'] );
		$crp_settings['comment_pids'] = $_POST['comment_pids'] == '' ? '' : implode( ',', array_map( 'intval', explode( ",", $_POST['comment_pids'] ) ) );
		$crp_settings['pbtb_pids'] = $_POST['pbtb_pids'] == '' ? '' : implode( ',', array_map( 'intval', explode( ",", $_POST['pbtb_pids'] ) ) );
		$acc_settings['close_comment'] = isset( $_POST['close_comment'] ) ? true : false;
		$acc_settings['close_comment_pages'] = isset( $_POST['close_comment_pages'] ) ? true : false;
		$acc_settings['close_pbtb'] = isset( $_POST['close_pbtb'] ) ? true : false;
		$acc_settings['close_pbtb_pages'] = isset( $_POST['close_pbtb_pages'] ) ? true : false;
		$acc_settings['delete_revisions'] = isset( $_POST['delete_revisions'] ) ? true : false;
		$acc_settings['cron_hour'] = max( 23, intval( $_POST['cron_hour'] ) );
		$acc_settings['cron_min'] = max( 59, intval( $_POST['cron_min'] ) );

		if ( isset( $_POST['daily_run'] ) ) {
			$acc_settings['daily_run'] = true;
			acc_enable_run( $acc_settings['cron_hour'], $acc_settings['cron_min'] );
		} else {
			$acc_settings['daily_run'] = false;
			acc_disable_run();
		}
			
		update_option('ald_acc_settings', $acc_settings);
		
		if ( isset( $_POST['acc_save'] ) ) {
			echo '<div id="message" class="updated fade"><p>'. __('Options saved successfully.','ald_autoclose_plugin') .'</p></div>';
		} else {
			ald_acc();
			
			echo '<div id="message" class="updated fade">';
			if ( $acc_settings['close_comment'] ) {
			    echo "<p><strong>". __('Comments on posts closed upto','ald_autoclose_plugin') .":</strong> ";
				echo date('F j, Y, g:i a', (time() - $acc_settings[comment_age] * 86400));
				echo "</p>";
			}
			if ( $acc_settings['close_pbtb'] ) {
				echo "<p><strong>". __('Pingbacks/Trackbacks on posts closed upto','ald_autoclose_plugin') .": </strong> ";
				echo date('F j, Y, g:i a', (time() - $acc_settings[pbtb_age] * 86400));
				echo "</p>";
			}
			if ( $acc_settings['close_comment_pages'] ) {
			    echo "<p><strong>". __('Comments on pages closed upto','ald_autoclose_plugin') .":</strong> ";
				echo date('F j, Y, g:i a', (time() - $acc_settings[comment_age] * 86400));
				echo "</p>";
			}
			if ( $acc_settings['close_pbtb_pages'] ) {
				echo "<p><strong>". __('Pingbacks/Trackbacks on pages closed upto','ald_autoclose_plugin') .": </strong> ";
				echo date('F j, Y, g:i a', (time() - $acc_settings[pbtb_age] * 86400));
				echo "</p>";
			}
			if ( $acc_settings['delete_revisions'] ) {
				echo "<p><strong>". __('Post revisions deleted','ald_autoclose_plugin') ."</strong></p>";
			}
			echo '<p>'. __('Options saved successfully.','ald_autoclose_plugin') .'</p></div>';
		}
	}
	
	if ( ( isset( $_POST['acc_default'] ) ) && ( check_admin_referer( 'acc-plugin' ) ) ) {
	
		delete_option('ald_acc_settings');
		$acc_settings = acc_default_options();
		update_option('ald_acc_settings', $acc_settings);
		acc_disable_run();
		
		echo '<div id="message" class="updated fade"><p>'. __('Options set to Default.','ald_autoclose_plugin') .'</p></div>';
	}

	if ( ( isset( $_POST['acc_opencomments'] ) ) && ( check_admin_referer( 'acc-plugin' ) ) ) {
		$wpdb->query("
			UPDATE $poststable
			SET comment_status = 'open'
			WHERE comment_status = 'closed'
			AND post_status = 'publish'
		");
	
		echo '<div id="message" class="updated fade"><p>'. __('Comments opened on all posts','ald_autoclose_plugin') .'</p></div>';
	}

	if ( ( isset( $_POST['acc_openpings'] ) ) && ( check_admin_referer( 'acc-plugin' ) ) ) {
		$wpdb->query("
			UPDATE $poststable
			SET ping_status = 'open'
			WHERE ping_status = 'closed'
			AND post_status = 'publish'
		");
	
		echo '<div id="message" class="updated fade"><p>'. __('Pingbacks/Trackbacks opened on all posts','ald_autoclose_plugin') .'</p></div>';
	}

	if ( function_exists('wp_schedule_event') ) {
		if (wp_next_scheduled('ald_acc_hook')) {
			$ald_acc_info['hook_schedule'] = wp_get_schedule('ald_acc_hook');
			$ald_acc_info['next_run'] = date("F j, Y, g:i a", wp_next_scheduled('ald_acc_hook'));
			$ald_acc_info['comments_date'] =  date("F j, Y, g:i a", (wp_next_scheduled('ald_acc_hook') - $acc_settings[comment_age] * 86400));
			$ald_acc_info['pbtb_date'] =  date("F j, Y, g:i a", (wp_next_scheduled('ald_acc_hook') - $acc_settings[pbtb_age] * 86400));
		}
	}

?>


<div class="wrap">
  <h2>
    <?php _e("Auto-Close Comments, Pingbacks and Trackbacks", 'ald_autoclose_plugin'); ?>
  </h2>
  <div style="border: #ccc 1px solid; padding: 10px">
    <fieldset class="options">
    <legend>
    <h3>
      <?php _e('Support the Development', 'ald_autoclose_plugin'); ?>
    </h3>
    </legend>
    <p><?php _e('If you find my', 'ald_autoclose_plugin'); ?> <a href="http://ajaydsouza.com/wordpress/plugins/auto-close-comments/">Auto-Close Comments, Pingbacks and Trackbacks</a> <?php _e('useful, please do', 'ald_autoclose_plugin'); ?> <a href="https://www.paypal.com/cgi-bin/webscr?cmd=_xclick&amp;business=donate@ajaydsouza.com&amp;item_name=Auto-Close%20Comments,%20Pingbacks%20and%20Trackbacks%20(From%20WP-Admin)&amp;no_shipping=1&amp;return=http://ajaydsouza.com/wordpress/plugins/auto-close-comments/&amp;cancel_return=http://ajaydsouza.com/wordpress/plugins/auto-close-comments/&amp;cn=Note%20to%20Author&amp;tax=0&amp;currency_code=USD&amp;bn=PP-DonationsBF&amp;charset=UTF-8" title="Donate via PayPal"><?php _e('drop in your contribution', 'ald_autoclose_plugin'); ?></a>. (<a href="http://ajaydsouza.com/donate/" title="Some reasons why you should donate"><?php _e('Why should you?', 'ald_autoclose_plugin'); ?></a>)</p>
    </fieldset>
  </div>
  <div style="border: #ccc 1px solid; padding: 10px">
    <fieldset class="options">
    <legend>
    <h3>
      <?php _e('Information', 'ald_autoclose_plugin'); ?>
    </h3>
    </legend>
	<?php if (wp_next_scheduled('ald_acc_hook')) { ?>
	    <p><strong><?php _e('Schedule: ', 'ald_autoclose_plugin'); ?></strong> <?php echo $ald_acc_info['hook_schedule']; ?></p>
	    <?php if ($acc_settings[close_comment]) { ?>
			<p><strong><?php _e('Comments closed upto: ', 'ald_autoclose_plugin'); ?></strong> <?php echo $ald_acc_info['comments_date']; ?></p>
		<?php } ?>

	    <?php if ($acc_settings[close_pbtb]) { ?>
			<p><strong><?php _e('Pingbacks/Trackbacks closed upto: ', 'ald_autoclose_plugin'); ?></strong> <?php echo $ald_acc_info['pbtb_date']; ?></p>
		<?php } ?>

	    <?php if ($acc_settings[comment_pids]!='') { ?>
			<p><strong><?php _e('Comments on the following posts will not be closed: ', 'ald_autoclose_plugin'); ?></strong> <?php echo $acc_settings['comment_pids']; ?></p>
		<?php } ?>

	    <?php if ($acc_settings[pbtb_pids]!='') { ?>
			<p><strong><?php _e('Pingbacks on the following posts will not be closed: ', 'ald_autoclose_plugin'); ?></strong> <?php echo $acc_settings['pbtb_pids']; ?></p>
		<?php } ?>

	    <p><strong><?php _e('Next Run: ', 'ald_autoclose_plugin'); ?></strong> <?php echo $ald_acc_info['next_run']; ?></p>
	<?php } else { ?>
	<p><?php _e('Comments are not being closed automatically. You can change that by setting the option below.', 'ald_autoclose_plugin'); ?></p>
	<?php } ?>
    </fieldset>
  </div>
  <form method="post" id="acc_options" name="acc_options" style="border: #ccc 1px solid; padding: 10px">
    <fieldset class="options">
    <legend>
    <h3>
      <?php _e('Options:', 'ald_autoclose_plugin'); ?>
    </h3>
    </legend>
	<p>
		<label><strong><?php _e('Keep comments on these posts/pages open: ', 'ald_autoclose_plugin'); ?></strong>
		<input type="text" name="comment_pids" id="comment_pids" value="<?php echo $acc_settings['comment_pids']; ?>" size="15" /> <?php _e('(Comma separated list of post IDs)', 'ald_autoclose_plugin'); ?>
		</label>
	</p>
	<p>
		<label><strong><?php _e('Keep pingbacks / trackbacks on these posts/pages open: ', 'ald_autoclose_plugin'); ?></strong>
		<input type="text" name="pbtb_pids" id="pbtb_pids" value="<?php echo $acc_settings['pbtb_pids']; ?>" size="15" /> <?php _e('(Comma separated list of post IDs)', 'ald_autoclose_plugin'); ?>
		</label>
	</p>
	<hr />
	<p>
		<label><input type="checkbox" name="close_comment" id="close_comment" value="true" <?php if ( $acc_settings['close_comment']) { ?> checked="checked" <?php } ?> />
		<?php _e('Close Comments on posts?', 'ald_autoclose_plugin'); ?></label>
		<label><input type="checkbox" name="close_comment_pages" id="close_comment_pages" value="true" <?php if ($acc_settings['close_comment_pages']) { ?> checked="checked" <?php } ?> />
		<?php _e('Close Comments on pages?', 'ald_autoclose_plugin'); ?></label>
	</p>
	<p>
		<label><strong><?php _e('Close Comments on posts/pages older than ', 'ald_autoclose_plugin'); ?></strong>
		<input type="text" name="comment_age" id="comment_age" value="<?php echo $acc_settings['comment_age']; ?>" size="5" /><?php _e(' days. (Only effective if above option is checked)', 'ald_autoclose_plugin'); ?>
		</label>
	</p>
	<p>
		<label><input type="checkbox" name="close_pbtb" id="close_pbtb" value="true" <?php if ($acc_settings['close_pbtb']) { ?> checked="checked" <?php } ?> />
		<?php _e('Close Pingbacks/Trackbacks on posts?', 'ald_autoclose_plugin'); ?></label>
		<label><input type="checkbox" name="close_pbtb_pages" id="close_pbtb_pages" value="true" <?php if ($acc_settings['close_pbtb_pages']) { ?> checked="checked" <?php } ?> />
		<?php _e('Close Pingbacks/Trackbacks on pages?', 'ald_autoclose_plugin'); ?></label>
	</p>
	<p>
		<label><strong><?php _e('Close Pingbacks/Trackbacks on posts/pages older than ', 'ald_autoclose_plugin'); ?></strong>
		<input type="text" name="pbtb_age" id="pbtb_age" value="<?php echo $acc_settings['pbtb_age']; ?>" size="5" /><?php _e(' days. (Only effective if above option is checked)', 'ald_autoclose_plugin'); ?>
		</label>
	</p>
	<p>
		<label><input type="checkbox" name="daily_run" id="daily_run" value="true" <?php if ($acc_settings['daily_run']) { ?> checked="checked" <?php } ?> />
		<?php _e('Run Daily?', 'ald_autoclose_plugin'); ?></label>
	</p>
	<p>
		<label><strong><?php _e('Run at: ', 'ald_autoclose_plugin'); ?></strong>
		<input type="text" name="cron_hour" id="cron_hour" value="<?php echo $acc_settings['cron_hour']; ?>" size="2" maxlength="2" /> : <input type="text" name="cron_min" id="cron_min" value="<?php echo $acc_settings['cron_min']; ?>" size="2" maxlength="2" />
		</label>
		<?php _e('(Enter in 24-hour format. e.g. to run at 1:30pm, enter 13 and 30 respectively)', 'ald_autoclose_plugin'); ?>
	</p>
	<p>
		<label><input type="checkbox" name="delete_revisions" id="delete_revisions" value="true" <?php if ($acc_settings['delete_revisions']) { ?> checked="checked" <?php } ?> />
		<?php _e('Delete Post Revisions?', 'ald_autoclose_plugin'); ?></label>
	</p>
	<p>
        <input name="run_once" type="submit" id="run_once" value="<?php _e( 'Save Options and Run Once', CRP_LOCAL_NAME ); ?>" class="button button-primary" />
	    <input type="submit" name="acc_save" id="acc_save" value="<?php _e( 'Save Options', CRP_LOCAL_NAME ); ?>" class="button button-primary" />
        <input name="acc_default" type="submit" id="acc_default" value="<?php _e( 'Default Options', CRP_LOCAL_NAME ); ?>" class="button button-secondary" onclick="if (!confirm('Do you want to set options to Default?')) return false;" />
        <input name="acc_opencomments" type="submit" id="acc_opencomments" value="<?php _e( 'Open Comments', CRP_LOCAL_NAME ); ?>" class="button button-secondary" onclick="if (!confirm('Do you want to open comments on all posts?')) return false;" />
        <input name="acc_openpings" type="submit" id="acc_openpings" value="<?php _e( 'Open Pings', CRP_LOCAL_NAME ); ?>" class="button button-secondary" onclick="if (!confirm('Do you want to open pings on all posts?')) return false;" />
	</p>
    </fieldset>
		<?php wp_nonce_field( 'acc-plugin' ) ?>
  </form>
</div>
<?php

}


function acc_adminmenu() {
	if (function_exists('current_user_can')) {
		// In WordPress 2.x
		if (current_user_can('manage_options')) {
			$acc_is_admin = true;
		}
	} else {
		// In WordPress 1.x
		global $user_ID;
		if (user_can_edit_user($user_ID, 0)) {
			$acc_is_admin = true;
		}
	}

	if ((function_exists('add_options_page'))&&($acc_is_admin)) {
		add_options_page(__("Auto-Close", 'ald_autoclose_plugin'), __("Auto-Close", 'ald_autoclose_plugin'), 9, 'acc_options', 'acc_options');
		}
}

add_action('admin_menu', 'acc_adminmenu');

?>