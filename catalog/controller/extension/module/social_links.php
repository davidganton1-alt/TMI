<?php
/**
 * The Miner Island — Social Links catalog controller.
 *
 * Two responsibilities:
 *   1. inject() — fires on every view-before event. Loads the active social
 *      links from the DB and injects:
 *        $args['social_locations']  — 8-key bucket array (see model)
 *        $args['current_route']     — e.g. "checkout/checkout"
 *      Every twig in the theme can now read these without controller edits.
 *
 *   2. lightbox() — small JSON endpoint that returns a single link's
 *      QR image URL + WeChat ID for the public-side lightbox to render.
 *      Used by JS that powers the QR popup on WeChat-icon click.
 *
 * Defensive: if the oc_social_link table does not exist (e.g. SQL has not
 * been run yet), inject() returns silently and the templates render
 * empty buckets (= no icons appear). Site is never broken by this hook.
 */
class ControllerExtensionModuleSocialLinks extends Controller {

	/**
	 * Event handler signature for view-before hooks:
	 *   public function inject(&$route, &$args, &$template)
	 * (the third arg is also passed by some OpenCart 3 builds — we accept it
	 * but don't use it).
	 */
	public function inject(&$route, &$args, &$template = null) {
		// Lightweight guard: only inject once per render, even if multiple
		// view-before events end up triggering us.
		if (isset($args['social_locations'])) {
			return;
		}

		try {
			$this->load->model('extension/module/social_links');
			$args['social_locations'] = $this->model_extension_module_social_links->getAllActiveByLocation();
		} catch (\Exception $e) {
			// Table missing or DB error — fail silent, frontend renders nothing.
			$args['social_locations'] = $this->emptyBuckets();
		}

		$args['current_route'] = is_string($route) ? $route : '';
	}

	/**
	 * JSON endpoint: GET /index.php?route=extension/module/social_links/lightbox&id=N
	 * Returns:
	 *   { ok: true, name, url, qr_image (resolved URL or ''), has_qr }
	 * Used by the public-side WeChat icon's onclick handler.
	 */
	public function lightbox() {
		$this->response->addHeader('Content-Type: application/json');

		$id = isset($this->request->get['id']) ? (int)$this->request->get['id'] : 0;
		if ($id <= 0) {
			$this->response->setOutput(json_encode(array('ok' => false, 'error' => 'bad_id')));
			return;
		}

		try {
			$query = $this->db->query(
				"SELECT platform_key, display_name, url, qr_image_url
				   FROM " . DB_PREFIX . "social_link
				  WHERE social_link_id = '" . $id . "' AND status = 1
				  LIMIT 1"
			);
			if (!$query->num_rows) {
				$this->response->setOutput(json_encode(array('ok' => false, 'error' => 'not_found')));
				return;
			}
			$row = $query->row;

			// Resolve qr_image_url through DIR_IMAGE (admin file manager convention).
			$qr_url = '';
			if ($row['qr_image_url']) {
				if (is_file(DIR_IMAGE . $row['qr_image_url'])) {
					// Public URL: site root + 'image/' + DB value
					$qr_url = HTTP_SERVER . 'image/' . $row['qr_image_url'];
					if (defined('HTTPS_SERVER') && isset($this->request->server['HTTPS']) && $this->request->server['HTTPS']) {
						$qr_url = HTTPS_SERVER . 'image/' . $row['qr_image_url'];
					}
				}
			}

			$display_name = $row['display_name'] !== '' ? $row['display_name'] : ucfirst($row['platform_key']);

			$this->response->setOutput(json_encode(array(
				'ok'       => true,
				'platform' => $row['platform_key'],
				'name'     => $display_name,
				'url'      => $row['url'],
				'qr_image' => $qr_url,
				'has_qr'   => ($qr_url !== '')
			)));
		} catch (\Exception $e) {
			$this->response->setOutput(json_encode(array('ok' => false, 'error' => 'server')));
		}
	}

	private function emptyBuckets() {
		return array(
			'footer' => array(), 'header_top' => array(), 'product_page' => array(),
			'cart_page' => array(), 'checkout' => array(), 'order_success' => array(),
			'contact_about' => array(), 'mobile_drawer' => array()
		);
	}
}
