<?php

class WC_Everypay_Repository
{

	private $tokenization_table;
	private $db_charset_collate;
	private $wpdb;

	public function __construct()
	{
		global $wpdb;
		$this->wpdb = $wpdb;
		$this->tokenization_table = $wpdb->prefix . "everypay_tokenization";
		$this->db_charset_collate = $wpdb->get_charset_collate();
	}

	public function delete_user_card($friendly_name, $user_id)
	{
		return $this->wpdb->query( "DELETE FROM $this->tokenization_table WHERE wp_user_id = $user_id and friendly_name = '$friendly_name'");
	}

	public function add_new_card($card_data)
	{
		$this->wpdb->insert($this->tokenization_table, $card_data);
	}

	public function update_card_crd($card_id, $crd, $customer_token)
	{
		$this->wpdb->update($this->tokenization_table,
         [ 'crd' => $crd ],
		 ['id' => $card_id, 'customer_token' => $customer_token]
		);
	}

	public function get_card_id($customer_token, $friendly_name)
	{
		if (!$friendly_name) {
			return false;
		}
		return $this->wpdb->get_row("
			SELECT
			id
			FROM $this->tokenization_table
			where customer_token = '$customer_token'
			and friendly_name = '$friendly_name'
		");
	}

	public function get_customer_cards($user_id)
	{
		$user_id = sanitize_text_field($user_id);
		return $this->wpdb->get_results( "
				SELECT  
				friendly_name, customer_token, card_expiration_month, card_expiration_year,
				card_last_four, card_type, crd       
				FROM $this->tokenization_table 
				where wp_user_id = $user_id 
		");
	}

	public function save_customer($customer_data)
	{
		$this->wpdb->insert($this->tokenization_table, $customer_data);
	}

	public function get_tokenization_customer($user_id)
	{
		$user_id = sanitize_text_field($user_id);
		return $this->wpdb->get_row( "SELECT * FROM $this->tokenization_table where wp_user_id = $user_id" );
	}


	public function drop_tokenization_table()
	{
		$sql = "DROP TABLE IF EXISTS ".$this->tokenization_table;
		$this->wpdb->query($sql);
	}

	public function create_tokenization_table()
	{
		$users_table = $this->wpdb->prefix . 'users(ID)';
		$sql = "CREATE TABLE IF NOT EXISTS ".$this->tokenization_table ." (
          id INT NOT NULL AUTO_INCREMENT,
          wp_user_id bigint(20) UNSIGNED,
          friendly_name VARCHAR(100) NOT NULL,
          customer_token VARCHAR(100) NOT NULL,
          crd VARCHAR(100) NOT NULL,
          card_expiration_month INT UNSIGNED NOT NULL,
          card_expiration_year INT UNSIGNED NOT NULL,
          card_last_four INT(4) UNSIGNED NOT NULL,
          card_type VARCHAR(100),
          FOREIGN KEY (wp_user_id) REFERENCES ".$users_table.",
          PRIMARY KEY  (id) 
        ) $this->db_charset_collate;";
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		dbDelta($sql);
	}





}