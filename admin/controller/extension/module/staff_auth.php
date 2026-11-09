<?php
/**
 * The Miner Island: Staff Verification (fraud prevention) admin controller.
 * CRUD over oc_staff_auth: index/getList, add, edit, delete, getForm,
 * validateForm, validateDelete. Mirrors the factory_updates module patterns
 * (user_token, redirect-on-success, session success message).
 */
class ControllerExtensionModuleStaffAuth extends Controller {
	private $error = array();

	/* Canonical platform list. Keys map to language labels platform_<key>.
	   Pulled from the social_links module + email/signal for staff contact. */
	private $platforms = array(
		'whatsapp', 'telegram', 'wechat', 'signal', 'email', 'skype',
		'viber', 'line', 'twitter', 'reddit', 'discord', 'youtube',
		'linkedin', 'facebook', 'instagram', 'tiktok', 'vk', 'odnoklassniki',
	);

	/* ====================================================================== *
	 * ENTRY POINTS
	 * ====================================================================== */

	public function index() {
		$this->load->language('extension/module/staff_auth');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('extension/module/staff_auth');
		$this->getList();
	}

	public function add() {
		$this->load->language('extension/module/staff_auth');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('extension/module/staff_auth');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
			$this->model_extension_module_staff_auth->addStaff($this->request->post);
			$this->session->data['success'] = $this->language->get('text_success');
			$this->response->redirect($this->url->link('extension/module/staff_auth', 'user_token=' . $this->session->data['user_token'], true));
		}

