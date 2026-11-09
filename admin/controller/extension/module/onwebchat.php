<?php 

class ControllerExtensionModuleOnwebchat extends Controller {
	
	private $error = array(); 

	public function index() {
		
		$this->load->language('extension/module/onwebchat');

		$this->document->setTitle($this->language->get('heading_module_title'));
		
		$this->load->model('setting/setting');
			
		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {

            $this->model_setting_setting->editSetting('module_onwebchat', $this->request->post);
			
			$this->session->data['success'] = $this->language->get('text_success');
			
			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true));
		}
		
 		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

        if (isset($this->error['module_onwebchat_chat_id'])) {
			$data['error_onwebchat_code'] = $this->error['module_onwebchat_chat_id'];
		} else {
			$data['error_onwebchat_code'] = '';
		}

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
       		'text'      => $this->language->get('heading_module_title'),
			'href'      => $this->url->link('extension/module/onwebchat', 'user_token=' . $this->session->data['user_token'], true)
   		);
				
		$data['action'] = $this->url->link('extension/module/onwebchat', 'user_token=' . $this->session->data['user_token'], true);
		
		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);
		
		if (isset($this->request->post['module_onwebchat_chat_id'])) {
			$data['module_onwebchat_chat_id'] = $this->request->post['module_onwebchat_chat_id'];
		} else {
			$data['module_onwebchat_chat_id'] = $this->config->get('module_onwebchat_chat_id');
		}
		
		
		if (isset($this->request->post['module_onwebchat_status'])) {
			$data['module_onwebchat_status'] = $this->request->post['module_onwebchat_status'];
		} else {
			$data['module_onwebchat_status'] = $this->config->get('module_onwebchat_status');
		}
		
		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');
		
		$this->response->setOutput($this->load->view('extension/module/onwebchat', $data));
	}

	private function validate() {
		if (!$this->user->hasPermission('modify', 'extension/module/onwebchat')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}
		
		if (!$this->request->post['module_onwebchat_chat_id']) {
			$this->error['module_onwebchat_chat_id'] = $this->language->get('error_onwebchat_code');
		}

		return !$this->error;
	}	
}