<?php


class WC_Everypay_Renderer
{
	private $helpers;
	private $public_key;
	private $locale;
	private $tokenization_status;

	private $isGooglePayEnabled;
	private $googlePayCountryCode;
	private $googlePayMerchantName;
	private $googlePayMerchantUrl;
	private $googlePayAllowedCardNetworks;
	private $googlePayAllowedAuthMethods;
	private $googlePayButtonColor;

	private $isApplePayEnabled;
	private $applePayCountryCode;
	private $applePayMerchantName;
	private $applePayMerchantUrl;
	private $applePayAllowedCardNetworks;
	private $applePayButtonColor;


	public function __construct($helpers, $public_key, $tokenization_status)
	{
		$this->helpers = $helpers;
		$this->public_key = $public_key;
		$this->locale = $this->helpers->get_locale();
		$this->tokenization_status = $tokenization_status;
	}

	public function render_iframe($amount, $max_installments)
	{
		$billing_address = WC()->customer->get_billing_address();
		$billing_email = WC()->customer->get_billing_email();
		$billing_phone = WC()->customer->get_billing_phone();

		$total = $this->helpers->format_amount($amount);

		$EVDATA = array(
			'amount' => $total,
			'pk' => $this->public_key,
			'max_installments' => $this->helpers->calculate_installments($total, $max_installments),
			'locale' => $this->locale,
			'billing_address' => $billing_address,
			'email' => $billing_email,
			'phone' => $billing_phone,
		);

		if ($this->isGooglePayEnabled) {
			$EVDATA['googlePay'] = [
				'countryCode' => $this->googlePayCountryCode,
				'merchantName' => $this->googlePayMerchantName,
				'merchantUrl' => $this->googlePayMerchantUrl,
				'allowedCardNetworks' => explode(',', $this->googlePayAllowedCardNetworks),
				'allowedAuthMethods' => explode(',', $this->googlePayAllowedAuthMethods),
				'buttonColor' => $this->googlePayButtonColor,
			];
		}

		if ($this->isApplePayEnabled) {
			$EVDATA['applePay'] = [
				'countryCode' => $this->applePayCountryCode,
				'merchantName' => $this->applePayMerchantName,
				'merchantUrl' => $this->applePayMerchantUrl,
				'allowedCardNetworks' => explode(',', $this->applePayAllowedCardNetworks),
				'buttonColor' => $this->applePayButtonColor,
			];
		}

		if (!empty($_POST['tokenized-card'])) {
			$EVDATA['tokenized'] = true;
		}

		if ($this->tokenization_status == 'yes' && is_user_logged_in() && empty($_POST['tokenized-card'])) {
			$EVDATA['save_cards'] = true;
		}

		$response_data = array(
			'messages' => "<script type=\"text/javascript\">" . "EVDATA = " . json_encode($EVDATA) . ";"
				. "load_everypay();</script>",
		);

		echo json_encode($response_data);
	}

	public function render_cards($cards)
	{
		if (!is_array($cards)) {
			return;
		}
		?>
        <div id="card-container">
			<?php
			foreach ($cards as $card) {
				?>
                <div class="card-box">
                    <div>
                        <input type="radio" name="tokenized-card" value="<?php echo esc_html($card->friendly_name); ?>"
                               crd="<?php echo esc_html($card->crd); ?>"
                               exp_month="<?php echo esc_html($card->card_expiration_month); ?>"
                               exp_year="<?php echo esc_html($card->card_expiration_year); ?>"
                               last_four="<?php echo esc_html($card->card_last_four); ?>"
                               card_type="<?php echo esc_html($card->card_type); ?>">
                        <label for="<?php echo esc_html($card->friendly_name); ?>"><?php echo esc_html($card->friendly_name); ?></label>
                    </div>
                    <span class="delete-card-btn" onclick="deleteCard(this)">&times;</span>
                </div>
				<?php
			}
			?> </div> <?php
	}

	public function setGooglePay(
		string $countryCode,
		string $merchantName,
		string $merchantUrl,
		string $allowedCardNetworks,
		string $allowedAuthMethods,
        string $buttonColor
	): void
	{
		$this->isGooglePayEnabled = true;
		$this->googlePayCountryCode = $countryCode;
		$this->googlePayMerchantName = $merchantName;
		$this->googlePayMerchantUrl = $merchantUrl;
		$this->googlePayAllowedCardNetworks = $allowedCardNetworks;
		$this->googlePayAllowedAuthMethods = $allowedAuthMethods;
        $this->googlePayButtonColor = $buttonColor;
	}

	public function setApplePay(
		string $countryCode,
		string $merchantName,
		string $merchantUrl,
		string $allowedCardNetworks,
		string $buttonColor
	): void
	{
		$this->isApplePayEnabled = true;
		$this->applePayCountryCode = $countryCode;
		$this->applePayMerchantName = $merchantName;
		$this->applePayMerchantUrl= $merchantUrl;
		$this->applePayAllowedCardNetworks = $allowedCardNetworks;
		$this->applePayButtonColor = $buttonColor;
	}
}