<?php
/*
Plugin Name: MF Backup
Plugin URI: https://github.com/frostkom/mf_backup
Description:
Version: 2.4.17
Licence: GPLv2 or later
Author: Martin Fors
Author URI: https://martinfors.se
Text Domain: lang_backup
Domain Path: /lang

Requires Plugins: meta-box
*/

if(!function_exists('is_plugin_active') || function_exists('is_plugin_active') && is_plugin_active("mf_base/index.php"))
{
	include_once("include/classes.php");

	$obj_backup = new mf_backup();

	add_action('cron_base', array($obj_backup, 'cron_base'), mt_rand(1, 10));

	add_action('init', array($obj_backup, 'init'));

	if(is_admin())
	{
		register_uninstall_hook(__FILE__, 'uninstall_backup');

		add_action('admin_init', array($obj_backup, 'settings_backup'));
		add_filter('pre_update_option_setting_rss_api_key', array($obj_backup, 'pre_update_option'), 10, 2);
		add_action('admin_init', array($obj_backup, 'admin_init'), 0);
		add_action('admin_menu', array($obj_backup, 'admin_menu'));

		//add_filter('filter_sites_table_settings', array($obj_backup, 'filter_sites_table_settings'));
		add_filter('filter_sites_table_pages', array($obj_backup, 'filter_sites_table_pages'));

		add_filter('post_row_actions', array($obj_backup, 'row_actions'), 10, 2);

		add_action('rwmb_meta_boxes', array($obj_backup, 'rwmb_meta_boxes'));

		add_filter('manage_'.$obj_backup->post_type.'_posts_columns', array($obj_backup, 'column_header'), 5);
		add_action('manage_'.$obj_backup->post_type.'_posts_custom_column', array($obj_backup, 'column_cell'), 5, 2);

		add_filter('filter_last_updated_post_types', array($obj_backup, 'filter_last_updated_post_types'), 10, 2);
	}

	add_action('wp_ajax_api_backup_perform', array($obj_backup, 'api_backup_perform'));

	add_action('wp_trash_post', array($obj_backup, 'wp_trash_post'));

	function uninstall_backup()
	{
		include_once("include/classes.php");

		$obj_backup = new mf_backup();

		mf_uninstall_plugin(array(
			'uploads' => $obj_backup->post_type,
			'post_types' => array($obj_backup->post_type),
			'options' => array('setting_backup_schedule', 'setting_backup_sites', 'setting_backup_perform', 'setting_rss_api_key', 'setting_backup_rss_allowed_ips', 'setting_rss_url', 'setting_backup_limit', 'setting_backup_compress', 'setting_backup_db_type', 'setting_backup_db_tables', 'setting_backup_db_tables'),
		));
	}
}