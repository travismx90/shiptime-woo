<?php
/**
 * ShippingService
 * Representation of a carrier service
 *
 * Determines how shipping services are displayed in the UI
 *
 * @author      travism
 * @version     1.0
*/
namespace emergeit;

class ShippingService {
	public $carrierId = null;
	public $carrierName = null;
	public $serviceId = null;
	public $serviceName = null;
	public $originCountries = array();
	public $destCountries = array();
	public $homeCountry = null;

	public function __construct($serviceId,$serviceName,$carrierId,$carrierName,$homeCountry='CA') {
		$this->serviceId = $serviceId;
		$this->serviceName = $serviceName;
		$this->carrierId = $carrierId;
		$this->carrierName = $carrierName;
		$this->homeCountry = $homeCountry;
		$this->setOriginDest();
	}

	// TODO: Add services here as they are added to the plugin
	public function setOriginDest() {
		switch ($this->carrierName) {
			case 'FedEx':
				switch ($this->serviceName) {
					case 'Priority Overnight':
						$this->originCountries = array('CA','US');
						$this->destCountries = array('CA','US');
						break;
					case 'International First':
						$this->originCountries = array('CA','US');
						$this->destCountries = array('ALL');
						break;
					case 'Economy':
						$this->originCountries = array('CA','US');
						$this->destCountries = array('CA','US');
						break;
					case 'Standard Overnight':
						$this->originCountries = array('CA','US');
						$this->destCountries = array('CA','US');
						break;
					case 'International Economy':
						$this->originCountries = array('CA','US');
						$this->destCountries = array('ALL');
						break;
					case 'Second Day AM':
						$this->originCountries = array('CA','US');
						$this->destCountries = array('CA','US');
						break;
					case 'International Priority':
						$this->originCountries = array('CA','US');
						$this->destCountries = array('ALL');
						break;
					case 'Ground':
						$this->originCountries = array('CA','US');
						$this->destCountries = array('CA','US','ALL'); // Domestic & Intl
						break;
					case 'First Overnight':
						$this->originCountries = array('CA','US');
						$this->destCountries = array('CA','US');
						break;
					default:
						$this->originCountries = array('CA','US');
						$this->destCountries = array('NONE');
						break;
				}
				break;
			case 'DHL INTL':
				switch ($this->serviceName) {
					case 'EXPRESS 10:30':
						$this->originCountries = array('CA','US');
						$this->destCountries = array('ALL');
						break;
					case 'EXPRESS 12:00':
						$this->originCountries = array('CA','US');
						$this->destCountries = array('ALL');
						break;
					case 'EXPRESS 9:00':
						$this->originCountries = array('CA','US');
						$this->destCountries = array('ALL');
						break;
					case 'EXPRESS WORLDWIDE':
						$this->originCountries = array('CA','US');
						$this->destCountries = array('ALL');
						break;
					default:
						$this->originCountries = array('CA','US');
						$this->destCountries = array('NONE');
						break;
				}
				break;
			case 'Canpar':
				$this->originCountries = array('CA');
				$this->destCountries = array('CA');			
				break;
			case 'Canada Post':
				$this->originCountries = array('CA');
				$this->destCountries = array('CA');			
				break;
			case 'Purolator':
				switch ($this->serviceName) {
					case 'Purolator Express Envelope U.S.':
						$this->originCountries = array('CA');
						$this->destCountries = array('US');
						break;
					case 'Purolator Express Pack':
						$this->originCountries = array('CA');
						$this->destCountries = array('CA');
						break;
					case 'Purolator Express 10:30AM':
						$this->originCountries = array('CA');
						$this->destCountries = array('CA');
						break;
					case 'Purolator Express':
						$this->originCountries = array('CA');
						$this->destCountries = array('CA');
						break;
					case 'Purolator Express Pack 9AM':
						$this->originCountries = array('CA');
						$this->destCountries = array('CA');
						break;
					case 'Purolator Express Envelope International':
						$this->originCountries = array('CA');
						$this->destCountries = array('ALL');
						break;
					case 'Purolator Express Envelope 9AM':
						$this->originCountries = array('CA');
						$this->destCountries = array('CA');
						break;
					case 'Purolator Ground':
						$this->originCountries = array('CA');
						$this->destCountries = array('CA');
						break;
					case 'Purolator Express Pack U.S.':
						$this->originCountries = array('CA');
						$this->destCountries = array('US');
						break;
					case 'Purolator Express 9AM':
						$this->originCountries = array('CA');
						$this->destCountries = array('CA');
						break;
					case 'Purolator Express International':
						$this->originCountries = array('CA');
						$this->destCountries = array('ALL');
						break;
					case 'Purolator Ground U.S.':
						$this->originCountries = array('CA');
						$this->destCountries = array('US');
						break;						
					case 'Purolator Express Envelope 10:30AM':
						$this->originCountries = array('CA');
						$this->destCountries = array('CA');
						break;
					case 'Purolator Express U.S.':
						$this->originCountries = array('CA');
						$this->destCountries = array('US');
						break;
					case 'Purolator Express Envelope':
						$this->originCountries = array('CA');
						$this->destCountries = array('CA');
						break;
					case 'Purolator Express Pack 10:30AM':
						$this->originCountries = array('CA');
						$this->destCountries = array('CA');
						break;
					default:
						$this->originCountries = array('CA');
						$this->destCountries = array('NONE');
						break;
				}
				break;
			case 'Dicom':
				$this->originCountries = array('CA');
				$this->destCountries = array('CA');			
				break;
			case 'Loomis':
				$this->originCountries = array('CA');
				$this->destCountries = array('CA');			
				break;
			case 'USPS':
				case 'Express_EP':
					$this->originCountries = array('US');
					$this->destCountries = array('US');
					break;
				case 'Priority_EP':
					$this->originCountries = array('US');
					$this->destCountries = array('US');
					break;
				case 'ParcelSelect_EP':
					$this->originCountries = array('US');
					$this->destCountries = array('US');
					break;
				default:
					$this->originCountries = array('US');
					$this->destCountries = array('NONE');
					break;
			case 'UPS':
				case 'Ground_EP':
					$this->originCountries = array('US');
					$this->destCountries = array('US');
					break;
				case '3DaySelect_EP':
					$this->originCountries = array('US');
					$this->destCountries = array('US');
					break;
				case '2ndDayAirAM_EP':
					$this->originCountries = array('US');
					$this->destCountries = array('US');
					break;
				case '2ndDayAir_EP':
					$this->originCountries = array('US');
					$this->destCountries = array('US');
					break;
				case 'NextDayAirSaver_EP':
					$this->originCountries = array('US');
					$this->destCountries = array('US');
					break;
				case 'NextDayAirEarlyAM_EP':
					$this->originCountries = array('US');
					$this->destCountries = array('US');
					break;
				case 'NextDayAir_EP':
					$this->originCountries = array('US');
					$this->destCountries = array('US');
					break;
				default:
					$this->originCountries = array('US');
					$this->destCountries = array('NONE');
					break;
			default:
				$this->originCountries = array('NONE');
				$this->destCountries = array('NONE');
				break;
		}
	}

	public function isDomestic() {
		return (in_array($this->homeCountry, $this->originCountries) && in_array($this->homeCountry, $this->destCountries));
	}

	public function isIntl() {
		foreach ($this->destCountries as $ctry) {
			if ($ctry != $this->homeCountry || $ctry == 'ALL') {
				return true;
			}
		}
		return false;
	}

	public function isValid() {
		return (in_array($this->homeCountry, $this->originCountries) && !in_array('NONE', $this->originCountries) && !in_array('NONE', $this->destCountries));
	}

	public function getId() {
		return $this->serviceId;
	}

	public function getName() {
		return $this->serviceName;
	}

	public function getFullName() {
		if (strpos($this->serviceName, $this->carrierName) !== false) {
			return $this->serviceName;
		} else {
			return $this->carrierName.' '.$this->serviceName;
		}
	}

}
