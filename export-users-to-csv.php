<?php
/**
 * @package Export_Users_to_CSV
 * @version 1.0.0
 */
/*
Plugin Name: Export Users to CSV
Plugin URI: http://wordpress.org/extend/plugins/export-users-to-csv/
Description: Export Users data and metadata to a csv file.
Version: 1.0.0
Author: Ulrich Sossou
Author URI: http://ulrichsossou.com/
License: GPL2
Text Domain: export-users-to-csv
*/
/*  Copyright 2011  Ulrich Sossou  (http://github.com/sorich87)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

load_plugin_textdomain( 'export-users-to-csv', false, basename( dirname( __FILE__ ) ) . '/languages' );

/**
 * Main plugin class
 *
 * @since 0.1
 **/
class PP_EU_Export_Users {
	
	private $options;
	/**
	 * Class contructor
	 *
	 * @since 0.1
	 **/
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_pages' ) );
		add_action( 'init', array( $this, 'generate_csv' ) );
		//add_filter( 'pp_eu_exclude_data', array( $this, 'exclude_data' ) );
	}
	/**
	 * Add administration menus
	 *
	 * @since 0.1
	 **/
	public function add_admin_pages() {
		add_users_page( __( 'Export to CSV', 'export-users-to-csv' ), __( 'Export to CSV', 'export-users-to-csv' ), 'list_users', 'export-users-to-csv', array( $this, 'users_page' ) );
	}

	/**
	 * Process content of CSV file
	 *
	 * @since 0.1
	 **/
	public function generate_csv() {
		if ( isset( $_POST['_wpnonce-pp-eu-export-users-users-page_export'] ) ) {
			check_admin_referer( 'pp-eu-export-users-users-page_export', '_wpnonce-pp-eu-export-users-users-page_export' );
			$users_var = array();
			$usermeta_var = array();
			
			foreach ($_POST as $key => $value) {
				if( strpos($key, 'eutcvs_users_') === 0 ){
					$users_var[] = str_replace('eutcvs_users_', '', $key);
					
				}elseif( strpos($key, 'eutcvs_usermeta_') === 0 ) {
					$usermeta_var[] = str_replace('eutcvs_users_', '', $key);
				}
			}
			
			$args = array(
				'fields' => 'all_with_meta',
				'role' => stripslashes( $_POST['role'] )
			);

			add_action( 'pre_user_query', array( $this, 'pre_user_query' ) );
			$users = get_users( $args );
			remove_action( 'pre_user_query', array( $this, 'pre_user_query' ) );
/*
			if ( ! $users ) {
				$referer = add_query_arg( 'error', 'empty', wp_get_referer() );
				wp_redirect( $referer );
				exit;
			}*/

			$sitename = sanitize_key( get_bloginfo( 'name' ) );
			if ( ! empty( $sitename ) )
				$sitename .= '.';
			$filename = $sitename . 'users.' . date( 'Y-m-d-H-i-s' ) . '.csv';

			header( 'Content-Description: File Transfer' );
			header( 'Content-Disposition: attachment; filename=' . $filename );
			header( 'Content-Type: text/csv; charset=' . get_option( 'blog_charset' ), true );


			global $wpdb;

			//$meta_keys = $wpdb->get_results( "SELECT distinct(meta_key) FROM $wpdb->usermeta" );
			//$meta_keys = wp_list_pluck( $meta_keys, 'meta_key' );
			//$fields = array_merge( $data_keys, $meta_keys );
			$fields = $users_var;
			

			echo implode( ',',  $users_var ) . "\n";

			foreach ( $users as $user ) {
				$data = array();
				foreach ( $fields as $field ) {
					$value = isset( $user->{$field} ) ? $user->{$field} : '';
					$value = is_array( $value ) ? serialize( $value ) : $value;
					$data[] = '"' . str_replace( '"', '""', $value ) . '"';
				}
				echo implode( ';', $data ) . "\n";
			}
			
			exit;
		}
	}
	private function user_query() {
		global $wpdb;
		$query = "SELECT *
				FROM $wpdb->users";
				
		$users = $wpdb->get_results( $query, ARRAY_A);
		return $users;
	}
	
	public function pre_user_query( $user_search ) {
		global $wpdb;

		$where = '';

		if ( ! empty( $_POST['start_date'] ) )
			$where .= $wpdb->prepare( " AND $wpdb->users.user_registered >= %s", date( 'Y-m-d', strtotime( $_POST['start_date'] ) ) );

		if ( ! empty( $_POST['end_date'] ) )
			$where .= $wpdb->prepare( " AND $wpdb->users.user_registered < %s", date( 'Y-m-d', strtotime( '+1 month', strtotime( $_POST['end_date'] ) ) ) );

		if ( ! empty( $where ) )
			$user_search->query_where = str_replace( 'WHERE 1=1', "WHERE 1=1$where", $user_search->query_where );

		return $user_search;
	}

	private function export_date_options() {
		global $wpdb, $wp_locale;

		$months = $wpdb->get_results( "
			SELECT DISTINCT YEAR( user_registered ) AS year, MONTH( user_registered ) AS month
			FROM $wpdb->users
			ORDER BY user_registered DESC
		" );

		$month_count = count( $months );
		if ( !$month_count || ( 1 == $month_count && 0 == $months[0]->month ) )
			return;

		$option_html = "";
		foreach ( $months as $date ) {
			if ( 0 == $date->year ) { continue; }
			
			$month = zeroise( $date->month, 2 );
			$option_html .= '<option value="' . $date->year . '-' . $month . '">' . $wp_locale->get_month( $month ) . ' ' . $date->year . '</option>';
		}
		return $option_html;
	}
	/**
	 * Content of the settings page
	 *
	 * @since 0.1
	 **/
	public function users_page() {
		if ( ! current_user_can( 'list_users' ) )
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'export-users-to-csv' ) );
		?>
	
		<div class="wrap">
		<h2><?php _e( 'Export users to a CSV file', 'export-users-to-csv' ); ?></h2>
		<?php
		if ( isset( $_GET['error'] ) ) {
			echo '<div class="updated"><p><strong>' . __( 'No user found.', 'export-users-to-csv' ) . '</strong></p></div>';
		}
		?>
			<form method="post" action="" enctype="multipart/form-data">
				<?php wp_nonce_field( 'pp-eu-export-users-users-page_export', '_wpnonce-pp-eu-export-users-users-page_export' ); ?>
				<table class="form-table">
					<tr valign="top">
						<th scope="row"><label for"pp_eu_users_role"><?php _e( 'Role', 'export-users-to-csv' ); ?></label></th>
						<td>
							<select name="role" id="pp_eu_users_role">
								<?php
								echo '<option value="">' . __( 'Every Role', 'export-users-to-csv' ) . '</option>';
								global $wp_roles;
								foreach ( $wp_roles->role_names as $role => $name ) {
									$name = translate_user_role($name);
									echo "\n\t<option value='" . esc_attr( $role ) . "'>$name</option>";
								}
								?>
							</select>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><label><?php _e( 'Date range', 'export-users-to-csv' ); ?></label></th>
						<td>
							<select name="start_date" id="pp_eu_users_start_date">
								<option value="0"><?php _e( 'Start Date', 'export-users-to-csv' ); ?></option>
								<?php $date = $this->export_date_options(); 
									echo $date;
								?>
							</select>
							<select name="end_date" id="pp_eu_users_end_date">
								<option value="0"><?php _e( 'End Date', 'export-users-to-csv' ); ?></option>
								<?php echo $date; ?>
							</select>
						</td>
					</tr>
					<?php $this->gnuside_display_all_fields(); ?>
				</table>
				<p class="submit">
					<input type="hidden" name="_wp_http_referer" value="<?php echo $_SERVER['REQUEST_URI'] ?>" />
					<input type="submit" class="button-primary" value="<?php _e( 'Export', 'export-users-to-csv' ); ?>" />
				</p>
			</form>
		<?php
	}
	
	public function gnuside_desc_array(){
		return array(
			'ID'					=> __( 'User ID in the database.', 'gnuside'),
			'user_login'			=> __( 'User login.', 'gnuside'),
			'user_pass'				=> __( 'User password.', 'gnuside'),
			'user_nicename'			=> __( 'Short name.', 'gnuside'),
			'user_email'			=> __( 'User e-mail.', 'gnuside'),
			'user_url'				=> __( 'User website.', 'gnuside'),
			'user_registered'		=> __( 'User registration date.', 'gnuside'),
			'user_activation_key'	=> __( 'Activation key send by e-mail.', 'gnuside'),
			'user_status'			=> __( 'Dead value. Useless value. Deprecated.', 'gnuside'),
			'display_name'			=> __( 'User name displayed on this website.', 'gnuside')
		);
	}
	
	private function gnuside_display_all_fields() {
		$users = $this->user_query();
		$users_nbr = count($users);

		if(!$users_nbr) { return; }
		
		$desc = $this->gnuside_desc_array();
		$thead_keys = array_keys($users[0]);
		?>
		<br/>
		<table class="wp-list-table widefat" id="gnuside-eutcvs-table">
			<thead>
				<tr>
					<th class="manage-column" >
						<?php _e("Nom du champ", 'gnuside') ?>
					</th>
					<th class="manage-column" >
						<?php _e("Nom du champ dans le fichier CSV", 'gnuside') ?>
					</th>
					<th class="manage-column" >
						<?php _e("Description du champ", 'gnuside') ?>
					</th>
				</tr>
			</thead>
		
			<tbody class="" id="" >
				<?php foreach ($thead_keys as $value) : ?>
					<tr class="alternate" >
						<td class="manage-column" >
							<label>
								<input type="checkbox" value="" name="<?php echo "eutcvs_users_".$value; ?>" /> 
								<?php echo $value; ?>
							</label>
						</td>
						<td><input type="text" name="" /></td>
						<td><?php echo $desc[$value]; ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>	

		<?php
	}
}

new PP_EU_Export_Users;
