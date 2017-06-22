<?php
/**
 * Box
 * Representation of a box used to package products in a shipment
 *
 * Determines which boxes should be used in a given order
 *
 * @author      travism
 * @version     1.0
*/
namespace emergeit;

class Box {
	public $Code;
	public $InnerLength;
	public $InnerWidth;
	public $InnerHeight;
	public $OuterLength;
	public $OuterWidth;
	public $OuterHeight;
	public $Weight;
	public $DimUnit;
	public $WeightUnit;

	public function __construct($c, $ol, $ow, $oh, $il, $iw, $ih, $w) {
		$this->Code = $c;	

		$inner_dims = array($il, $iw, $ih);
		sort($inner_dims);	

		$outer_dims = array($ol, $ow, $oh);
		sort($outer_dims);	

		$this->OuterLength = $outer_dims[2];
		$this->OuterWidth = $outer_dims[1];
		$this->OuterHeight = $outer_dims[0];	

		$this->InnerLength = $inner_dims[2];
		$this->InnerWidth = $inner_dims[1];
		$this->InnerHeight = $inner_dims[0];	

		$this->Weight = $w;	

		$this->DimUnit = 'IN';
		$this->WeightUnit = 'LB';
	}

	public function getLength() {
		return $this->OuterLength;
	}

	public function getWidth() {
		return $this->OuterWidth;
	}

	public function getHeight() {
		return $this->OuterHeight;
	}

	public function getInnerLength() {
		return $this->OuterLength;
	}

	public function getInnerWidth() {
		return $this->OuterWidth;
	}

	public function getInnerHeight() {
		return $this->OuterHeight;
	}

	public function getPackingVolume() {
		return ($this->getInnerLength() * $this->getInnerWidth() * $this->getInnerHeight());
	}

	public function getPackingWeight() {
		// Weight of the box before any items are added to it
		return $this->Weight;
	}

	public function setDimUnit($uom) {
		// Set unit of measure for dimensions
		// Currently API only supports 'IN'
		$this->DimUnit = $uom;
	}

	public function getDimUnit() {
		return $this->DimUnit;
	}

	public function setWeightUnit($uom) {
		// Set unit of measure for weights
		// Currently API only supports 'LB'
		$this->WeightUnit = $uom;
	}

	public function getWeightUnit() {
		return $this->WeightUnit;
	}

	public function pack($length, $width, $height) {
		$dims = array($length, $width, $height);
		sort($dims);

		if ($this->InnerLength >= $dims[2] && $this->InnerWidth >= $dims[1] && $this->InnerHeight >= $dims[0] && $this->getPackingVolume() >= $dims[0]*$dims[1]*$dims[2]) {
			return true;
		}
		return false;
	}

	public static function sortBoxes($sort) {
		if (empty($sort)) { return false; }
		uasort($sort, array(__CLASS__, 'boxSorting'));
		return $sort;
	}

	public static function boxSorting($a, $b) {
		if ( $a->getPackingVolume() == $b->getPackingVolume() ) {
			if ( $a->getPackingWeight() == $b->getPackingWeight() ) {
				return 0;
			}
			return ( $a->getPackingWeight() < $b->getPackingWeight() ) ? 1 : -1;
		}
		return ( $a->getPackingVolume() < $b->getPackingVolume() ) ? 1 : -1;
	}

}

?>
