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
    public $ItemCount;
    public $ItemVolume;

	public function __construct() {
		$this->Box = null;
		$this->Length = 0;
		$this->Width = 0;
		$this->Height = 0;
		$this->Weight = 0;
		$this->DimUnit = 'IN';
		$this->WeightUnit = 'LB';
		$this->ItemCount = 0;
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

    public function getPackageVolume() {
    	return $this->getLength() * $this->getWidth() * $this->getHeight();
    }

    public function getItemVolume() {
    	return $this->ItemVolume;
    }

	public function pack($length, $width, $height, $weight) {
		$this->ItemCount += 1;
		$this->ItemVolume += (float) $length * $width * $height;

		$curr_dims = array($this->Length, $this->Width, $this->Height);
        sort($curr_dims);

        $new_dims = array($length, $width, $height);
        sort($new_dims);

        if ($this->ItemCount == 1) {
        	// Add the largest item first
        	$this->Length = $new_dims[2];
        	$this->Width = $new_dims[1];
        	$this->Height = $new_dims[0];
        } elseif ($this->ItemCount == 2) {
        	// Check the smallest package dimension requirement
        	$this->Length = $curr_dims[2];
        	$this->Width = ceil(sqrt($this->getPackageVolume()/$this->Length));
        	$this->Height = $curr_dims[0] + $new_dims[0];
        	if ($this->getPackageVolume() < $this->getItemVolume()) {
        		$this->Width = ceil($this->getItemVolume()/($this->Length*$this->Height));
        	}
        } else {
            // General case for 3+ items
        	$this->Length = $curr_dims[2];
        	if ($this->getPackageVolume() < $this->getItemVolume()) {
        		$this->Width = ceil(sqrt($this->getItemVolume()/$this->Length));
        		$this->Height = ceil($this->getItemVolume()/($this->Length*$this->Width));
        	}
        }

		$this->Weight += (float) $weight;
	}

}

?>
