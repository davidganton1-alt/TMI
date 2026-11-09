<?php
/**
 * The Miner Island — Factory Updates admin controller.
 * Phase 7.1 Step 3: full CRUD — index, add, edit, delete + getList, getForm,
 *                   validateForm, validateDelete.
 */
class ControllerExtensionModuleFactoryUpdates extends Controller {
	private $error = array();

	/* ====================================================================== *
	 * ENTRY POINTS
	 * ====================================================================== */

	public function index() {
		$this->load->language('extension/module/factory_updates');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('extension/module/factory_updates');
		$this->getList();
	}

	public function add() {
		$this->load->language('extension/module/factory_updates');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('extension/module/factory_updates');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
			$this->request->post['slug'] = $this->normaliseSlug($this->request->post['slug'], $this->request->post['title']);

			$this->model_extension_module_factory_updates->addPost($this->request->post);

			$this->session->data['success'] = $this->language->get('text_success');

			$url = $this->buildListUrl();
			$this->response->redirect($this->url->link('extension/module/factory_updates', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}

		$this->getForm();
	}

	public function edit() {
		$this->load->language('extension/module/factory_updates');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('extension/module/factory_updates');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
			$this->request->post['slug'] = $this->normaliseSlug($this->request->post['slug'], $this->request->post['title']);

			$post_id = isset($this->request->get['factory_post_id']) ? (int)$this->request->get['factory_post_id'] : 0;

			if ($post_id > 0) {
				$this->model_extension_module_factory_updates->editPost($post_id, $this->request->post);
				$this->session->data['success'] = $this->language->get('text_success');
			}

			$url = $this->buildListUrl();
			$this->response->redirect($this->url->link('extension/module/factory_updates', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}

		$this->getForm();
	}

	/**
	 * Bulk delete handler. Accepts an array of factory_post_ids in
	 * $this->request->post['selected'] from the list view's checkbox form.
	 */
	public function delete() {
		$this->load->language('extension/module/factory_updates');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('extension/module/factory_updates');

		if (isset($this->request->post['selected']) && $this->validateDelete()) {
			foreach ((array)$this->request->post['selected'] as $post_id) {
				$this->model_extension_module_factory_updates->deletePost((int)$post_id);
			}

			$this->session->data['success'] = $this->language->get('text_success');

			$url = $this->buildListUrl();
			$this->response->redirect($this->url->link('extension/module/factory_updates', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}

		$this->getList();
	}

	/* ====================================================================== *
	 * LIST VIEW
	 * ====================================================================== */

	protected function getList() {
		$sort  = isset($this->request->get['sort'])  ? $this->request->get['sort']  : 'published_at';
		$order = isset($this->request->get['order']) ? $this->request->get['order'] : 'DESC';
		$page  = isset($this->request->get['page'])  ? (int)$this->request->get['page'] : 1;

		$url = $this->buildListUrl();

		$data['breadcrumbs'] = array();
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
		);
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/module/factory_updates', 'user_token=' . $this->session->data['user_token'] . $url, true)
		);

		$data['add']    = $this->url->link('extension/module/factory_updates/add',    'user_token=' . $this->session->data['user_token'] . $url, true);
		$data['delete'] = $this->url->link('extension/module/factory_updates/delete', 'user_token=' . $this->session->data['user_token'] . $url, true);

		$data['posts'] = array();

		$filter_data = array(
			'sort'  => $sort,
			'order' => $order,
			'start' => ($page - 1) * $this->config->get('config_limit_admin'),
			'limit' => $this->config->get('config_limit_admin')
		);

		$post_total = $this->model_extension_module_factory_updates->getTotalPosts();
		$results    = $this->model_extension_module_factory_updates->getPosts($filter_data);

		foreach ($results as $result) {
			$data['posts'][] = array(
				'factory_post_id' => $result['factory_post_id'],
				'title'           => $result['title'],
				'slug'            => $result['slug'],
				'published_at'    => date($this->language->get('date_format_short') ?: 'Y-m-d', strtotime($result['published_at'])),
				'status'          => ($result['status'] ? $this->language->get('text_enabled') : $this->language->get('text_disabled')),
				'sort_order'      => (int)$result['sort_order'],
				'edit'            => $this->url->link('extension/module/factory_updates/edit', 'user_token=' . $this->session->data['user_token'] . '&factory_post_id=' . $result['factory_post_id'] . $url, true)
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

		$flip = ($order == 'ASC') ? '&order=DESC' : '&order=ASC';
		if (isset($this->request->get['page'])) {
			$flip .= '&page=' . $this->request->get['page'];
		}

		$data['sort_title']        = $this->url->link('extension/module/factory_updates', 'user_token=' . $this->session->data['user_token'] . '&sort=title' . $flip, true);
		$data['sort_slug']         = $this->url->link('extension/module/factory_updates', 'user_token=' . $this->session->data['user_token'] . '&sort=slug' . $flip, true);
		$data['sort_published_at'] = $this->url->link('extension/module/factory_updates', 'user_token=' . $this->session->data['user_token'] . '&sort=published_at' . $flip, true);
		$data['sort_status']       = $this->url->link('extension/module/factory_updates', 'user_token=' . $this->session->data['user_token'] . '&sort=status' . $flip, true);
		$data['sort_sort_order']   = $this->url->link('extension/module/factory_updates', 'user_token=' . $this->session->data['user_token'] . '&sort=sort_order' . $flip, true);

		$purl = '';
		if (isset($this->request->get['sort']))  { $purl .= '&sort='  . $this->request->get['sort'];  }
		if (isset($this->request->get['order'])) { $purl .= '&order=' . $this->request->get['order']; }

		$pagination = new Pagination();
		$pagination->total = $post_total;
		$pagination->page  = $page;
		$pagination->limit = $this->config->get('config_limit_admin');
		$pagination->url   = $this->url->link('extension/module/factory_updates', 'user_token=' . $this->session->data['user_token'] . $purl . '&page={page}', true);

		$data['pagination'] = $pagination->render();
		$data['results'] = sprintf(
			$this->language->get('text_pagination'),
			($post_total) ? (($page - 1) * $this->config->get('config_limit_admin')) + 1 : 0,
			((($page - 1) * $this->config->get('config_limit_admin')) > ($post_total - $this->config->get('config_limit_admin'))) ? $post_total : ((($page - 1) * $this->config->get('config_limit_admin')) + $this->config->get('config_limit_admin')),
			$post_total,
			ceil($post_total / max(1, $this->config->get('config_limit_admin')))
		);

		$data['sort']  = $sort;
		$data['order'] = $order;

		$data['header']      = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer']      = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/factory_updates_list', $data));
	}

	/* ====================================================================== *
	 * FORM VIEW (shared add + edit)
	 * ====================================================================== */

	protected function getForm() {
		$data['text_form'] = !isset($this->request->get['factory_post_id'])
			? $this->language->get('text_add')
			: $this->language->get('text_edit');

		$data['error_warning']          = isset($this->error['warning'])          ? $this->error['warning']          : '';
		$data['error_title']            = isset($this->error['title'])            ? $this->error['title']            : '';
		$data['error_slug']             = isset($this->error['slug'])             ? $this->error['slug']             : '';
		$data['error_excerpt']          = isset($this->error['excerpt'])          ? $this->error['excerpt']          : '';
		$data['error_body']             = isset($this->error['body'])             ? $this->error['body']             : '';
		$data['error_meta_title']       = isset($this->error['meta_title'])       ? $this->error['meta_title']       : '';
		$data['error_meta_description'] = isset($this->error['meta_description']) ? $this->error['meta_description'] : '';
		$data['error_published_at']     = isset($this->error['published_at'])     ? $this->error['published_at']     : '';
		$data['error_author']           = isset($this->error['author'])           ? $this->error['author']           : '';
		$data['error_hero_image']       = isset($this->error['hero_image'])       ? $this->error['hero_image']       : '';

		$url = $this->buildListUrl();

		$data['breadcrumbs'] = array();
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
		);
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/module/factory_updates', 'user_token=' . $this->session->data['user_token'] . $url, true)
		);

		if (isset($this->request->get['factory_post_id'])) {
			$data['action'] = $this->url->link('extension/module/factory_updates/edit', 'user_token=' . $this->session->data['user_token'] . '&factory_post_id=' . (int)$this->request->get['factory_post_id'] . $url, true);
		} else {
			$data['action'] = $this->url->link('extension/module/factory_updates/add', 'user_token=' . $this->session->data['user_token'] . $url, true);
		}

		$data['cancel'] = $this->url->link('extension/module/factory_updates', 'user_token=' . $this->session->data['user_token'] . $url, true);

		$post_info = array();
		if (isset($this->request->get['factory_post_id']) && ($this->request->server['REQUEST_METHOD'] != 'POST')) {
			$post_info = $this->model_extension_module_factory_updates->getPost((int)$this->request->get['factory_post_id']);
		}

		$data['title']            = $this->pickField('title',            $post_info, '');
		$data['slug']             = $this->pickField('slug',             $post_info, '');
		$data['excerpt']          = $this->pickField('excerpt',          $post_info, '');
		$data['body_html']        = $this->pickField('body_html',        $post_info, '');
		$data['hero_image_url']   = $this->pickField('hero_image_url',   $post_info, '');
		$data['video_embed']      = $this->pickField('video_embed',      $post_info, '');
		$data['meta_title']       = $this->pickField('meta_title',       $post_info, '');
		$data['meta_description'] = $this->pickField('meta_description', $post_info, '');
		$data['author_name']      = $this->pickField('author_name',      $post_info, 'The Miner Island team');
		$data['published_at']     = $this->pickField('published_at',     $post_info, date('Y-m-d H:i:s'));
		$data['sort_order']       = $this->pickField('sort_order',       $post_info, 0);

		if (isset($this->request->post['status'])) {
			$data['status'] = (int)$this->request->post['status'];
		} elseif (!empty($post_info)) {
			$data['status'] = (int)$post_info['status'];
		} else {
			$data['status'] = 1;
		}

		$this->load->model('tool/image');
		if ($data['hero_image_url'] && is_file(DIR_IMAGE . $data['hero_image_url'])) {
			$data['thumb'] = $this->model_tool_image->resize($data['hero_image_url'], 200, 113);
		} else {
			$data['thumb'] = $this->model_tool_image->resize('no_image.png', 200, 113);
		}
		$data['placeholder'] = $this->model_tool_image->resize('no_image.png', 200, 113);

		$data['user_token'] = $this->session->data['user_token'];

		$data['header']      = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer']      = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/factory_updates_form', $data));
	}

	/* ====================================================================== *
	 * VALIDATION
	 * ====================================================================== */

	protected function validateForm() {
		if (!$this->user->hasPermission('modify', 'extension/module/factory_updates')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		$post = $this->request->post;

		$title_len = isset($post['title']) ? utf8_strlen(trim($post['title'])) : 0;
		if ($title_len < 3 || $title_len > 200) {
			$this->error['title'] = $this->language->get('error_title');
		}

		$slug = $this->normaliseSlug(isset($post['slug']) ? $post['slug'] : '', isset($post['title']) ? $post['title'] : '');
		if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) || strlen($slug) < 3 || strlen($slug) > 160) {
			$this->error['slug'] = $this->language->get('error_slug');
		} else {
			$exclude_post_id = isset($this->request->get['factory_post_id']) ? (int)$this->request->get['factory_post_id'] : 0;
			$exclude_query = $exclude_post_id ? ('factory_post_id=' . $exclude_post_id) : '';

			if ($this->model_extension_module_factory_updates->slugExists($slug, $exclude_post_id)) {
				$this->error['slug'] = $this->language->get('error_slug_unique');
			} elseif ($this->model_extension_module_factory_updates->slugReservedElsewhere($slug, $exclude_query)) {
				$this->error['slug'] = $this->language->get('error_slug_reserved');
			}
		}

		if (isset($post['excerpt']) && utf8_strlen($post['excerpt']) > 280) {
			$this->error['excerpt'] = $this->language->get('error_excerpt');
		}

		$body_strip = isset($post['body_html']) ? trim(strip_tags($post['body_html'])) : '';
		if (utf8_strlen($body_strip) < 100) {
			$this->error['body'] = $this->language->get('error_body');
		}

		if (isset($post['meta_title']) && utf8_strlen($post['meta_title']) > 255) {
			$this->error['meta_title'] = $this->language->get('error_meta_title');
		}
		if (isset($post['meta_description']) && utf8_strlen($post['meta_description']) > 255) {
			$this->error['meta_description'] = $this->language->get('error_meta_description');
		}

		if (!empty($post['published_at'])) {
			$ts = strtotime($post['published_at']);
			if ($ts === false) {
				$this->error['published_at'] = $this->language->get('error_published_at');
			}
		}

		if (isset($post['author_name']) && utf8_strlen($post['author_name']) > 100) {
			$this->error['author'] = $this->language->get('error_author');
		}

		if (isset($post['hero_image_url']) && utf8_strlen($post['hero_image_url']) > 255) {
			$this->error['hero_image'] = $this->language->get('error_hero_image');
		}

		if ($this->error && !isset($this->error['warning'])) {
			$this->error['warning'] = $this->language->get('error_form');
		}

		return !$this->error;
	}

	/**
	 * Permission check for bulk delete.
	 */
	protected function validateDelete() {
		if (!$this->user->hasPermission('modify', 'extension/module/factory_updates')) {
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

	protected function normaliseSlug($slug, $title_fallback = '') {
		$candidate = trim((string)$slug);
		if ($candidate === '') {
			$candidate = (string)$title_fallback;
		}
		$candidate = strtolower($candidate);
		$candidate = preg_replace('/[^a-z0-9]+/', '-', $candidate);
		$candidate = preg_replace('/-+/', '-', $candidate);
		$candidate = trim($candidate, '-');
		return $candidate;
	}
}