		$this->getForm();
	}

	public function edit() {
		$this->load->language('extension/module/staff_auth');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('extension/module/staff_auth');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
			$staff_auth_id = isset($this->request->get['staff_auth_id']) ? (int)$this->request->get['staff_auth_id'] : 0;
			if ($staff_auth_id > 0) {
				$this->model_extension_module_staff_auth->editStaff($staff_auth_id, $this->request->post);
				$this->session->data['success'] = $this->language->get('text_success');
			}
			$this->response->redirect($this->url->link('extension/module/staff_auth', 'user_token=' . $this->session->data['user_token'], true));
		}

		$this->getForm();
	}

	public function delete() {
		$this->load->language('extension/module/staff_auth');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('extension/module/staff_auth');

		if (isset($this->request->post['selected']) && $this->validateDelete()) {
			foreach ((array)$this->request->post['selected'] as $id) {
				$this->model_extension_module_staff_auth->deleteStaff((int)$id);
			}
			$this->session->data['success'] = $this->language->get('text_success');
			$this->response->redirect($this->url->link('extension/module/staff_auth', 'user_token=' . $this->session->data['user_token'], true));
		}

		$this->getList();
	}

	/* ====================================================================== *
	 * LIST VIEW
	 * ====================================================================== */

	protected function getList() {
		$data['heading_title'] = $this->language->get('heading_title');

		$data['add']    = $this->url->link('extension/module/staff_auth/add',    'user_token=' . $this->session->data['user_token'], true);
		$data['delete'] = $this->url->link('extension/module/staff_auth/delete', 'user_token=' . $this->session->data['user_token'], true);

		$data['staff'] = array();
		$results = $this->model_extension_module_staff_auth->getStaffList();
		foreach ($results as $row) {
			$lang_key = 'platform_' . $row['platform_key'];
			$label = $this->language->get($lang_key);
			if ($label == $lang_key) { $label = $row['platform_key']; }

			$data['staff'][] = array(
				'staff_auth_id'  => $row['staff_auth_id'],
				'name'           => $row['name'],
				'title'          => $row['title'],
				'platform_label' => $label,
				'account'        => $row['account'],
				'status'         => $row['status'] ? $this->language->get('text_enabled') : $this->language->get('text_disabled'),
				'sort_order'     => $row['sort_order'],
				'edit'           => $this->url->link('extension/module/staff_auth/edit', 'user_token=' . $this->session->data['user_token'] . '&staff_auth_id=' . $row['staff_auth_id'], true),
			);
		}

		$data['user_token'] = $this->session->data['user_token'];

		if (isset($this->error['warning'])) { $data['error_warning'] = $this->error['warning']; } else { $data['error_warning'] = ''; }
		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];
			unset($this->session->data['success']);
		} else { $data['success'] = ''; }

		$data['breadcrumbs'] = array();
		$data['breadcrumbs'][] = array('text' => $this->language->get('text_home'), 'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true));
		$data['breadcrumbs'][] = array('text' => $this->language->get('heading_title'), 'href' => $this->url->link('extension/module/staff_auth', 'user_token=' . $this->session->data['user_token'], true));

		// language passthrough
		foreach (array('text_confirm','text_no_results','button_add','button_delete','button_edit','column_name','column_title','column_platform','column_account','column_status','column_sort_order','column_action','text_list','text_intro') as $k) {
			$data[$k] = $this->language->get($k);
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/staff_auth', $data));
	}

	/* ====================================================================== *
	 * FORM VIEW
	 * ====================================================================== */

	protected function getForm() {
		$data['heading_title'] = $this->language->get('heading_title');

		foreach (array('text_edit','text_add','text_enabled','text_disabled','entry_name','entry_title','entry_platform','entry_account','entry_note','entry_status','entry_sort_order','help_account','button_save','button_cancel','text_form') as $k) {
			$data[$k] = $this->language->get($k);
		}

		$data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';
		$data['error_name']    = isset($this->error['name'])    ? $this->error['name']    : '';
		$data['error_account'] = isset($this->error['account']) ? $this->error['account'] : '';
		$data['error_platform']= isset($this->error['platform'])? $this->error['platform']: '';

		$staff_auth_id = isset($this->request->get['staff_auth_id']) ? (int)$this->request->get['staff_auth_id'] : 0;

		if (!empty($this->request->get['staff_auth_id']) && ($this->request->server['REQUEST_METHOD'] != 'POST')) {
			$info = $this->model_extension_module_staff_auth->getStaff($staff_auth_id);
		}

		$data['action'] = $staff_auth_id
			? $this->url->link('extension/module/staff_auth/edit', 'user_token=' . $this->session->data['user_token'] . '&staff_auth_id=' . $staff_auth_id, true)
			: $this->url->link('extension/module/staff_auth/add', 'user_token=' . $this->session->data['user_token'], true);
		$data['cancel'] = $this->url->link('extension/module/staff_auth', 'user_token=' . $this->session->data['user_token'], true);

		$fields = array('name','title','platform_key','account','note','sort_order','status');
		foreach ($fields as $f) {
			if (isset($this->request->post[$f])) {
				$data[$f] = $this->request->post[$f];
			} elseif (isset($info)) {
				$data[$f] = $info[$f];
			} else {
				$data[$f] = ($f === 'status') ? 1 : (($f === 'sort_order') ? 0 : '');
			}
		}

		// platform options with labels
		$data['platforms'] = array();
		foreach ($this->platforms as $key) {
			$lang_key = 'platform_' . $key;
			$label = $this->language->get($lang_key);
			if ($label == $lang_key) { $label = $key; }
			$data['platforms'][] = array('key' => $key, 'label' => $label);
		}

		$data['breadcrumbs'] = array();
		$data['breadcrumbs'][] = array('text' => $this->language->get('text_home'), 'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true));
		$data['breadcrumbs'][] = array('text' => $this->language->get('heading_title'), 'href' => $this->url->link('extension/module/staff_auth', 'user_token=' . $this->session->data['user_token'], true));

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/staff_auth_form', $data));
	}

	/* ====================================================================== *
	 * VALIDATION
	 * ====================================================================== */

	protected function validateForm() {
		if (!$this->user->hasPermission('modify', 'extension/module/staff_auth')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}
		if (utf8_strlen(trim((string)$this->request->post['name'])) < 1) {
			$this->error['name'] = $this->language->get('error_name');
		}
		if (utf8_strlen(trim((string)$this->request->post['account'])) < 1) {
			$this->error['account'] = $this->language->get('error_account');
		}
		if (!in_array((string)$this->request->post['platform_key'], $this->platforms, true)) {
			$this->error['platform'] = $this->language->get('error_platform');
		}
		return !$this->error;
	}

	protected function validateDelete() {
		if (!$this->user->hasPermission('modify', 'extension/module/staff_auth')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}
		return !$this->error;
	}
}
