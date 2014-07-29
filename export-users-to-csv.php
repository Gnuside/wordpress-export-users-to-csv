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
	
	
	private function gnuside_save_options( $csv_columns_names, $selected_fields, $meta_fields ){
		if( !is_array($csv_columns_names) || !is_array($selected_fields) ) 
			return;
		
		if( !$options ) 
			$this->options = get_option( 'gnuside_eutcvs_plugin' );
		
		$options_array = array();
		$options_array['csv_columns_names'] = $csv_columns_names;
		$options_array['selected_fields'] = implode(',', $selected_fields);
		
		if( is_array($meta_fields) )
			$options_array['meta_fields'] = $meta_fields;

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
	
	private function get_meta_config() {
		$path = dirname(__FILE__).'/export-users-to-csv.inc.php';
		if( file_exists($path) ) {
			$config = include $path;
			if( is_array($config) && !empty($config) ){
				$config = $this->gnuside_sanitize_array($config);
				$meta = array();
				foreach ($config as $key => $value) {
					if( empty($name) ){
						$meta[] = array( 'db_id' => $key );
					}else{
						$meta[] = array(
							'db_id' => $key,
							'name'  => $value
						);
					}
				}
				
				return $meta;
			}
		}
		return array();
	}
	
	private function gnuside_save_data() {
		$users_fields = $this->gnuside_extract_post_data('eutcvs_users_');
		$checked_fields = $this->gnuside_extract_post_data('eutcvs_checked_users_');
		$meta_fields = $this->gnuside_extract_post_data_meta('eutcvs_meta');
		
		foreach ($checked_fields as $key => $value) {
			if( $value === 'checked' )
				unset($checked_fields[key]);
		}
		$checked_fields = array_keys( $checked_fields );
		
		$csv_var_name = array();
		foreach ($users_fields as $key => $value) {
			if($value)
				$csv_var_name[$key] = $value;
			else
				$csv_var_name[$key] = $key;
		}
			
		$this->gnuside_save_options( $csv_var_name , $checked_fields, $meta_fields );
	}
	
	private function gnuside_extract_post_data_meta() {
		$extract_data = array();

		if(isset($_POST['eutcvs_meta']) && is_array($_POST['eutcvs_meta'])){
			$tab_meta = $_POST['eutcvs_meta'];
			foreach ($tab_meta as $value) {
				$extract_data[] = $this->gnuside_sanitize_array ( $value );
			}
		}
		
		return $extract_data;
		
	}
	
	private function gnuside_extract_post_data($prefix) {
		$extract_data = array();
		
		if( !is_string($prefix) ) 
			return $extract_data;
		
		foreach ($_POST as $key => $value) {
			if( strpos($key, $prefix) === 0 ){
				$cleaned_key = sanitize_key( str_replace($prefix, '', $key) );
				if( is_array($value) )
					$extract_data[$cleaned_key] = $this->gnuside_sanitize_array ( $value );
				else
					$extract_data[$cleaned_key] = sanitize_text_field( $value );
			}
		}

		return $extract_data;
	}

	function gnuside_sanitize_array ( $data = array() ) {
		if (!is_array($data) || !count($data)) {
			return array();
		}
		$sani = array();
		
		foreach ($data as $k => $v) {
			if (!is_array($v) ) {
				$sani[ sanitize_key($k) ] = sanitize_text_field($v);
			}
			if (is_array($v)) {
				$sani[ sanitize_key($k) ] = $this->gnuside_sanitize_array($v);
			}
		}
		return $data;
	}
	private function get_meta_db($meta_fields, $users_id){
		if( !is_array($meta_fields) || empty($meta_fields) )
			return array();
		
		global $wpdb;
		$my_query = "SELECT * ";
		$my_query .= "FROM  $wpdb->usermeta ";
		$my_query .= "WHERE  ";
		
		$first = TRUE;
		foreach ($meta_fields as $value) {
			if($first){
				$my_query .= "meta_key LIKE '$value' ";
				$first = FALSE;
				continue;
			}
			$my_query .= "OR meta_key LIKE '$value' ";
		}
		
		if( is_array($users_id) && !empty($users_id) ){
			$my_query .= "AND user_id in (". implode(',', $users_id) . ")";
		}
		
		$mysql_query = $wpdb->prepare( $my_query, '');
		$result = $wpdb->get_results($mysql_query, ARRAY_A);
		return $result;
	}
	/**
	 * Process content of CSV file
	 *
	 * @since 0.1
	 **/
	public function generate_csv() {
		$users_var = $this->gnuside_extract_post_data('eutcvs_users_');
		$checked_fields = $this->gnuside_extract_post_data('eutcvs_checked_users_');
		$meta_fields = $this->gnuside_extract_post_data_meta();
		$meta_db_id = array();
		$meta_csv_name = array();
		
		foreach ($meta_fields as $key => $field) {
			if( !isset($field['checked']) || $field['checked'] != 'checked' || !isset($field['db_id']) || empty($field['db_id']) ){
				unset($meta_fields[$key]);
				continue;
			}
			$meta_db_id[] = $field['db_id'];
			if( isset($field['name']) && !empty($field['name']) )
				$meta_csv_name[] = $field['name'];
			else
				$meta_csv_name[] = $field['db_id'];
		}
		
		
		foreach ($checked_fields as $key => $value) {
			if( $value === 'checked' )
				unset($checked_fields[key]);
		}
		$checked_fields = array_keys( $checked_fields );
		
		$args = array(
			'fields' => 'all_with_meta',
			'role' => sanitize_text_field( $_POST['role'] )
		);

		add_action( 'pre_user_query', array( $this, 'pre_user_query' ) );
		$users = get_users( $args );
		remove_action( 'pre_user_query', array( $this, 'pre_user_query' ) );
		
		$users_id = array();
		foreach ($users as $user) {
			$users_id[] = $user->ID;
		}
		$db_meta_rows = $this->get_meta_db($meta_db_id, $users_id );
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

		$fields = $checked_fields;
		$csv_col_name = array();
		
		foreach ($checked_fields as $value) {
			$csv_col_name[] = $users_var[$value];
		}
		
		$csv_col_name = array_merge($csv_col_name, $meta_csv_name);
		echo implode( ';', $csv_col_name ) . "\n";
		
		foreach ( $users as $user ) {
			$data = array();
			foreach ( $fields as $field ) {
				$value = isset( $user->{$field} ) ? $user->{$field} : '';
				$value = is_array( $value ) ? serialize( $value ) : $value;
				$data[] = '"' . str_replace( '"', '""', $value ) . '"';
			}
			foreach ($db_meta_rows as $key => $row) {
				if($row["user_id"] == $user->ID){
					$data[] = '"' . str_replace( '"', '""', $row["meta_value"] ) . '"';
					unset($db_meta_rows[key]);
				}
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
		<p><?php _e( 'Don\'t forget to save your changes', 'gnuside' ); ?></p>
		<?php
		if ( isset( $_GET['error'] ) ) {
			echo '<div class="updated"><p><strong>' . __( 'No user found.', 'export-users-to-csv' ) . '</strong></p></div>';
		}
		?>
			<form method="post" action="" enctype="multipart/form-data" data-id="gnuside-eutcvs-form" >
				<?php wp_nonce_field( 'pp-eu-export-users-users-page_export', '_wpnonce-pp-eu-export-users-users-page_export' ); ?>
				<table class="form-table">
					<tr valign="top">
						<th scope="row"><label for"pp_eu_users_role"><?php _e( 'User\'s role', 'export-users-to-csv' ); ?></label></th>
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
				</table>
				<?php $this->gnuside_display_users_fields(); ?>
				<?php $this->gnuside_display_meta_fields(); ?>
				<p class="submit">
					<input type="hidden" name="_wp_http_referer" value="<?php echo $_SERVER['REQUEST_URI'] ?>" />
					<input type="submit" class="button-primary" data-name="gnuside-eutcvs-save" value="<?php _e( 'Save Changes', 'export-users-to-csv' ); ?>" />
					<input type="submit" class="button-primary" value="<?php _e( 'Export', 'export-users-to-csv' ); ?>" />
				</p>
			</form>
		<?php
	}

	public function gnuside_display_meta_fields(){
		$this->options = get_option( 'gnuside_eutcvs_plugin' );
		$meta_user_fields = isset( $this->options['meta_fields'] ) ? $this->options['meta_fields'] : array() ;
		
		$meta_fields = $this->get_meta_config();
		
		foreach ($meta_fields as $key => $field) {
			foreach ($meta_user_fields as $k => $user_field) {
				if( $user_field['db_id'] === $field['db_id'] ){
					foreach ($field as $value) {
						
					}
					$meta_fields[$key] =  array_merge($user_field, $field);
					unset($meta_user_fields[$k]);
				}
			}
		}
		$meta_fields = array_merge($meta_fields, $meta_user_fields);
		?>
			<br/>
			<h2><?php _e('Users meta fields', 'gnuside') ?></h2>
			<table class="wp-list-table widefat fixed" id="gnuside-eutcvs-table-usermeta">
				<thead>
					<tr>
						<th class="manage-column" >
							<?php _e("Field name in the database : check to export", 'gnuside') ?>
						</th>
						<th class="manage-column" >
							<?php _e("Field name in the CSV file", 'gnuside') ?>
						</th>
						<th class="manage-column" >
							<?php _e("Field description", 'gnuside') ?>
						</th>
						<th class="manage-column" >
						</th>
					</tr>
				</thead>
			
				<tbody class="" id="" >
					<?php if( is_array($meta_fields) && !empty($meta_fields) ) : ?>
						<?php foreach ($meta_fields as $field) :  ?>
							<?php $db_id = $field['db_id']; ?>
							<tr class="alternate" >
								<td class="manage-column" >
									<label>
										<input type="checkbox" name="<?php echo "eutcvs_meta[$db_id][checked]"; ?>" data-toggle="gnuside-eutcvs-checkbox"
											<?php echo ( isset($field['checked']) && $field['checked'] ) ? ' checked="checked" value="checked" ' : '' ; ?> /> 
										<input data-toggle="eutcvs_meta_id" data-id="<?php echo $db_id; ?>" name="<?php echo "eutcvs_meta[$db_id][db_id]"; ?>" value="<?php echo $db_id; ?>" type="text" />
									</label>
								</td>
								<td>
									<input type="text" type="text" name="<?php echo "eutcvs_meta[$db_id][name]"; ?>" value="<?php echo $field['name']; ?>" />
								</td>
								<td>
									<input type="text" name="<?php echo "eutcvs_meta[$db_id][desc]"; ?>" value="<?php echo $field['desc']; ?>"/>
								</td>
								<td>
									<input type="button" class="button-secondary" value="remove" data-toggle="gnuside-eutcvs-remove" />
								</td>
							</tr>
						<?php endforeach; ?>
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
	
	private function gnuside_display_users_fields() {
		global $wpdb;
		$users_db_columns = $this->gnuside_get_db_columns_names($wpdb->users);
		$columns_nbr = count($users_db_columns);

		if(!$columns_nbr) { return; }
		
		$this->options = get_option( 'gnuside_eutcvs_plugin' );
		$selected_fields = isset( $this->options['selected_fields'] ) ? explode( ',', $this->options['selected_fields']) : array() ;
		$csv_columns_names = isset( $this->options['csv_columns_names'] ) ? $this->options['csv_columns_names'] : array() ;
		$desc = $this->gnuside_desc_array();
		
		?>
			<br/>
			<h2><?php _e('Users fields', 'gnuside') ?></h2>
			<table class="wp-list-table widefat fixed" id="gnuside-eutcvs-users">
				<thead>
					<tr>
						<th class="manage-column" >
							<?php _e("Field name in the database : check to export", 'gnuside') ?>
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
		<?php
	}
}

new PP_EU_Export_Users;


