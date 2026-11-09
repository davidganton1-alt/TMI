<?php
/**
 * The Miner Island: Staff Verification admin model.
 * CRUD over oc_staff_auth. account_norm is a normalised copy of account used
 * for robust matching on the storefront (strips +, 00, spaces, dashes, dots,
 * parens, leading @; lowercases). Stored at write time so lookups are indexed.
 */
class ModelExtensionModuleStaffAuth extends Model {

	/* Normalisation shared with the catalog model contract. Keep in sync. */
	public function normalise($platform_key, $account) {
		$s = trim((string)$account);
		$s = strtolower($s);
		if ($platform_key === 'email') {
			// emails: keep as-is lowercased, strip surrounding spaces only
			return $s;
		}
		// handles: drop a single leading @
		if (isset($s[0]) && $s[0] === '@') { $s = substr($s, 1); }
		// phone-like: strip everything except digits IF it looks like a number
		$digits = preg_replace('/\D+/', '', $s);
		$letters = preg_replace('/[^a-z]/', '', $s);
		if ($digits !== '' && $letters === '') {
			// pure number (after removing separators): normalise leading 00 -> '' and strip +
			$digits = preg_replace('/^00/', '', $digits);
			return $digits;
		}
		// username/handle: strip spaces and common separators, keep alnum + . _ -
		$s = preg_replace('/\s+/', '', $s);
		return $s;
	}

	public function getStaff($staff_auth_id) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "staff_auth WHERE staff_auth_id = '" . (int)$staff_auth_id . "'");
		return $query->row;
	}

	public function getStaffList() {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "staff_auth ORDER BY sort_order ASC, name ASC");
		return $query->rows;
	}

	public function getTotalStaff() {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "staff_auth");
		return $query->row['total'];
	}

	public function addStaff($data) {
		$platform = (string)$data['platform_key'];
		$account  = (string)$data['account'];
		$norm     = $this->normalise($platform, $account);

		$this->db->query("INSERT INTO " . DB_PREFIX . "staff_auth SET
			name = '"          . $this->db->escape(trim((string)$data['name'])) . "',
			title = '"         . $this->db->escape(trim((string)$data['title'])) . "',
			platform_key = '"  . $this->db->escape($platform) . "',
			account = '"       . $this->db->escape(trim($account)) . "',
			account_norm = '"  . $this->db->escape($norm) . "',
			note = '"          . $this->db->escape(trim((string)$data['note'])) . "',
			status = '"        . (isset($data['status']) ? (int)$data['status'] : 0) . "',
			sort_order = '"    . (int)$data['sort_order'] . "',
			date_added = NOW(),
			date_modified = NOW()");

		return $this->db->getLastId();
	}

	public function editStaff($staff_auth_id, $data) {
		$platform = (string)$data['platform_key'];
		$account  = (string)$data['account'];
		$norm     = $this->normalise($platform, $account);

		$this->db->query("UPDATE " . DB_PREFIX . "staff_auth SET
			name = '"          . $this->db->escape(trim((string)$data['name'])) . "',
			title = '"         . $this->db->escape(trim((string)$data['title'])) . "',
			platform_key = '"  . $this->db->escape($platform) . "',
			account = '"       . $this->db->escape(trim($account)) . "',
			account_norm = '"  . $this->db->escape($norm) . "',
			note = '"          . $this->db->escape(trim((string)$data['note'])) . "',
			status = '"        . (isset($data['status']) ? (int)$data['status'] : 0) . "',
			sort_order = '"    . (int)$data['sort_order'] . "',
			date_modified = NOW()
			WHERE staff_auth_id = '" . (int)$staff_auth_id . "'");
	}

	public function deleteStaff($staff_auth_id) {
		$this->db->query("DELETE FROM " . DB_PREFIX . "staff_auth WHERE staff_auth_id = '" . (int)$staff_auth_id . "'");
	}
}
