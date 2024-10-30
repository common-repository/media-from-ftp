<?php
/**
 * Media from FTP
 *
 * @package    Media from FTP
 * @subpackage MediaFromFtpRegist registered in the database
/*
	Copyright (c) 2013- Katsushi Kawamori (email : dodesyoswift312@gmail.com)
	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; version 2 of the License.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */

$mediafromftpregist = new MediaFromFtpRegist();

/** ==================================================
 * Register Database
 */
class MediaFromFtpRegist {

	/** ==================================================
	 * Path
	 *
	 * @var $upload_path  upload_path.
	 */
	private $upload_path;

	/** ==================================================
	 * Construct
	 *
	 * @since 9.81
	 */
	public function __construct() {

		if ( ! class_exists( 'MediaFromFtp' ) ) {
			include_once plugin_dir_path( __DIR__ ) . 'inc/class-mediafromftp.php';
		}
		$mediafromftp = new MediaFromFtp();
		list( $upload_dir, $upload_url, $this->upload_path ) = $mediafromftp->upload_dir_url_path();
		$plugin_tmp_dir = $upload_dir . '/media-from-ftp-tmp';
		/* Make tmp dir */
		if ( ! is_dir( $plugin_tmp_dir ) ) {
			wp_mkdir_p( $plugin_tmp_dir );
		}

		register_activation_hook( plugin_dir_path( __DIR__ ) . 'mediafromftp.php', array( $this, 'log_settings' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );

		add_action( 'admin_notices', array( $this, 'update_notice' ) );
		/* original hook */
		add_action( 'media_from_ftp_notices', array( $this, 'update_notice_option' ) );

	}

	/** ==================================================
	 * Settings Log Settings
	 *
	 * @since 10.05
	 */
	public function log_settings() {

		if ( ! is_multisite() ) {
			$this->log_write();
		} else { /* For Multisite */
			global $wpdb;
			$blog_ids = $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" );
			$original_blog_id = get_current_blog_id();
			foreach ( $blog_ids as $blog_id ) {
				switch_to_blog( $blog_id );
				$this->log_write();
			}
			switch_to_blog( $original_blog_id );
		}

	}

	/** ==================================================
	 * Settings Log Write
	 *
	 * @since 9.19
	 */
	private function log_write() {

		$mediafromftp_log_db_version = '3.0';
		$installed_ver = get_option( 'mediafromftp_log_version' );

		if ( $installed_ver != $mediafromftp_log_db_version ) {
			global $wpdb;
			$wpdb->log_name = $wpdb->prefix . 'mediafromftp_log';
			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			if ( ! $installed_ver ) {
				/* from version 9.57 */
				$sql = 'CREATE TABLE ' . $wpdb->log_name . " (
				meta_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				id bigint(20),
				user text,
				title text,
				permalink text,
				url text,
				filename text,
				time datetime,
				filetype text,
				filesize text,
				exif text,
				length text,
				thumbnail longtext,
				mlccategories longtext,
				emlcategories longtext,
				mlacategories longtext,
				mlatags longtext,
				UNIQUE KEY meta_id (meta_id)
				)
				CHARACTER SET 'utf8';";
				dbDelta( $sql );
			} else {
				$table_search = $wpdb->get_row( "SHOW TABLES FROM DB_NAME LIKE $wpdb->log_name" );
				if ( 1 == $wpdb->num_rows ) { /* db_version 1.0, 2.0 */
					$records = $wpdb->get_results( "SELECT * FROM $wpdb->log_name" );
					$wpdb->query( "DELETE FROM $wpdb->log_name" );

					$wpdb->query( "ALTER TABLE $wpdb->log_name DROP thumbnail1, DROP thumbnail2, DROP thumbnail3, DROP thumbnail4, DROP thumbnail5, DROP thumbnail6" );
					$wpdb->query( "ALTER TABLE $wpdb->log_name ADD thumbnail longtext" );
					$wpdb->query( "ALTER TABLE $wpdb->log_name ADD mlccategories longtext" );
					$wpdb->query( "ALTER TABLE $wpdb->log_name ADD emlcategories longtext" );
					$wpdb->query( "ALTER TABLE $wpdb->log_name ADD mlacategories longtext" );
					$wpdb->query( "ALTER TABLE $wpdb->log_name ADD mlatags longtext" );

					foreach ( $records as $record ) {
						$thumbnail = null;
						$thumbnails = array();
						if ( ! empty( $record->thumbnail1 ) ) {
							$thumbnails[0] = $record->thumbnail1; }
						if ( ! empty( $record->thumbnail2 ) ) {
							$thumbnails[1] = $record->thumbnail2; }
						if ( ! empty( $record->thumbnail3 ) ) {
							$thumbnails[2] = $record->thumbnail3; }
						if ( ! empty( $record->thumbnail4 ) ) {
							$thumbnails[3] = $record->thumbnail4; }
						if ( ! empty( $record->thumbnail5 ) ) {
							$thumbnails[4] = $record->thumbnail5; }
						if ( ! empty( $record->thumbnail6 ) ) {
							$thumbnails[5] = $record->thumbnail6; }
						if ( ! empty( $thumbnails ) ) {
							$thumbnail = json_encode( $thumbnails );
							$thumbnail = str_replace( '\\', '', $thumbnail );
						} else {
							$thumbnail = $record->thumbnail;
						}

						$log_arr = array(
							'id' => $record->id,
							'user' => $record->user,
							'title' => $record->title,
							'permalink' => $record->permalink,
							'url' => $record->url,
							'filename' => $record->filename,
							'time' => $record->time,
							'filetype' => $record->filetype,
							'filesize' => $record->filesize,
							'exif' => $record->exif,
							'length' => $record->length,
							'thumbnail' => $thumbnail,
							'mlccategories' => null,
							'emlcategories' => null,
							'mlacategories' => null,
							'mlatags' => null,
						);
						$wpdb->insert( $wpdb->log_name, $log_arr );
						$wpdb->show_errors();
					}
				}
			}
			update_option( 'mediafromftp_log_version', $mediafromftp_log_db_version );
		}

	}

