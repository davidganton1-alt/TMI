<?php
class ControllerExtensionModuleEmailVerification extends Controller {
    private $error = array();

    public function index() {
        $this->load->language('extension/module/email_verification');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('setting/setting');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting('module_email_verification', $this->request->post);

            if (empty($this->request->post['module_email_verification_cache'])) {
                $this->cache->delete('module_email_verification');
            }

            $this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module'));
        }

        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        if (isset($this->session->data['success'])) {
            $data['success'] = $this->session->data['success'];

            unset($this->session->data['success']);
        } else {
            $data['success'] = '';
        }

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module')
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/module/email_verification', 'user_token=' . $this->session->data['user_token'])
        );

        $data['action'] = $this->url->link('extension/module/email_verification', 'user_token=' . $this->session->data['user_token']);
        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module');

		if (isset($this->request->post['module_email_verification_status'])) {
			$data['module_email_verification_status'] = $this->request->post['module_email_verification_status'];
		} else {
			$data['module_email_verification_status'] = $this->config->get('module_email_verification_status');
        }

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/module/email_verification', $data));
    }

    public function install() {
        if (!$this->user->hasPermission('modify', 'extension/module/email_verification')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        $this->load->language('extension/module/email_verification');

        $this->load->model('setting/setting');

        $settings = $this->model_setting_setting->getSetting('module_email_verification');

        if (!is_array($settings)) {
            $settings = array();
        }

        $settings['module_email_verification_status'] = 1;

        $this->model_setting_setting->editSetting('module_email_verification', $settings);

        $this->load->model('extension/module/email_verification');

        $this->model_extension_module_email_verification->install();

		if (file_exists(DIR_APPLICATION . 'model/extension/module/emailtemplate.php')) {
			$this->load->model('extension/module/emailtemplate');
			$this->load->model('localisation/language');

			$languages = $this->model_localisation_language->getLanguages();

			$replace_language_vars = defined('REPLACE_LANGUAGE_VARIABLES') ? REPLACE_LANGUAGE_VARIABLES : true;

			$template_key = 'customer.approve';

			$template_info = $this->model_extension_module_emailtemplate->getTemplate($template_key);

			if (!$template_info) {
				$template_data = array();
				$template_data['emailtemplate_label'] = 'Customer Approve';
				$template_data['emailtemplate_key'] = $template_key;
				$template_data['emailtemplate_type'] = 'customer';
				$template_data['emailtemplate_preference'] = 'essential';
				$template_data['emailtemplate_showcase'] = 1;
				$template_data['emailtemplate_mail_queue'] = 0;
				$template_data['emailtemplate_language_files'] = 'extension/module/email_verification';

				$emailtemplate_id = $this->model_extension_module_emailtemplate->insertTemplate($template_data);

				foreach ($languages as $language) {
					$template_description_data = array(
						'emailtemplate_description_heading' => $this->language->get('text_approve_heading'),
						'emailtemplate_description_content1' => $this->language->get('text_approve_content1'),
						'emailtemplate_description_subject' => $this->language->get('text_approve_subject'),
					);

					if ($replace_language_vars) {
						$oLanguage = new Language($language['code']);

						if (method_exists($oLanguage, 'setPath') && substr($template_key, 0, 6) != 'admin.' && defined('DIR_CATALOG')) {
							$oLanguage->setPath(DIR_CATALOG . 'language/');
						}

						$oLanguage->load($language['code']);
						$oLanguage->load('extension/module/emailtemplate/emailtemplate');
						$langData = $oLanguage->load('extension/module/email_verification');

						if (!empty($template_data['emailtemplate_language_files'])) {
							$language_files = explode(',', $template_data['emailtemplate_language_files']);
							if ($language_files) {
								foreach ($language_files as $language_file) {
									if ($language_file) {
										$_langData = $oLanguage->load(trim($language_file));
										if ($_langData) {
											$langData = array_merge($langData, $_langData);
										}
									}
								}
							}
						}

						$find = array();
						$replace = array();

						foreach ($langData as $i => $val) {
							if ((is_string($val) && (strpos($val, '%s') === false) || is_int($val))) {
								$find[$i] = '{{ ' . $i . ' }}';
								$replace[$i] = $val;
							}
						}

						foreach ($template_description_data as $col => $val) {
							if ($val && is_string($val)) {
								$template_description_data[$col] = str_replace($find, $replace, $val);
							}
						}
					}

					$template_description_data['language_id'] = $language['language_id'];
					$template_description_data['emailtemplate_id'] = $emailtemplate_id;

					$this->model_extension_module_emailtemplate->insertTemplateDescription($template_description_data);
				}
			}

			$this->model_extension_module_emailtemplate->clear();
			$this->model_extension_module_emailtemplate->updateModification();
		}
    }

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/module/email_verification')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}
}