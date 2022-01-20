<?php

class mf_backup
{
	function __construct()
	{
		$this->post_type = 'mf_backup';
		$this->meta_prefix = $this->post_type.'_';
	}

	function get_tables_for_select($data = array())
	{
		global $wpdb;

		if(!isset($data['search'])){	$data['search'] = '';}

		$arr_data = array();

		$query_where = "";

		if($data['search'] != '')
		{
			$query_where = " LIKE '".$data['search']."%'";
		}

		$result = $wpdb->get_results("SHOW TABLES".$query_where, ARRAY_N);

		foreach($result as $r)
		{
			$table_id = $table_name = $r[0];

			$table_size = $wpdb->get_var($wpdb->prepare("SELECT (DATA_LENGTH + INDEX_LENGTH) FROM information_schema.TABLES WHERE table_schema = %s AND table_name = %s", DB_NAME, $table_id));

			if($table_size > (1024 * 1024))
			{
				$table_name .= " (".show_final_size($table_size).")";
			}

			$arr_data[$table_id] = $table_name;
		}

		return $arr_data;
	}

	function get_backup_dir()
	{
		$out = "";

		$option = (is_multisite() ? get_site_option('backwpup_jobs') : get_option('backwpup_jobs'));

		foreach($option as $key => $value)
		{
			if(isset($value['backupdir']))
			{
				$out .= ($out != '' ? ", " : "").$value['backupdir'];
			}
		}

		return $out;
	}

	function change_backup_htaccess($type)
	{
		switch($type)
		{
			case 'remove':
				$filename = ".htaccess";
			break;

			default:
			case 'rename':
				$filename = ".htaccess";
				$filename_change = ".htaccess_temp";
			break;

			case 'restore':
				$filename = ".htaccess_temp";
				$filename_change = ".htaccess";
			break;
		}

		list($upload_path, $upload_url) = get_uploads_folder();

		$backup_dir = $this->get_backup_dir();
		$backup_htaccess = str_replace("uploads/", $upload_path, $backup_dir.$filename);

		if(file_exists($backup_htaccess))
		{
			//After a new plugin release backwpup_jobs might not be the same as previuosly expected, so this will remove .htaccess from the WP root
			if(preg_match("/uploads/", $backup_htaccess))
			{
				switch($type)
				{
					case 'remove':
						unlink($backup_htaccess);
					break;

					case 'rename':
					case 'restore':
						$backup_htaccess_change = str_replace("uploads/", $upload_path, $backup_dir.$filename_change);

						if(file_exists($backup_htaccess_change))
						{
							unlink($backup_htaccess);
						}

						else
						{
							rename($backup_htaccess, $backup_htaccess_change);

							if(file_exists($backup_htaccess))
							{
								do_log("I could not rename ".$backup_htaccess." to ".$backup_htaccess_change);
							}
						}
					break;
				}
			}

			else
			{
				switch($type)
				{
					case 'remove':
						do_log("Remove ".$backup_htaccess." but make sure that it is not the one in the WP root");
					break;

					case 'rename':
					case 'restore':
						do_log("Rename ".$backup_htaccess." to ".$backup_htaccess_change." but make sure that it is not the one in the WP root");
					break;
				}
			}
		}
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
		global $obj_base;

		$setting_backup_limit = get_site_option('setting_backup_limit', 5);

		if($setting_backup_limit > 0)
		{
			if(!isset($obj_base))
			{
				$obj_base = new mf_base();
			}

			$this->arr_files = array();

			get_file_info(array('path' => $data['path'], 'callback' => array($this, 'gather_files'), 'allow_depth' => false));

			foreach($this->arr_files as $suffix => $arr_files)
			{
				$arr_files = $obj_base->array_sort(array('array' => $arr_files, 'on' => 'time', 'order' => 'desc'));

				$count_temp = count($arr_files);

				for($i = ($setting_backup_limit - 1); $i < $count_temp; $i++)
				{
					unlink($arr_files[$i]['file']);
				}
			}
		}
	}

	function random_chars($data = array())
	{
		if(!isset($data['limit'])){	$data['limit'] = 5;}

		return substr(md5(microtime()), rand(0, 26), $data['limit']);
	}

	/*function archive($data)
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
	}*/

