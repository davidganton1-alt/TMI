<?php
class ModelExtensionModuleSocialLinks extends Model {
	/**
	 * Catalog-side reader for the oc_social_link table.
	 * Called by the event hook (controller/extension/module/social_links->inject)
	 * once per page render. Results are cached in the registry to avoid a second
	 * query if multiple twigs reference the data.
	 */

	private static $cache = null;

	/**
	 * Fetch every status=1 row, ordered by sort_order ASC. Returns an
	 * associative array keyed by location_key — each value is an array of
	 * link rows visible at that location.
	 *
	 * Output shape:
	 *   array(
	 *     'footer'        => [ {row}, {row}, ... ],
	 *     'header_top'    => [ {row}, ... ],
	 *     'product_page'  => [ ... ],
	 *     ...8 keys total
	 *   )
	 */
	public function getAllActiveByLocation() {
		if (self::$cache !== null) {
			return self::$cache;
		}

		$buckets = array(
			'footer'        => array(),
			'header_top'    => array(),
			'product_page'  => array(),
			'cart_page'     => array(),
			'checkout'      => array(),
			'order_success' => array(),
			'contact_about' => array(),
			'mobile_drawer' => array(),
		);

		// One query for the whole page render.
		$query = $this->db->query(
			"SELECT social_link_id, platform_key, display_name, url, qr_image_url,
			        show_footer, show_header_top, show_product_page, show_cart_page,
			        show_checkout, show_order_success, show_contact_about, show_mobile_drawer,
			        sort_order
			   FROM " . DB_PREFIX . "social_link
			  WHERE status = 1
			  ORDER BY sort_order ASC, platform_key ASC"
		);

		foreach ($query->rows as $row) {
			$display_name = $row['display_name'] !== '' ? $row['display_name'] : ucfirst($row['platform_key']);

			$link = array(
				'id'           => (int)$row['social_link_id'],
				'platform'     => $row['platform_key'],
				'name'         => $display_name,
				'url'          => $row['url'],
				'qr_image'     => $row['qr_image_url'],
				'has_qr'       => ($row['qr_image_url'] !== ''),
			);

			if ($row['show_footer'])        { $buckets['footer'][]        = $link; }
			if ($row['show_header_top'])    { $buckets['header_top'][]    = $link; }
			if ($row['show_product_page']) { $buckets['product_page'][]  = $link; }
			if ($row['show_cart_page'])     { $buckets['cart_page'][]     = $link; }
			if ($row['show_checkout'])      { $buckets['checkout'][]      = $link; }
			if ($row['show_order_success']) { $buckets['order_success'][] = $link; }
			if ($row['show_contact_about']) { $buckets['contact_about'][] = $link; }
			if ($row['show_mobile_drawer']) { $buckets['mobile_drawer'][] = $link; }
		}

		self::$cache = $buckets;
		return $buckets;
	}
}
