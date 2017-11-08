<?php

/*function is_exec() {
	// Is function avail
	if ( ! function_exists( 'exec' ) ) {
		return false;
	}

	// Is shell_exec disabled?
	if ( in_array( 'exec', array_map( 'trim', explode( ',', @ini_get( 'disable_functions' ) ) ), true ) ) {
		return false;
	}

	// Can we issue a simple echo command?
	$output = exec( 'echo backwpupechotest' );
	if ( $output != 'backwpupechotest' ) {
		return false;
	}

	return true;
}*/

class mf_backup
{
	function __construct()
	{
		
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
		$setting_backup_limit = get_option('setting_backup_limit', 5);

		$this->arr_files = array();

		get_file_info(array('path' => $data['path'], 'callback' => array($this, 'gather_files')));

		//do_log("Gather files: ".var_export($this->arr_files, true));

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

	function backup_db($data = array())
	{
		global $wpdb;

		if(!isset($data['tables'])){	$data['tables'] = "*";}
		
		$success = false;

		$time_reset = strtotime(date("Y-m-d H:i:s"));
		set_time_limit(60);

		$setting_backup_compress = get_option_or_default('setting_backup_compress');
		$setting_backup_db_type = get_option_or_default('setting_backup_db_type', 'all');

		$file_suffix = "sql".($setting_backup_compress != '' ? ".".$setting_backup_compress : "");

		list($upload_path, $upload_url) = get_uploads_folder('mf_backup');
		
		$this->check_limit(array('path' => $upload_path, 'suffix' => $file_suffix));

		$file = $upload_path.date("Y-m-d_Hi")."_db_".$setting_backup_db_type."_".$this->random_chars().".".$file_suffix; //.($data['tables'] != "*" && $data['tables'] != '' ? "_".sanitize_title_with_dashes(sanitize_title($data['tables'])) : "")

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

		echo json_encode($result);
		die();
	}
}