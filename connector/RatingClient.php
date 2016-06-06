<?php

namespace emergeit;

require_once('ApiClientBase.php');
require_once('types.php');

class RatingClient extends ApiClientBase
{	
	//private $_defaultBaseUrl = 'http://ship.emergeit.com/api';
	private $_defaultBaseUrl = 'http://sandbox.shiptime.com/api';

	public function __construct($encUsername, $encPassword, $baseUrl = null)
	{
		if ($baseUrl === null)
		{
			$baseUrl = $this->_defaultBaseUrl;
		}
	
		parent::__construct($encUsername, $encPassword, $baseUrl, 'rating?wsdl');
	}
	
	public function getRates(GetRatesRequest $req)
	{
		$soapResp = $this->apiRequest('getRates', $req);
		$resp = new GetRatesResponse();
		$this->populateObject($soapResp, $resp);
	
		return $resp;
	}
}

class GetRatesRequest extends EmergeitApiRequest
{
	public $From = null;
	public $To = null;
	public $PackageType = null;
	public $ServiceOptions = null;
	public $ShipmentItems = null;
	public $ShipDate = null;
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

class GetRatesResponse extends EmergeitApiResponse
{
	public $AvailableRates = null;
	
	public function __construct()
	{
		parent::__construct();
		$this->AvailableRates = array(new Quote());
	}
}

?>