<?php

class WC_Everypay_Global_Checks {

    public $errors = [];

    public function __construct() {
        $this->check_json();
        $this->defineErrorsFromChecks();
    }

    public function defineErrorsFromChecks() {
        try {
            define('EVERYPAY_GLOBAL_ERRORS', $this->errors);
        } catch (Error $e) {
        }
    }

    public function check_json() {
        if (!function_exists('json_decode'))
            $this->errors[] = ' Everypay plugin needs the JSON PHP extension.';

    }

}

new WC_Everypay_Global_Checks();