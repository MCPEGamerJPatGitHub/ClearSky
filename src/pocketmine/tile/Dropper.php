<?php
namespace pocketmine\tile;

use pocketmine\inventory\DropperInventory;
use pocketmine\inventory\InventoryHolder;
use pocketmine\item\Item;
use pocketmine\level\format\FullChunk;
use pocketmine\level\particle\SmokeParticle;
use pocketmine\math\Vector3;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\ShortTag;
use pocketmine\entity\Item as ItemEntity;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\nbt\tag\IntTag;

class Dropper extends Spawnable implements InventoryHolder, Container, Nameable{

	/** @var DropperInventory */
	protected $inventory;

	public function __construct(FullChunk $chunk, CompoundTag $nbt){
		parent::__construct($chunk, $nbt);
		$this->inventory = new DropperInventory($this);

		if(!isset($this->namedtag->Items) or !($this->namedtag->Items instanceof ListTag)){
			$this->namedtag->Items = new ListTag("Items", []);
			$this->namedtag->Items->setTagType(NBT::TAG_Compound);
		}

		for($i = 0; $i < $this->getSize(); ++$i){
			$this->inventory->setItem($i, $this->getItem($i));
		}
		
		$this->scheduleUpdate();
	}

	public function close(){
		if($this->closed === false){
			foreach($this->getInventory()->getViewers() as $player){
				$player->removeWindow($this->getInventory());
			}
			parent::close();
		}
	}

	public function saveNBT(){
		$this->namedtag->Items = new ListTag("Items", []);
		$this->namedtag->Items->setTagType(NBT::TAG_Compound);
		for($index = 0; $index < $this->getSize(); ++$index){
			$this->setItem($index, $this->inventory->getItem($index));
		}
	}

	/**
	 * @return int
	 */
	public function getSize(){
		return 9;
	}

	/**
	 * @param $index
	 *
	 * @return int
	 */
	protected function getSlotIndex($index){
		foreach($this->namedtag->Items as $i => $slot){
			if((int) $slot["Slot"] === (int) $index){
				return (int) $i;
			}
		}

		return -1;
	}

	/**
	 * This method should not be used by plugins, use the Inventory
	 *
	 * @param int $index
	 *
	 * @return Item
	 */
	public function getItem($index){
		$i = $this->getSlotIndex($index);
		if($i < 0){
			return Item::get(Item::AIR, 0, 0);
		}else{
			return NBT::getItemHelper($this->namedtag->Items[$i]);
		}
	}

	/**
	 * This method should not be used by plugins, use the Inventory
	 *
	 * @param int  $index
	 * @param Item $item
	 *
	 * @return bool
	 */
	public function setItem($index, Item $item){
		$i = $this->getSlotIndex($index);

		$d = NBT::putItemHelper($item, $index);

		if($item->getId() === Item::AIR or $item->getCount() <= 0){
			if($i >= 0){
				unset($this->namedtag->Items[$i]);
			}
		}elseif($i < 0){
			for($i = 0; $i <= $this->getSize(); ++$i){
				if(!isset($this->namedtag->Items[$i])){
					break;
				}
			}
			$this->namedtag->Items[$i] = $d;
		}else{
			$this->namedtag->Items[$i] = $d;
		}

		return true;
	}

	/**
	 * @return DropperInventory
	 */
	public function getInventory(){
		return $this->inventory;
	}

	public function getName(){
		return isset($this->namedtag->CustomName) ? $this->namedtag->CustomName->getValue() : "Dropper";
	}

	public function hasName(){
		return isset($this->namedtag->CustomName);
	}

	public function setName($str){
		if($str === ""){
			unset($this->namedtag->CustomName);
			return;
		}

		$this->namedtag->CustomName = new StringTag("CustomName", $str);
	}

	public function getMotion(){
		$meta = $this->getBlock()->getDamage();
		switch($meta){
			case Vector3::SIDE_DOWN:
				return [0, -1, 0];
			case Vector3::SIDE_UP:
				return [0, 1, 0];
			case Vector3::SIDE_NORTH:
				return [0, 0, -1];
			case Vector3::SIDE_SOUTH:
				return [0, 0, 1];
			case Vector3::SIDE_WEST:
				return [-1, 0, 0];
			case Vector3::SIDE_EAST:
				return [1, 0, 0];
			default:
				return [0, 0, 0];
		}
	}

	public function activate(){
		$itemArr = [];
		for($i = 0; $i < $this->getInventory()->getSize(); $i++){
			$slot = $this->getInventory()->getItem($i);
			if($slot instanceof Item && $slot->getId() != 0) $itemArr[] = $slot;
		}

		if(!empty($itemArr)){
			/** @var Item $item */
			$itema = $itemArr[array_rand($itemArr)];
			$this->getLevel()->getServer()->broadcastTip($itema);
			$item = Item::get($itema->getId(), $itema->getDamage(), 1, $itema->getCompoundTag());
			$this->getLevel()->getServer()->broadcastPopup($item);
			$this->getInventory()->removeItem($item);
			$motion = $this->getMotion();
			$needItem = Item::get($item->getId(), $item->getDamage());
			$item = NBT::putItemHelper($needItem);
			$item->setName("Item");
				$nbt = new CompoundTag("", [
				"Pos" => new ListTag("Pos", [
					new DoubleTag("", $this->x + $motion[0] * 2 + 0.5),
					new DoubleTag("", $this->y + ($motion[1] > 0 ? $motion[1] : 0.5)),
					new DoubleTag("", $this->z + $motion[2] * 2 + 0.5)
				]),
				"Motion" => new ListTag("Motion", [
					new DoubleTag("", $motion[0]),
					new DoubleTag("", $motion[1]),
					new DoubleTag("", $motion[2])
				]),
				"Rotation" => new ListTag("Rotation", [
					new FloatTag("", lcg_value() * 360),
					new FloatTag("", 0)
				]),
				"Health" => new ShortTag("Health", 5),
				"Item" => $item,
				"PickupDelay" => new ShortTag("PickupDelay", 10)
			]);
			$f = 0.3;
			$itemEntity = new ItemEntity($this->chunk, $nbt, $this);
			$itemEntity->setMotion($itemEntity->getMotion()->multiply($f));
			$itemEntity->spawnToAll();
		}
	}

	public function getSpawnCompound(){
		$c = new CompoundTag("", [
			new StringTag("id", Tile::DROPPER),
			new IntTag("x", (int) $this->x),
			new IntTag("y", (int) $this->y),
			new IntTag("z", (int) $this->z)
		]);

		if($this->hasName()){
			$c->CustomName = $this->namedtag->CustomName;
		}

		return $c;
	}
}
