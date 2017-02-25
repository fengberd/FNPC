<?php
namespace FNPC\npc;

/*
Copyright © 2017 FENGberd All right reserved.
GitHub Project:
https://github.com/fengberd/FNPC
*/

use pocketmine\Player;
use pocketmine\Server;

use pocketmine\entity\Entity;

use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;

use pocketmine\math\Vector3;

use FNPC\SystemProvider;
use FNPC\Utils\Converter;

class NPC extends \pocketmine\level\Location
{
	public static $pool=array();
	public static $config=null;
	public static $packet_hash='';
	public static $metadata=array();
	private static $unknownTypeData=array();
	
	public static function reloadUnknownNPC()
	{
		foreach(NPC::$unknownTypeData as $key=>$val)
		{
			if(($class=\FNPC\Main::getRegisteredNpcClass($val['type']))!==false)
			{
				$npc=new $class($key);
				$npc->reload();
				$npc->save();
				unset(NPC::$unknownTypeData[$key]);
			}
			unset($key,$val,$npc,$class);
		}
	}
	
	public static function init()
	{
		@mkdir(SystemProvider::$plugin->getDataFolder());
		@mkdir(SystemProvider::$plugin->getDataFolder().'skins/');
		@mkdir(SystemProvider::$plugin->getDataFolder().'skins/cache/');
		NPC::$pool=array();
		NPC::$config=new Config(SystemProvider::$plugin->getDataFolder().'NPC.yml',Config::YAML,array());
		SystemProvider::debug('static,config_loaded');
		foreach(NPC::$config->getAll() as $key=>$val)
		{
			if(($class=\FNPC\Main::getRegisteredNpcClass($val['type']))!==false)
			{
				$npc=new $class($key);
				$npc->reload();
				$npc->save();
			}
			else
			{
				NPC::$unknownTypeData[$key]=$val;
			}
			unset($key,$val,$npc,$class);
		}
	}
	
	public static function spawnAllTo($player,$level=false)
	{
		foreach(NPC::$pool as $npc)
		{
			$npc->spawnTo($player,$level);
			unset($npc);
		}
		unset($player,$level);
	}
	
	public static function packetReceive($player,$packet)
	{
		if($packet->pid()==\pocketmine\network\protocol\Info::INTERACT_PACKET)
		{
			if(NPC::$packet_hash!=spl_object_hash($packet))
			{
				NPC::$packet_hash=spl_object_hash($packet);
				foreach(NPC::$pool as $npc)
				{
					if($packet->target==$npc->getEID())
					{
						if($npc->needPay() && !$npc->checkPay($player,true,$player))
						{
							break;
						}
						$npc->onTouch($player);
					}
					unset($npc);
				}
			}
		}
		unset($player,$packet);
	}
	
	public static function tick()
	{
		foreach(NPC::$pool as $npc)
		{
			$npc->onTick();
			unset($npc);
		}
	}
	
	public static function playerMove($player)
	{
		foreach(NPC::$pool as $npc)
		{
			if($npc->distance($player)<=10)
			{
				$npc->look($player);
			}
			unset($npc);
		}
		unset($player);
	}
	
/*************************/
	
	public $nametag='';
	public $clientID=0;
	protected $eid=0;
	public $handItem;
	public $skinpath='';
	public $skin='';
	public $skinName='';
	protected $nid='';
	public $level='';
	public $uuid='';
	public $pay=0;
	public $extra='';
	
	public function __construct($nid,$nametag='',$x=0,$y=0,$z=0,$handItem=false,$clientID=false)
	{
		$this->nid=$nid;
		SystemProvider::debug('NPC:'.$this->nid.',construct_start');
		$this->uuid=\pocketmine\utils\UUID::fromRandom();
		$this->x=$x;
		$this->y=$y;
		$this->z=$z;
		$this->nametag=$nametag;
		if($clientID===false)
		{
			$clientID=mt_rand(1000000,9999999);
		}
		$this->clientID=$clientID;
		$this->eid=Entity::$entityCount++;
		if($handItem===false)
		{
			$handItem=\pocketmine\item\Item::get(0);
		}
		$this->handItem=$handItem;
		if(isset(NPC::$pool[$this->nid]))
		{
			SystemProvider::$plugin->getLogger()->warning('警告:尝试创建ID重复NPC:'.$this->nid.',请检查是否出现逻辑错误');
			NPC::$pool[$this->nid]->close();
		}
		NPC::$pool[$this->nid]=$this;
		SystemProvider::debug('NPC:'.$this->nid.',construct_success');
		unset($nametag,$x,$y,$z,$handItem,$clientID);
	}
	
