<?php
class ControllerCheckoutSuccess extends Controller {
	public function index() {
		$this->load->language('checkout/success');

		// S19 ANALYTICS: capture the order id before the session is cleared so the
		// template can fire a one-time GA4 purchase event. The session unset below
		// guarantees the event cannot fire again on reload.
		$mi_order_id = 0;

		if (isset($this->session->data['order_id'])) {
			$mi_order_id = (int)$this->session->data['order_id'];

			$this->cart->clear();

			unset($this->session->data['shipping_method']);
			unset($this->session->data['shipping_methods']);
			unset($this->session->data['payment_method']);
			unset($this->session->data['payment_methods']);
			unset($this->session->data['guest']);
			unset($this->session->data['comment']);
			unset($this->session->data['order_id']);
			unset($this->session->data['coupon']);
			unset($this->session->data['reward']);
			unset($this->session->data['voucher']);
			unset($this->session->data['vouchers']);
			unset($this->session->data['totals']);
		}

		// S19 ANALYTICS: build the GA4 purchase payload (json) for the template.
		$data['mi_purchase'] = false;

		if ($mi_order_id) {
			$order_query = $this->db->query("SELECT total, currency_code FROM " . DB_PREFIX . "order WHERE order_id = '" . (int)$mi_order_id . "'");

			if ($order_query->num_rows) {
				$product_query = $this->db->query("SELECT product_id, name, price, quantity FROM " . DB_PREFIX . "order_product WHERE order_id = '" . (int)$mi_order_id . "'");

				$mi_items = array();

				foreach ($product_query->rows as $mi_p) {
					$mi_items[] = array(
						'item_id'   => (string)$mi_p['product_id'],
						'item_name' => $mi_p['name'],
						'price'     => round((float)$mi_p['price'], 2),
						'quantity'  => (int)$mi_p['quantity']
					);
				}

				$data['mi_purchase'] = json_encode(array(
					'transaction_id' => (string)$mi_order_id,
					'value'          => round((float)$order_query->row['total'], 2),
					'currency'       => $order_query->row['currency_code'] ? $order_query->row['currency_code'] : 'USD',
					'items'          => $mi_items
				));
			}
		}

		$this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home')
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_basket'),
			'href' => $this->url->link('checkout/cart')
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_checkout'),
			'href' => $this->url->link('checkout/checkout', '', true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_success'),
			'href' => $this->url->link('checkout/success')
		);

		if ($this->customer->isLogged()) {
			$data['text_message'] = sprintf($this->language->get('text_customer'), $this->url->link('account/account', '', true), $this->url->link('account/order', '', true), $this->url->link('account/download', '', true), $this->url->link('information/contact'));
		} else {
			$data['text_message'] = sprintf($this->language->get('text_guest'), $this->url->link('information/contact'));
		}

		$data['continue'] = $this->url->link('common/home');

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('common/success', $data));
	}
}