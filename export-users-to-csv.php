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

register_activation_hook( __FILE__, 'gnuside_eutcvs_activation' );

function gnuside_eutcvs_activation() {
	if( is_admin() ) {
		if( !get_option( 'gnuside_eutcvs_plugin' ) ){
			add_option( 'gnuside_eutcvs_plugin', '', '', 'no' );
		}
	}
}

add_action( 'admin_enqueue_scripts', 'gnuside_eutcvs_js' );

function gnuside_eutcvs_js($hook) {
	if( 'users_page_export-users-to-csv' != $hook) 
		return;
	wp_enqueue_script( 'gnuside_eutcvs_js', plugin_dir_url( __FILE__ ) . 'gnuside_eutcvs.js' );
}
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
		add_action( 'init', array( $this, 'gnuside_action' ) );
	}
	
	/**
	 * Add administration menus
	 *
	 * @since 0.1
	 **/
	public function add_admin_pages() {
		add_users_page( __( 'Export to CSV', 'export-users-to-csv' ), __( 'Export to CSV', 'export-users-to-csv' ), 'list_users', 'export-users-to-csv', array( $this, 'users_page' ) );
	}
	
	
	private function gnuside_save_options( $csv_columns_names, $selected_fields ){
		if( !is_array($csv_columns_names) || !is_array($selected_fields) ) 
			return;
		
		if( !$options ) 
			$this->options = get_option( 'gnuside_eutcvs_plugin' );
		
		$options_array = array();
		$options_array['csv_columns_names'] = $csv_columns_names;
		$options_array['selected_fields'] = implode(',', $selected_fields);

		if($this->options)
			$this->options = $options_array + $this->options;
		else 
			$this->options = $options_array ;
		
		update_option( 'gnuside_eutcvs_plugin' , $this->options);
	}
	
	public function gnuside_action() {
		if ( isset( $_POST['_wpnonce-pp-eu-export-users-users-page_export'] ) ) {
			check_admin_referer( 'pp-eu-export-users-users-page_export', '_wpnonce-pp-eu-export-users-users-page_export' );
			if( isset( $_POST['gnuside-eutcvs-save'] ) )
				$this->gnuside_save_data();
			else
				$this->generate_csv();
		}
	}
	
	public function gnuside_save_data() {
		$users_var = $this->gnuside_extract_post_data('eutcvs_users_');
		//$usermeta_var = $this->gnuside_extract_post_data('eutcvs_usermeta_');
		$checked_fields = $this->gnuside_extract_post_data('eutcvs_checked_users_');

		foreach ($checked_fields as $key => $value) {
			if( $value === 'checked' )
				unset($checked_fields[key]);
		}
		$checked_fields = array_keys( $checked_fields );
		
		$csv_var_name = array();
		foreach ($users_var as $key => $value) {
			if($value)
				$csv_var_name[$key] = $value;
			else
				$csv_var_name[$key] = $key;
		}
		
		$this->gnuside_save_options( $csv_var_name , $checked_fields );
	}
	
	public function gnuside_extract_post_data($prefix) {
		$extract_data = array();
		
		if( !is_string($prefix) ) 
			return $extract_data;
		
		foreach ($_POST as $key => $value) {
			if( strpos($key, $prefix) === 0 ){
				$cleaned_key = sanitize_key( str_replace($prefix, '', $key) );
				$extract_data[$cleaned_key] = sanitize_text_field( $value );
			}
		}
		
		return $extract_data;
	}
	/**
	 * Process content of CSV file
	 *
	 * @since 0.1
	 **/
	public function generate_csv() {
		$users_var = $this->gnuside_extract_post_data('eutcvs_users_');
		//$usermeta_var = $this->gnuside_extract_post_data('eutcvs_usermeta_');
		$checked_fields = $this->gnuside_extract_post_data('eutcvs_checked_users_');
		
		foreach ($checked_fields as $key => $value) {
			if( $value === 'checked' )
				unset($checked_fields[key]);
		}
		$checked_fields = array_keys( $checked_fields );
		
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
		$filename = $sitename . __('users.', 'gnuside') . date( 'Y-m-d-H-i-s' ) . '.csv';

		header( 'Content-Description: File Transfer' );
		header( 'Content-Disposition: attachment; filename=' . $filename );
		header( 'Content-Type: text/csv; charset=' . get_option( 'blog_charset' ), true );


		global $wpdb;

		//$meta_keys = $wpdb->get_results( "SELECT distinct(meta_key) FROM $wpdb->usermeta" );
		//$meta_keys = wp_list_pluck( $meta_keys, 'meta_key' );
		//$fields = array_merge( $data_keys, $meta_keys );
		$fields = $checked_fields;
		$csv_col_name = array();
		
		foreach ($checked_fields as $value) {
			$csv_col_name[] = $users_var[$value];
		}
		
		echo implode( ';', $csv_col_name ) . "\n";

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
	
	private function gnuside_get_db_columns_names($table_name) {
		global $wpdb;
		
		$query = "SHOW COLUMNS FROM `$table_name`";
		$columns_info = $wpdb->get_results( $query, ARRAY_A);
		
		if (!$columns_info)
			return array();
		
		$columns_names = array();
		foreach ($columns_info as $column_info ) {
			$columns_names[] = $column_info['Field'];
		}
		return $columns_names;
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
			<form method="post" action="" enctype="multipart/form-data" data-id="gnuside-eutcvs-form" >
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
					<input type="submit" class="button-primary" data-name="gnuside-eutcvs-save" value="<?php _e( 'Save Changes', 'export-users-to-csv' ); ?>" 
						onclick="
							var nameValue = this.getAttribute('data-name'),
								inputCsvName = jQuery( 'input:text[data-name]' );
							jQuery(this).attr('name', nameValue); 
							inputCsvName.each( function(){
								var nameValue = this.getAttribute('data-name');
								jQuery(this).attr('name', nameValue); 
							});
						"
					/>
					<input type="submit" class="button-primary" value="<?php _e( 'Export', 'export-users-to-csv' ); ?>" />
				</p>
			</form>
		<?php
	}
	
	public function gnuside_desc_array(){
		return array(
			'ID'                    => __( 'User ID in the database.', 'gnuside'),
			'user_login'            => __( 'User login.', 'gnuside'),
			'user_pass'             => __( 'User password.', 'gnuside'),
			'user_nicename'         => __( 'Short name.', 'gnuside'),
			'user_email'            => __( 'User e-mail.', 'gnuside'),
			'user_url'              => __( 'User website.', 'gnuside'),
			'user_registered'       => __( 'User registration date.', 'gnuside'),
			'user_activation_key'   => __( 'Activation key sent by e-mail.', 'gnuside'),
			'user_status'           => __( 'Dead value. Useless value. Deprecated.', 'gnuside'),
			'display_name'          => __( 'User name displayed on this website.', 'gnuside')
		);
	}
	
	private function gnuside_display_all_fields() {
		global $wpdb;
		$users_db_columns = $this->gnuside_get_db_columns_names($wpdb->users);
		$columns_nbr = count($users_db_columns);

		if(!$columns_nbr) { return; }
		
		$this->options = get_option( 'gnuside_eutcvs_plugin' );
		$selected_fields = isset( $this->options['selected_fields'] ) ? explode( ',', $this->options['selected_fields']) : array() ;
		$csv_columns_names = isset( $this->options['csv_columns_names'] ) ? $this->options['csv_columns_names'] : array() ;
		$desc = $this->gnuside_desc_array();
		
		$selected_meta_fields = isset( $this->options['selected_meta_fields'] ) ? explode( ',', $this->options['selected_meta_fields']) : array() ;
		$db_meta_fields = isset( $this->options['db_meta_fields'] ) ? explode( ',', $this->options['db_meta_fields']) : array() ;
		$csv_columns_names_meta = isset( $this->options['csv_columns_names_meta'] ) ? $this->options['csv_columns_names_meta'] : array() ;
		$desc_meta_fields = isset( $this->options['desc_meta_fields'] ) ? explode( ',', $this->options['desc_meta_fields']) : array() ;
		
		?>
		<br/>
		<table class="wp-list-table widefat fixed" id="gnuside-eutcvs-users">
			<thead>
				<tr>
					<th class="manage-column" >
						<?php _e("Users table : Field name in the database", 'gnuside') ?>
					</th>
					<th class="manage-column" >
						<?php _e("Field name in the CSV file", 'gnuside') ?>
					</th>
					<th class="manage-column" >
						<?php _e("Field description", 'gnuside') ?>
					</th>
				</tr>
			</thead>
		
			<tbody class="" id="" >
				<?php foreach ($users_db_columns as $value) : ?>
					<tr class="alternate" >
						<td class="manage-column" >
							<label>
								<input type="checkbox" name="<?php echo "eutcvs_checked_users_".$value; ?>" data-toggle="gnuside-eutcvs-checkbox"
									<?php 
										$active = in_array( strtolower($value), $selected_fields );
										
										if( $active ) {
											echo ' checked="checked" ';
											echo ' value="checked" ';
										}
									?>
								/> 
								<?php echo $value; ?>
							</label>
						</td>
						<td>
							<input type="text"
							<?php 
								echo ' name="eutcvs_users_'.$value.'" ';
								if(! $csv_columns_names [$value])
									echo ' value="'.$value.'"';
								else 
									echo ' value="'.$csv_columns_names[$value].'"';
							?>
							/>
						</td>
						<td>
							<?php echo $desc[$value]; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				
			</tbody>
		</table>
		
		<br/>
		<table class="wp-list-table widefat fixed" id="gnuside-eutcvs-table-usermeta">
			<thead>
				<tr>
					<th class="manage-column" >
						<?php _e("User meta table : Add name in the database", 'gnuside') ?>
					</th>
					<th class="manage-column" >
						<?php _e("Field name in the CSV file", 'gnuside') ?>
					</th>
					<th class="manage-column" >
						<?php _e("Field description", 'gnuside') ?>
					</th>
				</tr>
			</thead>
		
			<tbody class="" id="" >
				<?php if( ! empty($db_meta_fields) ) : ?>
					<tr class="alternate" >
						<td class="manage-column" >
							<label>
								<input type="checkbox" name="<?php echo "eutcvs_checked_usermeta_".$value; ?>" data-toggle="gnuside-eutcvs-checkbox"
									<?php 
										$active = in_array( strtolower($value), $selected_fields );
										
										if( $active ) {
											echo ' checked="checked" ';
											echo ' value="checked" ';
										}
									?>
								/> 
								<input type="text" />
							</label>
						</td>
						<td>
							<input type="text"
							<?php 
								echo ' name="eutcvs_users_'.$value.'" ';
								if(! $csv_columns_names [$value])
									echo ' value="'.$value.'"';
								else 
									echo ' value="'.$csv_columns_names[$value].'"';
							?>
							/>
						</td>
						<td>
							<input type="text" />
						</td>
					</tr>
				<?php endif; ?>
				<tr data-toggle="gnuside-eutcvs-usermeta-button">
					<td>
						<input type="button" class="button-primary" value="<?php _e('add field', 'gnuside') ?>" data-toggle="gnuside-eutcvs-add-field" />
					</td>
				</tr>
			</tbody>
		</table>

		<?php
	}
}

new PP_EU_Export_Users;


