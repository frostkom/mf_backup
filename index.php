<?php
/*
Plugin Name: MF Backup
Plugin URI: https://github.com/frostkom/mf_backup
Description: 
Version: 2.2.0
Licence: GPLv2 or later
Author: Martin Fors
Author URI: https://frostkom.se
Text Domain: lang_backup
Domain Path: /lang

Depends: MF Base
GitHub Plugin URI: frostkom/mf_backup
*/

include_once("include/classes.php");

$obj_backup = new mf_backup();

add_action('cron_base', array($obj_backup, 'cron_base'), mt_rand(1, 10));

if(is_admin())
{
	register_activation_hook(__FILE__, 'activate_backup');
	register_uninstall_hook(__FILE__, 'uninstall_backup');

	add_action('admin_init', array($obj_backup, 'settings_backup'));
	add_action('admin_init', array($obj_backup, 'admin_init'), 0);

	load_plugin_textdomain('lang_backup', false, dirname(plugin_basename(__FILE__))."/lang/");
}

add_action('wp_ajax_perform_backup', array($obj_backup, 'perform_backup'));

function activate_backup()
{
	mf_uninstall_plugin(array(
		'options' => array('setting_backup_mysql', 'setting_backup_perform'),
	));
}

function uninstall_backup()
{
	mf_uninstall_plugin(array(
		'uploads' => 'mf_backup',
		'options' => array('setting_backup_schedule', 'setting_backup_sites', 'setting_backup_perform', 'setting_rss_api_key', 'setting_rss_url', 'setting_backup_limit', 'setting_backup_compress', 'setting_backup_db_type', 'setting_backup_db_tables', 'setting_backup_db_tables'),
	));
}