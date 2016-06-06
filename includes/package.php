<?php
/**
 * Package
 * Representation of a package in a shipment
 *
 * Determines how a package of items is grouped into a box
 *
 * @author      travism
 * @version     1.0
*/
namespace emergeit;

class Package {
	public $Box;
	public $Length;
	public $Width;
	public $Height;
	public $Weight;
    public $DimUnit;
    public $WeightUnit;
    public $ItemVolume;

	public function __construct() {
		$this->Box = null;
		$this->Length = 0;
		$this->Width = 0;
		$this->Height = 0;
		$this->Weight = 0;
		$this->DimUnit = 'IN';
		$this->WeightUnit = 'LB';
		$this->ItemVolume = 0;
	}

	public function setBox($box) {
		$this->Box = $box;
		$this->Length = $box->getLength();
		$this->Width = $box->getWidth();
		$this->Height = $box->getHeight();
		$this->Weight += $box->getPackingWeight();
	}

	public function getBox() {
		return $this->Box;
	}

	public function setLength($val) {
		$this->Length = $val;
	}

	public function setWidth($val) {
		$this->Width = $val;
	}

	public function setHeight($val) {
		$this->Height = $val;
	}

	public function getLength() {
		return $this->Length;
	}

	public function getWidth() {
		return $this->Width;
	}

	public function getHeight() {
		return $this->Height;
	}

	public function getWeight() {
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

    public function getItemVolume() {
    	return $this->ItemVolume;
    }

	public function pack($length, $width, $height, $weight) {
		$curr_dims = array($this->Length, $this->Width, $this->Height);
        sort($curr_dims);

        $new_dims = array($length, $width, $height);
        sort($new_dims);
		
		$this->Length = (float) $curr_dims[2] + $new_dims[0];
		$this->Width = (float) $curr_dims[1] + $new_dims[1];
		$this->Height = (float) $curr_dims[0] + $new_dims[2];

		$this->Weight += (float) $weight;

		$this->ItemVolume += (float) $length * $width * $height;
	}

}

?>