	public function look($player)
	{
		if(!$player instanceof Player)
		{
			unset($player);
			return false;
		}
		$x=$this->x-$player->x;
		$y=$this->y-$player->y;
		$z=$this->z-$player->z;
		if(sqrt($x*$x+$z*$z)==0 || sqrt($x*$x+$z*$z+$y*$y)==0)
		{
			return true;
		}
		$yaw=asin($x/sqrt($x*$x+$z*$z))/3.14*180;
		$pitch=round(asin($y/sqrt($x*$x+$z*$z+$y*$y))/3.14*180);
		if($z>0)
		{
			$yaw=-$yaw+180;
		}
		$pk=new \pocketmine\network\protocol\MovePlayerPacket();
		$pk->eid=$this->getEID();
		$pk->x=$this->x;
		$pk->y=$this->y+1.62;
		$pk->z=$this->z;
		$pk->bodyYaw=$yaw;
		$pk->pitch=$pitch;
		$pk->yaw=$yaw;
		$pk->mode=0;
		$player->dataPacket($pk);
		unset($x,$y,$z,$yaw,$pitch,$player,$pk);
		return true;
	}
	
	public function reload()
	{
		if(NPC::$config->exists($this->getId()))
		{
			SystemProvider::debug('NPC:'.$this->nid.',reload_start');
			$cfg=NPC::$config->get($this->getId());
			$this->x=$this->get($cfg,'x');
			$this->y=$this->get($cfg,'y');
			$this->z=$this->get($cfg,'z');
			$this->level=$this->get($cfg,'level');
			$this->yaw=$this->get($cfg,'yaw');
			$this->pitch=$this->get($cfg,'pitch');
			$this->clientID=$this->get($cfg,'clientID');
			$this->nametag=$this->get($cfg,'nametag');
			$this->skinName=$this->get($cfg,'skinName');
			$this->skinName=$this->skinName==''?'Standard_Custom':$this->skinName;
			$this->pay=$this->get($cfg,'pay');
			$this->extra=$this->get($cfg,'extra');
			SystemProvider::debug('NPC:'.$this->nid.',reload_item');
			$this->handItem=\pocketmine\item\Item::get($cfg['handItem']['id'],$cfg['handItem']['data']);
			SystemProvider::debug('NPC:'.$this->nid.',reload_skin_start');
			if(is_file(SystemProvider::$plugin->getDataFolder().'skins/'.$this->get($cfg,'skin')))
			{
				$this->skin=Converter::getPngSkin(SystemProvider::$plugin->getDataFolder().'skins/'.$this->get($cfg,'skin'));
				SystemProvider::debug('NPC:'.$this->nid.',reload_skin_converted');
				if($this->skin===false)
				{
					$this->skin='';
				}
				else
				{
					SystemProvider::debug('NPC:'.$this->nid.',reload_skin_success');
					$this->skinpath=$this->get($cfg,'skin');
				}
			}
			return $cfg;
		}
		return false;
	}
	
	protected function get($cfg,$name)
	{
		return isset($cfg[$name])?$cfg[$name]:'';
	}
	
	public function setName($name)
	{
		$this->nametag=str_replace('\n',"\n",$name);
		$this->save();
		$this->spawnToAll();
		return true;
	}
	
	public function setPay($pay)
	{
		$this->pay=$pay;
		$this->save();
	}
	
	public function needPay()
	{
		return $this->pay!=0;
	}
	