	function copy_directory($src, $dst)
	{
		$dir = opendir($src);
		@mkdir($dst);

		while(false !== ($file = readdir($dir)))
		{
			if(($file != '.') && ($file != '..'))
			{
				$source_dir = $src."/".$file;
				$destination_dir = $dst."/".$file;

				if(is_dir($source_dir))
				{
					if(is_numeric($file))
					{
						$this->copy_directory($source_dir, $destination_dir);
					}
				}

				else
				{
					copy($source_dir, $destination_dir);
				}
			}
		}

		closedir($dir);
	}

	function backup_ftp($data = array())
	{
		$success = false;

		$time_reset = strtotime(date("Y-m-d H:i:s"));
		set_time_limit(600);

		if(!isset($data['folder'])){			$data['folder'] = '';}
		if(!isset($data['random_chars'])){		$data['random_chars'] = $this->random_chars();}

		if($data['folder'] != '')
		{
			if(is_dir($data['folder']))
			{
				list($upload_path, $upload_url) = get_uploads_folder('mf_backup');

				//Check if there are more folders present in backup folder than 'setting_backup_limit'
				//$this->check_limit(array('path' => $upload_path, 'suffix' => $file_suffix));

				$folder_name = basename($data['folder']);

				if($folder_name == 'uploads')
				{
					$folder_name = 1;
				}

				$destination_folder = $upload_path.date("Y-m-d_Hi")."_ftp_".$folder_name."_".$data['random_chars']."/";

				$this->copy_directory($data['folder'], $destination_folder);

				$success = true;
			}

			/*else
			{
				do_log($data['folder']." is not a folder");
			}*/
		}

		return $success;
	}

	function backup_db($data = array())
	{
		global $wpdb;

		$success = false;

		$time_reset = strtotime(date("Y-m-d H:i:s"));
		set_time_limit(600);

		if(!isset($data['compress'])){		$data['compress'] = get_site_option('setting_backup_compress');}
		if(!isset($data['db_tables'])){		$data['db_tables'] = get_site_option('setting_backup_db_tables');}
		if(!isset($data['db_type'])){		$data['db_type'] = get_site_option('setting_backup_db_type');}
		if(!isset($data['random_chars'])){	$data['random_chars'] = $this->random_chars();}

		if($data['db_tables'] == '*' || $data['db_tables'] == '')
		{
			$data['db_tables'] = get_or_set_transient(array('key' => 'tables_for_select', 'callback' => array($this, 'get_tables_for_select')));
			$table_type = $data['db_type'];
		}

		else if(substr($data['db_tables'], 0, 3) == $wpdb->base_prefix)
		{
			@list($prefix, $id) = explode("_", $data['db_tables']);

			if(!(intval($id) > 0))
			{
				$id = 1;
			}

			$data['db_tables'] = $this->get_tables_for_select(array('search' => $data['db_tables']));
			$table_type = $id;
		}

		else
		{
			$table_type = 'select';
		}

		$file_suffix = "sql".($data['compress'] != '' ? ".".$data['compress'] : "");

		list($upload_path, $upload_url) = get_uploads_folder('mf_backup');

		$this->check_limit(array('path' => $upload_path, 'suffix' => $file_suffix));

		$file = prepare_file_name(date("Y-m-d")."_db_".$table_type."_".$data['random_chars']).".".$file_suffix;

		$db_struct = $db_info = "# ".get_site_url()." dump";

		foreach($data['db_tables'] as $table => $name)
		{
			if(in_array($data['db_type'], array('all', 'struct')))
			{
				$rows2 = $wpdb->get_results("SHOW CREATE TABLE ".$table, ARRAY_N);

				if($wpdb->num_rows > 0)
				{
					$db_struct .= $db_info .= "\n\n# Structure: ".$table."\n\n"
						.$rows2[0][1].";\n\n";
				}
			}

			if(in_array($data['db_type'], array('all', 'data')))
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

								$success = set_file_content(array('file' => $upload_path.$file, 'mode' => 'a', 'content' => $db_info));

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
				$success = set_file_content(array('file' => $upload_path.$file, 'mode' => 'a', 'content' => $db_info));

				$db_info = "";
			}
		}

		//$this->archive(array('source' => $upload_path.$file, 'target' => $file.".tar.bz2", 'remove_source' => true));

