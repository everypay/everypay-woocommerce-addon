<?php

class WC_Everypay_Actions {

    private string $tokenization_table_name;
    private string $db_charset_collate;

    public function __construct()
    {
        global $wpdb;
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        $this->tokenization_table_name = $wpdb->prefix . "everypay_tokenization";
        $this->db_charset_collate = $wpdb->get_charset_collate();
    }

    public function run_activation_actions()
    {
        $this->create_tokenization_table();
    }

    public function run_deactivation_actions()
    {
        $this->drop_tokenization_table();
    }

    public function create_tokenization_table()
    {

        $sql = "CREATE TABLE IF NOT EXISTS $this->tokenization_table_name (
          id INT NOT NULL AUTO_INCREMENT,
          wp_user_id bigint(20) UNSIGNED,
          friendly_name VARCHAR(100) NOT NULL,
          customer_token VARCHAR(100) NOT NULL,
          card_token VARCHAR(100) NOT NULL,
          card_expiration_month INT UNSIGNED NOT NULL,
          card_expiration_year INT UNSIGNED NOT NULL,
          card_last_four INT(4) UNSIGNED NOT NULL,
          card_type VARCHAR(100),
          FOREIGN KEY (wp_user_id) REFERENCES wp_users(ID),
          PRIMARY KEY  (id) 
        ) $this->db_charset_collate;";

        dbDelta($sql);
    }

    public function drop_tokenization_table()
    {
        global $wpdb;
        $sql = "DROP TABLE IF EXISTS ".$this->tokenization_table_name;
        $wpdb->query($sql);
    }



}