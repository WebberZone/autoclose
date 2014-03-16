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
		$acc_settings['comment_pids'] = $_POST['comment_pids'] == '' ? '' : implode( ',', array_map( 'intval', explode( ",", $_POST['comment_pids'] ) ) );
		$acc_settings['pbtb_pids'] = $_POST['pbtb_pids'] == '' ? '' : implode( ',', array_map( 'intval', explode( ",", $_POST['pbtb_pids'] ) ) );
		$acc_settings['close_comment'] = isset( $_POST['close_comment'] ) ? true : false;
		$acc_settings['close_comment_pages'] = isset( $_POST['close_comment_pages'] ) ? true : false;
		$acc_settings['close_pbtb'] = isset( $_POST['close_pbtb'] ) ? true : false;
		$acc_settings['close_pbtb_pages'] = isset( $_POST['close_pbtb_pages'] ) ? true : false;
		$acc_settings['delete_revisions'] = isset( $_POST['delete_revisions'] ) ? true : false;
		$acc_settings['cron_hour'] = min( 23, intval( $_POST['cron_hour'] ) );
		$acc_settings['cron_min'] = min( 59, intval( $_POST['cron_min'] ) );

		if ( isset( $_POST['daily_run'] ) ) {
			$acc_settings['daily_run'] = true;
			acc_enable_run( $acc_settings['cron_hour'], $acc_settings['cron_min'] );
		} else {
			$acc_settings['daily_run'] = false;
			acc_disable_run();
		}
			
		update_option( 'ald_acc_settings', $acc_settings );
		
		if ( isset( $_POST['acc_save'] ) ) {
			echo '<div id="message" class="updated fade"><p>'. __( 'Options saved successfully.', ACC_LOCAL_NAME ) .'</p></div>';
		} else {
			ald_acc();	// Call the main function 
			
			echo '<div id="message" class="updated fade">';
			if ( $acc_settings['close_comment'] ) {
			    echo "<p><strong>". __( 'Comments on posts closed upto', ACC_LOCAL_NAME ) .":</strong> ";
				echo date( 'F j, Y, g:i a', ( time() - $acc_settings['comment_age'] * 86400 ) );
				echo "</p>";
			}
			if ( $acc_settings['close_pbtb'] ) {
				echo "<p><strong>". __( 'Pingbacks/Trackbacks on posts closed upto', ACC_LOCAL_NAME ) .": </strong> ";
				echo date( 'F j, Y, g:i a', ( time() - $acc_settings['pbtb_age'] * 86400 ) );
				echo "</p>";
			}
			if ( $acc_settings['close_comment_pages'] ) {
			    echo "<p><strong>". __( 'Comments on pages closed upto', ACC_LOCAL_NAME ) .":</strong> ";
				echo date( 'F j, Y, g:i a', ( time() - $acc_settings['comment_age'] * 86400 ) );
				echo "</p>";
			}
			if ( $acc_settings['close_pbtb_pages'] ) {
				echo "<p><strong>". __( 'Pingbacks/Trackbacks on pages closed upto', ACC_LOCAL_NAME ) .": </strong> ";
				echo date( 'F j, Y, g:i a', ( time() - $acc_settings['pbtb_age'] * 86400 ) );
				echo "</p>";
			}
			if ( $acc_settings['delete_revisions'] ) {
				echo "<p><strong>". __( 'Post revisions deleted', ACC_LOCAL_NAME ) ."</strong></p>";
			}
			echo '<p>'. __( 'Options saved successfully.', ACC_LOCAL_NAME ) .'</p></div>';
		}
	}
	
	if ( ( isset( $_POST['acc_default'] ) ) && ( check_admin_referer( 'acc-plugin' ) ) ) {
	
		delete_option('ald_acc_settings');
		$acc_settings = acc_default_options();
		update_option('ald_acc_settings', $acc_settings);
		acc_disable_run();
		
		echo '<div id="message" class="updated fade"><p>'. __( 'Options set to Default.', ACC_LOCAL_NAME ) .'</p></div>';
	}

	if ( ( isset( $_POST['acc_opencomments'] ) ) && ( check_admin_referer( 'acc-plugin' ) ) ) {
		$wpdb->query( "
			UPDATE $poststable
			SET comment_status = 'open'
			WHERE comment_status = 'closed'
			AND post_status = 'publish'
		" );
	
		echo '<div id="message" class="updated fade"><p>'. __( 'Comments opened on all posts', ACC_LOCAL_NAME ) .'</p></div>';
	}

	if ( ( isset( $_POST['acc_openpings'] ) ) && ( check_admin_referer( 'acc-plugin' ) ) ) {
		$wpdb->query( "
			UPDATE $poststable
			SET ping_status = 'open'
			WHERE ping_status = 'closed'
			AND post_status = 'publish'
		" );
	
		echo '<div id="message" class="updated fade"><p>'. __( 'Pingbacks/Trackbacks opened on all posts', ACC_LOCAL_NAME ) .'</p></div>';
	}

	if ( function_exists( 'wp_schedule_event' ) ) {
		if ( wp_next_scheduled('ald_acc_hook' ) ) {
			$ald_acc_info['hook_schedule'] = wp_get_schedule( 'ald_acc_hook' );
			$ald_acc_info['next_run'] = date( "F j, Y, g:i a", wp_next_scheduled( 'ald_acc_hook' ) );
			$ald_acc_info['comments_date'] =  date( "F j, Y, g:i a", ( wp_next_scheduled( 'ald_acc_hook' ) - $acc_settings['comment_age'] * 86400 ) );
			$ald_acc_info['pbtb_date'] =  date( "F j, Y, g:i a", ( wp_next_scheduled( 'ald_acc_hook' ) - $acc_settings['pbtb_age'] * 86400 ) );
		}
	}

?>


<div class="wrap">
	<h2>Auto-Close Comments, Pingbacks and Trackbacks</h2>
	<div id="poststuff">
	<div id="post-body" class="metabox-holder columns-2">
	<div id="post-body-content">
	  <form method="post" id="acc_options" name="acc_options" onsubmit="return checkForm()">
	    <div id="genopdiv" class="postbox"><div class="handlediv" title="Click to toggle"><br /></div>
	      <h3 class='hndle'><span><?php _e( 'Information', ACC_LOCAL_NAME ); ?></span></h3>
	      <div class="inside">
			<table class="form-table">
			<tr>
				<td colspan="2">
				<?php if ( wp_next_scheduled( 'ald_acc_hook' ) ) { ?>
				    <p><strong><?php _e( 'Schedule:', ACC_LOCAL_NAME ); ?></strong> <?php echo $ald_acc_info['hook_schedule']; ?></p>
				    <?php if ( $acc_settings['close_comment'] ) { ?>
						<p><strong><?php _e( 'Comments closed upto:', ACC_LOCAL_NAME ); ?></strong> <?php echo $ald_acc_info['comments_date']; ?></p>
					<?php } ?>
			
				    <?php if ( $acc_settings['close_pbtb'] ) { ?>
						<p><strong><?php _e( 'Pingbacks/Trackbacks closed upto:', ACC_LOCAL_NAME ); ?></strong> <?php echo $ald_acc_info['pbtb_date']; ?></p>
					<?php } ?>
			
				    <?php if ( '' != $acc_settings['comment_pids'] ) { ?>
						<p><strong><?php _e( 'Comments on the following posts will not be closed:', ACC_LOCAL_NAME ); ?></strong> <?php echo $acc_settings['comment_pids']; ?></p>
					<?php } ?>
			
				    <?php if ( '' != $acc_settings['pbtb_pids'] ) { ?>
						<p><strong><?php _e( 'Pingbacks on the following posts will not be closed:', ACC_LOCAL_NAME ); ?></strong> <?php echo $acc_settings['pbtb_pids']; ?></p>
					<?php } ?>
			
				    <p><strong><?php _e( 'Next Run:', ACC_LOCAL_NAME ); ?></strong> <?php echo $ald_acc_info['next_run']; ?></p>
				<?php } else { ?>
				<p><?php _e( 'Comments are not being closed automatically. You can change that by setting the option below.', ACC_LOCAL_NAME ); ?></p>
				<?php } ?>
				</td>
			</tr>
			</table>		
	      </div>
	    </div>
	    <div id="outputopdiv" class="postbox"><div class="handlediv" title="Click to toggle"><br /></div>
	      <h3 class='hndle'><span><?php _e( 'Options', ACC_LOCAL_NAME ); ?></span></h3>
	      <div class="inside">
			<table class="form-table">
			<tr><th scope="row"><?php _e( 'Close Comments on:', ACC_LOCAL_NAME ); ?></th>
				<td>
					<label><input type="checkbox" name="close_comment" id="close_comment" value="true" <?php if ( $acc_settings['close_comment'] ) echo 'checked="checked"' ?> /> <?php _e( 'posts', ACC_LOCAL_NAME ); ?></label>
					<label><input type="checkbox" name="close_comment_pages" id="close_comment_pages" value="true" <?php if ( $acc_settings['close_comment_pages'] ) echo 'checked="checked"' ?> /> <?php _e( 'pages', ACC_LOCAL_NAME ); ?></label>
				</td>
			</tr>
			<tr><th scope="row"><label for="comment_age"><?php _e( 'Close Comments on posts/pages older than:', ACC_LOCAL_NAME ); ?></label></th>
				<td>
					<input type="text" name="comment_age" id="comment_age" value="<?php echo $acc_settings['comment_age']; ?>" size="5" /> <?php _e( 'days', ACC_LOCAL_NAME ); ?>
					<p class="description"><?php _e( 'This option is only effective if either of the above options are checked', ACC_LOCAL_NAME ); ?></p>
				</td>
			</tr>
			<tr><th scope="row"><label for="comment_pids"><?php _e( 'Keep comments on these posts/pages open:', ACC_LOCAL_NAME ); ?></label></th>
				<td>
					<input type="textbox" name="comment_pids" id="comment_pids" value="<?php echo esc_attr( stripslashes( $acc_settings['comment_pids'] ) ); ?>"  style="width:250px">
					<p class="description"><?php _e( 'Comma separated list of post IDs', ACC_LOCAL_NAME ); ?></p>
				</td>
			</tr>
			<tr><th scope="row"><?php _e( 'Close Pingbacks/Trackbacks:', ACC_LOCAL_NAME ); ?></th>
				<td>
					<label><input type="checkbox" name="close_pbtb" id="close_pbtb" value="true" <?php if ( $acc_settings['close_pbtb'] ) echo 'checked="checked"' ?> /> <?php _e( 'posts', ACC_LOCAL_NAME ); ?></label>
					<label><input type="checkbox" name="close_pbtb_pages" id="close_pbtb_pages" value="true" <?php if ( $acc_settings['close_pbtb_pages'] ) echo 'checked="checked"' ?> /> <?php _e( 'pages', ACC_LOCAL_NAME ); ?></label>
				</td>
			</tr>
			<tr><th scope="row"><label for="pbtb_age"><?php _e( 'Close Pingbacks/Trackbacks on posts/pages older than:', ACC_LOCAL_NAME ); ?></label></th>
				<td>
					<input type="text" name="pbtb_age" id="pbtb_age" value="<?php echo $acc_settings['pbtb_age']; ?>" size="5" /><?php _e( 'days', ACC_LOCAL_NAME ); ?>
					<p class="description"><?php _e( 'This option is only effective if either of the above options are checked', ACC_LOCAL_NAME ); ?></p>
				</td>
			</tr>
			<tr><th scope="row"><label for="pbtb_pids"><?php _e( 'Keep Pingbacks/Trackbacks on these posts/pages open:', ACC_LOCAL_NAME ); ?></label></th>
				<td>
					<input type="textbox" name="pbtb_pids" id="pbtb_pids" value="<?php echo esc_attr( stripslashes( $acc_settings['pbtb_pids'] ) ); ?>"  style="width:250px">
					<p class="description"><?php _e( 'Comma separated list of post IDs', ACC_LOCAL_NAME ); ?></p>
				</td>
			</tr>
			<tr><th scope="row"><label for="daily_run"><?php _e( 'Run Daily?', ACC_LOCAL_NAME ); ?></label></th>
				<td>
					<input type="checkbox" name="daily_run" id="daily_run" value="true" <?php if ( $acc_settings['daily_run'] ) echo 'checked="checked"' ?> />
					<p class="description"><?php _e( 'This will create a daily cron job. Comments and/or pingbacks/trackbacks will be closed at this time specified. The options above will be used when running the job.', ACC_LOCAL_NAME ); ?></p>
				</td>
			</tr>
			<tr><th scope="row"><label for="daily_run"><?php _e( 'Run at:', ACC_LOCAL_NAME ); ?></label></th>
				<td>
					<input type="text" name="cron_hour" id="cron_hour" value="<?php echo $acc_settings['cron_hour']; ?>" size="2" maxlength="2" /> : <input type="text" name="cron_min" id="cron_min" value="<?php echo $acc_settings['cron_min']; ?>" size="2" maxlength="2" />
					<p class="description"><?php _e( 'Enter in 24-hour format. e.g. to run at 1:30pm, enter 13 and 30 respectively', ACC_LOCAL_NAME ); ?></p>
				</td>
			</tr>
			<tr><th scope="row"><label for="pbtb_age"><?php _e( 'Delete Post Revisions?', ACC_LOCAL_NAME ); ?></label></th>
				<td>
					<input type="checkbox" name="delete_revisions" id="delete_revisions" value="true" <?php if ( $acc_settings['delete_revisions'] ) echo 'checked="checked"' ?> />
					<p class="description"><?php _e( 'The WordPress revisions system stores a record of each saved draft or published update. This can gather up a lot of overhead in the long run. Use this option to delete old post revisions.', ACC_LOCAL_NAME ); ?></p>
				</td>
			</tr>
			</table>
	      </div>
	    </div>

		<p>
	        <input name="run_once" type="submit" id="run_once" value="<?php _e( 'Save Options and Run Once', ACC_LOCAL_NAME ); ?>" class="button button-primary" />
		    <input type="submit" name="acc_save" id="acc_save" value="<?php _e( 'Save Options', ACC_LOCAL_NAME ); ?>" class="button button-primary" />
	        <input name="acc_default" type="submit" id="acc_default" value="<?php _e( 'Default Options', ACC_LOCAL_NAME ); ?>" class="button button-secondary" onclick="if (!confirm('Do you want to set options to Default?')) return false;" />
	        <input name="acc_opencomments" type="submit" id="acc_opencomments" value="<?php _e( 'Open Comments', ACC_LOCAL_NAME ); ?>" class="button button-secondary" onclick="if (!confirm('Do you want to open comments on all posts?')) return false;" />
	        <input name="acc_openpings" type="submit" id="acc_openpings" value="<?php _e( 'Open Pings', ACC_LOCAL_NAME ); ?>" class="button button-secondary" onclick="if (!confirm('Do you want to open pings on all posts?')) return false;" />
		</p>
		<?php wp_nonce_field( 'acc-plugin' ) ?>
	  </form>
	</div><!-- /post-body-content -->
	<div id="postbox-container-1" class="postbox-container">
	  <div id="side-sortables" class="meta-box-sortables ui-sortable">
	    <div id="donatediv" class="postbox"><div class="handlediv" title="Click to toggle"><br /></div>
	      <h3 class='hndle'><span><?php _e( 'Support the development', ACC_LOCAL_NAME ); ?></span></h3>
	      <div class="inside">
			<div id="donate-form">
				<form action="https://www.paypal.com/cgi-bin/webscr" method="post">
				<input type="hidden" name="cmd" value="_xclick">
				<input type="hidden" name="business" value="donate@ajaydsouza.com">
				<input type="hidden" name="lc" value="IN">
				<input type="hidden" name="item_name" value="Donation for Auto-Close">
				<input type="hidden" name="item_number" value="acc">
				<strong><?php _e( 'Enter amount in USD:', ACC_LOCAL_NAME ); ?></strong> <input name="amount" value="10.00" size="6" type="text"><br />
				<input type="hidden" name="currency_code" value="USD">
				<input type="hidden" name="button_subtype" value="services">
				<input type="hidden" name="bn" value="PP-BuyNowBF:btn_donate_LG.gif:NonHosted">
				<input type="image" src="https://www.paypal.com/en_US/i/btn/btn_donate_LG.gif" border="0" name="submit" alt="<?php _e( 'Send your donation to the author of', ACC_LOCAL_NAME ); ?> Auto-Close?">
				<img alt="" border="0" src="https://www.paypal.com/en_US/i/scr/pixel.gif" width="1" height="1">
				</form>
			</div>
	      </div>
	    </div>
	    <div id="followdiv" class="postbox"><div class="handlediv" title="Click to toggle"><br /></div>
	      <h3 class='hndle'><span><?php _e( 'Follow me', ACC_LOCAL_NAME ); ?></span></h3>
	      <div class="inside">
			<div id="follow-us">
				<iframe src="//www.facebook.com/plugins/likebox.php?href=http%3A%2F%2Fwww.facebook.com%2Fajaydsouzacom&amp;width=292&amp;height=62&amp;colorscheme=light&amp;show_faces=false&amp;border_color&amp;stream=false&amp;header=true&amp;appId=113175385243" scrolling="no" frameborder="0" style="border:none; overflow:hidden; width:292px; height:62px;" allowTransparency="true"></iframe>
				<div style="text-align:center"><a href="https://twitter.com/ajaydsouza" class="twitter-follow-button" data-show-count="false" data-size="large" data-dnt="true">Follow @ajaydsouza</a>
				<script>!function(d,s,id){var js,fjs=d.getElementsByTagName(s)[0];if(!d.getElementById(id)){js=d.createElement(s);js.id=id;js.src="//platform.twitter.com/widgets.js";fjs.parentNode.insertBefore(js,fjs);}}(document,"script","twitter-wjs");</script></div>
			</div>
	      </div>
	    </div>
	    <div id="qlinksdiv" class="postbox"><div class="handlediv" title="Click to toggle"><br /></div>
	      <h3 class='hndle'><span><?php _e( 'Quick links', ACC_LOCAL_NAME ); ?></span></h3>
	      <div class="inside">
	        <div id="quick-links">
				<ul>
					<li><a href="http://ajaydsouza.com/wordpress/plugins/autoclose/"><?php _e( 'Auto-Close plugin page', ACC_LOCAL_NAME ); ?></a></li>
					<li><a href="http://ajaydsouza.com/wordpress/plugins/"><?php _e( 'Other plugins', ACC_LOCAL_NAME ); ?></a></li>
					<li><a href="http://ajaydsouza.com/"><?php _e( "Ajay's blog", ACC_LOCAL_NAME ); ?></a></li>
					<li><a href="https://wordpress.org/plugins/autoclose/faq/"><?php _e( 'FAQ', ACC_LOCAL_NAME ); ?></a></li>
					<li><a href="http://wordpress.org/support/plugin/autoclose"><?php _e( 'Support', ACC_LOCAL_NAME ); ?></a></li>
					<li><a href="https://wordpress.org/support/view/plugin-reviews/autoclose"><?php _e( 'Reviews', ACC_LOCAL_NAME ); ?></a></li>
				</ul>
	        </div>
	      </div>
	    </div>
	  </div><!-- /side-sortables -->
	</div><!-- /postbox-container-1 -->
	</div><!-- /post-body -->
	<br class="clear" />
	</div><!-- /poststuff -->
</div><!-- /wrap -->
<?php
}


/**
 * Add a link under Settings to the plugins settings page.
 * 
 * @access public
 * @return void
 */
function acc_adminmenu() {
	if ( ( function_exists( 'add_options_page' ) ) ) {
		$plugin_page = add_options_page( __( "Auto-Close", ACC_LOCAL_NAME ), __( "Auto-Close", ACC_LOCAL_NAME ), 'manage_options', 'acc_options', 'acc_options' );
		add_action( 'admin_head-'. $plugin_page, 'acc_adminhead' );
	}
}
add_action( 'admin_menu', 'acc_adminmenu' );


/**
 * Function to add CSS and JS to the Admin header.
 * 
 * @access public
 * @return void
 */
function acc_adminhead() {
	wp_enqueue_script( 'common' );
	wp_enqueue_script( 'wp-lists' );
	wp_enqueue_script( 'postbox' );
?>
	<style type="text/css">
	.postbox .handlediv:before {
		right:12px;
		font:400 20px/1 dashicons;
		speak:none;
		display:inline-block;
		top:0;
		position:relative;
		-webkit-font-smoothing:antialiased;
		-moz-osx-font-smoothing:grayscale;
		text-decoration:none!important;
		content:'\f142';
		padding:8px 10px;
	}
	.postbox.closed .handlediv:before {
		content: '\f140';
	}
	.wrap h2:before {
	    content: "\f321";
	    display: inline-block;
	    -webkit-font-smoothing: antialiased;
	    font: normal 29px/1 'dashicons';
	    vertical-align: middle;
	    margin-right: 0.3em;
	}
	</style>

	<script type="text/javascript">
		//<![CDATA[
		jQuery(document).ready( function($) {
			// close postboxes that should be closed
			$('.if-js-closed').removeClass('if-js-closed').addClass('closed');
			// postboxes setup
			postboxes.add_postbox_toggles('acc_options');
		});
		//]]>
	</script>
	<script type="text/javascript" language="JavaScript">
		//<![CDATA[
		function checkForm() {
		answer = true;
		if (siw && siw.selectingSomething)
			answer = false;
		return answer;
		}//
		//]]>
	</script>
	
<?php 
}


?>