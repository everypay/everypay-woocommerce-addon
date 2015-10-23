<?php
/**
 * API Request Service.
 */
class Everypay
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
     * Update customer.
     *
     * @param  string $token
     * @param  array  $params
     * @return array
     */
    public static function updateCustomer($token, $params)
    {
        $url = self::getApiEndPoint() . '/customers/' . $token;

        return self::request($url, $params);
    }

    /**
     * Deactivate provided customer.
     *
     * @param  string $token
     * @return array
     */
    public static function deleteCustomer($token)
    {
        $url = self::getApiEndPoint() . '/customers/' . $token;

        return self::request($url, array(), 'DELETE');
    }

    /**
     * Make an API request with curl.
     *
     * @param  string $url
     * @param  array  $params
     * @param  string $method
     * @return array
     */
    private static function request($url, array $params = array(), $method = 'POST')
    {
        $curl   = curl_init();
        $apiKey = self::getApiKey();

        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'User-Agent: EveryPay Internal PHP Library'
        ));
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);

        // HTTP Auth Basic
        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($curl, CURLOPT_USERPWD, $apiKey . ':');

        if (!empty($params)) {
            $query = http_build_query($params, null, '&');
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
            $response['status'] = 500;
            $response['body']['error']['message'] = curl_error($curl);

            return $response;
        }

        curl_close($curl);

        if (stripos($info['content_type'], 'application/json') === false) {
            $response['status'] = 500;
            $message = 'The returned curl response is not in json format';
            $response['body']['error']['message'] = $message;

            return $response;
        }

        $response['status'] = $info['http_code'];
        $response['body']   = json_decode($result, true);

        return $response;
    }
}
