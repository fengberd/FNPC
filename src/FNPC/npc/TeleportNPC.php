<?php
namespace FNPC\npc;

/*
Copyright © 2016 FENGberd All right reserved.
GitHub Project:
https://github.com/fengberd/FNPC
*/

use pocketmine\level\Level;
use pocketmine\math\Vector3;
use pocketmine\level\Position;
use pocketmine\utils\TextFormat;

use FNPC\SystemProvider;

class TeleportNPC extends NPC
{
	public $teleport=false;
	
	public function __construct(...$args)
	{
		parent::__construct(...$args);
	}
	
	public function onTouch($player)
	{
		if($this->teleport===false)
		{
			$player->sendMessage('[System] '.TextFormat::RED.'该NPC未设置传送目标');
		}
		else if(isset($this->teleport['ip']))
		{
			$pk=new \FNPC\protocol\StrangePacket;
			$pk->address=$this->teleport['ip'];
			$pk->port=intval($this->teleport['port']);
			$player->dataPacket($pk->setChannel(\pocketmine\network\Network::CHANNEL_ENTITY_SPAWNING));
		}
		else if($this->teleport['level']!=='')
		{
			if(($level=SystemProvider::$server->getLevelByName($this->teleport['level']))===false)
			{
				$player->sendMessage('[System] '.TextFormat::RED.'目标传送世界不存在');
			}
			else
			{
				$player->teleport(new Position($this->teleport['x'],$this->teleport['y'],$this->teleport['z'],$level));
				$player->sendMessage('[System] '.TextFormat::GREEN.'传送成功');
			}
			unset($level);
		}
		else
		{
			$player->teleport(new Vector3($this->teleport['x'],$this->teleport['y'],$this->teleport['z']));
			$player->sendMessage('[System] '.TextFormat::GREEN.'传送成功');
		}
		unset($player);
	}
	
	public function reload()
	{
		if(is_array($cfg=parent::reload()))
		{
			$this->teleport=$cfg['teleport'];
		}
		unset($cfg);
	}
	
	public function setTeleport($data)
	{
		if($data instanceof Vector3)
		{
			$this->teleport=array(
				'x'=>$data->x,
				'y'=>$data->y,
				'z'=>$data->z,
				'level'=>'');
			if($data instanceof Position && $data->getLevel() instanceof Level)
			{
				$this->teleport['level']=$data->getLevel()->getFolderName();
			}
		}
		else if(is_array($data))
		{
			$this->teleport=$data;
		}
		else
		{
			$this->teleport=false;
		}
		$this->save();
	}
	
	public function save(array $arr=null)
	{
		parent::save(array(
			'type'=>'teleport',
			'teleport'=>$this->teleport));
	}
}
?>
