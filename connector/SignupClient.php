<?php

namespace emergeit;

require_once('ApiClientBase.php');
require_once('types.php');

class SignupClient extends ApiClientBase
{
	private $_defaultBaseUrl = 'http://ship.appspace.ca/api';

	private $_soapClient = null;
	
	public function __construct($baseUrl=null, $wsdlUri=null)
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
}

class SignupRequest extends EmergeitApiRequest
{
	public $IntegrationID = null;
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

?>