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
		$exploded_items = array();

		foreach ($items as $item) {
			for ($i=0; $i < $item["quantity"]; $i++) {
				$exploded_items[] = $item;
			}
		}

		$this->Items = self::sortItems($exploded_items);
	}

	public function getItems() {
		return $this->Items;
	}

	public static function sortItems($sort) {
		if (empty($sort)) { return false; }
		uasort($sort, array(__CLASS__, 'itemSorting'));
		return $sort;
	}

	public static function itemSorting($a, $b) {
		$a_dims = array($a["length"], $a["width"], $a["height"]);
		sort($a_dims);

		$b_dims = array($b["length"], $b["width"], $b["height"]);
		sort($b_dims);

		if ($a_dims[2] == $b_dims[2]) return 0;
		return ($a_dims[2] > $b_dims[2]) ? 1 : -1;
	}

	public function canPackage($length, $width, $height, $weight, $package, $boxs, $max_l, $max_w, $max_h) {
		// max_l, max_w, max_h
		// Service maximum package weight = 70 lbs
		$p = clone($package);
		$p->pack($length, $width, $height, $weight);
		$l = $p->getLength();
		$w = $p->getWidth();
		$h = $p->getHeight();
		if ($l <= $max_l && $w <= $max_w && $h <= $max_h && ($p->getWeight() + $weight < 70)) {
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
		if (!empty($boxes)) {
			foreach ($boxes as $b) {
				$box = new Box($b['label'], $b['outer_length'], $b['outer_width'], $b['outer_height'], 
					$b['inner_length'], $b['inner_width'], $b['inner_height'], $b['weight']);
				$boxs[] = $box;
			}
			$boxs = Box::sortBoxes($boxs);
			foreach ($boxs as $b) {
				if ($b->getLength() + 2*($b->getWidth() + $b->getHeight()) <= 108) {
					$max_l = $b->getInnerLength();
					$max_w = $b->getInnerWidth();
					$max_h = $b->getInnerHeight();
					break;
				}
			}
		}
		if (is_array($this->Items)) {
			foreach ($this->Items as $item) {
				if (!isset($package) || !$this->canPackage($item["length"], $item["width"], $item["height"], $item["weight"], $package, $boxs, $max_l, $max_w, $max_h)) {
					$package = new Package();
					$this->Packages[] = $package;	
				}
				$package->pack($item["length"], $item["width"], $item["height"], $item["weight"]);
			}
		}
		foreach ($this->Packages as $package) {
			foreach (array_reverse($boxs) as $b) {
				if ($b->pack($package->getLength(), $package->getWidth(), $package->getHeight())) {
					$package->setBox($b);
					break;
				}
			}
		}
		return $this->Packages;
	}

}
