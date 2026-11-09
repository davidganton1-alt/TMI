<?php
class ControllerStartupSeoUrl extends Controller {
	public function index() {
		// Add rewrite to url class
		if ($this->config->get('config_seo_url')) {
			$this->url->addRewrite($this);
		}

		// Decode URL
		if (isset($this->request->get['_route_'])) {
			$parts = explode('/', $this->request->get['_route_']);

			// remove any empty arrays from trailing
			if (utf8_strlen(end($parts)) == 0) {
				array_pop($parts);
			}

			foreach ($parts as $part) {
				$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "seo_url WHERE keyword = '" . $this->db->escape($part) . "' AND store_id = '" . (int)$this->config->get('config_store_id') . "'");

				if ($query->num_rows) {
					$url = explode('=', $query->row['query']);

					if ($url[0] == 'product_id') {
						$this->request->get['product_id'] = $url[1];
					}

					if ($url[0] == 'category_id') {
						if (!isset($this->request->get['path'])) {
							$this->request->get['path'] = $url[1];
						} else {
							$this->request->get['path'] .= '_' . $url[1];
						}
					}

					if ($url[0] == 'manufacturer_id') {
						$this->request->get['manufacturer_id'] = $url[1];
					}

					if ($url[0] == 'information_id') {
						$this->request->get['information_id'] = $url[1];
					}

					// ─── MinerIsland Phase 7 addition ─────────────────────────
					// Factory Updates blog posts. Slug resolves to a numeric
					// post id which the controller reads to render the single
					// post view. Without this whitelist entry, the line below
					// (line 44 in original) would treat the row as a route
					// override and overwrite the listing route.
					if ($url[0] == 'factory_post_id') {
						$this->request->get['factory_post_id'] = $url[1];
					}
					// ──────────────────────────────────────────────────────────

					if ($query->row['query'] && $url[0] != 'information_id' && $url[0] != 'manufacturer_id' && $url[0] != 'category_id' && $url[0] != 'product_id' && $url[0] != 'factory_post_id') {
						$this->request->get['route'] = $query->row['query'];
					}
				} else {
					$this->request->get['route'] = 'error/not_found';

					break;
				}
			}

			if (!isset($this->request->get['route'])) {
				if (isset($this->request->get['product_id'])) {
					$this->request->get['route'] = 'product/product';
				} elseif (isset($this->request->get['path'])) {
					$this->request->get['route'] = 'product/category';
				} elseif (isset($this->request->get['manufacturer_id'])) {
					$this->request->get['route'] = 'product/manufacturer/info';
				} elseif (isset($this->request->get['information_id'])) {
					$this->request->get['route'] = 'information/information';
				}
				// Note: factory_post_id does NOT auto-set a route here — the
				// listing slug 'factory-updates' (which resolves to the route
				// 'extension/module/factory_updates') is always present first
				// in the URL path, so by the time the loop processes the post
				// slug, the route is already set correctly by the line above
				// at the listing slug iteration. The controller then reads
				// factory_post_id to decide list vs single view.
			}
		}
	}

	public function rewrite($link) {
		$url_info = parse_url(str_replace('&amp;', '&', $link));

		$url = '';

		$data = array();

		parse_str($url_info['query'], $data);

		foreach ($data as $key => $value) {
			if (isset($data['route'])) {
				if (($data['route'] == 'product/product' && $key == 'product_id') || (($data['route'] == 'product/manufacturer/info' || $data['route'] == 'product/product') && $key == 'manufacturer_id') || ($data['route'] == 'information/information' && $key == 'information_id')) {
					$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "seo_url WHERE `query` = '" . $this->db->escape($key . '=' . (int)$value) . "' AND store_id = '" . (int)$this->config->get('config_store_id') . "' AND language_id = '" . (int)$this->config->get('config_language_id') . "'");

					if ($query->num_rows && $query->row['keyword']) {
						$url .= '/' . $query->row['keyword'];

						unset($data[$key]);
					}
				} elseif ($key == 'path') {
					$categories = explode('_', $value);

					foreach ($categories as $category) {
						$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "seo_url WHERE `query` = 'category_id=" . (int)$category . "' AND store_id = '" . (int)$this->config->get('config_store_id') . "' AND language_id = '" . (int)$this->config->get('config_language_id') . "'");

						if ($query->num_rows && $query->row['keyword']) {
							$url .= '/' . $query->row['keyword'];
						} else {
							$url = '';

							break;
						}
					}

					unset($data[$key]);
				}
			}
		}

		// ─────────────────────────────────────────────────────────────────────
		// MinerIsland extension: arbitrary-route aliasing.
		// If we got here with no $url match AND the route has no _id params,
		// try to find a SEO keyword whose `query` column equals the full route
		// string (e.g. 'extension/payment/plisio/invoice' -> 'secure-payment').
		// This lets us pretty-fy routes like the Plisio invoice page without
		// touching the controller. Safe: only fires when the standard rewriter
		// produced nothing, so it never collides with product/category URLs.
		//
		// Phase 7 addition: also handles 'extension/module/factory_updates'
		// resolving to 'factory-updates', AND appends factory_post_id slug
		// after the listing slug when the link is to a single post (so a
		// link like ?route=extension/module/factory_updates&factory_post_id=3
		// rewrites to /factory-updates/kaspa-emission-cliff-july-2026).
		// ─────────────────────────────────────────────────────────────────────
		if (!$url && isset($data['route'])) {
			$query = $this->db->query("SELECT keyword FROM " . DB_PREFIX . "seo_url WHERE `query` = '" . $this->db->escape($data['route']) . "' AND store_id = '" . (int)$this->config->get('config_store_id') . "' AND language_id = '" . (int)$this->config->get('config_language_id') . "'");

			if ($query->num_rows && $query->row['keyword']) {
				$url = '/' . $query->row['keyword'];

				// Phase 7: append factory post slug if present
				if (isset($data['factory_post_id'])) {
					$post_q = $this->db->query("SELECT keyword FROM " . DB_PREFIX . "seo_url WHERE `query` = 'factory_post_id=" . (int)$data['factory_post_id'] . "' AND store_id = '" . (int)$this->config->get('config_store_id') . "' AND language_id = '" . (int)$this->config->get('config_language_id') . "'");
					if ($post_q->num_rows && $post_q->row['keyword']) {
						$url .= '/' . $post_q->row['keyword'];
						unset($data['factory_post_id']);
					}
				}
			}
		}
		// ─────────────────────────────────────────────────────────────────────

		if ($url) {
			unset($data['route']);

			$query = '';

			if ($data) {
				foreach ($data as $key => $value) {
					$query .= '&' . rawurlencode((string)$key) . '=' . rawurlencode((is_array($value) ? http_build_query($value) : (string)$value));
				}

				if ($query) {
					$query = '?' . str_replace('&', '&amp;', trim($query, '&'));
				}
			}

			return $url_info['scheme'] . '://' . $url_info['host'] . (isset($url_info['port']) ? ':' . $url_info['port'] : '') . str_replace('/index.php', '', $url_info['path']) . $url . $query;
		} else {
			return $link;
		}
	}
}
