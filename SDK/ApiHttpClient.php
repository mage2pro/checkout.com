<?php
namespace Dfe\CheckoutCom\SDK;
# 2016-08-05
class ApiHttpClient {
	/**
	 * 2016-08-05
	 * @param array(string => string) $d [optional]
	 */
	static function postRequest(string $uri, array $d = []):\CheckoutApi_Lib_RespondObj {return
		\CheckoutApi_Api::getApi()->request($uri, ['method' => 'POST'] + $d, true)
	;}
}