	public function checkPay($player,$pay=true,Player $realPlayer=null)
	{
		if(!$this->needPay())
		{
			unset($player,$pay,$realPlayer);
			return true;
		}
		if($player instanceof Player)
		{
			$player=$player->getName();
		}
		$player=strtolower($player);
		if(Economy::getMoney($player)>=$this->pay)
		{
			if($pay)
			{
				if($realPlayer instanceof Player)
				{
					$realPlayer->sendMessage('[System] '.TextFormat::GREEN.'您花费了 '.$this->pay.' '.Economy::$moneyName);
				}
				return Economy::takeMoney($player,$this->pay);
			}
			unset($player,$pay,$realPlayer);
			return true;
		}
		if($realPlayer instanceof Player)
		{
			$realPlayer->sendMessage('[System] '.TextFormat::RED.'抱歉 ,您没有足够的'.Economy::$moneyName.'来使用NPC');
		}
		unset($player,$pay,$realPlayer);
		return false;
	}
	
	public function setPNGSkin($path,$useCache=true)
	{
		$this->skin=Converter::getPngSkin(SystemProvider::$plugin->getDataFolder().'skins/'.$path,$useCache);
		if($this->skin===-1)
		{
			$this->skin='';
			return -1;
		}
		else if($this->skin===-2)
		{
			$this->skin='';
			return -2;
		}
		else if($this->skin===-3)
		{
			$this->skin='';
			return -3;
		}
		$this->skinpath=$path;
		$this->save();
		$this->spawnToAll();
		return 0;
	}
	
	public function setHandItem($item)
	{
		$this->handItem=$item;
		$this->save();
		$this->spawnToAll();
		unset($item);
	}
	
	public function close($removeData=true)
	{
		$this->despawnFromAll();
		if($removeData)
		{
			NPC::$config->remove($this->getId());
			NPC::$config->save();
		}
		unset(NPC::$pool[$this->getId()],$this);
	}
	
	public function getEID()
	{
		return $this->eid;
	}
	
	public function getSkin()
	{
		return $this->skin;
	}
	
	public function getSkinPath()
	{
		return $this->skinpath;
	}
	
	public function getLevel()
	{
		return $this->level;
	}
	
	public function getId()
	{
		return $this->nid;
	}
	
	public function onTick()
	{
		
	}
	
	public function onTouch($player)
	{
		
	}
	
	public function teleport(Vector3 $pos)
	{
		$this->x=$pos->x;
		$this->y=$pos->y;
		$this->z=$pos->z;
		if($pos instanceof \pocketmine\level\Position)
		{
			$this->level=$pos->getLevel()->getFolderName();
			$this->spawnToAll();
		}
		else
		{
			$this->sendPosition();
		}
	}
	
	public function save(array $extra=array('type'=>'normal'))
	{
		NPC::$config->set($this->getId(),array_merge(array(
			'x'=>$this->x,
			'y'=>$this->y,
			'z'=>$this->z,
			'level'=>$this->level,
			'yaw'=>$this->yaw,
			'pitch'=>$this->pitch,
			'skin'=>$this->skinpath,
			'nametag'=>$this->nametag,
			'clientID'=>$this->clientID,
			'skinName'=>$this->skinName,
			'pay'=>$this->pay,
			'extra'=>$this->extra,
			'handItem'=>array(
				'id'=>$this->handItem->getId(),
				'data'=>$this->handItem->getDamage())),$extra));
		NPC::$config->save();
	}
	
	public function despawnFromAll()
	{
		if(($level=SystemProvider::$server->getLevelByName($this->level)) instanceof \pocketmine\level\Level)
		{
			$players=$level->getPlayers();
		}
		else
		{
			$players=SystemProvider::$plugin->getServer()->getOnlinePlayers();
		}
		foreach($players as $p)
		{
			$this->despawnFrom($p);
			unset($p);
		}
		unset($level,$players);
	}
	
