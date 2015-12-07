<?php
namespace FNPC;

/*
Copyright FENGberd © 2015 All right reserved.
Coding Project:
http://coding.net/u/FENGberd/p/FNPC
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

