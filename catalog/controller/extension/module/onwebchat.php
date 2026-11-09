<?php

class ControllerExtensionModuleOnwebchat extends Controller {
	
	public function index() {
		
        $data = array();
       
		$data['module_onwebchat_chat_id'] = htmlspecialchars_decode($this->config->get('module_onwebchat_chat_id'));

		if($this->config->get('module_onwebchat_status')) {
		  return $this->load->view('extension/module/onwebchat', $data);
		}
	}
}