	public function despawnFrom($player)
	{
		$class='\\pocketmine\\network\\protocol\\Remove'.(class_exists('\\pocketmine\\network\\protocol\\RemovePlayerPacket',false)?'Player':'Entity').'Packet';
		$pk=new $class();
		$pk->eid=$this->getEID();
		if(\pocketmine\API_VERSION!='2.0.0')
		{
			$pk->clientId=$this->uuid;
		}
		$player->dataPacket($pk);
		Server::getInstance()->removePlayerListData($this->uuid,array($player));
		unset($player,$pk);
	}
	
	public function spawnToAll()
	{
		if(($level=SystemProvider::$server->getLevelByName($this->level)) instanceof \pocketmine\level\Level)
		{
			$players=$level->getPlayers();
		}
		else
		{
			$players=SystemProvider::$plugin->getServer()->getOnlinePlayers();
		}
		foreach($players as $p)
		{
			$this->spawnTo($p);
			unset($p);
		}
		unset($player,$level);
	}
	
	public function spawnTo($player,$level=false)
	{
		if($level===false)
		{
			$level=$player->getLevel();
		}
		if($this->getLevel()!=='' && strtolower($level->getFolderName())!==strtolower($this->level))
		{
			$this->despawnFrom($player);
			return false;
		}
		$pk=new \pocketmine\network\protocol\AddPlayerPacket();
		$pk->clientID=$this->clientID;
		$pk->username=$this->nametag;
		$pk->eid=$this->getEID();
		$pk->uuid=$this->uuid;
		$pk->x=$this->x;
		$pk->y=$this->y;
		$pk->z=$this->z;
		$pk->speedX=0;
		$pk->speedY=0;
		$pk->speedZ=0;
		$pk->yaw=$this->yaw;
		$pk->pitch=$this->pitch;
		$pk->item=$this->handItem;
		$pk->metadata=self::$metadata;
		$base='\\pocketmine\\entity\\Entity::';
		if(defined($base.'DATA_NAMETAG') && defined($base.'DATA_FLAGS') && defined($base.'DATA_FLAG_CAN_SHOW_NAMETAG') && defined($base.'DATA_FLAG_ALWAYS_SHOW_NAMETAG'))
		{
			$pk->metadata[Entity::DATA_NAMETAG]=[Entity::DATA_TYPE_STRING,$this->nametag];
			$flags=0x00^1<<Entity::DATA_FLAG_CAN_SHOW_NAMETAG;
			$flags^=1<<Entity::DATA_FLAG_ALWAYS_SHOW_NAMETAG;
			$pk->metadata[Entity::DATA_FLAGS]=[Entity::DATA_TYPE_LONG,$flags];
		}
		if(defined($base.'DATA_LEAD_HOLDER') && class_exists('\\pocketmine\\network\\protocol\\SetEntityLinkPacket',false))
		{
			$pk->metadata[Entity::DATA_LEAD_HOLDER]=[Entity::DATA_TYPE_LONG,-1];
			$pk->metadata[Entity::DATA_LEAD]=[Entity::DATA_TYPE_BYTE,0];
			$pk1=new \pocketmine\network\protocol\SetEntityLinkPacket();
			$pk1->from=$this->getId();
			$pk1->to=0;
			$pk1->type=3;
			$player->dataPacket($pk1);
			unset($pk1);
		}
		$player->dataPacket($pk);
		Server::getInstance()->updatePlayerListData($this->uuid,$this->getEID(),$this->nametag,$this->skinName,$this->skin,array($player));
		Server::getInstance()->removePlayerListData($this->uuid,array($player));
		unset($player,$pk,$level);
		return true;
	}
	
	public function sendPosition()
	{
		$pk=new \pocketmine\network\protocol\MovePlayerPacket();
		$pk->eid=$this->getEID();
		$pk->x=$this->x;
		$pk->y=$this->y+1.62;
		$pk->z=$this->z;
		$pk->bodyYaw=$this->yaw;
		$pk->pitch=$this->pitch;
		$pk->yaw=$this->yaw;
		$pk->mode=0;
		foreach(SystemProvider::$plugin->getServer()->getOnlinePlayers() as $p)
		{
			$p->dataPacket($pk);
			unset($p);
		}
		unset($pk);
	}
}
