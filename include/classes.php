<?php

class mf_backup
{
	function __construct()
	{
		
	}

	function remove_oldest($data)
	{
		$int_file_oldest = $int_file_newest = $str_file_oldest = $str_file_newest = "";

		$arr_file_path = $arr_file_date = array();

		$i = 0;

		$dp = opendir($data['path']);

		while(($child = readdir($dp)) !== false)
		{
			if($child == '.' || $child == '..') continue;

			$file = $data['path'].$child;

			if(!is_dir($file) && substr($file, -4) == ".".$data['suffix'])
			{
				$file_date = date("Y-m-d H:i:s", filemtime($file));

				if($file_date < $str_file_oldest || $str_file_newest == '')
				{
					$int_file_oldest = $i;
					$str_file_oldest = $file_date;
				}

				if($file_date > $str_file_newest || $str_file_newest == '')
				{
					$int_file_newest = $i;
					$str_file_newest = $file_date;
				}

				$arr_file_path[$i] = $file;
				$arr_file_date[$i] = $file_date;

				$i++;
			}
		}

		closedir($dp);

		if(isset($arr_file_date[$int_file_oldest]) && $arr_file_date[$int_file_oldest] < date("Y-m-d H:i:s", strtotime("-24 day")))
		{
			do_log("Unlink ".$arr_file_path[$int_file_oldest]);

			//unlink($arr_file_path[$int_file_oldest]);
		}
	}

	function random_chars($data = array())
	{
		if(!isset($data['limit'])){	$data['limit'] = 5;}

		return substr(md5(microtime()), rand(0, 26), $data['limit']);
	}

	function backup_db($data = array())
	{
		global $wpdb;

		if(!isset($data['tables'])){	$data['tables'] = "*";}
		
		$success = false;

		$time_reset = strtotime(date("Y-m-d H:i:s"));
		set_time_limit(60);

		$setting_backup_db_type = get_option_or_default('setting_backup_db_type', 'all');

		list($upload_path, $upload_url) = get_uploads_folder('mf_backup');
		
		$this->remove_oldest(array('path' => $upload_path, 'suffix' => 'sql'));

		$file = $upload_path.date("Y-m-d_Hi")."_db_".$setting_backup_db_type."_".$this->random_chars().".sql"; //.($data['tables'] != "*" && $data['tables'] != '' ? "_".sanitize_title_with_dashes(sanitize_title($data['tables'])) : "")

		$db_struct = $db_info = "# ".get_site_url()." dump";

		if($data['tables'] == "*")
		{
			$data['tables'] = array();

			$result = $wpdb->get_results("SHOW TABLES", ARRAY_N);

			foreach($result as $r)
			{
				$data['tables'][] = $r[0];
			}
		}

		else
		{
			$data['tables'] = is_array($data['tables']) ? $data['tables'] : explode(',', $data['tables']);
		}

		foreach($data['tables'] as $table)
		{
			if(in_array($setting_backup_db_type, array('all', 'struct')))
			{
				$rows2 = $wpdb->get_results("SHOW CREATE TABLE ".$table, ARRAY_N);

				$db_struct .= $db_info .= "\n\n# Structure: ".$table."\n\n"
					.$rows2[0][1].";\n\n";
			}

			if(in_array($setting_backup_db_type, array('all', 'data')))
			{
				$result = $wpdb->get_results("SELECT * FROM ".$table, ARRAY_N);
				$rows = $wpdb->num_rows;

				if($rows > 0)
				{
					$db_info .= "# Data: ".$table."\n";

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
							if(strtotime(date("Y-m-d H:i:s")) - $time_reset > 20)
							{
								sleep(0.1);
								set_time_limit(60);

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

			if($db_info != '')
			{
				$success = set_file_content(array('file' => $file, 'mode' => 'a', 'content' => $db_info));

				$db_info = "";
			}
		}

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

		echo json_encode($result);
		die();
	}
}