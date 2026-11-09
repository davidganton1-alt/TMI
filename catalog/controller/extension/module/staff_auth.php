<?php
/**
 * The Miner Island: Staff Verification storefront.
 *
 * Routes:
 *   index.php?route=extension/module/staff_auth          → verification page
 *   index.php?route=extension/module/staff_auth/check     → AJAX lookup (JSON)
 *
 * Pretty URL: /staff-verification (via oc_seo_url alias row, resolved by the
 * existing arbitrary-route aliasing in catalog/controller/startup/seo_url.php;
 * no core patch required).
 *
 * Purpose: customers verify a phone number / handle against the official staff
 * list to avoid impersonation scams. Anything not on the enabled list is
 * reported as unverified with a scam warning + support link.
 */
class ControllerExtensionModuleStaffAuth extends Controller {

	private $platforms = array(
		'whatsapp', 'telegram', 'wechat', 'signal', 'email', 'skype',
		'viber', 'line', 'twitter', 'reddit', 'discord', 'youtube',
		'linkedin', 'facebook', 'instagram', 'tiktok', 'vk', 'odnoklassniki',
	);

	public function index() {
		$this->load->language('extension/module/staff_auth');

		$this->document->setTitle($this->language->get('meta_title'));
		$this->document->setDescription($this->language->get('meta_description'));

		// Breadcrumbs
		$data['breadcrumbs'] = array();
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home'),
		);
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/module/staff_auth'),
		);

		// Platform dropdown options
		$data['platforms'] = array();
		foreach ($this->platforms as $key) {
			$lang_key = 'platform_' . $key;
			$label = $this->language->get($lang_key);
			if ($label == $lang_key) { $label = $key; }
			$data['platforms'][] = array('key' => $key, 'label' => $label);
		}

		// Endpoints + copy
		$data['check_url']    = $this->url->link('extension/module/staff_auth/check');
		$data['contact_url']  = $this->url->link('information/contact');

		foreach (array(
			'heading_title','text_intro','entry_platform','entry_account',
			'placeholder_account','help_example','button_verify','text_result_idle',
			'text_verifying','text_safety_tips','tip_1','tip_2','tip_3','tip_4',
			'text_input_required'
		) as $k) {
			$data[$k] = $this->language->get($k);
		}

		// Standard page children
		$data['column_left']    = $this->load->controller('common/column_left');
		$data['column_right']   = $this->load->controller('common/column_right');
		$data['content_top']    = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer']         = $this->load->controller('common/footer');
		$data['header']         = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('extension/module/staff_auth', $data));
	}

	/**
	 * AJAX endpoint. Accepts POST platform + account, returns JSON:
	 *   { status: 'verified'|'unverified'|'error', ... }
	 */
	public function check() {
		$this->load->language('extension/module/staff_auth');
		$this->load->model('extension/module/staff_auth');

		$json = array();

		$platform = isset($this->request->post['platform']) ? (string)$this->request->post['platform'] : '';
		$account  = isset($this->request->post['account'])  ? (string)$this->request->post['account']  : '';

		if (!in_array($platform, $this->platforms, true)) {
			$json['status'] = 'error';
			$json['message'] = $this->language->get('text_input_required');
		} elseif (trim($account) === '') {
			$json['status'] = 'error';
			$json['message'] = $this->language->get('text_input_required');
		} else {
			$match = $this->model_extension_module_staff_auth->verify($platform, $account);

			$lang_key = 'platform_' . $platform;
			$platform_label = $this->language->get($lang_key);
			if ($platform_label == $lang_key) { $platform_label = $platform; }

			if ($match) {
				$json['status']         = 'verified';
				$json['name']           = $match['name'];
				$json['title']          = $match['title'];
				$json['platform_label'] = $platform_label;
				$json['account']        = $match['account'];
				$json['heading']        = $this->language->get('text_verified_heading');
				$json['message']        = sprintf($this->language->get('text_verified_body'), $match['name'], $platform_label);
			} else {
				$json['status']         = 'unverified';
				$json['platform_label'] = $platform_label;
				$json['heading']        = $this->language->get('text_unverified_heading');
				$json['message']        = $this->language->get('text_unverified_body');
				$json['contact_url']    = $this->url->link('information/contact');
				$json['contact_label']  = $this->language->get('text_contact_support');
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
