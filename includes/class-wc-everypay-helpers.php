<?php

class WC_Everypay_Helpers
{

	/**
	 *  Get the website's locale
	 * @return string
	 */
	public function get_locale() {

		if(substr(get_locale(), 0, 2) != "el")
			return "en";

		return "el";
	}

	/**
	 *  Convert the amount to the appropriate format
	 * @param $amount numeric
	 * @return numeric
	 */
	public function format_amount($amount)
	{
		if( gettype($amount) != "string"){
			if (round($amount, 0) == $amount){
				return $amount * 100;
			}
		}
		$tmp = intval(preg_replace("/[^0-9]/", '', (string) $amount));
		if($tmp == intval($amount)){
			return $tmp * 100;
		}
		return $tmp;
	}

	/**
	 * @param $total numeric
	 * @param $installments integer
	 * @return int
	 */
	public function calculate_installments($total, $installments)
	{
		$installments = htmlspecialchars_decode($installments);
		if ($installments) {
			$installments = json_decode($installments, true);
			$max_installments = 0;
			foreach ($installments as $i) {
				if ($total >= $this->format_amount($i['from']) && $total <= $this->format_amount($i['to']) && intval($i['max']) > $max_installments)
					$max_installments = intval($i['max']);
			}
			return $max_installments;
		}
		return 0;
	}



}