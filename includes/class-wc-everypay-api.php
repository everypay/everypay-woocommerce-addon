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

        $query = http_build_query($params, null, '&');
        $api_response = wp_remote_request(
            $url,
            array(
                'method'  => $method,
                'headers' => array(
                    'User-Agent' => 'EveryPay Woocommerce',
                    'Authorization' => 'Basic ' . base64_encode( $apiKey . ':')
                ),
                'body'    => $query,
                'timeout' => 50
            )
        );

        if (is_wp_error($api_response)) {
	        throw new Exception($api_response->get_error_message(). ' '. $query);
        }

        $response = array();
        if (empty($api_response['body']) ) {
			throw new Exception('response body from api is empty.'. ' '. $query);
        }

        if (wp_remote_retrieve_header($api_response, 'content-type') != 'application/json') {
			throw new Exception('content type is not application/json'. ' '. $query);
        }
        $response['status'] = wp_remote_retrieve_response_code($api_response);
        $response['body'] = json_decode(wp_remote_retrieve_body($api_response), true);

        if (isset($response['body']['error'])) {
            throw new Exception($response['body']['error']['message']. ' '. $query);
        }

        return $response;
    }
}
