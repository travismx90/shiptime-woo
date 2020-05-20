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
	public $displayName = null;
	public $originCountries = array();
	public $destCountries = array();
	public $homeCountry = null;

	public function __construct($serviceId,$serviceName,$carrierId,$carrierName,$homeCountry='CA') {
		$this->serviceId = $serviceId;
		$this->serviceName = $serviceName;
		$this->displayName = $serviceName;
		$this->carrierId = $carrierId;
		$this->carrierName = $carrierName;
		$this->homeCountry = $homeCountry;
		$this->setServiceData();
	}

	// TODO: Add services here as they are added to the plugin
	// FedEx services start with CarrierName ('FedEx' required in ServiceName to determine Dom/Intl)
	public function setServiceData() {
		switch ($this->carrierName) {
			case 'FedEx':
				switch ($this->serviceName) {
					case 'FedEx Economy':
						$this->originCountries = array('CA','US');
						$this->destCountries = array('CA','US');
						break;
					case 'FedEx Second Day AM':
						$this->originCountries = array('CA','US');
						$this->destCountries = array('CA','US');
						break;
					case 'FedEx First Overnight':
						$this->originCountries = array('CA','US');
						$this->destCountries = array('CA','US');
						break;
					case 'FedEx Standard Overnight':
						$this->originCountries = array('CA','US');
						$this->destCountries = array('CA','US');
						break;
					case 'FedEx Priority Overnight':
						$this->originCountries = array('CA','US');
						$this->destCountries = array('CA','US');
						break;
					case 'FedEx 1-Day Freight':
						$this->originCountries = array('CA','US');
						$this->destCountries = array('CA','US');
						break;
					case 'FedEx Ground':
						$this->originCountries = array('CA','US');
						$this->destCountries = array('CA','US','ALL'); // Domestic & Intl
						break;
					case 'FedEx International First':
						$this->originCountries = array('CA','US');
						$this->destCountries = array('ALL');
						break;
					case 'FedEx International Economy':
						$this->originCountries = array('CA','US');
						$this->destCountries = array('ALL');
						break;
					case 'FedEx International Priority':
						$this->originCountries = array('CA','US');
						$this->destCountries = array('ALL');
						break;
					default:
						$this->originCountries = array('CA','US');
						$this->destCountries = array('ALL');
						break;
				}
				break;
			case 'DHL INTL':
				switch ($this->serviceName) {
					case 'EXPRESS 10:30':
						$this->originCountries = array('CA','US');
						$this->destCountries = array('ALL');
						$this->displayName = 'Express 10:30AM';
						break;
					case 'EXPRESS 12:00':
						$this->originCountries = array('CA','US');
						$this->destCountries = array('ALL');
						$this->displayName = 'Express 12PM';
						break;
					case 'EXPRESS 9:00':
						$this->originCountries = array('CA','US');
						$this->destCountries = array('ALL');
						$this->displayName = 'Express 9AM';
						break;
					case 'EXPRESS WORLDWIDE':
						$this->originCountries = array('CA','US');
						$this->destCountries = array('ALL');
						$this->displayName = 'Express Worldwide';
						break;
					default:
						$this->originCountries = array('CA','US');
						$this->destCountries = array('ALL');
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
						$this->displayName = 'Express Envelope U.S.';
						break;
					case 'Purolator Express Pack':
						$this->originCountries = array('CA');
						$this->destCountries = array('CA');
						$this->displayName = 'Express Pack';
						break;
					case 'Purolator Express 10:30AM':
						$this->originCountries = array('CA');
						$this->destCountries = array('CA');
						$this->displayName = 'Express 10:30AM';
						break;
					case 'Purolator Express':
						$this->originCountries = array('CA');
						$this->destCountries = array('CA');
						$this->displayName = 'Express';
						break;
					case 'Purolator Express Pack 9AM':
						$this->originCountries = array('CA');
						$this->destCountries = array('CA');
						$this->displayName = 'Express Pack 9AM';
						break;
					case 'Purolator Express Envelope International':
						$this->originCountries = array('CA');
						$this->destCountries = array('ALL');
						$this->displayName = 'Express Envelope International';
						break;
					case 'Purolator Express Envelope 9AM':
						$this->originCountries = array('CA');
						$this->destCountries = array('CA');
						$this->displayName = 'Express Envelope 9AM';
						break;
					case 'Purolator Ground':
						$this->originCountries = array('CA');
						$this->destCountries = array('CA');
						$this->displayName = 'Ground';
						break;
					case 'Purolator Express Pack U.S.':
						$this->originCountries = array('CA');
						$this->destCountries = array('US');
						$this->displayName = 'Express Pack U.S.';
						break;
					case 'Purolator Express 9AM':
						$this->originCountries = array('CA');
						$this->destCountries = array('CA');
						$this->displayName = 'Express 9AM';
						break;
					case 'Purolator Express International':
						$this->originCountries = array('CA');
						$this->destCountries = array('ALL');
						$this->displayName = 'Express International';
						break;
					case 'Purolator Ground U.S.':
						$this->originCountries = array('CA');
						$this->destCountries = array('US');
						$this->displayName = 'Ground U.S.';
						break;
					case 'Purolator Express Envelope 10:30AM':
						$this->originCountries = array('CA');
						$this->destCountries = array('CA');
						$this->displayName = 'Express Envelope 10:30AM';
						break;
					case 'Purolator Express U.S.':
						$this->originCountries = array('CA');
						$this->destCountries = array('US');
						$this->displayName = 'Express U.S.';
						break;
					case 'Purolator Express Envelope':
						$this->originCountries = array('CA');
						$this->destCountries = array('CA');
						$this->displayName = 'Express Envelope';
						break;
					case 'Purolator Express Pack 10:30AM':
						$this->originCountries = array('CA');
						$this->destCountries = array('CA');
						$this->displayName = 'Express Pack 10:30AM';
						break;
					case 'Purolator Express 12PM':
						$this->originCountries = array('CA');
						$this->destCountries = array('US');
						$this->displayName = 'Express 12PM';
						break;
					case 'Purolator Express Envelope 12PM':
						$this->originCountries = array('CA');
						$this->destCountries = array('CA');
						$this->displayName = 'Express Envelope 12PM';
						break;
					default:
						$this->originCountries = array('CA');
						$this->destCountries = array('CA');
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
				switch ($this->serviceName) {
					// EasyPost integration
					case 'Express_EP':
						$this->originCountries = array('US');
						$this->destCountries = array('US');
						$this->displayName = 'Express';
						break;
					case 'Priority_EP':
						$this->originCountries = array('US');
						$this->destCountries = array('US');
						$this->displayName = 'Priority';
						break;
					case 'ParcelSelect_EP':
						$this->originCountries = array('US');
						$this->destCountries = array('US');
						$this->displayName = 'Parcel Select';
						break;
					case 'ExpressMailInternational_EP':
						$this->originCountries = array('US');
						$this->destCountries = array('ALL');
						$this->displayName = 'Express International';
						break;
					case 'PriorityMailInternational_EP':
						$this->originCountries = array('US');
						$this->destCountries = array('ALL');
						$this->displayName = 'Priority International';
						break;
					// Direct integration
					case 'Priority Mail Express':
						$this->originCountries = array('US');
						$this->destCountries = array('US');
						break;
					case 'Priority Mail':
						$this->originCountries = array('US');
						$this->destCountries = array('US');
						break;
					case 'Priority Mail  Large Flat Rate Box':
						$this->originCountries = array('US');
						$this->destCountries = array('US');
						break;
					case 'Priority Mail  Medium Flat Rate Box':
						$this->originCountries = array('US');
						$this->destCountries = array('US');
						break;
					case 'Priority Mail  Small Flat Rate Box':
						$this->originCountries = array('US');
						$this->destCountries = array('US');
						break;
					case 'Priority Mail Express  Legal Flat Rate Envelope':
						$this->originCountries = array('US');
						$this->destCountries = array('US');
						break;
					case 'Priority Mail Express  Padded Flat Rate Envelope':
						$this->originCountries = array('US');
						$this->destCountries = array('US');
						break;
					case 'Priority Mail  Flat Rate Envelope':
						$this->originCountries = array('US');
						$this->destCountries = array('US');
						break;
					case 'Priority Mail  Legal Flat Rate Envelope':
						$this->originCountries = array('US');
						$this->destCountries = array('US');
						break;
					case 'Priority Mail  Padded Flat Rate Envelope':
						$this->originCountries = array('US');
						$this->destCountries = array('US');
						break;
					case 'Priority Mail  Small Flat Rate Envelope':
						$this->originCountries = array('US');
						$this->destCountries = array('US');
						break;
					case 'Priority Mail  Window Flat Rate Envelope':
						$this->originCountries = array('US');
						$this->destCountries = array('US');
						break;
					default:
						$this->originCountries = array('US');
						$this->destCountries = array('ALL');
						break;
				}
				break;
			case 'UPS':
				switch ($this->serviceName) {
					// EasyPost integration
					case 'Ground_EP':
						$this->originCountries = array('US');
						$this->destCountries = array('US');
						$this->displayName = 'Ground';
						break;
					case '3DaySelect_EP':
						$this->originCountries = array('US');
						$this->destCountries = array('US');
						$this->displayName = '3 Day Select';
						break;
					case '2ndDayAirAM_EP':
						$this->originCountries = array('US');
						$this->destCountries = array('US');
						$this->displayName = '2nd Day Air AM';
						break;
					case '2ndDayAir_EP':
						$this->originCountries = array('US');
						$this->destCountries = array('US');
						$this->displayName = '2nd Day Air';
						break;
					case 'NextDayAirSaver_EP':
						$this->originCountries = array('US');
						$this->destCountries = array('US');
						$this->displayName = 'Next Day Air Saver';
						break;
					case 'NextDayAirEarlyAM_EP':
						$this->originCountries = array('US');
						$this->destCountries = array('US');
						$this->displayName = 'Next Day Air Early AM';
						break;
					case 'NextDayAir_EP':
						$this->originCountries = array('US');
						$this->destCountries = array('US');
						$this->displayName = 'Next Day Air';
						break;
					default:
						$this->originCountries = array('US');
						$this->destCountries = array('US');
						break;
				}
				break;
			default:
				$this->originCountries = array('NONE');
				$this->destCountries = array('NONE');
				break;
		}
	}

	public function isDomestic() {
		return (in_array($this->homeCountry, $this->originCountries) && 
			in_array($this->homeCountry, $this->destCountries));
	}

	public function isIntl() {
		foreach ($this->destCountries as $ctry) {
			if ($ctry != $this->homeCountry) {
				return true;
			}
		}
		return false;
	}

	public function isValid() {
		return (in_array($this->homeCountry, $this->originCountries) && 
			!in_array('NONE', $this->originCountries) && !in_array('NONE', $this->destCountries));
	}

	public function getId() {
		return $this->serviceId;
	}

	public function getKey() {
		// Note: USPS does not have unique ServiceIds
		return $this->serviceId.base64_encode($this->getFullName());
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

	public function getDisplayName() {
		return $this->displayName;
	}

}
