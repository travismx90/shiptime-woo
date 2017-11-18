<?php

namespace emergeit;

abstract class ApiClientBase
{
	private $_encUsername = null;
	private $_encPassword = null;
	private $_wsdlUrl = null;

	private $_soapClient = null;

	protected function __construct($encUsername, $encPassword, $baseUrl, $wsdlUri)
	{
		$this->_encUsername = $encUsername;
		$this->_encPassword = $encPassword;

		if (substr($baseUrl, -1) != '/')
		{
			$baseUrl .= '/';
		}

		$this->_wsdlUrl = $baseUrl . $wsdlUri;

		$opts = array(
			'trace' => 1, // enables tracing of requests so faults can be backtraced
			'exceptions' => 1, // SOAP errors throw exceptions of type SoapFault
			'connection_timeout' => 10, // defines timeout for connection to SOAP service
			'cache_wsdl' => WSDL_CACHE_NONE // Do NOT cache WSDL in case of updates to API
		);

		try {
			$this->_soapClient = new \SoapClient($this->_wsdlUrl, $opts);
		} catch (\Exception $e) {
			// SoapFault Exception
			//die("Connection to ShipTime failed.");
			// Handle more gracefully with admin message when getSoapClient() == null
			// "Connection to ShipTime has failed. Please try again in a moment."
		}
	}

	public function getLastReq() {
		return $this->_soapClient->__getLastRequest();
	}

	public function getLastResp() {
		return $this->_soapClient->__getLastResponse();
	}

	public function getTypes() {
		return $this->_soapClient->__getTypes();
	}

	public function getFuncs() {
		return $this->_soapClient->__getFunctions();
	}

	public function getSoapClient() {
		return $this->_soapClient;
	}

	public function isConnected() {
		return is_object($this->getSoapClient());
	}

	protected function apiRequest($method, EmergeitApiRequest $req)
	{
		$key = array(
			'EncryptedUsername' => $this->_encUsername,
			'EncryptedPassword' => $this->_encPassword
		);

		// All API requests are wrapped within a <Request> and returned within
		// a <Response> except for getLocation, which uses neither
		if ($method !== 'getLocation') {
			$soapReq = array(array('Key' => $key, 'Request' => get_object_vars($req)));

			$soapResp = $this->_soapClient->__soapCall($method, $soapReq)->Response;
			//$this->storeSessionId();
		} else {
			$soapReq = array(array_merge(array('Key' => $key), get_object_vars($req)));

			$soapResp = $this->_soapClient->__soapCall($method, $soapReq);
		}

		return $soapResp;
	}

	protected function populateObject(&$source, &$target)
	{
		$props = get_object_vars($target);
		foreach ($props as $k => $v)
		{
			if (property_exists($source, $k))
			{
				if (is_array($target->{$k}))
				{
					$targetArr =& $target->{$k};
					$targetArrItem = $targetArr[0];

					$targetArr = array();

					if (is_array($source->{$k}))
					{
						$sourceArr =& $source->{$k};
					}
					else
					{
						$sourceArr =& current(get_object_vars($source->{$k}));

						if (!is_array($sourceArr))
						{
							if (is_object($sourceArr) && count(get_object_vars($sourceArr)) > 0)
							{
								$sourceArr = array($sourceArr);
							}
							else if (is_string($sourceArr))
							{
								$sourceArr = array($sourceArr);
							}
							else
							{
								$sourceArr = array();
							}
						}
					}

					// handle both arrays of objects...
					if (is_object($targetArrItem))
					{
						for ($i = 0; $i < count($sourceArr); ++$i)
						{
							$targetArr[$i] = clone($targetArrItem);
							$this->populateObject($sourceArr[$i], $targetArr[$i]);
						}
					}
					else // ...and arrays of instrinsic types
					{
						for ($i = 0; $i < count($sourceArr); ++$i)
						{
							$targetArr[$i] = $sourceArr[$i];
						}
					}
				}
				else if (is_object($target->{$k}))
				{
					$this->populateObject($source->{$k}, $target->{$k});
				}
				else
				{
					$target->{$k} = $source->{$k};
				}
			}
			else
			{
				if (is_array($target->{$k}))
				{
					// TODO empty array or null?
					$target->{$k} = array();
				}
			}
		}
	}

	private function storeSessionId()
	{
		if (array_key_exists('JSESSIONID', $this->_soapClient->_cookies))
		{
			$this->_soapClient->__setCookie('JSESSIONID', $this->_soapClient->_cookies['JSESSIONID'][0]);
		}
	}
}

class EmergeitApiRequest
{
	public function __construct()
	{
	}
}

class EmergeitApiResponse
{
	public $Messages = null;

	public function __construct()
	{
		$this->Messages = array(new Message());
	}
}
