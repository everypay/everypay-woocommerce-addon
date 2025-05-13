<?php

class WC_Everypay_Api
{
    /**
     * Internal API url.
     *
     * @var string
     */
    private static $apiEndPoint = null;

    /**
     * Live or sandbox mode.
     *
     * @var boolean
     */
    private static $testMode = false;

    /**
     * API key.
     *
     * Always set to secret key.
     *
     * @var string
     */
    private static $apiKey = null;

    /**
     * Set the API key for the request.
     *
     * @param string $key
     */
    public static function setApiKey($key)
    {
        self::$apiKey = $key;
    }

    /**
     * Get test mode indicator
     *
     * @return boolean
     */
    public static function isTestMode()
    {
        return self::$testMode;
    }

    /**
     * Set the test mode to true.
     *
     * @param string $mode
     */
    public static function setTestMode()
    {
        self::$testMode = true;
    }

    /**
     * Get the API key.
     *
     * @return string
     */
    public static function getApiKey()
    {
        return self::$apiKey;
    }

    /**
     * Return the API uri.
     *
     * @return string
     */
    private static function getApiEndPoint()
    {
        if (null === self::$apiEndPoint) {
            self::$apiEndPoint = 'https://' . (self::isTestMode() ? 'sandbox-' : '') . 'api.everypay.gr';
        }

        return self::$apiEndPoint;
    }


    /**
     * Refund the provided payment.
     *
     * @param  string $token
     * @param  array  $params
     * @return array
     */
    public static function refundPayment($token, $params)
    {
        $url = self::getApiEndPoint()
            . '/payments/refund/'
            . $token;

        return self::request($url, $params);
    }

    /**
     * Add a new payment with provided credit card details.
     *
     * @param  array $params
     * @return array
     */
    public static function addPayment($params)
    {
        $url = self::getApiEndPoint() . '/payments';

        return self::request($url, $params);
    }



    /**
     * Make an API request with curl.
     *
     * @param  string $url
     * @param  array  $params
     * @param  string $method
     * @return array
     */
    private static function request(string $url, array $params = array(), string $method = 'POST')
    {
        $apiKey = self::getApiKey();

        if (!$apiKey) {
            throw new Exception('api secret key is missing');
        }
        $query = http_build_query($params, '', '&');
        $curl   = curl_init();
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'User-Agent: EveryPay Internal PHP Library'
        ));
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);

        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($curl, CURLOPT_USERPWD, $apiKey . ':');

        if (!empty($params)) {
            $query = http_build_query($params, '', '&');
            if ('get' === strtolower($method)) {
                $url .= (false === strpos($url, '?')) ? '?' : '&';
                $url .= $query;
            } else {
                curl_setopt($curl, CURLOPT_POSTFIELDS, $query);
            }
        }

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $result   = curl_exec($curl);
        $info     = curl_getinfo($curl);

        $response = array();

        if (curl_errno($curl)) {
            $curlError = curl_error($curl);
            throw new Exception($curlError);
        }

        if (stripos($info['content_type'], 'application/json') === false) {
            throw new Exception('content type is not application/json' . ' ' . $query);
        }

        $response['status'] = $info['http_code'];
        $response['body']   = json_decode($result, true);

		if ((!isset($response['body']) || empty($response['body'])) && $response['status'] !== 204) {
            throw new Exception('response body is empty. ' . $query);
        }

        if (isset($response['body']['error'])) {
            throw new Exception($response['body']['error']['message'] . ' ' . $query);
        }

        return $response;
    }

	public static function registerApplePayMerchantDomain(string $domain): array
	{
		$url = self::getApiEndPoint() . '/applepay/domains';

		return self::request($url, [
			'domain_names' => [
				$domain,
			],
		]);
	}
}
