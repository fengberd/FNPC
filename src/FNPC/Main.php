<?php
namespace FNPC;

/*
Copyright © 2016 FENGberd All right reserved.
GitHub Project:
https://github.com/fengberd/FNPC
*/

use pocketmine\utils\TextFormat;

use FNPC\npc\NPC;
use FNPC\npc\CommandNPC;
use FNPC\npc\ReplyNPC;
use FNPC\npc\TeleportNPC;

class Main extends \pocketmine\plugin\PluginBase implements \pocketmine\event\Listener
{
	private static $obj=null;
	private static $registeredNPC=array();
	
	public static function getInstance()
	{
		return self::$obj;
	}
	
	public static function getRegisteredNpcClass($name)
	{
		$name=strtolower($name);
		if(isset(self::$registeredNPC[$name]))
		{
			return self::$registeredNPC[$name][0];
		}
		return false;
	}
	
	public static function unregisterNpc($name)
	{
		$name=strtolower($name);
		unset(self::$registeredNPC[$name]);
	}
	
	public static function registerNpc($name,$description,$className,$force=false)
	{
		$class=new \ReflectionClass($className);
		$name=strtolower($name);
		if(is_a($className,NPC::class,true) && !$class->isAbstract() && (!isset(self::$registeredNPC[$name]) || $force))
		{
			self::$registeredNPC[$name]=array($className,$description);
			NPC::reloadUnknownNPC();
			unset($className,$class,$force,$name,$description);
			return true;
		}
		unset($className,$class,$force,$name,$description);
		return false;
	}
	
	public function onEnable()
	{
		$start=microtime(true);
		if(!self::$obj instanceof Main)
		{
			self::$obj=$this;
			self::registerNpc('normal','普通NPC(无实际功能)',NPC::class,true);
			self::registerNpc('reply','回复型NPC(使用/fnpc chat)',ReplyNPC::class,true);
			self::registerNpc('command','指令型NPC(使用/fnpc command)',CommandNPC::class,true);
			self::registerNpc('teleport','传送型NPC(使用/fnpc teleport或/fnpc transfer)',TeleportNPC::class,true);
		}
		$base='\\pocketmine\\entity\\Entity::';
		if(!defined($base.'DATA_NAMETAG') || !defined($base.'DATA_FLAGS') || !defined($base.'DATA_FLAG_CAN_SHOW_NAMETAG') || !defined($base.'DATA_FLAG_ALWAYS_SHOW_NAMETAG'))
		{
			$this->getLogger()->warning('当前核心存在奇葩问题,将导致无法正常显示NPC名称');
		}
		if(defined($base.'DATA_LEAD_HOLDER') && !class_exists('\\pocketmine\\network\\protocol\\SetEntityLinkPacket',false))
		{
			$this->getLogger()->warning('你这奇葩核心删掉了SetEntityLink包,我不敢保证在玩家和NPC之间不会出现奇怪的绳子');
		}
		$reflect=new \ReflectionClass('\\pocketmine\\entity\\Entity');
		$reflect=$reflect->getDefaultProperties();
		if(!isset($reflect['dataProperties']))
		{
			throw new \Exception('抱歉,你正在使用一个FNPC无法兼容的智障核心');
		}
		NPC::$metadata=$reflect['dataProperties'];
		SystemProvider::init($this);
		NPC::init();
		
		$this->initTasks();
		$this->getServer()->getPluginManager()->registerEvents($this,$this);
		$this->getLogger()->info(TextFormat::GREEN.'NPC数据加载完毕,耗时'.(microtime(true)-$start).'秒');
	}
	
	public function initTasks()
	{
		$this->quickSystemTask=new Tasks\QuickSystemTask($this);
		$this->getServer()->getScheduler()->scheduleRepeatingTask($this->quickSystemTask,1);
	}
	
