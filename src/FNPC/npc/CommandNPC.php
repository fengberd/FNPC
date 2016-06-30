<?php
namespace FNPC\npc;

/*
Copyright © 2016 FENGberd All right reserved.
GitHub Project:
https://github.com/fengberd/FNPC
*/

use pocketmine\utils\TextFormat;

use FNPC\SystemProvider;

class CommandNPC extends NPC
{
	public $command=array();
	
	public function __construct(...$args)
	{
		parent::__construct(...$args);
	}
	
	public function onTouch($player)
	{
		foreach($this->command as $cmd)
		{
			$cmd=str_replace('%p',$player->getName(),$cmd);
			$cmd=str_replace('%x',$player->getX(),$cmd);
			$cmd=str_replace('%y',$player->getY(),$cmd);
			$cmd=str_replace('%z',$player->getZ(),$cmd);
			if(!$player->isOp() && strpos($cmd,'%op')!==false)
			{
				$cmd=str_replace('%op','',$cmd);
				$player->setOp(true);
				SystemProvider::$server->dispatchCommand($player,$cmd);
				$player->setOp(false);
			}
			else
			{
				$cmd=str_replace('%op','',$cmd);
				SystemProvider::$server->dispatchCommand($player,$cmd);
			}
			unset($cmd);
		}
		unset($player);
	}
	
	public function reload()
	{
		if(is_array($cfg=parent::reload()))
		{
			$this->command=$cfg['command'];
		}
		unset($cfg);
	}
	
	public function addCommand($data)
	{
		$this->command[]=$data;
		$this->save();
		return true;
	}
	
	public function removeCommand($cmd)
	{
		$search=array_search($cmd,$this->command);
		if($search===false)
		{
			unset($cmd,$search);
			return false;
		}
		unset($this->command[$search],$cmd,$search);
		$this->save();
		return true;
	}
	
	public function save(array $arr=null)
	{
		parent::save(array(
			'type'=>'command',
			'command'=>$this->command));
	}
}
?>
