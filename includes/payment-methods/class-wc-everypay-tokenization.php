<?php

class WC_Everypay_Tokenization
{

	private $repository;
	private $user;
	private $user_id;

	public function __construct()
	{
		$this->repository = new WC_Everypay_Repository();
	}

	public function delete_card($friendly_name, $user_id)
	{
		if (empty($friendly_name)) {
			return;
		}
		$friendly_name = sanitize_text_field($friendly_name);
		$this->repository->delete_user_card($friendly_name, $user_id);
	}

	public function process_tokenized_payment($user_id, $payload)
	{
		$this->user_id = $user_id;
		$this->user = $this->repository->get_tokenization_customer($user_id);
		if (!$this->user) {
			$payload['create_customer'] = 1;
			$api_response = $this->pay($payload);
			$this->save_new_customer($api_response);
			return $api_response;
		}
		$this->add_new_customer_card($payload);
	}

	private function pay($payload)
	{
		return WC_Everypay_Api::addPayment($payload);
	}

	private function add_new_customer_card($payload)
	{
		$card_id = $this->repository->get_card_id($this->user->customer_token, $_POST['tokenized-card']);

		if ($card_id) {
			$api_response = $this->pay($payload);
			$card_data = $this->createCardData($api_response);
			$this->repository->update_card_crd($card_id->id, $card_data['crd'], $card_data['customer_token']);
			return $api_response;
		}

		$payload['customer'] = $this->user->customer_token;
		$api_response = $this->pay($payload);
		$card_data = $this->createCardData($api_response);
		$this->repository->add_new_card($card_data);
		return $api_response;
	}

	private function createCardData($api_response)
	{
		if (isset($api_response['error'])) {
			throw new Exception('An error occurred.');
		}
		$api_response = $api_response['body'];
		$customer_token = $this->user->customer_token;
		if (!$customer_token) {
			$customer_token = $api_response['customer']['token'];
		}
		return array(
			'wp_user_id' => $this->user_id,
			'friendly_name' => sanitize_text_field($api_response['card']['friendly_name']),
			'customer_token' => sanitize_text_field($customer_token),
			'crd' => sanitize_text_field($api_response['card']['token']),
			'card_expiration_month' => sanitize_text_field($api_response['card']['expiration_month']),
			'card_expiration_year' => sanitize_text_field($api_response['card']['expiration_year']),
			'card_type' => sanitize_text_field($api_response['card']['type']),
			'card_last_four' => sanitize_text_field($api_response['card']['last_four'])
		);
	}

	private function save_new_customer($api_response)
	{
		$customer_data = $this->createCardData($api_response);
		$this->repository->save_customer($customer_data);
	}


}