	/** ==================================================
	 * Settings register
	 *
	 * @since 2.3
	 */
	public function register_settings() {

		$user = wp_get_current_user();

		$pagemax = 20;
		$basedir = $this->upload_path;
		$searchdir = $this->upload_path;
		$ext2typefilter = 'all';
		$extfilter = 'all';
		$search_display_metadata = true;
		$dateset = 'new';
		if ( function_exists( 'wp_date' ) ) {
			$datefixed = wp_date( 'Y-m-d H:i' );
		} else {
			$datefixed = date_i18n( 'Y-m-d H:i' );
		}
		$datetimepicker = 1;
		$max_execution_time = 300;
		if ( strtoupper( substr( PHP_OS, 0, 3 ) ) === 'WIN' && get_locale() === 'ja' ) { /* Japanese Windows */
			$character_code = 'CP932';
		} else {
			$character_code = 'UTF-8';
		}
		$exclude = '(.ktai.)|(.backwpup_log.)|(.ps_auto_sitemap.)|\.php|\.js|(.wpcf7_captcha.)|\.htaccess|(.woocommerce_uploads.)|(.wc-logs.)';
		$recursive_search = true;
		$thumb_deep_search = false;
		$search_limit_number = 100000;
		$cron_apply = false;
		$cron_schedule = 'hourly';
		$cron_limit_number = false;
		$cron_mail_apply = true;

		$caption_apply = false;
		$exif_text = '%title% %credit% %camera% %caption% %created_timestamp% %copyright% %aperture% %shutter_speed% %iso% %focal_length% %white_balance% %orientation%';
		$log = false;

		/* for media-from-ftp-add-on-category */
		$mlcc = null;
		$emlc = null;
		$mlac = null;
		$mlat = null;

		/* Old option 11.07 -> New option for media-from-ftp-add-on-wpcron */
		if ( get_option( 'mediafromftp_add_on_wpcron_events_' . $user->ID ) ) {
			update_option( 'mediafromftp_add_on_wpcron_events', get_option( 'mediafromftp_add_on_wpcron_events_' . $user->ID ) );
			delete_option( 'mediafromftp_add_on_wpcron_events_' . $user->ID );
			update_user_option( $user->ID, 'mediafromftp_add_on_wpcron_events', get_option( 'mediafromftp_add_on_wpcron_events' ) );
		}

		if ( ! get_option( 'mediafromftp_add_on_wpcron_events' ) ) {
			update_option( 'mediafromftp_add_on_wpcron_events', array() );
		}
		if ( ! get_user_option( 'mediafromftp_add_on_wpcron_events', $user->ID ) ) {
			update_user_option( $user->ID, 'mediafromftp_add_on_wpcron_events', array() );
		}

		/* Old option 11.07 -> New option */
		if ( get_option( 'mediafromftp_settings_' . $user->ID ) ) {
			update_option( 'mediafromftp', get_option( 'mediafromftp_settings_' . $user->ID ) );
			delete_option( 'mediafromftp_settings_' . $user->ID );
			update_user_option( $user->ID, 'mediafromftp', get_option( 'mediafromftp' ) );
		}

		if ( ! get_option( 'mediafromftp' ) ) {
			$mediafromftp_tbl = array(
				'pagemax' => $pagemax,
				'basedir' => $basedir,
				'searchdir' => $searchdir,
				'ext2typefilter' => $ext2typefilter,
				'extfilter' => $extfilter,
				'search_display_metadata' => $search_display_metadata,
				'dateset' => $dateset,
				'datefixed' => $datefixed,
				'datetimepicker' => $datetimepicker,
				'max_execution_time' => $max_execution_time,
				'character_code' => $character_code,
				'exclude' => $exclude,
				'recursive_search' => $recursive_search,
				'thumb_deep_search' => $thumb_deep_search,
				'search_limit_number' => $search_limit_number,
				'cron' => array(
					'apply' => $cron_apply,
					'schedule' => $cron_schedule,
					'limit_number' => $cron_limit_number,
					'mail_apply' => $cron_mail_apply,
					'mail' => $user->user_email,
					'user' => $user->ID,
				),
				'caption' => array(
					'apply' => $caption_apply,
					'exif_text' => $exif_text,
				),
				'log' => $log,
				'mlcc' => $mlcc,
				'emlc' => $emlc,
				'mlac' => $mlac,
				'mlat' => $mlat,
			);

			update_option( 'mediafromftp', $mediafromftp_tbl );
		}
		if ( ! get_user_option( 'mediafromftp', $user->ID ) ) {
			$mediafromftp_set = get_option( 'mediafromftp' );
			$mediafromftp_set['cron']['mail'] = $user->user_email;
			$mediafromftp_set['cron']['user'] = $user->ID;
			update_user_option( $user->ID, 'mediafromftp', $mediafromftp_set );
		}

		if ( ! get_option( 'mediafromftp_notice' ) ) {
			update_option( 'mediafromftp_notice', 11.08 );
		}

	}

