<?php


class WC_Everypay_Renderer
{
    private $helpers;
    private $public_key;
    private $locale;
    private $tokenization_status;

    public function __construct($helpers, $public_key, $tokenization_status)
    {
        $this->helpers = $helpers;
        $this->public_key = $public_key;
        $this->locale = $this->helpers->get_locale();
        $this->tokenization_status = $tokenization_status;
    }

	public function render_iframe($amount, $max_installments)
	{
		global $current_user;
		$billing_address = get_user_meta($current_user->ID, 'billing_address_1', true );
		$billing_email = get_user_meta($current_user->ID, 'billing_email', true);
		$billing_phone = get_user_meta($current_user->ID, 'billing_phone', true);

        $total = $this->helpers->format_amount($amount);
        // @note

		$EVDATA = array(
			'amount' => $total,
			'pk' => $this->public_key,
			'max_installments' => $this->helpers->calculate_installments($total, $max_installments),
			'locale' => $this->locale,
			'billing_address' => $billing_address,
			'email' => $billing_email,
			'phone' => $billing_phone,
		);
		// @note
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
		?> <div id="card-container">
        <?php
		foreach ($cards as $card) {
			?>
			<div class="card-box">
                <div>
                    <input type="radio" name="tokenized-card" value="<?php echo esc_html($card->friendly_name);?>"
                           crd="<?php echo esc_html($card->crd);?>"  exp_month="<?php echo esc_html($card->card_expiration_month);?>"
                           exp_year="<?php echo esc_html($card->card_expiration_year);?>" last_four="<?php echo esc_html($card->card_last_four);?>" card_type="<?php echo esc_html($card->card_type);?>">
                    <label for="<?php echo esc_html($card->friendly_name);?>"><?php echo esc_html($card->friendly_name);?></label>
                </div>
                <span class="delete-card-btn" onclick="deleteCard(this)">&times;</span>
			</div>
			<?php
		}
		?> </div> <?php
	}

}