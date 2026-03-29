<?php
/**
 * Email template: AutoClose cron summary.
 *
 * Variables available:
 *   string $site_name  Blog name.
 *   string $site_url   Home URL.
 *   array  $rows       Associative array of label => count.
 *
 * @package    AutoClose
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

?>
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f0f0f1;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f0f1;padding:30px 0;">
<tr><td align="center">
<table width="560" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:4px;overflow:hidden;">

	<tr>
		<td style="background:#2271b1;padding:24px 32px;">
			<h1 style="margin:0;color:#fff;font-size:20px;font-weight:600;"><?php esc_html_e( 'AutoClose Cron Summary', 'autoclose' ); ?></h1>
			<p style="margin:4px 0 0;font-size:13px;"><a href="<?php echo esc_url( $site_url ); ?>" style="color:#b3d1f0;text-decoration:none;"><?php echo esc_html( $site_name ); ?></a></p>
		</td>
	</tr>

	<tr>
		<td style="padding:32px;">
			<p style="margin:0 0 24px;color:#3c434a;font-size:14px;"><?php esc_html_e( 'The scheduled cron job ran successfully. Here is a summary of actions performed:', 'autoclose' ); ?></p>
			<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
				<?php $striped = false; ?>
				<?php foreach ( $rows as $label => $count ) : ?>
					<?php $striped = ! $striped; ?>
					<tr style="<?php echo $striped ? 'background:#f6f7f7;' : ''; ?>">
						<td style="padding:12px 16px;font-size:14px;color:#50575e;border-bottom:1px solid #dcdcde;"><?php echo esc_html( $label ); ?></td>
						<td style="padding:12px 16px;font-size:14px;color:#1d2327;font-weight:600;text-align:right;border-bottom:1px solid #dcdcde;"><?php echo (int) $count; ?></td>
					</tr>
				<?php endforeach; ?>
			</table>
			<p style="margin:24px 0 0;color:#8c8f94;font-size:12px;"><?php echo esc_html( current_time( 'mysql' ) ); ?></p>
		</td>
	</tr>

</table>
</td></tr>
</table>
</body>
</html>
