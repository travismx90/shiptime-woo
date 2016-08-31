<?php

namespace emergeit;

require_once('ApiClientBase.php');
require_once('types.php');

class ShippingClient extends ApiClientBase
{
	private $_defaultBaseUrl = 'http://ship.emergeit.com/api';
	//private $_defaultBaseUrl = 'http://sandbox.shiptime.com/api';

	public function __construct($encUsername, $encPassword, $baseUrl = null)
	{
		if ($baseUrl === null)
		{
			$baseUrl = $this->_defaultBaseUrl;
		}

		parent::__construct($encUsername, $encPassword, $baseUrl, 'shipping?wsdl');
	}

	public function placeShipment(PlaceShipmentRequest $req)
	{
		$soapResp = $this->apiRequest('placeShipment', $req);
		$resp = new PlaceShipmentResponse();
		$this->populateObject($soapResp, $resp);

		return $resp;
	}

	public function cancelShipment(CancelShipmentRequest $req)
	{
		$soapResp = $this->apiRequest('cancelShipment', $req);
		$resp = new CancelShipmentResponse();
		$this->populateObject($soapResp, $resp);

		return $resp;
	}

	public function getShipmentHistory(GetShipmentHistoryRequest $req)
	{
		$soapResp = $this->apiRequest('getShipmentHistory', $req);
		$resp = new GetShipmentHistoryResponse();
		$this->populateObject($soapResp, $resp);

		return $resp;
	}

	public function trackShipment(TrackShipmentRequest $req)
	{
		$soapResp = $this->apiRequest('trackShipment', $req);
		$resp = new TrackShipmentResponse();
		$this->populateObject($soapResp, $resp);

		return $resp;
	}
}

class PlaceShipmentRequest extends EmergeitApiRequest
{
	public $CarrierId = null;
	public $ServiceId = null;
	public $From = null;
	public $To = null;
	public $PackageType = null;
	public $ServiceOptions = null;
	public $ShipmentItems = null;
	public $ShipDate = null;
	public $DeferredProcessing = null;
	public $CustomsInvoice = null;

	public function __construct()
	{
		parent::__construct();
		$this->From = new Address();
		$this->To = new Address();
		$this->ServiceOptions = array();
		$this->ShipmentItems = array();
		$this->CustomsInvoice = new CustomsInvoice();
	}
}

class PlaceShipmentResponse extends EmergeitApiResponse
{
	public $LabelUrl = null;
	public $InvoiceUrl = null;
	public $ShipId = null;
	public $TrackingNumbers = null;

	public function __construct()
	{
		parent::__construct();
		$this->TrackingNumbers = array();
	}
}

class CancelShipmentRequest extends EmergeitApiRequest
{
	public $ShipId = null;

	public function __construct()
	{
		parent::__construct();
	}
}

class CancelShipmentResponse extends EmergeitApiResponse
{
	public function __construct()
	{
		parent::__construct();
	}
}

class GetShipmentHistoryRequest extends EmergeitApiRequest
{
	public $Offset = null;
	public $Limit = null;
	public $HistoryFilters = null;

	public function __construct()
	{
		parent::__construct();
		$this->HistoryFilters = new HistoryFilters();
	}
}

class GetShipmentHistoryResponse extends EmergeitApiResponse
{
	public $Shipments = null;

	public function __construct()
	{
		parent::__construct();
		$this->Shipments = array(new Shipment());
	}
}

class TrackShipmentRequest extends EmergeitApiRequest
{
	public $ShipId = null;

	public function __construct()
	{
		parent::__construct();
	}
}

class TrackShipmentResponse extends EmergeitApiResponse
{
	public $TrackingRecord = null;
	public $ProofOfDelivery = null;

	public function __construct()
	{
		parent::__construct();
		$this->TrackingRecord = new TrackingRecord();
		$this->ProofOfDelivery = new ProofOfDelivery();
	}
}

?>
