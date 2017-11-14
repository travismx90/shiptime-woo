<?php

namespace emergeit;

class CurrencyUtil
{
	private static $baseUrl = 'https://finance.google.com/finance/converter?';

	public static function convert($from, $to, $val) {
		$url = static::$baseUrl.'a='.$val.'&from='.$from.'&to='.$to;
		$data = file_get_contents($url);
		preg_match("/<span class=bld>(.*)<\/span>/",$data, $converted);
		$converted = preg_replace("/[^0-9.]/", "", $converted[1]);
		return number_format(round($converted, 3),2);
	}
}

?>
