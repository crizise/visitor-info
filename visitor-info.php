<?php
/**
 * @package Visitor Info
 */
/*
 Plugin Name: Visitor info
 Plugin URI: 
 Description: Plugin for detecting visitor's ip, geolocation.
 Version: 0.09
 Author: crizise
 Author URI: crizise@github.io
 License: GPL
 *
 *v0.1 - added: checking if database not exist and creating it if not, make name of table is dynamic 
 *	
 *v0.09 - First working prototype
 */

/**************************************************//*
*************************************************/
// TODO
//
//-Handle with users ajax sent info
//- fix check ajax refer

/**************************************************//**************************************************/

function user_ident(){
	//check_ajax_referer( 'user_ident', 'nonce' );
	global $wpdb;
	$table_name = $wpdb->prefix . 'user_by_location'; 
	
	error_reporting(E_ALL);
	ini_set('display_errors', 1);
	
	if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name){
		$charset_collate = $wpdb->get_charset_collate();
		$sql = "CREATE TABLE $table_name (
		id int(11) NOT NULL AUTO_INCREMENT,
		ip varchar(255) DEFAULT '' NOT NULL,
		views int(10) NOT NULL,
		time int(11) NOT NULL,
		country varchar(255) DEFAULT '' NOT NULL,
		city varchar(255) DEFAULT '' NOT NULL,
		language varchar(255) DEFAULT '' NOT NULL,
		PRIMARY KEY  (id)
	) $charset_collate;";
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );
	echo('table created');
}

if(isset($_POST) && !empty($_POST)){
	$data = $_POST['data'];
	$result = $wpdb->get_row(
		$wpdb->prepare('
			SELECT * 
			FROM '. $table_name .' 
			WHERE ip = "%1$s"', $data['ip']
		)
		, ARRAY_A, 0);
	if(empty($result)){

		$wpdb ->insert($table_name,
			array(
				'ip' => $data['ip'], 
				'time' => (int)$data['time'] / 1000, 
				'country' => $data['country_name'], 
				'language' => $data['language'], 
				'views' => 1, 
				'city' => $data['city']
			),
			array(
				'%s', 
				'%d', 
				'%s', 
				'%s', 
				'%d',
				'%s', 
				'%s'
			)
		);
		echo('new visitor added');
	} else{

		$wpdb ->update($table_name,
			array(
				'ip' => $data['ip'], 
				'time' => (int)$data['time'] / 1000, 
				'country' => $data['country_name'], 
				'language' => $data['language'], 
				'views' => (int)$result['views'] + 1, 
				'city' => $data['city']
			),
			array(
				'ip' => $data['ip']
			),
			array(
				'%s', 
				'%d', 
				'%s', 
				'%s', 
				'%d', 
				'%s', 
				'%s'
			),
			array(
				'%s'
			)
		);
		var_dump($result);
	}

} else{
	echo "No set POST data";
}
wp_die();
};

add_action( 'wp_ajax_user_ident', 'user_ident' );
add_action( 'wp_ajax_nopriv_user_ident', 'user_ident' );

/*
 *	Frontend for admin
 *	TODO
 *	- add map with markers
 *
 */

function visitors_dashbord() {
	global $wpdb;
	$table_name = $wpdb->prefix . "user_by_location"; 
	$results = $wpdb->get_results( "SELECT * FROM $table_name ORDER BY time DESC", ARRAY_A ); 
	echo '<div class="content-box">';
	if(is_array($results) || !empty($results)){ ?>
	<h2><?php _e('Visitors Information', 'jstsecurity') ?></h2>
	<div class="table-box">
		<table class="w100">
			<thead>
				<tr class="table-head">
					<?php foreach (array_keys($results[0]) as $value) {
						if($value == 'time'){
							$value = $value.' CEST';
						}
						echo '<th class="uppercase text-left" data-coumn="'. $value .'">'. $value .'</th>';
					} ?>
				</tr>
			</thead>
			<tbody >
				<?php foreach ($results  as $result ) {
					echo '<tr class="table-item">';
					foreach ($result as $key => $value) {
						if($key == 'time'){
							$dt = $value + 7200;
							$value = date('M d, Y ,G:i', $dt);
						}
						echo "<td>". $value ."</td>";
					}
					echo '</tr>';
				}
				?>
			</tbody>
		</table>
	</div>
	<?php } else{
		echo "There is no answer from db";
	}
	echo '</div>';
	wp_die();
};
add_action('admin_menu', function(){

	add_menu_page( 'Vistors Info', 'Vistors Info', 'manage_options', 'visitor_info', 'visitors_dashbord', 'dashicons-admin-site', 0); 
} );

/**************************************************//**************************************************/
	// Identify the user and send his data to handler
/**************************************************//**************************************************/
function get_user_info_script(){
	?>
	<script>
		u = window.location;
		baseUrl = u.protocol + "//" + u.host + "/" + u.pathname.split('/')[0];
		getDate = new Date(), getCurrent = new Date();
		getDate.setMonth(11);
		expireCookie = getDate.toUTCString();
		ajaxUrl = baseUrl + 'wp-admin/admin-ajax.php';
		$(document).ready(function(){
			//Take user IP, depend of it get GEO location, store the timestamp,country_name,language 
			//then store this at database using handle-user.php, ajax-admin.php with checking of nonce
			var localeObj = takeObj();
			function takeObj(){
				d = {};

				$.ajax({
					dataType: "json",
					async: false,
					url: 'https://freegeoip.net/json/?callback=?',
					method: 'GET',
					success: function(data){
						time = getCurrent.getTime();
						ip = data['ip'];
						language = window.navigator.userLanguage || window.navigator.language;
						country_name = data['country_name'];
						city = data['city'];
						d =	{ip ,country_name, time, language, city};
						$.ajax({
							method: "POST",
							url: ajaxUrl,
							data: {
								action: 'user_ident',
								data: d,
								//nonce: $('#nonce').attr('value')
							},
							success: function(data){
								//$('#output').html('<pre>' + data + '</pre>');
							},
							error: function(xhr, ajaxOptions, thrownError){
								console.log(thrownError);
							}
						});
					},
					error: function(xhr, ajaxOptions, thrownError){
						console.log('error');
						console.log(thrownError);

					}
				});
			};

		});

	</script>
	<?php
}

add_action('wp_footer', 'get_user_info_script', 100);

function admin_dashboard_style(){
	?>
	<style>
	.content-box{
		margin: 3em auto;
		padding: 0 2em;
	}
	.table-box{
		max-height: 800px;
		overflow-y: auto;	
	}
	.w100{
		width: 100%;
	}
	.uppercase{
		text-transform: uppercase;
	}
	.text-left{
		text-align: left;
	}
	.table-head{
		background: #333;
		color: #dedede;
	}
	.table-head > th{
		padding: 0 0.4em;
	}
	.table-item:nth-child(even){
		background: #fff;
	}
	@media (max-width: 1400px){
		.table-box{
			max-height: 400px
		}
	}
</style>
<?php
}
add_action('admin_head', 'admin_dashboard_style', 100);