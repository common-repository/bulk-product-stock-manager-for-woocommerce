<?php
if ( !defined('WP_UNINSTALL_PLUGIN') ) {
	die;
}
		global $wpdb;
		$table_name = $wpdb->prefix."osgtransactions";
		$sql = "DROP TABLE ".$table_name.";";
		$results = $wpdb->query($sql);
?>