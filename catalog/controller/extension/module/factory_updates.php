<?php
/**
 * The Miner Island: Factory Updates blog
 *
 * Catalog routes:
 *   index.php?route=extension/module/factory_updates
 *     → listing page (paginated grid of all published posts)
 *   index.php?route=extension/module/factory_updates&factory_post_id=N
 *     → single post detail page
 *
 * Pretty URLs (resolved via patched seo_url.php in Phase 7 Step 1):
 *   /factory-updates                          → listing
 *   /factory-updates/<slug>                   → single post
 *
 * The seo_url.php whitelist for factory_post_id ensures the slug part of a
 * 2-segment URL sets the post id WITHOUT overwriting the route set by the
 * first segment ('factory-updates'). See seo_url.php Phase 7 addition for
 * the exact logic.
 *
 * In Step 2 we ship only the listing path (renders factory_updates_list.twig).
 * Single-post rendering is wired here but its template (factory_updates_post.twig)
 * lands in Step 3, until then, single-post URLs render a stub message.
 */
class ControllerExtensionModuleFactoryUpdates extends Controller {

	public function index() {
		// Route by presence of factory_post_id: single post if set, listing otherwise.
		if (isset($this->request->get['factory_post_id']) && (int)$this->request->get['factory_post_id'] > 0) {
			$this->singlePost((int)$this->request->get['factory_post_id']);
		} else {
			$this->listing();
		}
	}

