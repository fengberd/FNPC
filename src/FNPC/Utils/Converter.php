<?php
namespace FNPC\Utils;

/*
Copyright © 2016 FENGberd All right reserved.
GitHub Project:
https://github.com/fengberd/FNPC
*/

use FNPC\SystemProvider;

class Converter
{
	public static function getPngSkin($path,$useCache=true)
	{
		try
		{
			if($useCache && is_file(SystemProvider::$plugin->getDataFolder().'skins/cache/'.basename($path).'.cache'))
			{
				return file_get_contents(SystemProvider::$plugin->getDataFolder().'skins/cache/'.basename($path).'.cache');
			}
			if(!is_file($path))
			{
				return -1;
			}
			$png=new PNG($path);
			if($png->status===false)
			{
				return -2;
			}
			$png->decode();
			$skin='';
			foreach($png->color as $v)
			{
				foreach($v as $k)
				{
					$data=self::hex(array($k->r,$k->g,$k->b,$k->a));
					$skin.=$data[0].$data[1].$data[2].$data[3];
					unset($k);
				}
				unset($v);
			}
			unset($png,$useCache);
			file_put_contents(SystemProvider::$plugin->getDataFolder().'skins/cache/'.basename($path).'.cache',$skin);
			return $skin;
		}
		catch(\Exception $e)
		{
			return -3;
		}
	}
	
	private static function hex($data)
	{
		$arr=array(0,1,2,3,4,5,6,7,8,9,'A','B','C','D','E','F');
		foreach($data as $num)
		{
			$t="";
			$num=intval($num);
			if($num===0)
			{
				$aOutChar[]=hex2bin('00');
				unset($t,$num);
				continue;
			}
			while($num>0)
			{
				$t=$arr[$num%16].$t;
				$num=floor($num/16);
			}
			if(strlen($t)==1)
			{
				$t='0'.$t;
			}
			$aOutChar[]=hex2bin($t);
			unset($t,$num);
		}
		unset($arr,$data);
		return $aOutChar;
	}
	
	public static function PPM2Skin($ppm)
	{
		if(substr($ppm,0,2)!=='P6')
		{
			SystemProvider::$plugin->getLogger()->info(\pocketmine\utils\TextFormat::RED.'错误的PPM图片,仅支持Binary格式');
			unset($ppm);
			return false;
		}
		$stream=fopen('php://memory','r+');
		fwrite($stream,$ppm);
		rewind($stream);
		fgets($stream);
		fgets($stream);
		fgets($stream);
		fgets($stream);
		$ppm=stream_get_contents($stream);
		fclose($stream);
		$offset=0;
		$str='';
		while(isset($ppm{$offset}))
		{
			$str.=$ppm{$offset++}.$ppm{$offset++}.$ppm{$offset++}.chr(0);
		}
		unset($stream,$offset,$ppm);
		return $str;
	}
}