	public function onCommand(\pocketmine\command\CommandSender $sender,\pocketmine\command\Command $command,$label,array $args)
	{
		unset($command,$label);
		if(!isset($args[0]))
		{
			unset($sender,$args);
			return false;
		}
		if(isset($args[1]) && is_numeric($args[1]))
		{
			$sender->sendMessage('[NPC] '.TextFormat::YELLOW.'纯数字ID会导致无法找到NPC，请使用英文/中文/混合ID');
		}
		switch($args[0])
		{
		case 'type':
			$data=TextFormat::GREEN.'=========='.TextFormat::YELLOW.'FNPC Type List'.TextFormat::GREEN.'==========';
			foreach(self::$registeredNPC as $key=>$val)
			{
				$data.="\n".TextFormat::YELLOW.$key.TextFormat::WHITE.' - '.TextFormat::AQUA.$val[1];
				unset($key,$val);
			}
			$sender->sendMessage($data);
			unset($data);
			break;
		case 'add':
			if(!isset($args[3]))
			{
				unset($sender,$args);
				return false;
			}
			if(isset(NPC::$pool[$args[2]]))
			{
				$sender->sendMessage('[NPC] '.TextFormat::RED.'已存在同ID的NPC');
				break;
			}
			$args[1]=strtolower($args[1]);
			if(!isset(self::$registeredNPC[$args[1]]))
			{
				$sender->sendMessage('[NPC] '.TextFormat::RED.'指定类型不存在 ,请使用 /fnpc type 查看可用类型');
				break;
			}
			$npc=new self::$registeredNPC[$args[1]][0]($args[2],$args[3],$sender->x,$sender->y,$sender->z);
			$npc->level=$sender->getLevel()->getFolderName();
			$npc->spawnToAll();
			$npc->save();
			unset($npc);
			$sender->sendMessage('[NPC] '.TextFormat::GREEN.'NPC创建成功');
			break;
		case 'transfer':
			if(!isset($args[3]))
			{
				unset($sender,$args);
				return false;
			}
			if(!isset(NPC::$pool[$args[1]]))
			{
				$sender->sendMessage('[NPC] '.TextFormat::RED.'不存在此NPC');
				break;
			}
			if(!NPC::$pool[$args[1]] instanceof TeleportNPC)
			{
				$sender->sendMessage('[NPC] '.TextFormat::RED.'该NPC不是传送型NPC');
				break;
			}
			NPC::$pool[$args[1]]->setTeleport(array(
				'ip'=>$args[2],
				'port'=>$args[3]
			));
			$sender->sendMessage('[NPC] '.TextFormat::GREEN.'NPC传送点设置成功');
			break;
		case 'remove':
			if(!isset($args[1]))
			{
				unset($sender,$args);
				return false;
			}
			if(!isset(NPC::$pool[$args[1]]))
			{
				$sender->sendMessage('[NPC] '.TextFormat::RED.'不存在此NPC');
				break;
			}
			NPC::$pool[$args[1]]->close();
			$sender->sendMessage('[NPC] '.TextFormat::GREEN.'移除成功');
			break;
		case 'reset':
			if(!isset($args[1]))
			{
				unset($sender,$args);
				return false;
			}
			if(!isset(NPC::$pool[$args[1]]))
			{
				$sender->sendMessage('[NPC] '.TextFormat::RED.'不存在此NPC');
				break;
			}
			$npc=NPC::$pool[$args[1]];
			if($npc instanceof TeleportNPC)
			{
				$npc->setTeleport(false);
				$sender->sendMessage('[NPC] '.TextFormat::GREEN.'NPC传送点移除成功');
			}
			else if($npc instanceof CommandNPC)
			{
				$npc->command=array();
				$npc->save();
				$sender->sendMessage('[NPC] '.TextFormat::GREEN.'NPC指令清空成功');
			}
			break;
		case 'teleport':
			if(!isset($args[1]))
			{
				unset($sender,$args);
				return false;
			}
			if(!isset(NPC::$pool[$args[1]]))
			{
				$sender->sendMessage('[NPC] '.TextFormat::RED.'不存在此NPC');
				break;
			}
			if(!NPC::$pool[$args[1]] instanceof TeleportNPC)
			{
				$sender->sendMessage('[NPC] '.TextFormat::RED.'该NPC不是传送型NPC');
				break;
			}
			NPC::$pool[$args[1]]->setTeleport($sender);
			$sender->sendMessage('[NPC] '.TextFormat::GREEN.'NPC传送点设置成功');
			break;
		case 'command':
			if(!isset($args[2]))
			{
				unset($sender,$args);
				return false;
			}
			if(!isset(NPC::$pool[$args[1]]))
			{
				$sender->sendMessage('[NPC] '.TextFormat::RED.'不存在此NPC');
				break;
			}
			if(!NPC::$pool[$args[1]] instanceof CommandNPC)
			{
				$sender->sendMessage('[NPC] '.TextFormat::RED.'该NPC不是指令型NPC');
				break;
			}
			switch($args[2])
			{
			case 'add':
				if(!isset($args[3]))
				{
					unset($sender,$args);
					return false;
				}
				$cmd='';
				for($i=3;$i<count($args);$i++)
				{
					$cmd.=$args[$i];
					if($i!=count($args)-1)
					{
						$cmd.=' ';
					}
				}
				unset($i);
				NPC::$pool[$args[1]]->addCommand($cmd);
				$sender->sendMessage('[NPC] '.TextFormat::GREEN.'NPC指令添加成功');
				break;
			case 'remove':
				if(!isset($args[3]))
				{
					unset($sender,$args);
					return false;
				}
				$cmd='';
				for($i=3;$i<count($args);$i++)
				{
					$cmd.=$args[$i];
					if($i!=count($args)-1)
					{
						$cmd.=' ';
					}
				}
				unset($i);
				if(NPC::$pool[$args[1]]->removeCommand($cmd))
				{
					$sender->sendMessage('[NPC] '.TextFormat::GREEN.'NPC指令移除成功');
				}
				else
				{
					$sender->sendMessage('[NPC] '.TextFormat::RED.'NPC未添加该指令');
				}
				break;
			case 'list':
				$msg=TextFormat::GREEN.'===NPC指令列表==='."\n";
				foreach(NPC::$pool[$args[1]]->command as $cmd)
				{
					$msg.=TextFormat::YELLOW.$cmd."\n";
					unset($cmd);
				}
				$sender->sendMessage($msg);
				unset($msg);
				break;
			default:
				unset($sender,$args);
				return false;
			}
			break;
		case 'chat':
			if(!isset($args[2]))
			{
				unset($sender,$args);
				return false;
			}
			if(!isset(NPC::$pool[$args[1]]))
			{
				$sender->sendMessage('[NPC] '.TextFormat::RED.'不存在此NPC');
				break;
			}
			if(!NPC::$pool[$args[1]] instanceof ReplyNPC)
			{
				$sender->sendMessage('[NPC] '.TextFormat::RED.'该NPC不是回复型NPC');
				break;
			}
			switch($args[2])
			{
			case 'add':
				if(!isset($args[3]))
				{
					unset($sender,$args);
					return false;
				}
				$cmd='';
				for($i=3;$i<count($args);$i++)
				{
					$cmd.=$args[$i];
					if($i!=count($args)-1)
					{
						$cmd.=' ';
					}
				}
				unset($i);
				NPC::$pool[$args[1]]->addChat($cmd);
				$sender->sendMessage('[NPC] '.TextFormat::GREEN.'NPC对话数据添加成功');
				break;
			case 'remove':
				if(!isset($args[3]))
				{
					unset($sender,$args);
					return false;
				}
				$cmd='';
				for($i=3;$i<count($args);$i++)
				{
					$cmd.=$args[$i];
					if($i!=count($args)-1)
					{
						$cmd.=' ';
					}
				}
				unset($i);
				if(NPC::$pool[$args[1]]->removeChat($cmd))
				{
					$sender->sendMessage('[NPC] '.TextFormat::GREEN.'NPC对话数据移除成功');
				}
				else
				{
					$sender->sendMessage('[NPC] '.TextFormat::RED.'NPC未添加该对话数据');
				}
				break;
			default:
				unset($sender,$args);
				return false;
			}
			break;
		case 'name':
			if(!isset($args[2]))
			{
				unset($sender,$args);
				return false;
			}
			if(!isset(NPC::$pool[$args[1]]))
			{
				$sender->sendMessage('[NPC] '.TextFormat::RED.'不存在此NPC');
				break;
			}
			NPC::$pool[$args[1]]->setName($args[2]);
			$sender->sendMessage('[NPC] '.TextFormat::GREEN.'NameTag设置成功');
			break;
		case 'skin':
			if(!isset($args[2]))
			{
				unset($sender,$args);
				return false;
			}
			if(!isset(NPC::$pool[$args[1]]))
			{
				$sender->sendMessage('[NPC] '.TextFormat::RED.'不存在此NPC');
				break;
			}
			switch(NPC::$pool[$args[1]]->setPNGSkin($args[2],false))
			{
			case 0:
				$sender->sendMessage('[NPC] '.TextFormat::GREEN.'皮肤更换成功');
				break;
			case -1:
				$sender->sendMessage('[NPC] '.TextFormat::RED.'皮肤文件不存在,请检查输入的路径是否正确,需要加.png');
				break;
			case -2:
				$sender->sendMessage('[NPC] '.TextFormat::RED.'皮肤文件无效,请使用MCPE可以正常加载的png皮肤');
				break;
			case -3:
			default:
				$sender->sendMessage('[NPC] '.TextFormat::RED.'未知错误,请检查皮肤路径是否正确以及是否可以在MCPE正常使用');
				break;
			}
			break;
		case 'item':
			if(!isset($args[2]))
			{
				unset($sender,$args);
				return false;
			}
			if(!isset(NPC::$pool[$args[1]]))
			{
				$sender->sendMessage('[NPC] '.TextFormat::RED.'不存在此NPC');
				break;
			}
			$item=explode(':',$args[2]);
			if(!isset($item[1]))
			{
				$item[1]=0;
			}
			$item[0]=intval($item[0]);
			$item[1]=intval($item[1]);
			NPC::$pool[$args[1]]->setHandItem(\pocketmine\item\Item::get($item[0],$item[1]));
			$sender->sendMessage('[NPC] '.TextFormat::GREEN.'手持物品更换成功');
			break;
		case 'tphere':
		case 'teleporthere':
			if(!isset($args[1]))
			{
				unset($sender,$args);
				return false;
			}
			if(!isset(NPC::$pool[$args[1]]))
			{
				$sender->sendMessage('[NPC] '.TextFormat::RED.'不存在此NPC');
				break;
			}
			NPC::$pool[$args[1]]->teleport($sender);
			$sender->sendMessage('[NPC] '.TextFormat::GREEN.'传送成功');
			break;
		case 'help':
			$help=TextFormat::GREEN.'===NPC系统指令帮助==='."\n";
			$help.=TextFormat::GREEN.'所有指令前面必须加/fnpc '."\n";
			$help.=TextFormat::YELLOW.'add <Type> <ID> <Name> - 添加一个NPC'."\n";
			$help.=TextFormat::YELLOW.'type - 列出可用的Type类型'."\n";
			$help.=TextFormat::YELLOW.'remove <ID> - 移除一个NPC'."\n";
			$help.=TextFormat::YELLOW.'skin <ID> <File> - 设置NPC皮肤'."\n";
			$help.=TextFormat::YELLOW.'name <ID> <Name> - 设置NPC名称'."\n";
			$help.=TextFormat::YELLOW.'command <ID> <add/remove> <Command> - 添加/删除NPC指令'."\n";
			$help.=TextFormat::YELLOW.'command <ID> list - 列出NPC指令'."\n";
			$help.=TextFormat::YELLOW.'tphere <ID> - 把NPC传送过来'."\n";
			$help.=TextFormat::YELLOW.'teleport <ID> - 设置NPC传送目标为你的位置'."\n";
			$help.=TextFormat::YELLOW.'transfer <ID> <IP> <Port> - 设置NPC跨服传送'."\n";
			$help.=TextFormat::YELLOW.'reset <ID> - 重置NPC的设置'."\n";
			$help.=TextFormat::YELLOW.'chat <ID> <add/remove> <Chat> - 添加/删除NPC对话数据'."\n";
			$help.=TextFormat::YELLOW.'item <ID> <Item[:Damage]> - 设置NPC手持物品'."\n";
			$help.=TextFormat::YELLOW.'help - 查看帮助';
			$sender->sendMessage($help);
			unset($help);
			break;
		default:
			unset($sender,$args);
			return false;
		}
		unset($sender,$args);
		return true;
	}
	
	public function onPlayerMove(\pocketmine\event\player\PlayerMoveEvent $event)
	{
		NPC::playerMove($event->getPlayer());
		unset($event);
	}
	
	public function onDataPacketReceive(\pocketmine\event\server\DataPacketReceiveEvent $event)
	{
		NPC::packetReceive($event->getPlayer(),$event->getPacket());
		unset($event);
	}
	
	public function onPlayerJoin(\pocketmine\event\player\PlayerJoinEvent $event)
	{
		NPC::spawnAllTo($event->getPlayer());
		unset($event);
	}
	
	public function onEntityLevelChange(\pocketmine\event\entity\EntityLevelChangeEvent $event)
	{
		if($event->getEntity() instanceof \pocketmine\Player)
		{
			NPC::spawnAllTo($event->getEntity(),$event->getTarget());
		}
		unset($event);
	}
}

