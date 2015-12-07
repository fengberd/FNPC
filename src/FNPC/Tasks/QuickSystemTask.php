<?php
namespace FNPC\Tasks;

/*
Copyright FENGberd © 2015
Coding Project:
http://coding.net/u/FENGberd/p/FNPC
*/

class QuickSystemTask extends \pocketmine\scheduler\PluginTask
{
	private $plugin;
	
	public function __construct(\FNPC\Main $plugin)
	{
		parent::__construct($plugin);
		$this->plugin=$plugin;
	}
	
	public function onRun($currentTick)
	{
		$this->plugin=$this->getOwner();
		\FNPC\npc\NPC::tick();
	}
}
?>
