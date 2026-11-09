<?php
class ModelExtensionModuleSocialLinks extends Model {
	/**
	 * The Miner Island — Social Links admin model.
	 * Phase 7.3 Step 1: full admin CRUD.
	 * Step 2 will add: getActiveByLocation() for public-side rendering.
	 *
	 * Schema (see 01_phase7_3_step1.sql):
	 *   social_link_id   PK
	 *   platform_key     UNIQUE — one row per platform (15 seeded)
	 *   display_name     optional label override
	 *   url              link or handle
	 *   qr_image_url     optional, mainly for WeChat
	 *   show_<location>  8 TINYINT visibility flags
	 *   sort_order, status
	 */

	/* ---------------------------------------------------------------------- *
	 * LIST + READ
	 * ---------------------------------------------------------------------- */

	public function getLinks($data = array()) {
		$sql = "SELECT * FROM " . DB_PREFIX . "social_link";

		$sort_data = array('platform_key', 'display_name', 'sort_order', 'status');

		if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
			$sql .= " ORDER BY `" . $data['sort'] . "`";
		} else {
			$sql .= " ORDER BY `sort_order`";
		}

		if (isset($data['order']) && strtoupper($data['order']) == 'DESC') {
			$sql .= " DESC";
		} else {
			$sql .= " ASC";
		}

		if (isset($data['start']) || isset($data['limit'])) {
			if (!isset($data['start']) || $data['start'] < 0) {
				$data['start'] = 0;
			}
			if (!isset($data['limit']) || $data['limit'] < 1) {
				$data['limit'] = 20;
			}
			$sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
		}

		$query = $this->db->query($sql);
		return $query->rows;
	}

	public function getTotalLinks() {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "social_link");
		return $query->row['total'];
	}

	public function getLink($social_link_id) {
		$query = $this->db->query(
			"SELECT * FROM " . DB_PREFIX . "social_link
			 WHERE social_link_id = '" . (int)$social_link_id . "'"
		);
		return $query->row;
	}

	/**
	 * Check if a platform_key is already used by ANOTHER row.
	 * Pass exclude_id = 0 when adding.
	 */
	public function platformExists($platform_key, $exclude_id = 0) {
		$query = $this->db->query(
			"SELECT social_link_id FROM " . DB_PREFIX . "social_link
			 WHERE platform_key = '" . $this->db->escape($platform_key) . "'
			   AND social_link_id != '" . (int)$exclude_id . "'"
		);
		return (bool)$query->num_rows;
	}

	/* ---------------------------------------------------------------------- *
	 * ADD / EDIT / DELETE
	 * ---------------------------------------------------------------------- */

	public function addLink($data) {
		$this->db->query("INSERT INTO " . DB_PREFIX . "social_link SET
			platform_key       = '" . $this->db->escape($data['platform_key']) . "',
			display_name       = '" . $this->db->escape(isset($data['display_name']) ? $data['display_name'] : '') . "',
			url                = '" . $this->db->escape(isset($data['url']) ? $data['url'] : '') . "',
			qr_image_url       = '" . $this->db->escape(isset($data['qr_image_url']) ? $data['qr_image_url'] : '') . "',
			show_footer        = '" . (int)(isset($data['show_footer'])        ? $data['show_footer']        : 0) . "',
			show_header_top    = '" . (int)(isset($data['show_header_top'])    ? $data['show_header_top']    : 0) . "',
			show_product_page  = '" . (int)(isset($data['show_product_page'])  ? $data['show_product_page']  : 0) . "',
			show_cart_page     = '" . (int)(isset($data['show_cart_page'])     ? $data['show_cart_page']     : 0) . "',
			show_checkout      = '" . (int)(isset($data['show_checkout'])      ? $data['show_checkout']      : 0) . "',
			show_order_success = '" . (int)(isset($data['show_order_success']) ? $data['show_order_success'] : 0) . "',
			show_contact_about = '" . (int)(isset($data['show_contact_about']) ? $data['show_contact_about'] : 0) . "',
			show_mobile_drawer = '" . (int)(isset($data['show_mobile_drawer']) ? $data['show_mobile_drawer'] : 0) . "',
			sort_order         = '" . (int)(isset($data['sort_order']) ? $data['sort_order'] : 0) . "',
			status             = '" . (int)(isset($data['status']) ? $data['status'] : 0) . "'");

		return $this->db->getLastId();
	}

	public function editLink($social_link_id, $data) {
		$social_link_id = (int)$social_link_id;

		$this->db->query("UPDATE " . DB_PREFIX . "social_link SET
			platform_key       = '" . $this->db->escape($data['platform_key']) . "',
			display_name       = '" . $this->db->escape(isset($data['display_name']) ? $data['display_name'] : '') . "',
			url                = '" . $this->db->escape(isset($data['url']) ? $data['url'] : '') . "',
			qr_image_url       = '" . $this->db->escape(isset($data['qr_image_url']) ? $data['qr_image_url'] : '') . "',
			show_footer        = '" . (int)(isset($data['show_footer'])        ? $data['show_footer']        : 0) . "',
			show_header_top    = '" . (int)(isset($data['show_header_top'])    ? $data['show_header_top']    : 0) . "',
			show_product_page  = '" . (int)(isset($data['show_product_page'])  ? $data['show_product_page']  : 0) . "',
			show_cart_page     = '" . (int)(isset($data['show_cart_page'])     ? $data['show_cart_page']     : 0) . "',
			show_checkout      = '" . (int)(isset($data['show_checkout'])      ? $data['show_checkout']      : 0) . "',
			show_order_success = '" . (int)(isset($data['show_order_success']) ? $data['show_order_success'] : 0) . "',
			show_contact_about = '" . (int)(isset($data['show_contact_about']) ? $data['show_contact_about'] : 0) . "',
			show_mobile_drawer = '" . (int)(isset($data['show_mobile_drawer']) ? $data['show_mobile_drawer'] : 0) . "',
			sort_order         = '" . (int)(isset($data['sort_order']) ? $data['sort_order'] : 0) . "',
			status             = '" . (int)(isset($data['status']) ? $data['status'] : 0) . "'
			WHERE social_link_id = '" . $social_link_id . "'");
	}

	public function deleteLink($social_link_id) {
		$social_link_id = (int)$social_link_id;
		if ($social_link_id <= 0) {
			return;
		}
		$this->db->query("DELETE FROM " . DB_PREFIX . "social_link WHERE social_link_id = '" . $social_link_id . "'");
	}
}
