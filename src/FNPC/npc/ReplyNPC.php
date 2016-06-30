<?php
namespace FNPC\npc;

/*
Copyright © 2016 FENGberd All right reserved.
GitHub Project:
https://github.com/fengberd/FNPC
*/

use pocketmine\utils\TextFormat;
use pocketmine\event\player\PlayerCommandPreprocessEvent;

use FNPC\SystemProvider;

class ReplyNPC extends NPC
{
	public $chat=array();
	
	public function __construct(...$args)
	{
		parent::__construct(...$args);
	}
	
	public function onTouch($player)
	{
		if(count($this->chat)>0)
		{
			$player->sendMessage('<'.$this->nametag.'> '.$this->chat[array_rand($this->chat)]);
		}
		unset($player);
	}
	
	public function reload()
	{
		if(is_array($cfg=parent::reload()))
		{
			$this->chat=$cfg['chat'];
		}
		unset($cfg);
	}
	
	public function addChat($data)
	{
		$this->chat[]=$data;
		$this->save();
		return true;
	}
	
	public function removeChat($cmd)
	{
		$search=array_search($cmd,$this->chat);
		if($search===false)
		{
			unset($cmd,$search);
			return false;
		}
		unset($this->chat[$search],$cmd,$search);
		$this->save();
		return true;
	}
	
	public function save(array $arr=null)
	{
		parent::save(array(
			'type'=>'reply',
			'chat'=>$this->chat));
	}
}
?>
