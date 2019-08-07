<?php

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Drop ShipTime database tables if plugin is deleted
global $wpdb;

// Table: shiptime_login
$table_name = $wpdb->prefix . 'shiptime_login';
$sql = "DROP TABLE IF EXISTS $table_name";
$wpdb->query( $sql );

// Table: shiptime_order
$table_name = $wpdb->prefix . 'shiptime_order';
$sql = "DROP TABLE IF EXISTS $table_name";
$wpdb->query( $sql );

// Table: shiptime_quote
$table_name = $wpdb->prefix . 'shiptime_quote';
$sql = "DROP TABLE IF EXISTS $table_name";
$wpdb->query( $sql );
