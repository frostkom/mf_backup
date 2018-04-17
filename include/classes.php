<?php

class mf_backup
{
	function __construct()
	{
	
	}

	function admin_init()
	{
		global $pagenow;

		if($pagenow == 'options-general.php' && check_var('page') == 'settings_mf_base')
		{
			$plugin_include_url = plugin_dir_url(__FILE__);
			$plugin_version = get_plugin_version(__FILE__);

			mf_enqueue_script('script_backup', $plugin_include_url."script_wp.js", array('plugin_url' => $plugin_include_url, 'ajax_url' => admin_url('admin-ajax.php')), $plugin_version);
		}
	}

	function settings_backup()
	{
		if(IS_SUPER_ADMIN)
		{
			$options_area = __FUNCTION__;

			add_settings_section($options_area, "", array($this, $options_area."_callback"), BASE_OPTIONS_PAGE);

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

			show_settings_fields(array('area' => $options_area, 'object' => $this, 'settings' => $arr_settings));
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

				echo "<p".($backup_next < date("Y-m-d H:i:s") ? "" : "").">"
					.($backup_next < date("Y-m-d H:i:s") ? "<i class='fa fa-warning yellow'></i> " : "")
					.sprintf(__("The backup was last run %s and will run again %s", 'lang_backup'), format_date($option_backup_saved), $backup_next)
				."</p>";
			}

			else
			{
				echo "<p class='display_warning'>"
					."<i class='fa fa-warning yellow'></i> "
					.sprintf(__("The backup has never been run but will be %s", 'lang_backup'), get_next_cron())
				."</p>";
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
		$arr_data = $obj_backup->get_tables_for_select(array('check_size' => (is_array($option) && count($option) > 0)));

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
					get_file_info(array('path' => str_replace("uploads/", $upload_path, $value['backupdir']), 'callback' => array($this, 'get_backup_files'), 'upload_path' => $upload_path, 'upload_url' => $upload_url));
				}
			}

