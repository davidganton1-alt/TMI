<?php
/**
 * The Miner Island: Staff Verification storefront model.
 * verify() normalises the submitted account the same way the admin model
 * stored account_norm, then looks for an ENABLED row on the same platform.
 * Normalisation kept in sync with admin/model/.../staff_auth.php::normalise().
 */
class ModelExtensionModuleStaffAuth extends Model {

	public function normalise($platform_key, $account) {
		$s = trim((string)$account);
		$s = strtolower($s);
		if ($platform_key === 'email') {
			return $s;
		}
		if (isset($s[0]) && $s[0] === '@') { $s = substr($s, 1); }
		$digits  = preg_replace('/\D+/', '', $s);
		$letters = preg_replace('/[^a-z]/', '', $s);
		if ($digits !== '' && $letters === '') {
			$digits = preg_replace('/^00/', '', $digits);
			return $digits;
		}
		$s = preg_replace('/\s+/', '', $s);
		return $s;
	}

	public function verify($platform_key, $account) {
		$norm = $this->normalise($platform_key, $account);
		if ($norm === '') { return false; }

		$query = $this->db->query("SELECT name, title, account, platform_key
			FROM " . DB_PREFIX . "staff_auth
			WHERE platform_key = '" . $this->db->escape($platform_key) . "'
			  AND account_norm = '" . $this->db->escape($norm) . "'
			  AND status = '1'
			LIMIT 1");

		return $query->num_rows ? $query->row : false;
	}
}
