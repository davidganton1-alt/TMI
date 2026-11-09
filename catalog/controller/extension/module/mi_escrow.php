<?php
class ControllerExtensionModuleMiEscrow extends Controller {

    public function select() {
        if (!isset($this->session->data['order_id'])) {
            $this->response->redirect($this->url->link('checkout/checkout', '', true));
        }

        $this->load->model('checkout/order');
        $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);

        $data['order_id'] = $order_info['order_id'];
        $data['total'] = $this->currency->format($order_info['total'], $order_info['currency_code']);
        $data['action_choose'] = $this->url->link('extension/module/mi_escrow/choose', '', true);
        $data['continue'] = $this->url->link('extension/payment/plisio/checkout', '', true);

        $data['header'] = $this->load->controller('common/header');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/module/mi_escrow_select', $data));
    }

    public function choose() {
        if ($this->request->server['REQUEST_METHOD'] == 'POST') {
            $this->session->data['mi_escrow_choice'] = isset($this->request->post['escrow_type']) ? $this->request->post['escrow_type'] : 'direct';

            // Create or update escrow record
            $existing = $this->db->query("SELECT `escrow_id` FROM `" . DB_PREFIX . "mi_escrow` WHERE `order_id` = '" . (int)$this->session->data['order_id'] . "'");

            if ($existing->num_rows) {
                $this->db->query("UPDATE `" . DB_PREFIX . "mi_escrow` SET
                    `escrow_type` = '" . $this->db->escape($this->session->data['mi_escrow_choice']) . "',
                    `date_modified` = NOW()
                    WHERE `order_id` = '" . (int)$this->session->data['order_id'] . "'");
            } else {
                $this->db->query("INSERT INTO `" . DB_PREFIX . "mi_escrow` SET
                    `order_id` = '" . (int)$this->session->data['order_id'] . "',
                    `escrow_type` = '" . $this->db->escape($this->session->data['mi_escrow_choice']) . "',
                    `status` = 'pending',
                    `date_added` = NOW(),
                    `date_modified` = NOW()");
            }
        }

        $this->response->redirect($this->url->link('extension/payment/plisio/checkout', '', true));
    }

    public function confirm() {
        if (!isset($this->session->data['order_id'])) {
            $this->response->redirect($this->url->link('account/account', '', true));
        }

        $this->load->model('checkout/order');
        $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);

        $escrow_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "mi_escrow` WHERE `order_id` = '" . (int)$order_info['order_id'] . "'");

        if ($escrow_query->num_rows && $escrow_query->row['escrow_type'] == 'escrow_15day') {
            $data['order_id'] = $order_info['order_id'];
            $data['total'] = $this->currency->format($order_info['total'], $order_info['currency_code']);
            $data['release_date'] = date('d M Y', strtotime('+20 days'));

            // FIX: Link to the Escrow Center for this specific order
            $data['account_order'] = $this->url->link('extension/module/mi_escrow/center', 'order_id=' . $order_info['order_id'], true);

            $data['header'] = $this->load->controller('common/header');
            $data['footer'] = $this->load->controller('common/footer');

            $this->response->setOutput($this->load->view('extension/module/mi_escrow_confirm', $data));
        } else {
            $this->response->redirect($this->url->link('checkout/success', '', true));
        }
    }

            public function center() {
        if (!$this->customer->isLogged()) {
            $this->session->data['redirect'] = $this->url->link('extension/module/mi_escrow/center', '', true);
            $this->response->redirect($this->url->link('account/login', '', true));
        }

        $this->load->model('checkout/order');
        $order_id = isset($this->request->get['order_id']) ? (int)$this->request->get['order_id'] : 0;

        // ── IF NO ORDER ID: Show the list of all escrow orders ──
        if (!$order_id) {
            $escrow_orders = $this->db->query("SELECT e.*, o.order_id, o.total, o.currency_code, o.date_added 
                FROM `" . DB_PREFIX . "mi_escrow` e 
                LEFT JOIN `" . DB_PREFIX . "order` o ON (e.order_id = o.order_id) 
                WHERE o.customer_id = '" . (int)$this->customer->getId() . "' 
                ORDER BY e.date_added DESC");

            $data['escrow_orders'] = array();
            foreach ($escrow_orders->rows as $row) {
                $data['escrow_orders'][] = array(
                    'order_id'    => $row['order_id'],
                    'total'       => $this->currency->format($row['total'], $row['currency_code']),
                    'status'      => $row['status'],
                    'escrow_type' => $row['escrow_type'],
                    'date_added'  => date('d M Y', strtotime($row['date_added'])),
                    'href'        => $this->url->link('extension/module/mi_escrow/center', 'order_id=' . $row['order_id'], true)
                );
            }

            $data['breadcrumbs'] = array(
                array('text' => 'Home', 'href' => $this->url->link('common/home')),
                array('text' => 'Account', 'href' => $this->url->link('account/account', '', true)),
                array('text' => 'Escrow Center', 'href' => $this->url->link('extension/module/mi_escrow/center', '', true))
            );

            $data['header'] = $this->load->controller('common/header');
            $data['footer'] = $this->load->controller('common/footer');
            
            // This loads the list template
            $this->response->setOutput($this->load->view('extension/module/mi_escrow_center_list', $data));
            return;
        }

        // ── IF ORDER ID IS PROVIDED: Show the single order details ──
        $order_info = $this->model_checkout_order->getOrder($order_id);

        if (!$order_info || $order_info['customer_id'] != $this->customer->getId()) {
            $this->response->redirect($this->url->link('account/order', '', true));
        }

        $escrow_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "mi_escrow` WHERE `order_id` = '" . (int)$order_id . "'");

        if ($escrow_query->num_rows) {
            $data['escrow'] = $escrow_query->row;
            $data['order_id'] = $order_id;
            $data['total'] = $this->currency->format($order_info['total'], $order_info['currency_code']);
            
            // Check if payment is still pending
            if ($data['escrow']['status'] == 'pending') {
                $data['is_pending'] = true;
            } else {
                $data['is_pending'] = false;
                $data['release_date'] = $data['escrow']['release_date'] ? date('d M Y', strtotime($data['escrow']['release_date'])) : date('d M Y', strtotime('+20 days'));
                $data['action_release'] = $this->url->link('extension/module/mi_escrow/release', '', true);
                $data['action_dispute'] = $this->url->link('extension/module/mi_escrow/dispute', '', true);
            }

            $data['breadcrumbs'] = array(
                array('text' => 'Home', 'href' => $this->url->link('common/home')),
                array('text' => 'Account', 'href' => $this->url->link('account/account', '', true)),
                array('text' => 'Orders', 'href' => $this->url->link('account/order', '', true)),
                array('text' => 'Escrow Center', 'href' => $this->url->link('extension/module/mi_escrow/center', 'order_id=' . $order_id, true))
            );

            $data['header'] = $this->load->controller('common/header');
            $data['footer'] = $this->load->controller('common/footer');
            
            // This loads the single order template
            $this->response->setOutput($this->load->view('extension/module/mi_escrow_center', $data));
        } else {
            $this->response->redirect($this->url->link('account/order', '', true));
        }
    }

    public function release() {
        $json = array();

        if ($this->request->server['REQUEST_METHOD'] == 'POST' && isset($this->request->post['order_id'])) {
            $order_id = (int)$this->request->post['order_id'];

            // Verify the customer owns this order
            if (!$this->customer->isLogged()) {
                $json['error'] = 'You must be logged in.';
            } else {
                $this->load->model('checkout/order');
                $order_info = $this->model_checkout_order->getOrder($order_id);

                if (!$order_info || $order_info['customer_id'] != $this->customer->getId()) {
                    $json['error'] = 'Order not found.';
                } else {
                    // ═══════════════════════════════════════════════════════════════
                    // TODO: COMPANY BACKEND INTEGRATION POINT (RELEASE)
                    // Call your third-party escrow API here to release the funds.
                    // Example: $this->company_escrow_api->releaseFunds($order_id);
                    // ═══════════════════════════════════════════════════════════════

                    $this->db->query("UPDATE `" . DB_PREFIX . "mi_escrow` SET
                        `status` = 'released',
                        `release_date` = NOW(),
                        `date_modified` = NOW()
                        WHERE `order_id` = '" . $order_id . "'");

                    $json['success'] = 'Funds released successfully. Thank you for your order!';
                }
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function dispute() {
        $json = array();

        if ($this->request->server['REQUEST_METHOD'] == 'POST' && isset($this->request->post['order_id'])) {
            $order_id = (int)$this->request->post['order_id'];
            $reason = isset($this->request->post['reason']) ? strip_tags($this->request->post['reason']) : '';
            $details = isset($this->request->post['details']) ? strip_tags($this->request->post['details']) : '';

            if (!$this->customer->isLogged()) {
                $json['error'] = 'You must be logged in.';
            } elseif (!$reason) {
                $json['error'] = 'Please select a reason.';
            } else {
                $this->load->model('checkout/order');
                $order_info = $this->model_checkout_order->getOrder($order_id);

                if (!$order_info || $order_info['customer_id'] != $this->customer->getId()) {
                    $json['error'] = 'Order not found.';
                } else {
                    // ═══════════════════════════════════════════════════════════════
                    // TODO: COMPANY BACKEND INTEGRATION POINT (DISPUTE)
                    // Call your third-party escrow API here to pause the countdown.
                    // Example: $this->company_escrow_api->pauseEscrow($order_id);
                    // ═══════════════════════════════════════════════════════════════

                    $this->db->query("UPDATE `" . DB_PREFIX . "mi_escrow` SET
                        `status` = 'disputed',
                        `dispute_reason` = '" . $this->db->escape($reason) . "',
                        `dispute_details` = '" . $this->db->escape($details) . "',
                        `date_modified` = NOW()
                        WHERE `order_id` = '" . $order_id . "'");

                    // ── Email The Miner Island ──────────────────────────────────────
                    $subject = "Escrow Dispute - Order #" . $order_id;

                    $message = "Order Number: " . $order_id . "\n";
                    $message .= "Customer: " . $order_info['firstname'] . " " . $order_info['lastname'] . "\n";
                    $message .= "Email: " . $order_info['email'] . "\n";
                    $message .= "Reason: " . $reason . "\n\n";
                    $message .= "Details:\n" . $details . "\n\n";
                    $message .= "---\nSent from The Miner Island Escrow Center";

                    $mail = new Mail($this->config->get('config_mail_engine'));
                    $mail->parameter = $this->config->get('config_mail_parameter');
                    $mail->smtp_hostname = $this->config->get('config_mail_smtp_hostname');
                    $mail->smtp_username = $this->config->get('config_mail_smtp_username');
                    $mail->smtp_password = html_entity_decode($this->config->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8');
                    $mail->smtp_port = $this->config->get('config_mail_smtp_port');
                    $mail->smtp_timeout = $this->config->get('config_mail_smtp_timeout');

                    // Email to the company
                    $mail->setTo($this->config->get('config_email'));
                    $mail->setFrom($this->config->get('config_email'));
                    $mail->setSender(html_entity_decode($this->config->get('config_name'), ENT_QUOTES, 'UTF-8'));
                    $mail->setSubject(html_entity_decode($subject, ENT_QUOTES, 'UTF-8'));
                    $mail->setHtml(nl2br($message));
                    $mail->send();

                    // Confirmation email to the customer
                    $mail->setTo($order_info['email']);
                    $mail->setSubject("Your Escrow Dispute has been received - Order #" . $order_id);
                    $mail->setHtml("<p>Dear " . $order_info['firstname'] . ",</p><p>We have received your dispute for Order #" . $order_id . ". Our team is reviewing it and will contact you within 24 hours. The escrow countdown has been paused.</p><p><strong>Reason:</strong> " . $reason . "</p><p><strong>Details:</strong><br>" . nl2br($details) . "</p><p>— The Miner Island Escrow Team</p>");
                    $mail->send();

                    $json['success'] = 'Dispute submitted. Our team has been emailed and will contact you within 24 hours.';
                }
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
}