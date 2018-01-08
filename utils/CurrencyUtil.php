<?php
/**
 * CurrencyUtil
 *
 * Converts values from API currency to store currency when different
 *
 * @author      travism
 * @version     1.0
*/
namespace emergeit;

class CurrencyUtil
{
	private static $baseUrl = 'finance.google.com';

	public static function convert($from, $to, $val, $raw=false) {
		if (empty($val))  { return $val; }
		if ($from == $to) { return $val; }

		$converted = 0;
		$url = 'https://'.static::$baseUrl.'/finance/converter?a='.$val.'&from='.$from.'&to='.$to;
		$regex = "/<span class=bld>(.*)<\/span>/";
		
		$curl_enabled = function_exists('curl_init');
		$resolve_url = !(gethostbyname(static::$baseUrl) === static::$baseUrl);
		$php_v_high_enough = version_compare(phpversion(), '5.5.0', '>=');
		
		// Try cURL by default
		if ($curl_enabled) {
			if ($resolve_url || $php_v_high_enough) {
				$req = curl_init();
				if (!$resolve_url) {
					// If name resolution not working (Req. PHP 5.5)
					curl_setopt($req, CURLOPT_RESOLVE, array("finance.google.com:443:172.217.9.206"));
				}
				curl_setopt($req, CURLOPT_URL, $url);
				curl_setopt($req, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($req, CURLOPT_USERAGENT,"Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.1)");
				curl_setopt($req, CURLOPT_CONNECTTIMEOUT, 0);
				$res = curl_exec($req);
				if ($res) {
					curl_close($req);
					preg_match($regex, $res, $matches);
					$converted = preg_replace("/[^0-9.]/", "", $matches[1]);
				}
			}
		}

		// Try file_get_contents() next if name resolution is working
		if ($resolve_url) {
			// If PHP version < 5.5.0, or
			// If cURL is not configured on this server or did not return a result
			$res = file_get_contents($url);
			if ($res) {
				preg_match($regex, $res, $matches);
				$converted = preg_replace("/[^0-9.]/", "", $matches[1]);
			}
		}
		
		// Fallback: Cannot obtain a live conversion rate; use saved value if found here
		if ($from == 'CAD' && $to == 'USD') {
			$converted = $val/1.25;
		} elseif ($from == 'CAD' && $to == 'EUR') {
			$converted = $val/1.50;	
		}

		if ($raw) {
			return $converted;
		} else {
			return number_format(round($converted, 2), 2, '.', '');
		}
	}

	public static function convFactor($from, $to) {
		return static::convert($from, $to, 1.00, true);
	}

}
