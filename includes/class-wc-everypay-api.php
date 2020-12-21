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
     * Create a new customer.
     *
     * @param  array $params
     * @return array
     */
    public static function createCustomer(array $params)
    {
        $url = self::getApiEndPoint() . '/customers';

        return self::request($url, $params);
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
        $query = http_build_query($params, null, '&');

        $api_response = wp_remote_post(
            $url,
            array(
                'method'  => $method,
                'headers' => array(
                    'User-Agent' => 'EveryPay Woocommerce Plugin',
                    'Authorization' => 'Basic ' . base64_encode( $apiKey . ':')
                ),
                'body'    => $query,
                'timeout' => 30,
                'sslverify' => false,
            )
        );
        $response = array();

        if ( is_wp_error($api_response) || empty($api_response['body']) ) {
            $response['status'] = 500;
            $response['body']['error']['message'] = 'A problem occurred with the payment. Please try again.';
            return $response;
        }

        if (wp_remote_retrieve_header($api_response, 'content-type') != 'application/json') {
            $response['status'] = 500;
            $message = 'The returned curl response is not in json format';
            $response['body']['error']['message'] = $message;
            return $response;
        }

        $response['status'] = wp_remote_retrieve_response_code($api_response);
        $response['body'] = json_decode(wp_remote_retrieve_body($api_response), true);

        if (isset($response['body']['error'])) {
            $response['status'] = 500;
            $response['body']['error']['message'] = pll__('An error with the payment occurred. Please try again.');
        }

        return $response;
    }
}
