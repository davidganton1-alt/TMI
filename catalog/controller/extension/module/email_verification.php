<?php

class ControllerExtensionModuleEmailVerification extends Controller {

	public function index() {
		if ($this->customer->isLogged()) {
			$this->response->redirect($this->url->link('account/account'));
		}

		if (empty($this->request->get['v']) || strlen($this->request->get['v']) != 32) {
			$this->response->redirect($this->url->link('common/home'));
		}

		$this->load->language('extension/module/email_verification');

		$customer_verification_query = $this->db->query("SELECT customer_id FROM " . DB_PREFIX . "customer_verification WHERE code = '" . $this->db->escape($this->request->get['v']) . "'");

		if ($customer_verification_query->row) {
			$this->db->query("UPDATE " . DB_PREFIX . "customer SET status = 1 WHERE customer_id = '" . (int)$customer_verification_query->row['customer_id'] . "'");

			$this->db->query("DELETE FROM " . DB_PREFIX . "customer_approval WHERE customer_id = '" . (int)$customer_verification_query->row['customer_id'] . "'");
			$this->db->query("DELETE FROM " . DB_PREFIX . "customer_verification WHERE customer_id = '" . (int)$customer_verification_query->row['customer_id'] . "'");

			$customer = $this->db->query("SELECT email FROM " . DB_PREFIX . "customer WHERE customer_id = '" . (int)$customer_verification_query->row['customer_id'] . "'");

			$this->session->data['success'] = $this->language->get('success_verified');

			$this->response->redirect($this->url->link('account/login', '&email=' . $customer->row['email'], true));
		} else {
			$this->session->data['error'] = $this->language->get('error_verification');

			$this->response->redirect($this->url->link('account/login'));
		}
	}

	public function resend(){
		if (empty($this->request->get['email'])) {
			$this->response->redirect($this->url->link('account/login'));
		}

		$customer_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "customer c WHERE c.email='" . $this->db->escape($this->request->get['email']) . "' LIMIT 1");

		if ($customer_query->row) {
			$customer_verification_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "customer_verification WHERE customer_id = " . (int)$customer_query->row['customer_id'] . " LIMIT 1");

			if ($customer_verification_query->row) {
				$code = $customer_verification_query->row['code'];
			} else {
				list($usec, $sec) = explode(' ', microtime());
				srand((float)$sec + ((float)$usec * 100000));

				$code = md5((int)$customer_query->row['customer_id'] . ':' . rand());

				$this->db->query("INSERT INTO " . DB_PREFIX . "customer_verification SET customer_id = '" . (int)$customer_query->row['customer_id'] . "', code = '" . $code . "'");
			}

			$verification_link = $this->url->link('extension/module/email_verification', 'v=' . $code);

			$this->load->language('mail/regsiter');
			$this->load->language('extension/module/email_verification');

            $subject = sprintf($this->language->get('text_subject'), html_entity_decode($this->config->get('config_name'), ENT_QUOTES, 'UTF-8'));

            $message = sprintf($this->language->get('text_welcome'), html_entity_decode($this->config->get('config_name'), ENT_QUOTES, 'UTF-8')) . "\n\n";

            $message .= $this->language->get('text_email_verification') . "\n";

            $message .= str_replace('&amp;','&', $verification_link) . "\n\n";

            $message .= $this->language->get('text_services') . "\n\n";
            $message .= $this->language->get('text_thanks') . "\n";
            $message .= html_entity_decode($this->config->get('config_name'), ENT_QUOTES, 'UTF-8');

            $mail = new Mail($this->config->get('config_mail_engine'));
            $mail->parameter = $this->config->get('config_mail_parameter');
            $mail->smtp_hostname = $this->config->get('config_mail_smtp_hostname');
            $mail->smtp_username = $this->config->get('config_mail_smtp_username');
            $mail->smtp_password = html_entity_decode($this->config->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8');
            $mail->smtp_port = $this->config->get('config_mail_smtp_port');
            $mail->smtp_timeout = $this->config->get('config_mail_smtp_timeout');

            $mail->setTo($customer_query->row['email']);
            $mail->setFrom($this->config->get('config_email'));
            $mail->setSender(html_entity_decode($this->config->get('config_name'), ENT_QUOTES, 'UTF-8'));
            $mail->setSubject($subject);
            $mail->setText($message);

            $email_template_installed = false;
            if (file_exists(DIR_APPLICATION . 'model/extension/module/emailtemplate.php')) {
                $email_template_installed = true;
            }

            if ($email_template_installed) {
                $this->load->model('extension/module/emailtemplate');

                $template_load = array(
                    'key' => 'customer.approve',
                    'customer_id' => $customer_query->row['customer_id'],
                    'customer_group_id' => $customer_query->row['customer_group_id'],
                    'language_id' => $customer_query->row['language_id'],
                    'store_id' => $customer_query->row['store_id'],
                );

                $template = $this->model_extension_module_emailtemplate->load($template_load);

                if ($template) {
                    $template->addData($customer_query->row, 'customer');

                    $template->data['customer_text'] = $this->language->get('text_email_verification');

                    $template->data['verification_link'] = $verification_link;

                    if (strip_tags($template->data['emailtemplate']['content1']) == '') {
                        $template->fetch(null, $message);
                    }

                    $mail = $template->hook($mail);
                }
            }

            $mail->send();

            if ($email_template_installed) {
                $this->model_extension_module_emailtemplate->sent();
            }

            $this->session->data['success'] = sprintf($this->language->get('text_resent_verified'), $customer_query->row['email']);
		}

		$this->response->redirect($this->url->link('account/login'));
	}
}
