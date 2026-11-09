<?php
/**
 * The Miner Island: Google XML Sitemap (canonical, de-duplicated).
 *
 * Replaces stock OpenCart behaviour, which emitted every product up to ~3.5x
 * (standalone + once per category path + once per manufacturer). Search engines
 * should receive ONE canonical URL per entity. This version:
 *   - emits each product, category, manufacturer, information page, and factory
 *     post exactly once, at its canonical SEO URL (product_id / category_id /
 *     etc.), matching the rel=canonical the product page already declares;
 *   - includes the homepage and custom routes (staff verification, factory
 *     updates list);
 *   - keeps a product in the sitemap even if it has no image (image block is
 *     conditional, so imageless products are still indexable);
 *   - XML-escapes every value.
 *
 * Pretty URLs resolve via oc_seo_url; url->link() returns the SEO URL when SEO
 * is enabled, so <loc> values are the canonical slugs (no query-string dupes).
 */
class ControllerExtensionFeedGoogleSitemap extends Controller {
	public function index() {
		if (!$this->config->get('feed_google_sitemap_status')) {
			return;
		}

		$output  = '<?xml version="1.0" encoding="UTF-8"?>';
		$output .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">';

		// ── Homepage ──────────────────────────────────────────────────────────
		// Emit the canonical root (same config_url the homepage declares as its
		// rel=canonical), NOT url->link('common/home'), which resolves through
		// oc_seo_url to the /home alias that Google discards as a duplicate.
		// config_url is set unconditionally in startup/startup.php, but fall
		// back to the old behaviour if it were ever empty.
		$home = $this->config->get('config_url');

		if (!$home) {
			$home = $this->url->link('common/home');
		}

		$output .= '<url>';
		$output .= '<loc>' . $this->xml($home) . '</loc>';
		$output .= '<changefreq>daily</changefreq>';
		$output .= '<priority>1.0</priority>';
		$output .= '</url>';

		$this->load->model('catalog/product');
		$this->load->model('catalog/category');
		$this->load->model('catalog/manufacturer');
		$this->load->model('catalog/information');
		$this->load->model('tool/image');

		$popup_w = $this->config->get('theme_' . $this->config->get('config_theme') . '_image_popup_width');
		$popup_h = $this->config->get('theme_' . $this->config->get('config_theme') . '_image_popup_height');

		// ── Products (ONE canonical URL each) ──────────────────────────────────
		$products = $this->model_catalog_product->getProducts();

		foreach ($products as $product) {
			$output .= '<url>';
			$output .= '<loc>' . $this->xml($this->url->link('product/product', 'product_id=' . $product['product_id'])) . '</loc>';
			$output .= '<changefreq>weekly</changefreq>';
			if (!empty($product['date_modified'])) {
				$output .= '<lastmod>' . date('Y-m-d\\TH:i:sP', strtotime($product['date_modified'])) . '</lastmod>';
			}
			$output .= '<priority>0.9</priority>';

			if (!empty($product['image']) && $popup_w && $popup_h) {
				$output .= '<image:image>';
				$output .= '<image:loc>' . $this->xml($this->model_tool_image->resize($product['image'], $popup_w, $popup_h)) . '</image:loc>';
				$output .= '<image:caption>' . $this->xml($product['name']) . '</image:caption>';
				$output .= '<image:title>' . $this->xml($product['name']) . '</image:title>';
				$output .= '</image:image>';
			}

			$output .= '</url>';
		}

		// ── Categories (canonical, recursive) ─────────────────────────────────
		$output .= $this->getCategories(0);

		// ── Manufacturers (info page once each) ────────────────────────────────
		$manufacturers = $this->model_catalog_manufacturer->getManufacturers();

		foreach ($manufacturers as $manufacturer) {
			$output .= '<url>';
			$output .= '<loc>' . $this->xml($this->url->link('product/manufacturer/info', 'manufacturer_id=' . $manufacturer['manufacturer_id'])) . '</loc>';
			$output .= '<changefreq>weekly</changefreq>';
			$output .= '<priority>0.6</priority>';
			$output .= '</url>';
		}

		// ── Information pages ──────────────────────────────────────────────────
		$informations = $this->model_catalog_information->getInformations();

		foreach ($informations as $information) {
			$output .= '<url>';
			$output .= '<loc>' . $this->xml($this->url->link('information/information', 'information_id=' . $information['information_id'])) . '</loc>';
			$output .= '<changefreq>monthly</changefreq>';
			$output .= '<priority>0.4</priority>';
			$output .= '</url>';
		}

		// ── Custom routes (only if a SEO alias exists, so we never emit an ugly
		//    index.php URL). Staff verification + factory updates list. ─────────
		foreach (array('extension/module/staff_auth', 'extension/module/factory_updates') as $route) {
			$loc = $this->url->link($route);
			if (strpos($loc, 'route=') === false) { // SEO alias resolved
				$output .= '<url>';
				$output .= '<loc>' . $this->xml($loc) . '</loc>';
				$output .= '<changefreq>weekly</changefreq>';
				$output .= '<priority>0.5</priority>';
				$output .= '</url>';
			}
		}

		// ── Factory posts (blog), if the catalog model exists ──────────────────
		if (is_file(DIR_APPLICATION . 'model/extension/module/factory_updates.php')) {
			$this->load->model('extension/module/factory_updates');
			if (method_exists($this->model_extension_module_factory_updates, 'getPosts')) {
				// catalog getPosts($offset, $limit, $category): pull a large page
				// so all published posts are covered.
				$posts = $this->model_extension_module_factory_updates->getPosts(0, 1000, '');
				foreach ($posts as $post) {
					if (empty($post['factory_post_id'])) { continue; }
					$loc = $this->url->link('extension/module/factory_updates', 'factory_post_id=' . $post['factory_post_id']);
					$output .= '<url>';
					$output .= '<loc>' . $this->xml($loc) . '</loc>';
					$output .= '<changefreq>monthly</changefreq>';
					$output .= '<priority>0.4</priority>';
					$output .= '</url>';
				}
			}
		}

		$output .= '</urlset>';

		$this->response->addHeader('Content-Type: application/xml; charset=UTF-8');
		$this->response->setOutput($output);
	}

	protected function getCategories($parent_id) {
		$output = '';
		$results = $this->model_catalog_category->getCategories($parent_id);

		foreach ($results as $result) {
			// Category links MUST use the 'path' key: the SEO rewriter resolves
			// each underscore-separated path segment against 'category_id=N' in
			// oc_seo_url to build the slug. Passing category_id directly would
			// not trigger that branch and would emit an ugly index.php URL.
			$output .= '<url>';
			$output .= '<loc>' . $this->xml($this->url->link('product/category', 'path=' . $result['category_id'])) . '</loc>';
			$output .= '<changefreq>weekly</changefreq>';
			$output .= '<priority>0.7</priority>';
			$output .= '</url>';

			$output .= $this->getCategories($result['category_id']);
		}

		return $output;
	}

	/**
	 * XML-escape a value for safe insertion into the sitemap.
	 */
	protected function xml($value) {
		return htmlspecialchars((string)$value, ENT_QUOTES | ENT_XML1, 'UTF-8');
	}
}
