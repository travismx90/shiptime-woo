<?php
/**
 * Package
 * Representation of a package in a shipment
 *
 * Determines how a shipment for a given order breaks into one or more packages
 *
 * @author      travism
 * @version     1.0
*/
namespace emergeit;

require_once(dirname(__FILE__).'/package.php');

class ShipmentBuilder {
	public $Items;
	public $Packages;

	public function __construct() {
		$this->Packages = array();
	}

	public function setItems($items) {
		$this->Items = $items;
	}

	public function getItems() {
		return $this->Items;
	}

	public function addItem($item) {
		if (!is_array($this->Items)) {
			$this->Items = array();
		}
		$this->Items[] = $item;
	}	

	public function package($boxes) {
		$boxs = array();
		foreach ($boxes as $b) {
			$box = new Box($b['label'], $b['outer_length'], $b['outer_width'], $b['outer_height'], $b['inner_length'], $b['inner_width'], $b['inner_height'], $b['weight']);
			$boxs[] = $box;
		}
		$i=0;
		foreach ($this->Items as $item) {
			for ($j=0; $j < $item["quantity"]; $j++) {
				if ($i % 6 == 0) {
					$package = new Package();
					$this->Packages[] = $package;
				}
				$package->pack($item["length"], $item["width"], $item["height"], $item["weight"]);
				$i++;
			}
		}
		foreach ($this->Packages as $package) {
			foreach (array_reverse(Box::sortBoxes($boxs)) as $b) {
				if ($b->pack($package->Length, $package->Width, $package->Height)) {
					$package->Length = $b->getLength();
					$package->Width = $b->getWidth();
					$package->Height = $b->getHeight();
					break;
				}
			}
		}
		return $this->Packages;
	}

}

?>