	// ─── LISTING (paginated grid) ────────────────────────────────────────
	private function listing() {
		$this->load->model('extension/module/factory_updates');

		// Active category filter (from ?category=Slug-or-Label)
		$active_category = isset($this->request->get['category']) ? trim((string)$this->request->get['category']) : '';

		// Pagination
		$page = isset($this->request->get['page']) ? max(1, (int)$this->request->get['page']) : 1;
		$per_page = 12;
		$offset = ($page - 1) * $per_page;

		$total = $this->model_extension_module_factory_updates->getTotalPosts($active_category);
		$posts = $this->model_extension_module_factory_updates->getPosts($offset, $per_page, $active_category);

		// Build display data for each post
		$data['posts'] = array();
		foreach ($posts as $row) {
			$data['posts'][] = array(
				'title'      => $row['title'],
				'excerpt'    => $row['excerpt'],
				'hero_image' => $this->resolveImage($row['hero_image_url']),
				'date'       => date('F j, Y', strtotime($row['published_at'])),
				'author'     => $row['author_name'],
				'category'   => isset($row['category']) ? $row['category'] : '',
				'href'       => $this->url->link('extension/module/factory_updates', 'factory_post_id=' . (int)$row['factory_post_id']),
			);
		}

		// Category filter bar
		$base = $this->url->link('extension/module/factory_updates');
		$data['active_category'] = $active_category;
		$data['categories'] = array(
			array('label' => 'All Posts', 'value' => '', 'href' => $base, 'active' => ($active_category === '')),
		);
		foreach ($this->model_extension_module_factory_updates->getCategories() as $c) {
			$data['categories'][] = array(
				'label'  => $c['category'],
				'value'  => $c['category'],
				'href'   => $this->url->link('extension/module/factory_updates', 'category=' . rawurlencode($c['category'])),
				'active' => ($active_category === $c['category']),
			);
		}

		// SEO meta (no em or en dashes)
		$this->document->setTitle('Blog Updates: Notes from Our Hong Kong Warehouse | The Miner Island');
		$this->document->setDescription('Weekly Blog updates from The Miner Island Hong Kong warehouse. ASIC mining hardware QA, network analysis, dispatch walkthroughs, and real operator stories.');

		// Breadcrumbs
		$data['breadcrumbs'] = array(
			array(
				'text' => 'Home',
				'href' => $this->url->link('common/home'),
			),
			array(
				'text' => 'Blog Updates',
				'href' => $this->url->link('extension/module/factory_updates'),
			),
		);

		// Page heading + intro copy (no em or en dashes)
		$data['heading_title'] = 'Blog Updates: Weekly Notes from Our Hong Kong Warehouse';
		$data['intro_text'] = 'Most ASIC hardware vendors tell you nothing about how they operate. We do the opposite. This is where we publish what is actually happening on the floor: how we test units before dispatch, what network conditions mean for your hardware decisions, real operator stories from customers who have scaled with us, and honest breakdowns of the shipping and customs process. Updated weekly. No marketing fluff, no sponsored content, no affiliate agenda.';

		// "What We Cover" section data
		$data['cover_sections'] = array(
			array('title' => 'Warehouse and QA',  'body' => 'Behind-the-scenes documentation of how units are tested, verified, and prepared for dispatch at our Hong Kong warehouse. What we check, what we reject, and why it matters for the hardware that lands at your facility.'),
			array('title' => 'Network Analysis',  'body' => 'Data-driven breakdowns of mining network conditions: difficulty adjustments, emission schedule changes, efficiency tier shifts, and what current market conditions mean for hardware purchasing decisions.'),
			array('title' => 'Customer Stories',  'body' => 'Real accounts of how operators have sourced, scaled, and managed their fleets using hardware from The Miner Island. Composite stories drawn from actual buyer experiences with real numbers where possible.'),
			array('title' => 'Shipping and DDP',  'body' => 'Plain-language explanations of the DDP shipping process, customs procedures, transit timelines, and what happens when something goes wrong in transit.'),
			array('title' => 'Hardware Guides',   'body' => 'Platform-specific guides covering algorithm selection, cooling requirements, power specifications, and deployment considerations for the ASIC models we stock.'),
		);

		// Contact + related links for the footer sections
		$data['whatsapp_url'] = 'https://wa.me/16173008178';
		$data['email_url']    = 'mailto:sales@theminerisland.com';
		$data['related_links'] = array(
			array('text' => 'Our Team',            'href' => $this->url->link('information/information', 'information_id=13')),
			array('text' => 'About Us',            'href' => $this->url->link('information/information', 'information_id=4')),
			array('text' => 'Business Credentials','href' => $this->url->link('information/information', 'information_id=12')),
			array('text' => 'Shop All ASIC Miners','href' => $this->url->link('product/category', 'path=0')),
		);

		// Pagination control (preserve active category across pages)
		$cat_qs = $active_category !== '' ? '&category=' . rawurlencode($active_category) : '';
		$data['page'] = $page;
		$data['total_pages'] = (int)ceil($total / $per_page);
		$data['total'] = $total;
		$data['prev_href'] = $page > 1 ? $this->url->link('extension/module/factory_updates', 'page=' . ($page - 1) . $cat_qs) : '';
		$data['next_href'] = $page < $data['total_pages'] ? $this->url->link('extension/module/factory_updates', 'page=' . ($page + 1) . $cat_qs) : '';

		// JSON-LD for SEO (Blog schema)
		$data['jsonld'] = $this->buildBlogJsonLd($data['posts']);

		// Standard OpenCart common-region loads
		$data['column_left']    = $this->load->controller('common/column_left');
		$data['column_right']   = $this->load->controller('common/column_right');
		$data['content_top']    = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer']         = $this->load->controller('common/footer');
		$data['header']         = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('extension/module/factory_updates_list', $data));
	}

	// ─── SINGLE POST (full body view) ────────────────────────────────────
	private function singlePost($post_id) {
		$this->load->model('extension/module/factory_updates');

		$post = $this->model_extension_module_factory_updates->getPost($post_id);

		if (!$post) {
			// Post not found OR status=0, render 404
			$data['breadcrumbs'] = array(
				array('text' => 'Home', 'href' => $this->url->link('common/home')),
				array('text' => 'Blog Updates', 'href' => $this->url->link('extension/module/factory_updates')),
				array('text' => 'Not found', 'href' => ''),
			);
			$data['header'] = $this->load->controller('common/header');
			$data['footer'] = $this->load->controller('common/footer');
			$data['column_left']    = $this->load->controller('common/column_left');
			$data['column_right']   = $this->load->controller('common/column_right');
			$data['content_top']    = $this->load->controller('common/content_top');
			$data['content_bottom'] = $this->load->controller('common/content_bottom');
			$data['heading_title']  = 'Post not found';
			$data['text_error']     = 'The post you requested isn\'t published or no longer exists.';
			$data['continue']       = $this->url->link('extension/module/factory_updates');
			$data['button_continue'] = 'Back to Blog Updates';
			$this->response->addHeader($this->request->server['SERVER_PROTOCOL'] . ' 404 Not Found');
			$this->response->setOutput($this->load->view('error/not_found', $data));
			return;
		}

		// SEO meta
		$this->document->setTitle($post['meta_title'] ?: ($post['title'] . ' | The Miner Island'));
		$this->document->setDescription($post['meta_description'] ?: substr(strip_tags($post['excerpt']), 0, 160));

		// Display data
		$data['post'] = array(
			'title'      => $post['title'],
			'body_html'  => html_entity_decode($post['body_html'], ENT_QUOTES, 'UTF-8'),
			'hero_image' => $this->resolveImage($post['hero_image_url']),
			'date'       => date('F j, Y', strtotime($post['published_at'])),
			'date_iso'   => date('c', strtotime($post['published_at'])),
			'author'     => $post['author_name'],
			'video_embed'=> $post['video_embed'],
		);

		// Prev / next navigation
		$prev = $this->model_extension_module_factory_updates->getPrevPost($post_id, $post['published_at']);
		$next = $this->model_extension_module_factory_updates->getNextPost($post_id, $post['published_at']);
		$data['prev_post'] = $prev ? array(
			'title' => $prev['title'],
			'href'  => $this->url->link('extension/module/factory_updates', 'factory_post_id=' . (int)$prev['factory_post_id']),
		) : null;
		$data['next_post'] = $next ? array(
			'title' => $next['title'],
			'href'  => $this->url->link('extension/module/factory_updates', 'factory_post_id=' . (int)$next['factory_post_id']),
		) : null;

		// Breadcrumbs
		$data['breadcrumbs'] = array(
			array('text' => 'Home', 'href' => $this->url->link('common/home')),
			array('text' => 'Blog Updates', 'href' => $this->url->link('extension/module/factory_updates')),
			array('text' => $post['title'], 'href' => $this->url->link('extension/module/factory_updates', 'factory_post_id=' . (int)$post_id)),
		);

		// JSON-LD BlogPosting for SEO
		$data['jsonld'] = $this->buildPostJsonLd($post);

		// Back-to-list URL
		$data['list_href'] = $this->url->link('extension/module/factory_updates');

		// Share URL: this specific post's pretty URL (used by share buttons)
		$data['share_url'] = $this->url->link('extension/module/factory_updates', 'factory_post_id=' . (int)$post_id);

		// Common regions
		$data['column_left']    = $this->load->controller('common/column_left');
		$data['column_right']   = $this->load->controller('common/column_right');
		$data['content_top']    = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer']         = $this->load->controller('common/footer');
		$data['header']         = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('extension/module/factory_updates_post', $data));
	}

	// ─── HELPERS ─────────────────────────────────────────────────────────

	/**
	 * Resolve a hero image URL. If the file doesn't exist on disk, returns
	 * a neutral SVG placeholder so the listing/post pages don't render broken
	 * images while the user uploads photos.
	 *
	 * hero_image_url can arrive in two formats:
	 *   (A) Admin file manager (OpenCart standard): path is relative to
	 *       DIR_IMAGE, e.g. 'catalog/blog/foo.jpg'. The corresponding URL
	 *       served to the browser is 'image/catalog/blog/foo.jpg'.
	 *   (B) Phase 7 seed convention (legacy): path is relative to the web
	 *       root, e.g. 'catalog/view/theme/minerisland/image/blog/foo.jpg'.
	 *       URL served to the browser equals the stored path.
	 *
	 * Try format (A) first because it's what the admin CRUD writes. Fall
	 * back to (B) for any seed rows still using the legacy format. Anything
	 * unresolvable returns the SVG placeholder.
	 */
	private function resolveImage($path) {
		if ($path) {
			// (A) DIR_IMAGE-relative (admin file manager output)
			if (is_file(DIR_IMAGE . $path)) {
				return 'image/' . $path;
			}
			// (B) web-root-relative (Phase 7 seed convention)
			$abs = realpath(DIR_APPLICATION . '../' . $path);
			if ($abs !== false && is_file($abs)) {
				return $path;
			}
		}
		// Neutral SVG placeholder, same aspect as our hero (16:9 enough)
		return 'data:image/svg+xml;utf8,' . rawurlencode(
			'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 450" preserveAspectRatio="xMidYMid slice">' .
			'<rect width="800" height="450" fill="#F1F5F9"/>' .
			'<rect x="0" y="0" width="800" height="450" fill="url(#g)"/>' .
			'<defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1">' .
			'<stop offset="0" stop-color="#E2E8F0" stop-opacity="0.6"/>' .
			'<stop offset="1" stop-color="#CBD5E1" stop-opacity="0.6"/>' .
			'</linearGradient></defs>' .
			'<g fill="#94A3B8" font-family="system-ui" font-size="22" text-anchor="middle">' .
			'<text x="400" y="220">Blog Updates</text>' .
			'<text x="400" y="252" font-size="14" fill="#64748B">photo coming soon</text>' .
			'</g></svg>'
		);
	}

	/**
	 * Build JSON-LD Blog schema for the listing page (SEO).
	 */
	private function buildBlogJsonLd($posts) {
		$items = array();
		foreach ($posts as $i => $p) {
			$items[] = array(
				'@type'    => 'ListItem',
				'position' => $i + 1,
				'url'      => $p['href'],
				'name'     => $p['title'],
			);
		}
		$ld = array(
			'@context'    => 'https://schema.org',
			'@type'       => 'Blog',
			'name'        => 'Blog Updates: The Miner Island',
			'description' => 'Weekly Blog updates from our Hong Kong warehouse and the realities of running an ASIC mining business.',
			'url'         => $this->url->link('extension/module/factory_updates'),
			'publisher'   => array(
				'@type' => 'Organization',
				'name'  => 'The Miner Island Company LLC',
				'url'   => $this->url->link('common/home'),
			),
			'mainEntity'  => array(
				'@type'           => 'ItemList',
				'itemListElement' => $items,
			),
		);
		return json_encode($ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	}

	/**
	 * Build JSON-LD BlogPosting schema for a single post (SEO).
	 */
	private function buildPostJsonLd($post) {
		// B7 (Session 15): absolute base for image + publisher logo URLs.
		$base = defined('HTTPS_SERVER') ? HTTPS_SERVER : (defined('HTTP_SERVER') ? HTTP_SERVER : '');

		$ld = array(
			'@context'         => 'https://schema.org',
			'@type'            => 'BlogPosting',
			'headline'         => $post['title'],
			'description'      => $post['meta_description'] ?: substr(strip_tags($post['excerpt']), 0, 160),
			'datePublished'    => date('c', strtotime($post['published_at'])),
			'dateModified'     => date('c', strtotime($post['published_at'])),
			'author'           => array(
				'@type' => 'Organization',
				'name'  => $post['author_name'] ?: 'The Miner Island team',
			),
			'publisher'        => array(
				'@type' => 'Organization',
				'name'  => 'The Miner Island Company LLC',
				'url'   => $this->url->link('common/home'),
			),
			'mainEntityOfPage' => $this->url->link('extension/module/factory_updates', 'factory_post_id=' . (int)$post['factory_post_id']),
		);

		// B7: publisher logo (ImageObject), recommended for Article rich results.
		$logo_path = $this->config->get('config_logo');
		if ($logo_path) {
			$ld['publisher']['logo'] = array(
				'@type' => 'ImageObject',
				'url'   => $base . 'image/' . $logo_path,
			);
		}

		// B7: article image, only a real crawlable hero (skip the inline SVG placeholder).
		$hero = $this->resolveImage($post['hero_image_url']);
		if ($hero && strpos($hero, 'data:') !== 0) {
			$ld['image'] = $base . ltrim($hero, '/');
		}

		return json_encode($ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	}
}
