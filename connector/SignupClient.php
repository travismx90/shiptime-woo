<?php

namespace emergeit;

require_once('ApiClientBase.php');
require_once('types.php');

class SignupClient extends ApiClientBase
{
	private $_defaultBaseUrl = 'http://ship.emergeit.com/api';
	//private $_defaultBaseUrl = 'http://sandbox.shiptime.com/api';

	public function __construct($encUsername=null, $encPassword=null, $baseUrl=null, $wsdlUri=null)
	{	
		if ($baseUrl === null)
		{
			$baseUrl = $this->_defaultBaseUrl;
		}
	
		parent::__construct($encUsername, $encPassword, $baseUrl, 'signup?wsdl');
	}

	public function signup(SignupRequest $req)
	{
		$soapResp = $this->apiRequest('signup', $req);
		$resp = new SignupResponse();
		$this->populateObject($soapResp, $resp);

		return $resp;
	}

	public function getServices(GetServicesRequest $req)
	{
		$soapResp = $this->apiRequest('getServices', $req);
		$resp = new GetServicesResponse();
		$this->populateObject($soapResp, $resp);

		return $resp;
	}

	public function getKey(GetKeyRequest $req)
	{
		$soapResp = $this->apiRequest('getKey', $req);
		$resp = new GetKeyResponse();
		$this->populateObject($soapResp, $resp);
	
		return $resp;
	}

	public function getAccountDetail(GetAccountDetailRequest $req)
	{
		$soapResp = $this->apiRequest('getAccountDetail', $req);
		$resp = new GetAccountDetailResponse();
		$this->populateObject($soapResp, $resp);
	
		return $resp;
	}

	public function registerPaymentMethod(RegisterPaymentMethodRequest $req)
	{
		$soapResp = $this->apiRequest('registerPaymentMethod', $req);
		$resp = new RegisterPaymentMethodResponse();
		$this->populateObject($soapResp, $resp);
	
		return $resp;
	}
}

class SignupRequest extends EmergeitApiRequest
{
	public $Address = null;
	public $City = null;
	public $CompanyName = null;
	public $Country = null;
	public $Email = null;
	public $Password = null;
	public $FirstName = null;
	public $LastName = null;
	public $Language = null;
	public $Phone = null;
	public $PostalCode = null;
	public $State = null;

	public function __construct()
	{
		parent::__construct();
	}
}

class SignupResponse extends EmergeitApiResponse
{
	public $key = null;

	public function __construct()
	{
		parent::__construct();
		$this->key = new Key();
	}
}

class GetServicesRequest extends EmergeitApiRequest
{
	public $Credentials = null;

	public function __construct()
	{
		parent::__construct();
		$this->Credentials = new Key();
	}
}

class GetServicesResponse extends EmergeitApiResponse
{
	public $ServiceOptions = null;

	public function __construct()
	{
		parent::__construct();
		$this->ServiceOptions = array(new CarrierServiceOption());
	}
}

class GetKeyRequest extends EmergeitApiRequest
{
	public $Username = null;
	public $Password = null;
	
	public function __construct()
	{
		parent::__construct();
	}
}

class GetKeyResponse extends EmergeitApiResponse
{	
	public $Credentials = null;

	public function __construct()
	{
		parent::__construct();
		$this->Credentials = new Key();
	}
}

class GetAccountDetailRequest extends EmergeitApiRequest
{
	public $Key = null;

	public function __construct()
	{
		parent::__construct();
		$this->Key = new Key();
	}
}

class GetAccountDetailResponse extends EmergeitApiResponse
{	
	public $Address = null;
	public $Address2 = null;
	public $BusinessName = null;
	public $City = null;
	public $Country = null;
	public $Email = null;	
	public $Phone = null;
	public $state = null;
	public $Zip = null;	

	public function __construct()
	{
		parent::__construct();
	}
}

class RegisterPaymentMethodRequest extends EmergeitApiRequest
{
	public $CardNumber = null;
	public $CVD = null;
	public $City = null;
	public $CountryCode = null;
	public $ExpiryMonth = null;
	public $ExpiryYear = null;
	public $NameOnCard = null;
	public $PostalCode = null;
	public $Province = null;
	public $StreetAddress = null;

	public function __construct()
	{
		parent::__construct();
		$this->Key = new Key();
	}	
}

class RegisterPaymentMethodResponse extends EmergeitApiResponse
{
	public $Staus = null;

	public function __construct()
	{
		parent::__construct();
	}	
}
