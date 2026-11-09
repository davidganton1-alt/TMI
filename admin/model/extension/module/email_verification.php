<?php
class ModelExtensionModuleEmailVerification extends Model {
	public function install() {
		$chk = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "customer` WHERE `Field` = 'email_old'");

		if (!$chk->num_rows) {
			$this->db->query("ALTER TABLE `" . DB_PREFIX . "customer` ADD `email_old` VARCHAR(96) NOT NULL AFTER `email`");
		}

		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "customer_verification` ( 
			customer_id int(11) NOT NULL, 
			code varchar(32) NOT NULL, 
			UNIQUE(`customer_id`)
		)");
	}
}