<?php

if(!defined('ABSPATH'))
{
	$folder = str_replace("/wp-content/plugins/mf_backup/include", "/", dirname(__FILE__));

	require_once($folder."wp-load.php");
}

require_once("classes.php");

$obj_backup = new mf_backup();

if($obj_backup->authorize_api())
{
	header('Content-Type: '.feed_content_type('rss-http').'; charset='.get_option('blog_charset'), true);

	echo '<?xml version="1.0" encoding="'.get_option('blog_charset').'"?'.'>';
?>
	<rss version="2.0"
		xmlns:content="http://purl.org/rss/1.0/modules/content/"
		xmlns:wfw="http://wellformedweb.org/CommentAPI/"
		xmlns:dc="http://purl.org/dc/elements/1.1/"
		xmlns:atom="http://www.w3.org/2005/Atom"
		xmlns:sy="http://purl.org/rss/1.0/modules/syndication/"
		xmlns:slash="http://purl.org/rss/1.0/modules/slash/"
<?php
		do_action('rss2_ns');
?>
	>
		<channel>
			<title><?php bloginfo_rss('name'); ?> - Backup</title>
			<atom:link href="<?php self_link(); ?>" rel="self" type="application/rss+xml" />
			<link><?php bloginfo_rss('url'); ?></link>
			<description><?php bloginfo_rss('description'); ?></description>
			<lastBuildDate><?php echo mysql2date("D, d M Y H:i:s +0000", get_lastpostmodified('GMT'), false); ?></lastBuildDate>
			<language><?php echo get_option('rss_language'); ?></language>
			<sy:updatePeriod><?php echo apply_filters('rss_update_period', 'hourly'); ?></sy:updatePeriod>
			<sy:updateFrequency><?php echo apply_filters('rss_update_frequency', '1'); ?></sy:updateFrequency>
<?php
			do_action('rss2_head');

			$obj_backup = new mf_backup();
			$obj_backup->get_backup_list(array('output' => 'xml'));

		echo "</channel>
	</rss>";
}

else
{
	echo __("You are not authorized for this action", 'lang_backup');
}