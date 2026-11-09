<?php
class ModelExtensionModuleEmailVerification extends Model {

    public function getCustomerApproval($customer_id)
    {
        return $this->db->query("SELECT * FROM " . DB_PREFIX . "customer_approval WHERE `customer_id` = '" . (int)$customer_id . "' LIMIT 1")->row;
    }

}