	/** ==================================================
	 * Update notice
	 *
	 * @since 11.09
	 */
	public function update_notice() {

		if ( isset( $_POST['Notice_Dismiss'] ) && ! empty( $_POST['Notice_Dismiss'] ) ) {
			if ( check_admin_referer( 'mff_nt', 'mediafromftp_notice' ) ) {
				if ( isset( $_POST['notice_update_version'] ) && ! empty( $_POST['notice_update_version'] ) ) {
					return;
				}
			}
		}

		$screen = get_current_screen();
		if ( is_object( $screen ) && 'toplevel_page_mediafromftp' === $screen->id ||
				is_object( $screen ) && 'media-from-ftp_page_mediafromftp-settings' === $screen->id ||
				is_object( $screen ) && 'media-from-ftp_page_mediafromftp-search-register' === $screen->id ||
				is_object( $screen ) && 'media-from-ftp_page_mediafromftp-event' === $screen->id ||
				is_object( $screen ) && 'media-from-ftp_page_mediafromftp-log' === $screen->id ||
				is_object( $screen ) && 'media-from-ftp_page_mediafromftp-import' === $screen->id ||
				is_object( $screen ) && 'media-from-ftp_page_mediafromftp-addons' === $screen->id ) {
			if ( 'toplevel_page_mediafromftp' === $screen->id ) {
				$scriptname = admin_url( 'admin.php?page=mediafromftp' );
			} else {
				$page = str_replace( 'media-from-ftp_page_', '', $screen->id );
				$scriptname = admin_url( 'admin.php?page=' . $page );
			}
			$allowed_button_html = array(
				'input' => array(
					'type'  => array(),
					'id'    => array(),
					'name'  => array(),
					'class'  => array(),
					'value' => array(),
				),
			);
			$dismiss_button = get_submit_button( __( 'Dismiss' ), 'small', 'Notice_Dismiss', true );
			if ( class_exists( 'MovingMediaLibrary' ) ) {
				$movingmedialibrary_url = admin_url( 'admin.php?page=movingmedialibrary' );
			} else {
				if ( is_multisite() ) {
					$movingmedialibrary_url = network_admin_url( 'plugin-install.php?tab=plugin-information&plugin=moving-media-library' );
				} else {
					$movingmedialibrary_url = admin_url( 'plugin-install.php?tab=plugin-information&plugin=moving-media-library' );
				}
			}
			$movingmedialibrary_link = '<strong><a style="text-decoration: none;" href="' . $movingmedialibrary_url . '">Moving Media Library</a></strong>';
			if ( class_exists( 'BulkMediaRegister' ) ) {
				$bulkmediaregister_url = admin_url( 'admin.php?page=bulkmediaregister' );
			} else {
				if ( is_multisite() ) {
					$bulkmediaregister_url = network_admin_url( 'plugin-install.php?tab=plugin-information&plugin=bulk-media-register' );
				} else {
					$bulkmediaregister_url = admin_url( 'plugin-install.php?tab=plugin-information&plugin=bulk-media-register' );
				}
			}
			$bulkmediaregister_link = '<strong><a style="text-decoration: none;" href="' . $bulkmediaregister_url . '">Bulk Media Register</a></strong>';
			if ( 11.08 == get_option( 'mediafromftp_notice' ) || 11.09 == get_option( 'mediafromftp_notice' ) ) {
				if ( 11.08 == get_option( 'mediafromftp_notice' ) ) {
					$import_html = '<strong><a style="text-decoration: none;" href="' . admin_url( 'admin.php?page=mediafromftp-import' ) . '">' . __( 'Import' ) . '</a></strong>';
					?>
					<div class="notice notice-warning is-dismissible"><ul><li>
					<form method="post" action="<?php echo esc_url( $scriptname ); ?>">
					<?php
					wp_nonce_field( 'mff_nt', 'mediafromftp_notice' );
					/* translators: %1$s Plugin link %2$s Import link */
					echo wp_kses_post( sprintf( __( 'Created a plugin %1$s to move your media library. You can do it with your current %2$s, but it is faster and more accurate.', 'media-from-ftp' ), $movingmedialibrary_link, $import_html ) );
					?>
					&nbsp
					<?php echo wp_kses( $dismiss_button, $allowed_button_html ); ?>
					<input type="hidden" name="notice_update_version" value="11.09">
					</form>
					</li></ul></div>
					<?php
				}
				if ( 11.09 == get_option( 'mediafromftp_notice' ) ) {
					$import_html = '<strong><a style="text-decoration: none;" href="' . admin_url( 'admin.php?page=mediafromftp-import' ) . '">' . __( 'Import' ) . '</a></strong>';
					?>
					<div class="notice notice-warning is-dismissible"><ul><li>
					<form method="post" action="<?php echo esc_url( $scriptname ); ?>">
					<?php
					wp_nonce_field( 'mff_nt', 'mediafromftp_notice' );
					/* translators: %1$s New plugin link */
					echo wp_kses_post( sprintf( __( 'The import feature has been deprecated. Please use a new plugin %1$s to replace it.', 'media-from-ftp' ), $movingmedialibrary_link ) );
					?>
					&nbsp
					<?php echo wp_kses( $dismiss_button, $allowed_button_html ); ?>
					<input type="hidden" name="notice_update_version" value="11.10">
					</form>
					</li></ul></div>
					<?php
				}
			}
			if ( 11.10 == get_option( 'mediafromftp_notice' ) ) {
				$register_html = '<strong><a style="text-decoration: none;" href="' . admin_url( 'admin.php?page=mediafromftp-search-register' ) . '">' . __( 'Search & Register', 'media-from-ftp' ) . '</a></strong>';
				?>
				<div class="notice notice-warning is-dismissible"><ul><li>
				<form method="post" action="<?php echo esc_url( $scriptname ); ?>">
				<?php
				wp_nonce_field( 'mff_nt', 'mediafromftp_notice' );
				/* translators: %1$s Plugin link %2$s Search & Register link */
				echo wp_kses_post( sprintf( __( 'Created a plugin %1$s to bulk register files on the server. You can do it with your current %2$s, but it is faster and more accurate.', 'media-from-ftp' ), $bulkmediaregister_link, $register_html ) );
				?>
				&nbsp
				<?php echo wp_kses( $dismiss_button, $allowed_button_html ); ?>
				<input type="hidden" name="notice_update_version" value="11.11">
				</form>
				</li></ul></div>
				<?php
			}
			if ( 11.11 == get_option( 'mediafromftp_notice' ) ) {
				?>
				<div class="notice notice-warning is-dismissible"><ul><li>
				<form method="post" action="<?php echo esc_url( $scriptname ); ?>">
				<?php
				wp_nonce_field( 'mff_nt', 'mediafromftp_notice' );
				/* translators: %1$s Plugin link */
				echo wp_kses_post( sprintf( __( '%1$s is the successor to "Media from FTP". It has the same functions and greatly improved the search speed. Please switch to it.', 'media-from-ftp' ), $bulkmediaregister_link ) );
				?>
				&nbsp
				<?php echo wp_kses( $dismiss_button, $allowed_button_html ); ?>
				<input type="hidden" name="notice_update_version" value="11.13">
				</form>
				</li></ul></div>
				<?php
			}
			$bulk_html = '<div>' . __( 'Bulk register files on the server to the Media Library.', 'media-from-ftp' ) . ' : ' . $bulkmediaregister_link . '</div>';
			$moving_html = '<div>' . __( 'Supports the transfer of Media Library between servers.', 'media-from-ftp' ) . ' : ' . $movingmedialibrary_link . '</div>';
			?>
			<div class="notice notice-warning is-dismissible"><ul><li>
			<?php
			esc_html_e( 'This plugin will be closed eventually with no more maintenance. The next plugin is its successor. Please switch.', 'media-from-ftp' );
			echo wp_kses_post( $bulk_html );
			echo wp_kses_post( $moving_html );
			?>
			</li></ul></div>
			<?php
		}

	}

	/** ==================================================
	 * Update notice option
	 *
	 * @since 11.09
	 */
	public function update_notice_option() {

		if ( isset( $_POST['Notice_Dismiss'] ) && ! empty( $_POST['Notice_Dismiss'] ) ) {
			if ( check_admin_referer( 'mff_nt', 'mediafromftp_notice' ) ) {
				if ( isset( $_POST['notice_update_version'] ) && ! empty( $_POST['notice_update_version'] ) ) {
					$notice_update_version = sanitize_text_field( wp_unslash( $_POST['notice_update_version'] ) );
					update_option( 'mediafromftp_notice', $notice_update_version );
				}
			}
		}

	}

}


