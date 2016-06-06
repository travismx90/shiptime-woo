<?php
/**
 * ShipmentBuilder
 * Representation of a shipment
 *
 * Determines how a shipment for a given order breaks into one or more packages
 *
 * @author      travism
 * @version     1.0
*/
namespace emergeit;

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

    public function canPackage($length, $width, $height, $package, $boxs, $max_l, $max_w, $max_h) {
    	$l = $package->Length+$length;
    	$w = $package->Width+$width;
    	$h = $package->Height+$height;
    	if ($l <= $max_l && $w <= $max_w && $h <= $max_h) {
			foreach ($boxs as $b) {
				if ($b->pack($l, $w, $h)) {
					return true;
				}
			}
		}
		return false;
    }

	public function package($boxes) {
		// L + 2(W+H) <= 108
		// Maximums are inner dims
		// Build in some compensation for default values, which are
		// used if merchant does not configure any boxes.
		$max_l = 24;
		$max_w = 20;
		$max_h = 18;
		$boxs = array();
		foreach ($boxes as $b) {
			$box = new Box($b['label'], $b['outer_length'], $b['outer_width'], $b['outer_height'], $b['inner_length'], $b['inner_width'], $b['inner_height'], $b['weight']);
			$boxs[] = $box;
		}
		$boxs = Box::sortBoxes($boxs);
		foreach ($boxs as $b) {
			$max_l = $b->getInnerLength();
			$max_w = $b->getInnerWidth();
			$max_h = $b->getInnerHeight();
			break;
		}
		foreach ($this->Items as $item) {
			for ($j=0; $j < $item["quantity"]; $j++) {
				if (!isset($package) || !$this->canPackage($item["length"], $item["width"], $item["height"], $package, $boxs, $max_l, $max_w, $max_h)) {
					$package = new Package();
					$this->Packages[] = $package;
				}
				$package->pack($item["length"], $item["width"], $item["height"], $item["weight"]);
			}
		}
		foreach ($this->Packages as $package) {
			foreach (array_reverse($boxs) as $b) {
				if ($b->pack($package->getLength(), $package->getWidth(), $package->getHeight())) {
					$package->setLength($b->getLength());
					$package->setWidth($b->getWidth());
					$package->setHeight($b->getHeight());
					break;
				}
			}
		}
		return $this->Packages;
	}

}

?>
