<?php

if(!defined('ABSPATH'))
{
	header('Content-Type: application/json');

	$folder = str_replace("/wp-content/plugins/mf_backup/include/api", "/", dirname(__FILE__));

	require_once($folder."wp-load.php");
}

$json_output = array();

$type = check_var('type', 'char');

$authkey_db = get_site_option('setting_rss_api_key');
$authkey_sent = check_var('authkey');

if($authkey_sent != $authkey_db)
{
	header("Status: 401 Unauthorized");
}

else
{
	switch($type)
	{
		case 'backups':
			$obj_backup->remove_backup_htaccess();

			$obj_backup = new mf_backup();

			$json_output['success'] = true;
			$json_output['data'] = $obj_backup->get_backup_list(array('output' => 'json'));
		break;
	}
}

echo json_encode($json_output);