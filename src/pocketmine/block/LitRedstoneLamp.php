<?php
namespace pocketmine\block;

use pocketmine\item\Tool;
use pocketmine\item\Item;
use pocketmine\level\Level;
use pocketmine\Player;

class LitRedstoneLamp extends Solid implements Redstone,RedstoneConsumer,LightSource{

	protected $id = self::LIT_REDSTONE_LAMP;

	public function __construct(){

	}

	public function getLightLevel(){
		return 15;
	}
	
	public function isLightSource(){
		return true;
	}
	
	public function getToolType(){
		return Tool::TYPE_PICKAXE;
	}
	
	public function onUpdate($type){
		if(!$this->isPowered()){
			$this->id=123;
			$this->getLevel()->setBlock($this, $this, true, false);
		}
	}
	
	public function getName(){
		return "Lit Redstone Lamp";
	}

	public function getHardness(){
		return 0.3;
	}

	public function getDrops(Item $item){
		$drops = [];
		$drops[] = [Item::REDSTONE_LAMP, 0, 1];
		return $drops;
	}
}