			$globals['backup_files'] = array_sort(array('array' => $globals['backup_files'], 'on' => 'time', 'order' => 'desc'));
		}

		return $globals['backup_files'];
	}

	function get_backup_list($data)
	{
		$backup_files = $this->gather_backup_files();

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

		$display_instructions = true;

		if($authkey == '')
		{
			echo "<p>".__("You don't seam to have set an authorization key, please do so above", 'lang_backup')."</p>";
		}

		else if($this->get_backup_list(array('output' => 'htaccess')) == true)
		{
			echo "<p>".sprintf(__("You have to delete the %s file from (%s) the backup folders which you want to be able to download backups from", 'lang_backup'), ".htaccess", $backup_dir)."</p>";
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

		//$this->get_backup_list(array('output' => 'html'));
	}

	function gather_files($data)
	{
		if(!is_dir($data['file']))
		{
			$file_suffix = get_file_suffix(str_replace(array(".bz2", ".gz"), "", $data['file']));

			$this->arr_files[$file_suffix][] = array(
				'file' => $data['file'],
				'time' => date("Y-m-d H:i:s", filemtime($data['file'])),
			);
		}
	}

	function check_limit($data)
	{
		$setting_backup_limit = get_site_option('setting_backup_limit', 5);

		$this->arr_files = array();

		get_file_info(array('path' => $data['path'], 'callback' => array($this, 'gather_files'), 'allow_depth' => false));

		foreach($this->arr_files as $suffix => $arr_files)
		{
			$arr_files = array_sort(array('array' => $arr_files, 'on' => 'time', 'order' => 'desc'));

			$count_temp = count($arr_files);

			for($i = ($setting_backup_limit - 1); $i < $count_temp; $i++)
			{
				unlink($arr_files[$i]['file']);
			}
		}
	}

	function random_chars($data = array())
	{
		if(!isset($data['limit'])){	$data['limit'] = 5;}

		return substr(md5(microtime()), rand(0, 26), $data['limit']);
	}

	function archive($data)
	{
		if(!isset($data['options'])){			$data['options'] = "";}
		if(!isset($data['remove_source'])){		$data['remove_source'] = false;}

		switch(get_file_suffix($data['target']))
		{
			case 'bz2':
				$data['options'] .= 'j';
			break;

			case 'gz':
				$data['options'] .= 'z';
			break;

			case 'zip':
				$data['options'] .= 'Z';
			break;
		}

		exec("tar -cf".$data['options']." ".$data['target']." ".$data['source'], $output, $return_var);

		if($data['remove_source'] == true && file_exists($data['target']) && is_file($data['source']))
		{
			do_log("Remove ".$data['source']);
			//unlink($data['source']);
		}
	}

	function get_tables_for_select($data = array())
	{
		global $wpdb;

		if(!isset($data['include_ids'])){	$data['include_ids'] = true;}
		if(!isset($data['check_size'])){	$data['check_size'] = true;}

		$arr_data = array();

		$result = $wpdb->get_results("SHOW TABLES", ARRAY_N);

		foreach($result as $r)
		{
			$table_id = $table_name = $r[0];

			if($data['check_size'] == true)
			{
				$table_size = $wpdb->get_var($wpdb->prepare("SELECT (DATA_LENGTH + INDEX_LENGTH) FROM information_schema.TABLES WHERE table_schema = %s AND table_name = %s", DB_NAME, $table_id));

				if($table_size > (1024 * 1024))
				{
					$table_name .= " (".show_final_size($table_size).")";
				}
			}

			if($data['include_ids'] == true)
			{
				$arr_data[$table_id] = $table_name;
			}

			else
			{
				$arr_data[] = $table_name;
			}
		}

		return $arr_data;
	}

	function run_cron()
	{
		$obj_cron = new mf_cron();
		$obj_cron->start(__FUNCTION__);

		if($obj_cron->is_running == false)
		{
			$setting_backup_schedule = get_site_option('setting_backup_schedule');

			if($setting_backup_schedule != '')
			{
				$option_backup_saved = get_site_option('option_backup_saved');

				$schedule_cutoff = date("Y-m-d H:i:s", strtotime($option_backup_saved." -1 ".$setting_backup_schedule));

				if($option_backup_saved == '' || $schedule_cutoff < date("Y-m-d H:i:s"))
				{
					update_option('option_backup_saved', date("Y-m-d H:i:s"), 'no');

					$success = $this->backup_db();

					if($success == true)
					{
						//do_log(__("I have saved the backup for you", 'lang_backup'));
					}

					else
					{
						do_log(__("I could not save the backup for you", 'lang_backup'));
					}
				}
			}

			if(get_site_option('setting_rss_api_key') != '')
			{
				$this->remove_backup_htaccess();
			}
		}

		$obj_cron->end();
	}

	function remove_backup_htaccess()
	{
		list($upload_path, $upload_url) = get_uploads_folder();

		$backup_dir = $this->get_backup_dir();
		$backup_htaccess = str_replace("uploads/", $upload_path, $backup_dir.".htaccess");

		if(file_exists($backup_htaccess))
		{
			unlink($backup_htaccess);
		}
	}

	function backup_db($data = array())
	{
		global $wpdb;

		$success = false;

		$time_reset = strtotime(date("Y-m-d H:i:s"));
		set_time_limit(600);

		$setting_backup_compress = get_site_option('setting_backup_compress');
		$setting_backup_db_tables = get_site_option('setting_backup_db_tables');
		$setting_backup_db_type = get_site_option('setting_backup_db_type');

		if($setting_backup_db_tables == '*' || $setting_backup_db_tables == '')
		{
			$setting_backup_db_tables = $this->get_tables_for_select(array('check_size' => false)); //'include_ids' => false

			$table_type = $setting_backup_db_type;
		}

		else
		{
			$table_type = 'select';
		}

		$file_suffix = "sql".($setting_backup_compress != '' ? ".".$setting_backup_compress : "");

		list($upload_path, $upload_url) = get_uploads_folder('mf_backup');

		$this->check_limit(array('path' => $upload_path, 'suffix' => $file_suffix));

		$file = $upload_path.date("Y-m-d_Hi")."_db_".$table_type."_".$this->random_chars().".".$file_suffix;

		$db_struct = $db_info = "# ".get_site_url()." dump";

		foreach($setting_backup_db_tables as $table => $name)
		{
			if(in_array($setting_backup_db_type, array('all', 'struct')))
			{
				$rows2 = $wpdb->get_results("SHOW CREATE TABLE ".$table, ARRAY_N);

				$db_struct .= $db_info .= "\n\n# Structure: ".$table."\n\n"
					.$rows2[0][1].";\n\n";
			}

			if(in_array($setting_backup_db_type, array('all', 'data')))
			{
				$row_limit = 100;
				$row_amount = $wpdb->get_var("SELECT COUNT(1) FROM ".$table);

				for($row_start = 0; $row_start < $row_amount; $row_start += $row_limit)
				{
					$result = $wpdb->get_results("SELECT * FROM ".$table." LIMIT ".$row_start.", ".$row_limit, ARRAY_N);
					$rows = $wpdb->num_rows;

					if($rows > 0)
					{
						if($row_start == 0)
						{
							$db_info .= "# Data: ".$table."\n";
						}

						$i = 0;

						foreach($result as $r)
						{
							$db_info .= "\n";

							if($i % 5000 == 0)
							{
								$db_info .= "INSERT IGNORE INTO `".$table."` VALUES";
							}

							$db_info .= "(";

							$j = 0;

							foreach($r as $key => $value)
							{
								if((strtotime(date("Y-m-d H:i:s")) - $time_reset) > 300)
								{
									sleep(0.1);
									set_time_limit(600);

									$time_reset = strtotime(date("Y-m-d H:i:s"));
								}

								$value = str_replace("\n", "\\n", addslashes($value));

								$db_info .= ($j > 0 ? "," : "").(isset($value) ? "'".$value."'" : "'NULL'");

								$j++;
							}

							$db_info .= ")";

							$i++;

							if($i % 5000 == 0 || $i == $rows)
							{
								$db_info .= ";";

								$success = set_file_content(array('file' => $file, 'mode' => 'a', 'content' => $db_info));

								$db_info = "";
							}

							else
							{
								$db_info .= ",";
							}
						}
					}
				}
			}

			if($db_info != '')
			{
				$success = set_file_content(array('file' => $file, 'mode' => 'a', 'content' => $db_info));

				$db_info = "";
			}
		}

		//$this->archive(array('source' => $file, 'target' => $file.".tar.bz2", 'remove_source' => true));

		return $success;
	}

	function perform_backup()
	{
		global $wpdb, $done_text, $error_text;

		$result = array();

		$success = $this->backup_db();

		if($success == true)
		{
			$done_text = __("I have saved the backup for you", 'lang_backup');
		}

		else
		{
			$error_text = __("I could not save the backup for you", 'lang_backup');
		}

		$out = get_notification();

		if($done_text != '')
		{
			$result['success'] = true;
			$result['message'] = $out;
		}

		else
		{
			$result['error'] = $out;
		}

		header('Content-Type: application/json');
		echo json_encode($result);
		die();
	}
}