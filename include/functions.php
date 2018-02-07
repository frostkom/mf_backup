<?php

function cron_backup()
{
	$setting_backup_schedule = get_site_option('setting_backup_schedule');

	if($setting_backup_schedule != '')
	{
		$option_backup_saved = get_site_option('option_backup_saved');

		$schedule_cutoff = date("Y-m-d H:i:s", strtotime($option_backup_saved." -1 ".$setting_backup_schedule));

		if($option_backup_saved == '' || $schedule_cutoff > date("Y-m-d H:i:s"))
		{
			update_option('option_backup_saved', date("Y-m-d H:i:s"), 'no');

			$obj_backup = new mf_backup();

			$success = $obj_backup->backup_db();

			if($success == true)
			{
				error_log(__("I have saved the backup for you", 'lang_backup'));
			}

			else
			{
				error_log(__("I could not save the backup for you", 'lang_backup'));
			}
		}
	}
}

function settings_backup()
{
	if(IS_SUPER_ADMIN)
	{
		$plugin_include_url = plugin_dir_url(__FILE__);
		$plugin_version = get_plugin_version(__FILE__);

		mf_enqueue_script('script_backup', $plugin_include_url."script_wp.js", array('plugin_url' => $plugin_include_url, 'ajax_url' => admin_url('admin-ajax.php')), $plugin_version);

		$options_area = __FUNCTION__;

		add_settings_section($options_area, "", $options_area."_callback", BASE_OPTIONS_PAGE);

		$arr_settings = array();

		$arr_settings['setting_backup_schedule'] = __("Schedule", 'lang_backup');
		$arr_settings['setting_backup_limit'] = __("Number of backups to keep", 'lang_backup');
		$arr_settings['setting_backup_compress'] = __("Compression", 'lang_backup');
		$arr_settings['setting_backup_db_type'] = __("What to backup from DB", 'lang_backup');

		if(get_site_option('setting_backup_db_type') != 'struct')
		{
			$arr_settings['setting_backup_db_tables'] = __("Tables to Include", 'lang_backup');
		}

		else
		{
			delete_option('setting_backup_db_tables');
		}

		$arr_settings['setting_backup_perform'] = __("Perform Backup", 'lang_backup');

		if(is_plugin_active('backwpup/backwpup.php'))
		{
			$arr_settings['setting_rss_api_key'] = __("API Key", 'lang_backup');

			if(get_site_option('setting_rss_api_key') != '')
			{
				$arr_settings['setting_rss_url'] = __("URL", 'lang_backup');
			}
		}

		show_settings_fields(array('area' => $options_area, 'settings' => $arr_settings));
	}
}

function settings_backup_callback()
{
	$setting_key = get_setting_key(__FUNCTION__);

	echo settings_header($setting_key, __("Backup", 'lang_backup'));
}

function setting_backup_schedule_callback()
{
	$setting_key = get_setting_key(__FUNCTION__);
	settings_save_site_wide($setting_key);
	$option = get_site_option($setting_key, get_option($setting_key));

	$arr_data = array(
		'' => __("Inactivated", 'lang_backup'),
		'day' => __("Daily", 'lang_backup'),
		'week' => __("Weekly", 'lang_backup'),
		'month' => __("Monthly", 'lang_backup'),
	);

	echo show_select(array('data' => $arr_data, 'name' => $setting_key, 'value' => $option));

	if($option != '')
	{
		$option_backup_saved = get_site_option('option_backup_saved');

		if($option_backup_saved > DEFAULT_DATE)
		{
			$backup_next = format_date(date("Y-m-d H:i:s", strtotime($option_backup_saved." +1 ".$option)));

			echo "<p>".sprintf(__("The backup was last run %s and will run again %s", 'lang_backup'), format_date($option_backup_saved), $backup_next)."</p>";
		}

		else
		{
			echo "<p>".sprintf(__("The backup has never been run but will be %s", 'lang_backup'), get_next_cron())."</p>";
		}
	}
}

