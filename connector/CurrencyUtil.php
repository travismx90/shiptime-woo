<?php

namespace emergeit;

class CurrencyUtil
{
	private static $baseUrl = 'http://www.google.com/finance/converter?';

	public static function convert($from, $to, $val) {
		$url = static::$baseUrl.'a='.$val.'&from='.$from.'&to='.$to;
		$req = curl_init();
		curl_setopt ($req, CURLOPT_URL, $url);
        curl_setopt ($req, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt ($req, CURLOPT_USERAGENT,"Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.1)");
        curl_setopt ($req, CURLOPT_CONNECTTIMEOUT, 0);
        $res = curl_exec($req);
        curl_close($req);
        $regex = '#\<span class=bld\>(.+?)\<\/span\>#s';
        preg_match($regex, $res, $matches);
        return round(array_shift(preg_split("/[\s]+/",$matches[1])),2);
	}
}

?>
