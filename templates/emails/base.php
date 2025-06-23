<?php
/**
 * Base email template
 *
 * @package BuddyPress_Favorite_Notification
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo esc_html( $email_subject ?? get_bloginfo( 'name' ) ); ?></title>
	<!--[if mso]>
	<noscript>
		<xml>
			<o:OfficeDocumentSettings>
				<o:PixelsPerInch>96</o:PixelsPerInch>
			</o:OfficeDocumentSettings>
		</xml>
	</noscript>
	<![endif]-->
	<style>
		/* Reset styles */
		body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
		table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
		img { -ms-interpolation-mode: bicubic; border: 0; outline: none; text-decoration: none; }
		
		/* Main styles */
		body {
			margin: 0 !important;
			padding: 0 !important;
			background-color: #f4f4f4;
			font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
			font-size: 16px;
			line-height: 1.6;
			color: #333333;
		}
		
		/* Container */
		.email-container {
			max-width: 600px;
			margin: 0 auto;
			background-color: #ffffff;
		}
		
		/* Header */
		.email-header {
			background-color: <?php echo esc_attr( $header_color ?? '#1d84b5' ); ?>;
			padding: 30px;
			text-align: center;
		}
		
		.email-header h1 {
			margin: 0;
			color: #ffffff;
			font-size: 24px;
			font-weight: normal;
		}
		
		/* Content */
		.email-content {
			padding: 40px 30px;
		}
		
		.email-content h2 {
			margin-top: 0;
			margin-bottom: 20px;
			color: #333333;
			font-size: 20px;
		}
		
		.email-content p {
			margin: 0 0 20px;
		}
		
		/* Activity excerpt */
		.activity-excerpt {
			background-color: #f8f8f8;
			border-left: 4px solid <?php echo esc_attr( $accent_color ?? '#ff7b00' ); ?>;
			padding: 15px 20px;
			margin: 20px 0;
			font-style: italic;
			color: #666666;
		}
		
		/* Button */
		.email-button {
			display: inline-block;
			padding: 12px 30px;
			background-color: <?php echo esc_attr( $button_color ?? '#1d84b5' ); ?>;
			color: #ffffff !important;
			text-decoration: none;
			border-radius: 4px;
			font-weight: bold;
			margin: 20px 0;
		}
		
		.email-button:hover {
			background-color: <?php echo esc_attr( $button_hover_color ?? '#166b94' ); ?>;
		}
		
		/* User info */
		.user-info {
			display: table;
			margin: 20px 0;
		}
		
		.user-avatar {
			display: table-cell;
			vertical-align: top;
			padding-right: 15px;
		}
		
		.user-avatar img {
			width: 60px;
			height: 60px;
			border-radius: 50%;
		}
		
		.user-details {
			display: table-cell;
			vertical-align: middle;
		}
		
		.user-name {
			font-weight: bold;
			color: #333333;
			margin-bottom: 5px;
		}
		
		/* Footer */
		.email-footer {
			background-color: #f8f8f8;
			padding: 30px;
			text-align: center;
			font-size: 14px;
			color: #666666;
		}
		
		.email-footer a {
			color: <?php echo esc_attr( $link_color ?? '#1d84b5' ); ?>;
			text-decoration: none;
		}
		
		.email-footer a:hover {
			text-decoration: underline;
		}
		
		.email-footer .separator {
			margin: 0 10px;
			color: #cccccc;
		}
		
		/* Responsive */
		@media screen and (max-width: 600px) {
			.email-container {
				width: 100% !important;
			}
			
			.email-content {
				padding: 30px 20px !important;
			}
		}
	</style>
</head>
<body>
	<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
		<tr>
			<td>
				<div class="email-container">
					<!-- Header -->
					<div class="email-header">
						<h1><?php echo esc_html( $site_name ?? get_bloginfo( 'name' ) ); ?></h1>
					</div>
					
					<!-- Content -->
					<div class="email-content">
						<?php 
						// Include specific email template content
						if ( isset( $email_content ) ) {
							echo $email_content;
						}
						?>
					</div>
					
					<!-- Footer -->
					<div class="email-footer">
						<p>
							<?php _e( 'You received this email because you have notifications enabled.', 'bp-fav-notification' ); ?>
						</p>
						<p>
							<a href="<?php echo esc_url( $settings_link ?? '#' ); ?>">
								<?php _e( 'Manage Notifications', 'bp-fav-notification' ); ?>
							</a>
							<span class="separator">|</span>
							<a href="<?php echo esc_url( $site_url ?? home_url() ); ?>">
								<?php _e( 'Visit Site', 'bp-fav-notification' ); ?>
							</a>
						</p>
					</div>
				</div>
			</td>
		</tr>
	</table>
</body>
</html>