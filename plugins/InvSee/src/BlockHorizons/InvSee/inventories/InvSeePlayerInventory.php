<?php
namespace BlockHorizons\InvSee\inventories;

use BlockHorizons\InvSee\libs\muqsit\invmenu\inventories\DoubleChestInventory;

use pocketmine\inventory\ArmorInventory;
use pocketmine\inventory\Inventory;
use pocketmine\inventory\PlayerInventory;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\item\Item;
use pocketmine\nbt\tag\ListTag;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat;

class InvSeePlayerInventory extends DoubleChestInventory implements InvSeeInventory {
	use InvSeeInventoryTrait;

	const ARMOR_INVENTORY_MENU_SLOTS = [
		0 => 47,
		1 => 48,
		2 => 50,
		3 => 51
	];

	public function canSpyInventory(Inventory $inventory): bool {
		return $inventory instanceof PlayerInventory || $inventory instanceof ArmorInventory;
	}

	public function canModifySlot(Player $player, int $slot): bool {
		return $player->hasPermission($this->getSpying() === $player->getLowerCaseName() ? "invsee.inventory.modify.self" : "invsee.inventory.modify") && ($slot < 36 || in_array($slot, self::ARMOR_INVENTORY_MENU_SLOTS));
	}

	public function syncOnline(Player $player): void {
		$contents = $this->getContents();
		$player->getInventory()->setContents(array_slice($contents, 0, $player->getInventory()->getSize()));
		$player->getArmorInventory()->setContents(array_intersect_key($contents, array_flip(self::ARMOR_INVENTORY_MENU_SLOTS)));
	}

	public function syncOffline(): void {
		$server = Server::getInstance();

		$contents = [];
		for($slot = 0; $slot < 36; ++$slot) {
			$item = $this->getItem($slot);
			if(!$item->isNull()) {
				$contents[] = $item->nbtSerialize($slot + 9);
			}
		}

		for($slot = 100; $slot < 104; ++$slot) {
			$item = $this->getItem(self::ARMOR_INVENTORY_MENU_SLOTS[$slot - 100]);
			if(!$item->isNull()) {
				$contents[] = $item->nbtSerialize($slot);
			}
		}

		$nbt = $server->getOfflinePlayerData($this->spying);
		$nbt->setTag(new ListTag("Inventory", $contents));
		$server->saveOfflinePlayerData($this->spying, $nbt);
	}

	public function syncPlayerAction(SlotChangeAction $action): void {
		$inventory = $action->getInventory();

		if($inventory instanceof PlayerInventory) {
			$this->setItem($action->getSlot(), $action->getTargetItem());
		}elseif($inventory instanceof ArmorInventory) {
			$this->setItem(self::ARMOR_INVENTORY_MENU_SLOTS[$action->getSlot()], $action->getTargetItem());
		}
	}

	public function syncSpyerAction(Player $spying, SlotChangeAction $action): void {
		$slot = $action->getSlot();

		if(($armor_slot = array_search($slot, self::ARMOR_INVENTORY_MENU_SLOTS, true)) !== false) {
			$spying->getArmorInventory()->setItem($armor_slot, $action->getTargetItem());
		}else{
			$spying->getInventory()->setItem($slot, $action->getTargetItem());
		}
	}

	public function getSpyerContents(): array {
		$server = Server::getInstance();
		$player_instance = $server->getPlayerExact($this->spying);

		if($player_instance !== null) {
			$contents = $player_instance->getInventory()->getContents();
			foreach($player_instance->getArmorInventory()->getContents() as $slot => $armor) {
				$contents[self::ARMOR_INVENTORY_MENU_SLOTS[$slot]] = $armor;
			}
		}else{
			$contents = [];
			foreach($server->getOfflinePlayerData($this->spying)->getListTag("Inventory") as $nbt) {
				$slot = $nbt->getByte("Slot");
				if($slot >= 0 && $slot < 9) { //old hotbar stuff
				}elseif ($slot >= 100 && $slot < 104) { //armor
					$contents[self::ARMOR_INVENTORY_MENU_SLOTS[$slot - 100]] = Item::nbtDeserialize($nbt);
				}else{
					$contents[$slot - 9] = Item::nbtDeserialize($nbt);
				}
			}
		}

		//maybe make this configurable
		$contents[45] = Item::get(Item::STAINED_GLASS_PANE, 15)->setCustomName("");
		$contents[46] = Item::get(Item::STAINED_GLASS_PANE, 15)->setCustomName(TextFormat::RESET . TextFormat::AQUA . "§l§6Nón§c ->");
		$contents[49] = Item::get(Item::STAINED_GLASS_PANE, 15)->setCustomName(TextFormat::RESET . TextFormat::AQUA . "§l§c<- §6Aó§c |§6 Quần§c ->");
		$contents[52] = Item::get(Item::STAINED_GLASS_PANE, 15)->setCustomName(TextFormat::RESET . TextFormat::AQUA . "§l§c<-§6 Giày");
		$contents[53] = Item::get(Item::STAINED_GLASS_PANE, 15)->setCustomName("");
		return $contents;
	}
}