<?php
namespace FNPC;

/*
Copyright © 2016 FENGberd All right reserved.
GitHub Project:
https://github.com/fengberd/FNPC
*/

class SystemProvider
{
	public static $plugin=null;
	public static $server=null;
	
	public static function init(Main $plugin)
	{
		SystemProvider::$plugin=$plugin;
		SystemProvider::$server=$plugin->getServer();
	}
	
	public static function debug($message)
	{
		self::$plugin->getLogger()->debug($message);
		unset($message);
	}
}

