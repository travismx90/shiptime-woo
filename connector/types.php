<?php

namespace emergeit;

class Address
{
	public $Attention = null;
	public $City = null;
	public $CompanyName = null;
	public $CountryCode = null;
	public $Email = null;
	public $Instructions = null;
	public $Notify = null;
	public $Phone = null;
	public $PostalCode = null;
	public $Province = null;
	public $Residential = null;
	public $StreetAddress = null;
	public $StreetAddress2 = null;
}

class Amount
{
	public $UnitsType = null;
	public $Value = null;
}

class CarrierServiceOption 
{
	public $CarrierId = null;
	public $CarrierName = null;
	public $ServiceId = null;
	public $ServiceName = null;
	public $ServiceType = null;
}

class CustomsInvoice
{
	public $DutiesAndTaxes = null;
	public $InvoiceContact = null;
	public $InvoiceItems = null;

	public function __construct()
	{
		$this->init();
	}
	
	public function __clone()
	{
		$this->init();
	}
	
	private function init()
	{
		$this->DutiesAndTaxes = new DutiesAndTaxes();
		$this->InvoiceContact = new InvoiceContact();
		$this->InvoiceItems = array(new InvoiceItem());
	}
}

class DutiesAndTaxes
{
	public $Dutiable = null;
	public $Selection = null;
}

class Event
{
	public $Location = null;
	public $Name = null;
	public $Status = null;
	public $TimeStamp = null;	
}

class HistoryFilters
{
	public $StartDate = null;
	public $EndDate = null;
	public $Status = null;
	public $CarrierId = null;
}

class InvoiceContact
{
	public $Attention = null;
	public $City = null;
	public $CompanyName = null;
	public $CountryCode = null;
	public $Email = null;
	public $Notify = null;
	public $Phone = null;
	public $PostalCode = null;
	public $Province = null;
	public $Residential = null;
	public $StreetAddress = null;
	public $CustomsBroker = null;
	public $ShipperTaxId = null;
}

class InvoiceItem
{
	public $Code = null;
	public $Description = null;
	public $Origin = null;
	public $Quantity = null;
	public $UnitPrice = null;

	public function __construct()
	{
		$this->init();
	}
	
	public function __clone()
	{
		$this->init();
	}
	
	private function init()
	{
		$this->Quantity = new Amount();
		$this->UnitPrice = new MoneyAmount();
	}
}

class Key
{
	public $EncryptedUsername = null;
	public $EncryptedPassword = null;
}

class LineItem
{
	public $Length = null;
	public $Width = null;
	public $Height = null;
	public $Weight = null;
	public $DeclaredValue = null;
	public $Description = null;
	
	public function __construct()
	{
		$this->init();
	}
	
	public function __clone()
	{
		$this->init();
	}
	
	private function init()
	{
		$this->Length = new Amount();
		$this->Width = new Amount();
		$this->Height = new Amount();
		$this->Weight = new Amount();
		$this->DeclaredValue = new MoneyAmount();
	}
}

class Location
{
	public $city = null;
	public $postalCode = null;
	public $state = null;
}

class Message
{
	public $Severity = null;
	public $Text = null;
}

class MoneyAmount
{
	public $CurrencyCode = null;
	public $Amount = null;
}

class ProofOfDelivery
{
	public $Location = null;
	public $Place = null;
	public $SignedBy = null;
	public $TimeStamp = null;
}

class Quote
{
	public $CarrierId = null;
	public $CarrierName = null;
	public $ServiceId = null;
	public $ServiceName = null;
	public $BaseCharge = null;
	public $Surcharges = null;
	public $Taxes = null;
	public $TotalBeforeTaxes = null;
	public $TotalCharge = null;
	public $TransitDays = null;
	public $ServiceTerms = null;
	
	public function __construct()
	{
		$this->init();
	}
	
	public function __clone()
	{
		$this->init();
	}
	
	private function init()
	{
		$this->BaseCharge = new MoneyAmount();
		$this->Surcharges = array(new Surcharge());
		$this->Taxes = array(new Tax());
		$this->TotalBeforeTaxes = new MoneyAmount();
		$this->TotalCharge = new MoneyAmount();
	}
}

class Shipment
{
	public $CarrierId = null;
	public $CreationDate = null;
	public $From = null;
	public $LastKnownStatus = null;
	public $ModificationDate = null;
	public $NumberOfItems = null;
	public $OrderId = null;
	public $PackageType = null;
	public $ServiceId = null;
	public $ServiceName = null;
	public $ShipDate = null;
	public $To = null;
	public $TotalWeight = null;
	public $TrackId = null;
	
	public function __construct()
	{
		$this->init();
	}
	
	public function __clone()
	{
		$this->init();
	}
	
	private function init()
	{
		$this->From = new Address();
		$this->To = new Address();
	}
}

class ShipmentItem
{
	public $Length = null;
	public $Width = null;
	public $Height = null;
	public $Weight = null;
	
	public function __construct()
	{
		$this->init();
	}
	
	public function __clone()
	{
		$this->init();
	}
	
	private function init()
	{
		$this->Length = new Amount();
		$this->Width = new Amount();
		$this->Height = new Amount();
		$this->Weight = new Amount();
	}
}

class Surcharge
{
	public $Price = null;
	public $Code = null;
	public $Name = null;
	
	public function __construct()
	{
		$this->init();
	}
	
	public function __clone()
	{
		$this->init();
	}
	
	private function init()
	{
		$this->Price = new MoneyAmount();
	}
}

class Tax
{
	public $Price = null;
	public $Code = null;
	public $Name = null;
	
	public function __construct()
	{
		$this->init();
	}
	
	public function __clone()
	{
		$this->init();
	}
	
	private function init()
	{
		$this->Price = new MoneyAmount();
	}
}

class TrackingRecord
{
	public $CurrentStatus = null;
	public $Events = null;

	public function __construct()
	{
		$this->init();
	}

	public function __clone()
	{
		$this->init();
	}

	private function init()
	{
		$this->Events = array(new Event());
	}
}

?>