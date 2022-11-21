<?php
namespace Dfe\CheckoutCom\SDK;
# 2016-08-05
class ApiHttpClient {
	/**
	 * 2016-08-05
	 * @param array(string => string) $payload [optional]
	 */
	static function postRequest(string $uri, array $payload = []):\CheckoutApi_Lib_RespondObj {
		return self::api()->request($uri, ['method' => 'POST'] + $payload, true);
	}

	/**
	 * 2016-08-05
	 * @return \CheckoutApi_Client_Client|\CheckoutApi_Client_ClientGW3
	 */
	private static function api() {return \CheckoutApi_Api::getApi();}
}