function setting_backup_limit_callback()
{
	$setting_key = get_setting_key(__FUNCTION__);
	settings_save_site_wide($setting_key);
	$option = get_site_option($setting_key, get_option($setting_key, 5));

	echo show_textfield(array('type' => 'number', 'name' => $setting_key, 'value' => $option, 'xtra' => "min='0' max='20'"));
}

function setting_backup_compress_callback()
{
	$setting_key = get_setting_key(__FUNCTION__);
	settings_save_site_wide($setting_key);
	$option = get_site_option($setting_key, get_option($setting_key));

	$arr_data = array(
		'' => __("No", 'lang_backup'),
	);

	if(function_exists('bzcompress'))
	{
		$arr_data['bz2'] = __("Bz2", 'lang_backup');
	}

	if(function_exists('gzencode'))
	{
		$arr_data['gz'] = __("Gzip", 'lang_backup');
	}

	echo show_select(array('data' => $arr_data, 'name' => $setting_key, 'value' => $option));
}

function setting_backup_db_type_callback()
{
	$setting_key = get_setting_key(__FUNCTION__);
	settings_save_site_wide($setting_key);
	$option = get_site_option($setting_key, get_option($setting_key));

	$arr_data = array(
		'all' => __("All", 'lang_backup'),
		'struct' => __("Structure", 'lang_backup'),
		'data' => __("Data", 'lang_backup'),
	);

	echo show_select(array('data' => $arr_data, 'name' => $setting_key, 'value' => $option));
}

function setting_backup_db_tables_callback()
{
	$setting_key = get_setting_key(__FUNCTION__);
	settings_save_site_wide($setting_key);
	$option = get_site_option($setting_key, get_option($setting_key));

	$obj_backup = new mf_backup();
	$arr_data = $obj_backup->get_tables_for_select();

	echo show_select(array('data' => $arr_data, 'name' => $setting_key."[]", 'value' => $option, 'description' => __("If none are chosen, all are backed up", 'lang_backup')));
}

function setting_backup_perform_callback()
{
	echo "<div class='form_buttons'>"
		.show_button(array('type' => 'button', 'name' => 'btnBackupPerform', 'text' => __("Run", 'lang_backup'), 'class' => 'button-secondary'))
	."</div>
	<div id='backup_debug'></div>";
}

function setting_rss_api_key_callback()
{
	$setting_key = get_setting_key(__FUNCTION__);
	settings_save_site_wide($setting_key);
	$option = get_site_option($setting_key, get_option($setting_key));

	echo show_password_field(array('name' => $setting_key, 'value' => $option, 'suffix' => __("Create a custom key here, the more advanced the better to protect the feed and thus the backup files", 'lang_backup')));
}

function get_backup_dir()
{
	$out = "";

	$option = is_multisite() ? get_site_option('backwpup_jobs') : get_option('backwpup_jobs');

	foreach($option as $key => $value)
	{
		if(isset($value['backupdir']))
		{
			$out .= ($out != '' ? ", " : "").$value['backupdir'];
		}
	}

	return $out;
}

function get_backup_files($data)
{
	global $globals;

	$globals['backup_files'][] = array(
		//'dir' => $data['file'],
		'url' => str_replace($data['upload_path'], $data['upload_url'], $data['file']),
		'name' => basename($data['file']),
		'time' => filemtime($data['file']),
	);
}

function gather_backup_files()
{
	global $globals;

	list($upload_path, $upload_url) = get_uploads_folder();

	$globals['backup_files'] = array();

	$option = is_multisite() ? get_site_option('backwpup_jobs') : get_option('backwpup_jobs');

	if(is_array($option))
	{
		foreach($option as $key => $value)
		{
			if(isset($value['backupdir']))
			{
				get_file_info(array('path' => str_replace("uploads/", $upload_path, $value['backupdir']), 'callback' => 'get_backup_files', 'upload_path' => $upload_path, 'upload_url' => $upload_url));
			}
		}

		$globals['backup_files'] = array_sort(array('array' => $globals['backup_files'], 'on' => 'time', 'order' => 'desc'));
	}

	return $globals['backup_files'];
}

