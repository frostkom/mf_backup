<?php

if(!defined('ABSPATH'))
{
	header('Content-Type: application/json');

	$folder = str_replace("/wp-content/plugins/mf_backup/include/api", "/", dirname(__FILE__));

	require_once($folder."wp-load.php");
}

require_once("../classes.php");

$obj_backup = new mf_backup();

$json_output = array();

$type = check_var('type', 'char');

if($obj_backup->authorize_api())
{
	switch($type)
	{
		case 'get_backups':
			$obj_backup->change_backup_htaccess('rename');

			$json_output['success'] = true;
			$json_output['data'] = $obj_backup->get_backup_list(array('output' => 'json'));
		break;

		case 'end_backup':
			$obj_backup->change_backup_htaccess('restore');
		break;
	}
}

echo json_encode($json_output);