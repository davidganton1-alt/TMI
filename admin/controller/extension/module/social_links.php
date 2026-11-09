<?php
/**
 * The Miner Island — Social Links admin controller.
 * Phase 7.3 Step 1: full admin CRUD (list, add, edit, delete + form).
 * Step 2 will introduce the public-side rendering — no controller change.
 */
class ControllerExtensionModuleSocialLinks extends Controller {
	private $error = array();

	/**
	 * The canonical list of platforms supported by this module.
	 * Used by:
	 *   - The Platform dropdown on the add/edit form
	 *   - validateForm() to reject unknown platform_key values
	 *   - Step 2 frontend (each key maps to a built-in SVG icon)
	 *
	 * Sort order in this array = display order in the dropdown.
	 */
	private $platforms = array(
		'telegram', 'wechat', 'whatsapp',
		'twitter', 'reddit', 'discord', 'youtube',
		'linkedin', 'facebook', 'instagram', 'tiktok',
		'vk', 'odnoklassniki', 'viber', 'line',
	);

	/* ====================================================================== *
	 * ENTRY POINTS
	 * ====================================================================== */

	public function index() {
		$this->load->language('extension/module/social_links');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('extension/module/social_links');
		$this->getList();
	}

	public function add() {
		$this->load->language('extension/module/social_links');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('extension/module/social_links');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
			$this->model_extension_module_social_links->addLink($this->request->post);
			$this->session->data['success'] = $this->language->get('text_success');

			$url = $this->buildListUrl();
			$this->response->redirect($this->url->link('extension/module/social_links', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}

		$this->getForm();
	}

	public function edit() {
		$this->load->language('extension/module/social_links');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('extension/module/social_links');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
			$social_link_id = isset($this->request->get['social_link_id']) ? (int)$this->request->get['social_link_id'] : 0;
			if ($social_link_id > 0) {
				$this->model_extension_module_social_links->editLink($social_link_id, $this->request->post);
				$this->session->data['success'] = $this->language->get('text_success');
			}

			$url = $this->buildListUrl();
			$this->response->redirect($this->url->link('extension/module/social_links', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}

		$this->getForm();
	}

	public function delete() {
		$this->load->language('extension/module/social_links');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('extension/module/social_links');

		if (isset($this->request->post['selected']) && $this->validateDelete()) {
			foreach ((array)$this->request->post['selected'] as $id) {
				$this->model_extension_module_social_links->deleteLink((int)$id);
			}
			$this->session->data['success'] = $this->language->get('text_success');

			$url = $this->buildListUrl();
			$this->response->redirect($this->url->link('extension/module/social_links', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}

		$this->getList();
	}

	/* ====================================================================== *
	 * LIST
	 * ====================================================================== */

	protected function getList() {
		$sort  = isset($this->request->get['sort'])  ? $this->request->get['sort']  : 'sort_order';
		$order = isset($this->request->get['order']) ? $this->request->get['order'] : 'ASC';
		$page  = isset($this->request->get['page'])  ? (int)$this->request->get['page'] : 1;

		$url = $this->buildListUrl();

		$data['breadcrumbs'] = $this->breadcrumbs($url);

		$data['add']    = $this->url->link('extension/module/social_links/add',    'user_token=' . $this->session->data['user_token'] . $url, true);
		$data['delete'] = $this->url->link('extension/module/social_links/delete', 'user_token=' . $this->session->data['user_token'] . $url, true);

		$filter_data = array(
			'sort'  => $sort,
			'order' => $order,
			'start' => ($page - 1) * $this->config->get('config_limit_admin'),
			'limit' => $this->config->get('config_limit_admin')
		);

		$total   = $this->model_extension_module_social_links->getTotalLinks();
		$results = $this->model_extension_module_social_links->getLinks($filter_data);

		// Locations whose visibility we summarise per row in the list view.
		$location_keys = array(
			'show_footer', 'show_header_top', 'show_product_page', 'show_cart_page',
			'show_checkout', 'show_order_success', 'show_contact_about', 'show_mobile_drawer'
		);

		$data['links'] = array();
		foreach ($results as $row) {
			$enabled_count = 0;
			foreach ($location_keys as $k) {
				if (!empty($row[$k])) {
					$enabled_count++;
				}
			}

			// Platform display label via language file
			$lang_key = 'platform_' . $row['platform_key'];
			$platform_label = $this->language->get($lang_key);
			if ($platform_label == $lang_key) {
				$platform_label = $row['platform_key']; // fallback
			}

			$data['links'][] = array(
				'social_link_id' => $row['social_link_id'],
				'platform_key'   => $row['platform_key'],
				'platform_label' => $platform_label,
				'display_name'   => $row['display_name'] !== '' ? $row['display_name'] : $platform_label,
				'url'            => $row['url'],
				'url_short'      => (utf8_strlen($row['url']) > 50 ? utf8_substr($row['url'], 0, 47) . '…' : $row['url']),
				'locations'      => sprintf($this->language->get('text_locations_summary'), $enabled_count),
				'status'         => $row['status'] ? $this->language->get('text_enabled') : $this->language->get('text_disabled'),
				'sort_order'     => (int)$row['sort_order'],
				'edit'           => $this->url->link('extension/module/social_links/edit', 'user_token=' . $this->session->data['user_token'] . '&social_link_id=' . $row['social_link_id'] . $url, true)
			);
		}

		$data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';
		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];
			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		$data['selected'] = isset($this->request->post['selected']) ? (array)$this->request->post['selected'] : array();

		// Sort URLs
		$flip = ($order == 'ASC') ? '&order=DESC' : '&order=ASC';
		if (isset($this->request->get['page'])) {
			$flip .= '&page=' . $this->request->get['page'];
		}

		$data['sort_platform']     = $this->url->link('extension/module/social_links', 'user_token=' . $this->session->data['user_token'] . '&sort=platform_key' . $flip, true);
		$data['sort_display_name'] = $this->url->link('extension/module/social_links', 'user_token=' . $this->session->data['user_token'] . '&sort=display_name' . $flip, true);
		$data['sort_status']       = $this->url->link('extension/module/social_links', 'user_token=' . $this->session->data['user_token'] . '&sort=status' . $flip, true);
		$data['sort_sort_order']   = $this->url->link('extension/module/social_links', 'user_token=' . $this->session->data['user_token'] . '&sort=sort_order' . $flip, true);

		$purl = '';
		if (isset($this->request->get['sort']))  { $purl .= '&sort='  . $this->request->get['sort'];  }
		if (isset($this->request->get['order'])) { $purl .= '&order=' . $this->request->get['order']; }

		$pagination = new Pagination();
		$pagination->total = $total;
		$pagination->page  = $page;
		$pagination->limit = $this->config->get('config_limit_admin');
		$pagination->url   = $this->url->link('extension/module/social_links', 'user_token=' . $this->session->data['user_token'] . $purl . '&page={page}', true);

		$data['pagination'] = $pagination->render();
		$data['results'] = sprintf(
			$this->language->get('text_pagination'),
			$total ? (($page - 1) * $this->config->get('config_limit_admin')) + 1 : 0,
			((($page - 1) * $this->config->get('config_limit_admin')) > ($total - $this->config->get('config_limit_admin'))) ? $total : ((($page - 1) * $this->config->get('config_limit_admin')) + $this->config->get('config_limit_admin')),
			$total,
			ceil($total / max(1, $this->config->get('config_limit_admin')))
		);

		$data['sort']  = $sort;
		$data['order'] = $order;

		$data['header']      = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer']      = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/social_links_list', $data));
	}

	/* ====================================================================== *
	 * FORM (shared add + edit)
	 * ====================================================================== */

	protected function getForm() {
		$data['text_form'] = !isset($this->request->get['social_link_id'])
			? $this->language->get('text_add')
			: $this->language->get('text_edit');

		// Errors
		$data['error_warning']      = isset($this->error['warning'])      ? $this->error['warning']      : '';
		$data['error_platform_key'] = isset($this->error['platform_key']) ? $this->error['platform_key'] : '';
		$data['error_display_name'] = isset($this->error['display_name']) ? $this->error['display_name'] : '';
		$data['error_url']          = isset($this->error['url'])          ? $this->error['url']          : '';
		$data['error_qr_image']     = isset($this->error['qr_image'])     ? $this->error['qr_image']     : '';

		$url = $this->buildListUrl();
		$data['breadcrumbs'] = $this->breadcrumbs($url);

		if (isset($this->request->get['social_link_id'])) {
			$data['action'] = $this->url->link('extension/module/social_links/edit', 'user_token=' . $this->session->data['user_token'] . '&social_link_id=' . (int)$this->request->get['social_link_id'] . $url, true);
		} else {
			$data['action'] = $this->url->link('extension/module/social_links/add', 'user_token=' . $this->session->data['user_token'] . $url, true);
		}
		$data['cancel'] = $this->url->link('extension/module/social_links', 'user_token=' . $this->session->data['user_token'] . $url, true);

		$row = array();
		if (isset($this->request->get['social_link_id']) && ($this->request->server['REQUEST_METHOD'] != 'POST')) {
			$row = $this->model_extension_module_social_links->getLink((int)$this->request->get['social_link_id']);
		}

		// Field pre-fill: POST > DB > default
		$data['platform_key'] = $this->pickField('platform_key', $row, '');
		$data['display_name'] = $this->pickField('display_name', $row, '');
		$data['url']          = $this->pickField('url',          $row, '');
		$data['qr_image_url'] = $this->pickField('qr_image_url', $row, '');
		$data['sort_order']   = $this->pickField('sort_order',   $row, 0);

		// Status (tri-state pick)
		if (isset($this->request->post['status'])) {
			$data['status'] = (int)$this->request->post['status'];
		} elseif (!empty($row)) {
			$data['status'] = (int)$row['status'];
		} else {
			$data['status'] = 0;
		}

		// 8 location checkboxes (tri-state pick each)
		$location_keys = array(
			'show_footer', 'show_header_top', 'show_product_page', 'show_cart_page',
			'show_checkout', 'show_order_success', 'show_contact_about', 'show_mobile_drawer'
		);
		foreach ($location_keys as $k) {
			if (isset($this->request->post[$k])) {
				$data[$k] = (int)$this->request->post[$k];
			} elseif (!empty($row)) {
				$data[$k] = (int)$row[$k];
			} else {
				$data[$k] = 0;
			}
		}

		// Platforms dropdown — array of (key, label) for the template
		$data['platforms'] = array();
		foreach ($this->platforms as $key) {
			$lang_key = 'platform_' . $key;
			$label = $this->language->get($lang_key);
			if ($label == $lang_key) {
				$label = $key;
			}
			$data['platforms'][] = array('key' => $key, 'label' => $label);
		}

		// QR image preview (via tool/image, same convention as Factory Updates hero)
		$this->load->model('tool/image');
		if ($data['qr_image_url'] && is_file(DIR_IMAGE . $data['qr_image_url'])) {
			$data['thumb'] = $this->model_tool_image->resize($data['qr_image_url'], 200, 200);
		} else {
			$data['thumb'] = $this->model_tool_image->resize('no_image.png', 200, 200);
		}
		$data['placeholder'] = $this->model_tool_image->resize('no_image.png', 200, 200);

		$data['user_token']  = $this->session->data['user_token'];
		$data['header']      = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer']      = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/social_links_form', $data));
	}

	/* ====================================================================== *
	 * VALIDATION
	 * ====================================================================== */

	protected function validateForm() {
		if (!$this->user->hasPermission('modify', 'extension/module/social_links')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		$post = $this->request->post;

		// Platform key — must be one of the canonical 15
		$pk = isset($post['platform_key']) ? trim($post['platform_key']) : '';
		if ($pk === '' || !in_array($pk, $this->platforms, true)) {
			$this->error['platform_key'] = $this->language->get('error_platform_key');
		} else {
			$exclude = isset($this->request->get['social_link_id']) ? (int)$this->request->get['social_link_id'] : 0;
			if ($this->model_extension_module_social_links->platformExists($pk, $exclude)) {
				$this->error['platform_key'] = $this->language->get('error_platform_unique');
			}
		}

		// Display name
		if (isset($post['display_name']) && utf8_strlen($post['display_name']) > 80) {
			$this->error['display_name'] = $this->language->get('error_display_name');
		}

		// URL / handle
		$u = isset($post['url']) ? trim($post['url']) : '';
		if ($u !== '') {
			if (utf8_strlen($u) > 500) {
				$this->error['url'] = $this->language->get('error_url');
			} else {
				// WeChat allows a plain ID (no scheme). Everything else needs http(s)://
				if ($pk !== 'wechat' && !preg_match('#^https?://#i', $u)) {
					$this->error['url'] = $this->language->get('error_url_format');
				}
			}
		}

		// QR image
		if (isset($post['qr_image_url']) && utf8_strlen($post['qr_image_url']) > 255) {
			$this->error['qr_image'] = $this->language->get('error_qr_image');
		}

		if ($this->error && !isset($this->error['warning'])) {
			$this->error['warning'] = $this->language->get('error_form');
		}

		return !$this->error;
	}

	protected function validateDelete() {
		if (!$this->user->hasPermission('modify', 'extension/module/social_links')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}
		return !$this->error;
	}

	/* ====================================================================== *
	 * HELPERS
	 * ====================================================================== */

	protected function buildListUrl() {
		$url = '';
		if (isset($this->request->get['sort']))  { $url .= '&sort='  . $this->request->get['sort'];  }
		if (isset($this->request->get['order'])) { $url .= '&order=' . $this->request->get['order']; }
		if (isset($this->request->get['page']))  { $url .= '&page='  . $this->request->get['page'];  }
		return $url;
	}

	protected function pickField($key, $row, $default) {
		if (isset($this->request->post[$key])) {
			return $this->request->post[$key];
		}
		if (!empty($row) && isset($row[$key])) {
			return $row[$key];
		}
		return $default;
	}

	protected function breadcrumbs($url) {
		$crumbs = array();
		$crumbs[] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);
		$crumbs[] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
		);
		$crumbs[] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/module/social_links', 'user_token=' . $this->session->data['user_token'] . $url, true)
		);
		return $crumbs;
	}
}