function get_backup_list($data)
{
	$backup_files = gather_backup_files();

	$site_url = get_site_url();
	$arr_file_exclude = array('.donotbackup', 'index.php');

	switch($data['output'])
	{
		case 'htaccess':
			$htaccess_exists = false;

			foreach($backup_files as $file)
			{
				if($file['name'] == ".htaccess")
				{
					$htaccess_exists = true;
				}
			}

			return $htaccess_exists;
		break;

		case 'html':
			echo "<ul>";

				foreach($backup_files as $file)
				{
					if(!in_array($file['name'], $arr_file_exclude))
					{
						echo "<li><a href='".$site_url.$file['url']."'>".$file['name']." (".date("Y-m-d H:i:s", $file['time']).")</a></li>";
					}
				}

			echo "</ul>";
		break;

		case 'xml':
			foreach($backup_files as $file)
			{
				if(!in_array($file['name'], $arr_file_exclude))
				{
					echo "<item>
						<title>".$file['name']."</title>
						<link>".$file['url']."</link>
						<pubDate>".mysql2date('D, d M Y H:i:s +0000', date("Y-m-d H:i:s", $file['time']), false)."</pubDate>";

						rss_enclosure();
						do_action('rss2_item');

					echo "</item>";
				}
			}
		break;
	}
}

function setting_rss_url_callback()
{
	$setting_key = get_setting_key(__FUNCTION__);
	settings_save_site_wide($setting_key);
	$option = get_site_option($setting_key, get_option($setting_key));

	$authkey = get_site_option('setting_rss_api_key');

	if($authkey == '')
	{
		echo "<p>".__("You don't seam to have set an authorization key, please do so above", 'lang_backup')."</p>";
	}

	else if(get_backup_list(array('output' => 'htaccess')) == true)
	{
		echo "<p>".sprintf(__("You have to delete the %s file from (%s) the backup folders which you want to be able to download backups from", 'lang_backup'), ".htaccess", get_backup_dir())."</p>";
	}

	else
	{
		$rss_url = get_site_url()."/wp-content/plugins/mf_backup/include/feed.php?authkey=".$authkey;

		echo "<p><a href='".$rss_url."' class='button'>".__("RSS Link", 'lang_backup')."</a></p>
		<h4>".sprintf(__("Instructions to download backups to a %s", 'lang_backup'), "Synology NAS")."</h4>";

		echo "<ol>
			<li>".sprintf(__("Open %s", 'lang_backup'), "Download Station")."</li>
			<li>".sprintf(__("Go to % in the menu", 'lang_backup'), "RSS Feeds")."</li>
			<li>".__("Click on + and choose a name and enter the URL/Link as above", 'lang_backup')."</li>
			<li>".__("Go to settings (Cogwheel left bottom in the window)", 'lang_backup')."</li>
			<li>".sprintf(__("Click on the tab %s under %s", 'lang_backup'), "General", "BT/etc.")."</li>
			<li>".sprintf(__("Choose %s as %s", 'lang_backup'), "Download Schedule", "Immediately")."</li>
			<li>".sprintf(__("Click on the tab %s", 'lang_backup'), "RSS")."</li>
			<li>".sprintf(__("Choose %s as %s", 'lang_backup'), "Update Interval", "24 hours")."</li>
		</ol>";

		echo show_textfield(array('name' => $setting_key, 'value' => $option, 'placeholder' => "domain.quickconnect.to"));

		if($option != '')
		{
			echo "<p><a href='".validate_url($option, false)."' class='button'>".sprintf(__("Open the %s interface", 'lang_backup'), "Synology")."</a></p>";
		}
	}

	//get_backup_list(array('output' => 'html'));
}