		return $success;
	}

	function do_backup($data = array())
	{
		global $wpdb;

		$success = false;

		if(!isset($data['site'])){		$data['site'] = get_site_option('setting_backup_sites');}

		$random_chars = $this->random_chars();

		if($data['site'] > 0)
		{
			list($upload_path, $upload_url) = get_uploads_folder();

			$success = $this->backup_ftp(array('folder' => $upload_path.($data['site'] > 1 ? "sites/".$data['site']."/" : ''), 'random_chars' => $random_chars));

			if($success == true)
			{
				$success = $this->backup_db(array('db_tables' => $wpdb->base_prefix.($data['site'] > 1 ? $data['site']."_" : ''), 'random_chars' => $random_chars));
			}
		}

		else
		{
			$success = $this->backup_db(array('random_chars' => $random_chars));
		}

		return $success;
	}

	function download_file($data) //$file_source, $file_target, $try = 1
	{
		if(!isset($data['try'])){	$data['try'] = 1;}

		//$version = 4;
		$target_size = 0;

		/*switch($version)
		{
			case 1:
				$rh = fopen($data['source'], 'rb');
				$wh = fopen($data['target'], 'w+b');

				if(!$rh || !$wh)
				{
					return false;
				}

				while(!feof($rh))
				{
					set_time_limit(0);

					if(fwrite($wh, fread($rh, 4096)) === FALSE)
					{
						return false;
					}

					flush();
				}

				fclose($rh);
				fclose($wh);
			break;

			// Does not work yet
			case 2:
				//This is the file where we save the information
				$fp = fopen($data['target'], 'w+');

				//Here is the file we are downloading, replace spaces with %20
				$ch = curl_init(str_replace(" ", "%20", $data['source']));

				curl_setopt($ch, CURLOPT_TIMEOUT, 50);

				// write curl response to file
				curl_setopt($ch, CURLOPT_FILE, $fp);
				curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

				// get curl response
				curl_exec($ch);
				curl_close($ch);

				fclose($fp);
			break;

			// Does not work yet
			case 3:
				$fp = fopen($data['target'], 'w');

				$ch = curl_init($data['source']);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
				curl_setopt($ch, CURLOPT_FILE, $fp);

				$data = curl_exec($ch); 

				curl_close($ch);

				fclose($fp);
			break;

			case 4:*/
				$fp = fopen($data['target'], 'w+');

				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, $data['source']);

				# set return transfer to false
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
				curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

				# increase timeout to download big file
				curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
				curl_setopt($ch, CURLOPT_FILE, $fp);

				# execute curl
				curl_exec($ch);

				$headers = curl_getinfo($ch);

				curl_close($ch);
				fclose($fp);

				switch($headers['http_code'])
				{
					case 301:
						if(isset($headers['redirect_url']) && $headers['redirect_url'] != $data['source'])
						{
							if($data['try'] > 2)
							{
								do_log("I was redirected from ".$data['source']." for the ".$data['try']." time to ".$headers['redirect_url']." so I QUIT! I am done!");
							}

							else
							{
								//do_log("I was redirected from ".$data['source']." for the ".$data['try']." time to ".$headers['redirect_url']);

								unlink($data['source']);

								$data['source'] = $headers['redirect_url'];
								$data['try']++;
								$this->download_file($data);
							}
						}
					break;
				}

				$target_size = filesize($data['target']);
			/*break;
		}*/

		if($target_size == $data['source_size'])
		{
			return true;
		}

		else
		{
			return false;
		}
	}

	function add_item($item)
	{
		global $wpdb;

		$post_data = array(
			'post_title' => $item['name'],
			'post_parent' => $item['parent_id'],
			'post_type' => $this->post_type,
			'post_status' => 'publish',
			'meta_input' => array(
				//$this->meta_prefix.'url' => $item['url'],
				$this->meta_prefix.'path' => $item['path'],
				$this->meta_prefix.'size' => $item['size'],
				$this->meta_prefix.'time' => $item['time'],
			),
		);

		$result = $wpdb->get_results($wpdb->prepare("SELECT ID FROM ".$wpdb->posts." WHERE post_parent = '%d' AND post_title = %s", $item['parent_id'], $item['name']));

		if($wpdb->num_rows > 0)
		{
			$i = 0;

			foreach($result as $r)
			{
				$post_id = $r->ID;

				if($i == 0)
				{
					$post_data['ID'] = $post_id;

					wp_update_post($post_data);

					$i++;
				}

				else
				{
					wp_trash_post($post_id);
				}
			}
		}

		else
		{
			$post_id = wp_insert_post($post_data);
		}
	}

	function cron_base()
	{
		global $wpdb;

		$obj_cron = new mf_cron();
		$obj_cron->start(__CLASS__);

		if($obj_cron->is_running == false)
		{
			/* Save new backup */
			##########################
			$setting_backup_schedule = get_site_option('setting_backup_schedule');

			if($setting_backup_schedule != '')
			{
				$option_backup_saved = get_site_option('option_backup_saved');

				$schedule_cutoff = date("Y-m-d H:i:s", strtotime($option_backup_saved." -1 ".$setting_backup_schedule));

				if($option_backup_saved == '' || $schedule_cutoff < date("Y-m-d H:i:s"))
				{
					update_option('option_backup_saved', date("Y-m-d H:i:s"), 'no');

					if($this->do_backup() == false)
					{
						do_log("I could not save the backup for you");
					}
				}
			}
			##########################

			/* Download backups from external sites */
			####################
			//set_time_limit(0);

			$result = $wpdb->get_results($wpdb->prepare("SELECT ID FROM ".$wpdb->posts." WHERE post_type = %s AND post_status = %s", $this->post_type, 'publish'));

			if($wpdb->num_rows > 0)
			{
				list($upload_path_base, $upload_url_base) = get_uploads_folder("mf_backup/sites");

				if(!file_exists($upload_path_base.".htaccess"))
				{
					// 
					#############################
					global $obj_base;

					if(!isset($obj_base))
					{
						$obj_base = new mf_base();
					}

					$html = $update_with = "";

					switch($obj_base->get_server_type())
					{
						default:
						case 'apache':
							$update_with = "<Files \"*\">\r\n"
							."	<IfModule mod_access.c>\r\n"
							."		Deny from all\r\n"
							."	</IfModule>\r\n"
							."	<IfModule !mod_access_compat>\r\n"
							."		<IfModule mod_authz_host.c>\r\n"
							."			Deny from all\r\n"
							."		</IfModule>\r\n"
							."	</IfModule>\r\n"
							."	<IfModule mod_access_compat>\r\n"
							."		Deny from all\r\n"
							."	</IfModule>\r\n"
							."</Files>\r\n";
						break;

						case 'nginx':
							// What do I do here?
							$update_with = "";
						break;
					}

					$html .= $obj_base->update_config(array(
						'plugin_name' => "MF Backup",
						'file' => $upload_path_base.".htaccess",
						'update_with' => $update_with,
						'auto_update' => true,
					));

					if($html == '')
					{
						do_log("Added .htaccess to ".$upload_path_base." folder to prevent download");
					}

					else
					{
						do_log("I could not add .htaccess to ".$upload_path_base." folder to prevent download (".nl2br($html).")");
					}
					#############################
				}

				foreach($result as $r)
				{
					$post_id = $r->ID;

					$post_domain = get_post_meta($post_id, $this->meta_prefix.'domain', true);
					$post_api_key = get_post_meta($post_id, $this->meta_prefix.'api_key', true);

					if($post_domain != '' && $post_api_key != '')
					{
						$url = $post_domain."/wp-content/plugins/mf_backup/include/api/?type=get_backups&authkey=".$post_api_key;

						list($content, $headers) = get_url_content(array(
							'url' => $url,
							'catch_head' => true,
						));

						$log_message = sprintf("The response from %s had an error", $url);

						switch($headers['http_code'])
						{
							case 200:
								$json = json_decode($content, true);

								if(isset($json['success']) && $json['success'] == true)
								{
									list($upload_path, $upload_url) = get_uploads_folder("mf_backup/sites/".remove_protocol(array('url' => $post_domain, 'clean' => true)));

									$post_limit_amount = count($json['data']);

									foreach($json['data'] as $item)
									{
										$file_remote_url = $item['url'];

										$file_name = basename($file_remote_url);
										$file_local_path = $upload_path.$file_name;

										if($file_name == ".htaccess" || $file_name == ".htaccess_temp")
										{
											//do_log("Ignore file (".$file_name.") from ".$url);
											$post_limit_amount--;
										}

										else
										{
											if(file_exists($file_local_path) && filesize($file_local_path) > 0)
											{
												if(isset($item['size']) && $item['size'] != filesize($file_local_path))
												{
													unlink($file_local_path);
												}

												else
												{
													$item_temp = $item;
													$item_temp['parent_id'] = $post_id;
													$item_temp['path'] = $file_local_path;
													$this->add_item($item_temp);
												}
											}

											if(!file_exists($file_local_path))
											{
												$success = $this->download_file(array('source' => $file_remote_url, 'source_size' => $item['size'], 'target' => $file_local_path));

												if($success)
												{
													$item_temp = $item;
													$item_temp['parent_id'] = $post_id;
													$item_temp['path'] = $file_local_path;
													$this->add_item($item_temp);

													//do_log("Downloaded ".$file_name." to ".$file_local_path);
												}

												else
												{
													do_log("NOT downloaded ".$file_name." (".$item['size'].") to ".$file_local_path." (".filesize($file_local_path).")");
												}
											}
										}
									}

									update_post_meta($post_id, $this->meta_prefix.'limit_amount', $post_limit_amount);
									update_post_meta($post_id, $this->meta_prefix.'last_fetched', date("Y-m-d H:i:s"));

									if($this->get_amount(array('id' => $post_id)) > $post_limit_amount)
									{
										$backup_id = $wpdb->get_var($wpdb->prepare("SELECT ID FROM ".$wpdb->posts." INNER JOIN ".$wpdb->postmeta." ON ".$wpdb->posts.".ID = ".$wpdb->postmeta.".post_id WHERE post_type = %s AND post_parent = '%d' AND post_status != %s AND meta_key = %s ORDER BY meta_value ASC LIMIT 0, 1", $this->post_type, $post_id, 'trash', $this->meta_prefix.'time'));

										//do_log("Removed the oldest backup for ".$post_domain." (".$wpdb->last_query.")");

										if($backup_id > 0)
										{
											wp_trash_post($backup_id);
											//do_log("Trashed ".$backup_id." (".get_post_title($backup_id).")");
										}
									}

									do_log($log_message, 'trash');

									$url = $post_domain."/wp-content/plugins/mf_backup/include/api/?type=end_backup&authkey=".$post_api_key;

									list($content, $headers) = get_url_content(array(
										'url' => $url,
										'catch_head' => true,
									));
								}

								else
								{
									do_log($log_message." (".var_export($json, true).")");
								}
							break;

							default:
								do_log($log_message." (".$headers['http_code'].")");
							break;
						}
					}
				}
			}
			####################
		}

		$obj_cron->end();
	}

	function init()
	{
		if(get_site_option('setting_rss_api_key') == '')
		{
			$labels = array(
				'name' => _x(__("Backup", 'lang_backup'), 'post type general name'),
				'singular_name' => _x(__("Backup", 'lang_backup'), 'post type singular name'),
				'menu_name' => __("Backup", 'lang_backup'),
			);

			$args = array(
				'labels' => $labels,
				'public' => false,
				'show_ui' => true,
				'show_in_menu' => true,
				'show_in_nav_menus' => false,
				'exclude_from_search' => true,
				//'capability_type' => 'page',
				'menu_position' => 100,
				'menu_icon' => 'dashicons-backup',
				'supports' => array('title'),
				'hierarchical' => true,
				'has_archive' => false,
			);

			register_post_type($this->post_type, $args);
		}
	}

	function settings_backup()
	{
		if(IS_SUPER_ADMIN)
		{
			$options_area_orig = $options_area = __FUNCTION__;

			// Generic
			############################
			add_settings_section($options_area, "", array($this, $options_area."_callback"), BASE_OPTIONS_PAGE);

			$arr_settings = array(
				'setting_backup_schedule' => __("Schedule", 'lang_backup'),
				'setting_backup_limit' => __("Number of backups to keep", 'lang_backup'),
			);

			if(is_multisite() && get_site_option('setting_backup_db_tables') == '' && is_plugin_active("mf_site_manager/index.php"))
			{
				$arr_settings['setting_backup_sites'] = __("Sites", 'lang_backup');
			}

			$arr_settings['setting_backup_perform'] = __("Perform Backup", 'lang_backup');

			if(is_plugin_active("backwpup/backwpup.php"))
			{
				$arr_settings['setting_rss_api_key'] = __("API Key", 'lang_backup');

				if(get_site_option('setting_rss_api_key') != '')
				{
					$arr_settings['setting_rss_url'] = __("URL", 'lang_backup');
				}
			}

			show_settings_fields(array('area' => $options_area, 'object' => $this, 'settings' => $arr_settings));
			############################

			// FTP
			############################
			/*$options_area = $options_area_orig."_ftp";

			add_settings_section($options_area, "", array($this, $options_area."_callback"), BASE_OPTIONS_PAGE);

			$arr_settings = array(
				'setting_backup_limit' => ,
			);

			show_settings_fields(array('area' => $options_area, 'object' => $this, 'settings' => $arr_settings));*/
			############################

			// Database
			############################
			$options_area = $options_area_orig."_db";

			add_settings_section($options_area, "", array($this, $options_area."_callback"), BASE_OPTIONS_PAGE);

			$arr_settings = array(
				'setting_backup_compress' => __("Compression", 'lang_backup'),
			);

			if(get_site_option('setting_backup_sites') == '')
			{
				$arr_settings['setting_backup_db_type'] = __("What to backup from DB", 'lang_backup');

				if(get_site_option('setting_backup_db_type') != 'struct')
				{
					$arr_settings['setting_backup_db_tables'] = __("Tables to Include", 'lang_backup');
				}

				else
				{
					delete_option('setting_backup_db_tables');
				}
			}

			show_settings_fields(array('area' => $options_area, 'object' => $this, 'settings' => $arr_settings));
			############################
		}
	}

	function settings_backup_callback()
	{
		$setting_key = get_setting_key(__FUNCTION__);

		echo settings_header($setting_key, __("Backup", 'lang_backup'));
	}

	function settings_backup_db_callback()
	{
		$setting_key = get_setting_key(__FUNCTION__);

		echo settings_header($setting_key, __("Backup", 'lang_backup')." - ".__("Database", 'lang_backup'));
	}

	function setting_backup_schedule_callback()
	{
		$setting_key = get_setting_key(__FUNCTION__);
		settings_save_site_wide($setting_key);
		$option = get_site_option($setting_key, get_option($setting_key));

		$arr_data = array(
			'' => "-- ".__("Inactivated", 'lang_backup')." --",
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
					.($backup_next < date("Y-m-d H:i:s") ? "<i class='fa fa-exclamation-triangle yellow'></i> " : "")
					.sprintf(__("The backup was last run %s and will run again %s", 'lang_backup'), format_date($option_backup_saved), $backup_next)
				."</p>";
			}

			else
			{
				echo "<p class='display_warning'>"
					."<i class='fa fa-exclamation-triangle yellow'></i> "
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

	function setting_backup_sites_callback()
	{
		$setting_key = get_setting_key(__FUNCTION__);
		settings_save_site_wide($setting_key);
		$option = get_site_option($setting_key, get_option($setting_key));

		$obj_site_manager = new mf_site_manager();

		echo show_select(array('data' => $obj_site_manager->get_sites_for_select(), 'name' => $setting_key, 'value' => $option));
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
			$arr_data['bz2'] = "Bz2";
		}

		if(function_exists('gzencode'))
		{
			$arr_data['gz'] = "Gzip";
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

		$arr_data = get_or_set_transient(array('key' => 'tables_for_select', 'callback' => array($this, 'get_tables_for_select')));

		echo show_select(array('data' => $arr_data, 'name' => $setting_key."[]", 'value' => $option, 'description' => __("If none are chosen, all are backed up", 'lang_backup')));
	}

	function setting_backup_perform_callback()
	{
		echo "<div>"
			.show_button(array('type' => 'button', 'name' => 'btnBackupPerform', 'text' => __("Run", 'lang_backup'), 'class' => 'button-secondary'))
		."</div>
		<div id='backup_debug'></div>";
	}

	function setting_rss_api_key_callback()
	{
		$setting_key = get_setting_key(__FUNCTION__);
		settings_save_site_wide($setting_key);
		$option = get_site_option($setting_key, get_option($setting_key));

		echo show_password_field(array('name' => $setting_key, 'value' => $option, 'xtra' => " autocomplete='new-password'", 'suffix' => __("Create a custom key here, the more advanced the better to protect the feed and thus the backup files", 'lang_backup')));
	}

	function get_backup_files($data)
	{
		global $globals;

		$file_name = basename($data['file']);

		if(!preg_match("/\.zip\./", $file_name) && !isset($globals['backup_files'][$file_name]))
		{
			$globals['backup_files'][$file_name] = array(
				'path' => $data['file'],
				'url' => str_replace($data['upload_path'], $data['upload_url'], $data['file']),
				'name' => $file_name,
				'time' => filemtime($data['file']),
			);
		}
	}

	function gather_backup_files()
	{
		global $globals, $obj_base;

		list($upload_path, $upload_url) = get_uploads_folder();

		$globals['backup_files'] = array();

		$option = (is_multisite() ? get_site_option('backwpup_jobs') : get_option('backwpup_jobs'));

		if(is_array($option))
		{
			foreach($option as $key => $value)
			{
				if(isset($value['backupdir']))
				{
					get_file_info(array('path' => str_replace("uploads/", $upload_path, $value['backupdir']), 'callback' => array($this, 'get_backup_files'), 'upload_path' => $upload_path, 'upload_url' => $upload_url));
				}
			}

			if(!isset($obj_base))
			{
				$obj_base = new mf_base();
			}

			$globals['backup_files'] = $obj_base->array_sort(array('array' => $globals['backup_files'], 'on' => 'time', 'order' => 'desc'));
		}

		return $globals['backup_files'];
	}

	function get_backup_list($data)
	{
		$backup_files = $this->gather_backup_files();

		$site_url = get_site_url();
		$arr_file_exclude = array('.donotbackup', 'index.php', '.htaccess', '.htaccess_temp');

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
				$out = "<ul>";

					foreach($backup_files as $file)
					{
						if(!in_array($file['name'], $arr_file_exclude))
						{
							$out .= "<li><a href='".$site_url.$file['url']."'>".$file['name']." (".date("Y-m-d H:i:s", $file['time']).")</a></li>";
						}
					}

				$out .= "</ul>";

				return $out;
			break;

			case 'json':
				$arr_out = array();

				foreach($backup_files as $file)
				{
					if(!in_array($file['name'], $arr_file_exclude))
					{
						$arr_out[] = array(
							'url' => $file['url'],
							'name' => $file['name'],
							'time' => date("Y-m-d H:i:s", $file['time']),
							'size' => filesize($file['path']),
						);
					}
				}

				return $arr_out;
			break;

			case 'xml':
				$this->change_backup_htaccess('rename');

				foreach($backup_files as $file)
				{
					if(!in_array($file['name'], $arr_file_exclude))
					{
						echo "<item>
							<title>".$file['name']."</title>
							<link>".$file['url']."</link>
							<pubDate>".mysql2date("D, d M Y H:i:s +0000", date("Y-m-d H:i:s", $file['time']), false)."</pubDate>";

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

		else
		{
			/*if($this->get_backup_list(array('output' => 'htaccess')) == true)
			{
				$backup_dir = $this->get_backup_dir();

				echo "<p><i class='fa fa-exclamation-triangle yellow'></i> ".sprintf(__("You have to delete the %s file from (%s) the backup folders which you want to be able to download backups from", 'lang_backup'), ".htaccess", $backup_dir)."</p>";
			}*/

			$api_url = get_site_url()."/wp-content/plugins/mf_backup/include/api/?type=get_backups&authkey=".$authkey;
			$rss_url = get_site_url()."/wp-content/plugins/mf_backup/include/feed.php?authkey=".$authkey;

			echo "<p><a href='".$api_url."' class='button'>".__("API Link", 'lang_backup')."</a> <a href='".$rss_url."' class='button'>".__("RSS Link", 'lang_backup')."</a></p>
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

		//echo $this->get_backup_list(array('output' => 'html'));
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

	function filter_sites_table_settings($arr_settings)
	{
		$arr_settings['settings_backup'] = array(
			'setting_backup_schedule' => array(
				'type' => 'string',
				'global' => true,
				'icon' => "fas fa-download",
				'name' => __("Schedule", 'lang_backup'),
			),
		);

		return $arr_settings;
	}

	function row_actions($actions, $post)
	{
		if($post->post_type == $this->post_type)
		{
			$post_id = $post->ID;

			unset($actions['inline hide-if-no-js']);

			$post_domain = get_post_meta($post_id, $this->meta_prefix.'domain', true);
			$post_api_key = get_post_meta($post_id, $this->meta_prefix.'api_key', true);

			if($post_domain != '' && $post_api_key != '')
			{
				$url = $post_domain."/wp-content/plugins/mf_backup/include/api/?type=get_backups&authkey=".$post_api_key;

				$actions['source'] = "<a href='".$url."'>".__("Source", 'lang_backup')."</a>";
			}

			else
			{
				unset($actions['edit']);
				//unset($actions['trash']);
			}
		}

		return $actions;
	}

	function rwmb_meta_boxes($meta_boxes)
	{
		$meta_boxes[] = array(
			'id' => $this->meta_prefix.'settings',
			'title' => __("Settings", 'lang_backup'),
			'post_types' => array($this->post_type),
			'context' => 'normal',
			'priority' => 'low',
			'fields' => array(
				// Parents
				array(
					'name' => __("Domain", 'lang_backup'),
					'id' => $this->meta_prefix.'domain',
					'type' => 'url',
					'attributes' => array(
						'placeholder' => get_site_url(),
					),
				),
				array(
					'name' => __("API Key", 'lang_backup'),
					'id' => $this->meta_prefix.'api_key',
					'type' => 'text',
				),
				// Children
				/*array(
					'name' => __("URL", 'lang_backup'),
					'id' => $this->meta_prefix.'url',
					'type' => 'url',
				),*/
				array(
					'name' => __("Path", 'lang_backup'),
					'id' => $this->meta_prefix.'path',
					'type' => 'text',
				),
				array(
					'name' => __("Size", 'lang_backup'),
					'id' => $this->meta_prefix.'size',
					'type' => 'number',
				),
			)
		);

		return $meta_boxes;
	}

	function column_header($cols)
	{
		global $post_type;

		unset($cols['date']);

		switch($post_type)
		{
			case $this->post_type:
				$cols['size'] = __("Size", 'lang_backup');
				$cols['last_fetched'] = __("Last Updated", 'lang_backup');
			break;
		}

		return $cols;
	}

	function get_amount($data = array())
	{
		global $wpdb;

		if(!isset($data['id'])){			$data['id'] = 0;}
		//if(!isset($data['post_status'])){	$data['post_status'] = 'publish';}

		if($data['id'] > 0)
		{
			$this->id = $data['id'];
		}

		return $wpdb->get_var($wpdb->prepare("SELECT COUNT(ID) FROM ".$wpdb->posts." WHERE post_type = %s AND post_parent = '%d' AND post_status != %s", $this->post_type, $this->id, 'trash')); // AND post_status = %s //, $data['post_status']
	}

	function column_cell($col, $id)
	{
		global $wpdb, $post;

		switch($post->post_type)
		{
			case $this->post_type:
				switch($col)
				{
					case 'size':
						$post_meta = get_post_meta($id, $this->meta_prefix.'size', true);

						// Children
						if($post_meta > 0)
						{
							echo show_final_size($post_meta);
						}

						// Parents
						else
						{
							$post_amount = $this->get_amount(array('id' => $id));
							$post_limit_amount = get_post_meta($id, $this->meta_prefix.'limit_amount', true);

							if($post_amount > 0 || $post_limit_amount > 0)
							{
								echo $post_amount." / ".$post_limit_amount;
							}
						}
					break;

					case 'last_fetched':
						$post_meta = get_post_meta($id, $this->meta_prefix.'last_fetched', true);

						// Parents
						if($post_meta > DEFAULT_DATE)
						{
							echo format_date($post_meta);
						}

						// Children
						else
						{
							$post_meta = get_post_meta($id, $this->meta_prefix.'time', true);

							if($post_meta > DEFAULT_DATE)
							{
								echo format_date($post_meta);
							}
						}
					break;
				}
			break;
		}
	}

	function wp_trash_post($post_id)
	{
		global $wpdb;

		if(get_post_type($post_id) == $this->post_type)
		{
			$post_meta = get_post_meta($post_id, $this->meta_prefix.'path', true);

			if($post_meta != '')
			{
				unlink($post_meta);
				//do_log("Removed the file ".$post_meta);
			}
		}
	}

	function filter_last_updated_post_types($array, $type)
	{
		$array[] = $this->post_type;

		return $array;
	}

	function perform_backup()
	{
		global $done_text, $error_text;

		$result = array();

		if($this->do_backup() == true)
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