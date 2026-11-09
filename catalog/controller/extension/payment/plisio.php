<?php

require_once(DIR_SYSTEM . 'library/plisio/PlisioClient.php');
require_once(DIR_SYSTEM . 'library/plisio/version.php');

class ControllerExtensionPaymentPlisio extends Controller
{
    /** @var PlisioClient */
    private $plisio;
    private $secretKey;

    public function index()
    {
        $this->load->language('extension/payment/plisio');
        $this->load->model('checkout/order');
        $this->setupPlisioClient();

        $shop = $this->plisio->getShopInfo();
        $data = [];
        $data['white_label'] = $shop['data']['white_label'] ?? false;

        if (!isset($data['button_confirm'])) {
            $data['button_confirm'] = $this->language->get('button_confirm');
        }
        $data['fail'] = $this->session->data['fail'] ?? false;
        $data['action'] = $this->url->link('extension/payment/plisio/checkout', '', true);

        return $this->load->view('extension/payment/plisio', $data);
    }

    public function checkout()
    {
        // ═══════════════════════════════════════════════════════════════
        // ESCROW KILL SWITCH
        // Set to 'false' to disable escrow and revert to normal checkout
        // ═══════════════════════════════════════════════════════════════
        $mi_escrow_enabled = true;
        $mi_escrow_threshold = 5000;

        if ($mi_escrow_enabled && isset($this->session->data['order_id'])) {
            $this->load->model('checkout/order');
            $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);

            if (isset($order_info['total']) && $order_info['total'] >= $mi_escrow_threshold) {
                if (!isset($this->session->data['mi_escrow_choice'])) {
                    $this->response->redirect($this->url->link('extension/module/mi_escrow/select', '', true));
                } else {
                    // Choice has been consumed for this order — clear it
                    // so the NEXT order triggers the escrow selection again
                    unset($this->session->data['mi_escrow_choice']);
                }
            }
        }
        // ═══════════════════════════════════════════════════════════════

        $this->setupPlisioClient();
        $this->load->model('checkout/order');
        $this->load->model('extension/payment/plisio');

        $orderId = $this->session->data['order_id'];
        $order_info = $this->model_checkout_order->getOrder($orderId);
        $shop = $this->plisio->getShopInfo();

        $description = [];

        foreach ($this->cart->getProducts() as $product) {
            $description[] = $product['quantity'] . ' × ' . $product['name'];
        }

        $amount = $order_info['total'] * $this->currency->getvalue($order_info['currency_code']);

        $siteTitle = is_array($this->config->get('config_meta_title')) ? implode(',', $this->config->get('config_meta_title')) : $this->config->get('config_meta_title');
        $orderName = $siteTitle . ' Order #' . $order_info['order_id'];
        $request = array(
            'source_amount' => number_format($amount, 8, '.', ''),
            'source_currency' => $order_info['currency_code'],
            'order_name' => $orderName,
            'order_number' => $order_info['order_id'],
            'description' => implode(',', $description),
            'cancel_url' => $this->url->link('extension/payment/plisio/callback', '', true),
            'callback_url' => $this->url->link('extension/payment/plisio/callback', '', true),
            'success_url' => $this->url->link('extension/payment/plisio/success', '', true),
            'email' => $order_info['email'],
            'plugin' => 'opencart',
            'version' => PLISIO_OPENCART_EXTENSION_VERSION
        );

        $response = $this->plisio->createTransaction($request);

        if ($response && $response['status'] !== 'error' && !empty($response['data'])) {
            $orderData = array(
                'order_id' => $order_info['order_id'],
                'plisio_invoice_id' => $response['data']['txn_id']
            );
            $orderData = array_merge($orderData, $response['data']);
            $this->model_extension_payment_plisio->addOrder($orderData);
            $this->model_checkout_order->addOrderHistory($order_info['order_id'], $this->config->get('payment_plisio_order_status_id'));
            $this->cart->clear();
            $this->session->data['fail'] = false;
            if (isset($shop['data']['white_label']) && $shop['data']['white_label']) {
                $this->response->redirect($this->url->link('extension/payment/plisio/invoice', '', true));
            } else {
                $this->response->redirect($response['data']['invoice_url']);
            }
        } else {
            $this->log->write("Order #" . $order_info['order_id'] . " is not valid. " . (isset($response['data']) && isset($response['data']['message'])) ? $response['data']['message'] : '');
            $this->session->data['fail'] = implode(',', json_decode($response['data']['message'], true));
            $this->response->redirect($this->url->link('checkout/checkout', '', true));
        }
    }

    public function invoice()
    {
        $this->load->model('checkout/order');
        $this->load->language('extension/payment/plisio');
        $this->load->model('extension/payment/plisio');
        $this->setupPlisioClient();

        $orderId = isset($this->session->data['order_id']) ? $this->session->data['order_id'] : null;

        if (!$orderId){
            $this->response->redirect($this->url->link('common/home', '', true));
        }

        $plisioOrder = $this->model_extension_payment_plisio->getOrder($orderId);
        if (!$plisioOrder){
            $this->response->redirect($this->url->link('common/home', '', true));
        }

        $data = [];
        $data['plisio_invoice_id'] = $plisioOrder['plisio_invoice_id'];

        $order_info = $this->model_checkout_order->getOrder($orderId);
        if (empty($order_info)) {
            $this->response->redirect($this->url->link('common/home', '', true));
        }

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/home')
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_checkout'),
            'href' => $this->url->link('checkout/checkout', '', true)
        );

        $data['footer'] = $this->load->controller('common/footer');
        $data['header'] = $this->load->controller('common/header');

        $this->response->setOutput($this->load->view('extension/payment/plisio_invoice', $data));
    }

    public function cancel()
    {
        $this->response->redirect($this->url->link('checkout/cart', ''));
    }

    /**
     * PAYMENT GUARD: Returns true only when the order has been genuinely
     * marked as paid by the Plisio callback. Uses the configured paid
     * status ID so it stays correct if statuses change in admin.
     */
    private function orderIsPaid($order_id)
    {
        $paid_status_id = (int)$this->config->get('payment_plisio_paid_status_id');
        if ($paid_status_id <= 0) {
            // Fallback: Plisio defaults usually map to "Processing" (2)
            $paid_status_id = 2;
        }

        // Also accept any status beyond paid (e.g. shipped, complete)
        $accepted_status_ids = array(
            $paid_status_id,
            (int)$this->config->get('config_complete_status_id') ?: 5, // Shipped/Complete
            15, // Delivered (typical default)
        );
        $accepted_status_ids = array_unique(array_filter($accepted_status_ids, function($id) {
            return $id > 0;
        }));

        $this->load->model('checkout/order');
        $order = $this->model_checkout_order->getOrder($order_id);

        if (!$order) {
            return false;
        }

        return in_array((int)$order['order_status_id'], $accepted_status_ids);
    }

    /**
     * JSON endpoint polled by the invoice page every 5 seconds.
     * Returns { "paid": true/false } based on real order status.
     */
    public function paid()
    {
        $json = array('paid' => false);

        if (isset($this->session->data['order_id'])) {
            $json['paid'] = $this->orderIsPaid((int)$this->session->data['order_id']);
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * GUARDED "continue" route. Bounces unpaid users back to the invoice
     * instead of letting them reach the success page without a real payment.
     * The "Click here to continue" link in the Twig points here.
     */
    public function verify()
    {
        $order_id = isset($this->session->data['order_id']) ? (int)$this->session->data['order_id'] : 0;

        if (!$order_id || !$this->orderIsPaid($order_id)) {
            // Not paid — send them back to where they came from
            $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
            $fallback = $this->url->link('extension/payment/plisio/invoice', '', true);
            $this->response->redirect($referer ?: $fallback);
            return;
        }

        // Order genuinely paid — safe to proceed
        $this->response->redirect($this->url->link('checkout/success', '', true));
    }
    /**
     * RESUME PAYMENT: lets a logged-in customer return to the Plisio invoice
     * for a pending order. Validates ownership, regenerates the invoice if the
     * previous one expired (Plisio invoices expire after 60 minutes), then
     * sets the session order_id and redirects to the invoice page.
     */
    public function resume()
    {
        // Must be logged in
        if (!$this->customer->isLogged()) {
            $this->session->data['redirect'] = $this->url->link('extension/payment/plisio/resume', 'order_id=' . (int)($this->request->get['order_id'] ?? 0), true);
            $this->response->redirect($this->url->link('account/login', '', true));
            return;
        }

        $order_id = isset($this->request->get['order_id']) ? (int)$this->request->get['order_id'] : 0;
        if (!$order_id) {
            $this->response->redirect($this->url->link('account/order', '', true));
            return;
        }

        $this->load->model('checkout/order');
        $order_info = $this->model_checkout_order->getOrder($order_id);

        // Security: order must exist and belong to this customer
        if (!$order_info || (int)$order_info['customer_id'] !== (int)$this->customer->getId()) {
            $this->response->redirect($this->url->link('account/order', '', true));
            return;
        }

        // Only allow resume on unpaid/awaiting-payment orders.
        // 'payment_plisio_order_status_id' is the initial "Pending Plisio" status.
        $awaiting_status_id = (int)$this->config->get('payment_plisio_order_status_id');
        $pending_status_id  = (int)$this->config->get('config_order_status_id') ?: 1;
        $allowed_statuses = array_filter(array_unique(array($awaiting_status_id, $pending_status_id, 0)));

        if (!in_array((int)$order_info['order_status_id'], $allowed_statuses, true)) {
            $this->response->redirect($this->url->link('account/order', '', true));
            return;
        }

        $this->load->model('extension/payment/plisio');
        $this->setupPlisioClient();

        $plisioOrder = $this->model_extension_payment_plisio->getOrder($order_id);
        $needs_new_invoice = true;

        // Check if the existing Plisio invoice is still within its 60-min window.
        // We use a 55-min cutoff so customers who resume at minute 58 don't
        // land on an expired invoice.
        if ($plisioOrder && !empty($plisioOrder['plisio_invoice_id'])) {
            $created_ts = !empty($plisioOrder['date_added'])
                ? strtotime($plisioOrder['date_added'])
                : strtotime($order_info['date_added']);
            $elapsed_minutes = (time() - $created_ts) / 60;

            if ($elapsed_minutes < 55) {
                $needs_new_invoice = false;
            }
        }

        if ($needs_new_invoice) {
            // Generate a fresh Plisio invoice for this order
            $shop = $this->plisio->getShopInfo();

            $description = [];
            $this->load->model('extension/payment/plisio');
            // Rebuild cart items list from the order products
            $products = $this->db->query("SELECT name, quantity FROM `" . DB_PREFIX . "order_product` WHERE order_id = " . $order_id);
            foreach ($products->rows as $p) {
                $description[] = $p['quantity'] . ' × ' . $p['name'];
            }

            $amount = $order_info['total'] * $this->currency->getvalue($order_info['currency_code']);
            $siteTitle = is_array($this->config->get('config_meta_title'))
                ? implode(',', $this->config->get('config_meta_title'))
                : $this->config->get('config_meta_title');

            $request = array(
                'source_amount'    => number_format($amount, 8, '.', ''),
                'source_currency'  => $order_info['currency_code'],
                'order_name'       => $siteTitle . ' Order #' . $order_info['order_id'],
                'order_number'     => $order_info['order_id'],
                'description'      => implode(',', $description),
                'cancel_url'       => $this->url->link('extension/payment/plisio/callback', '', true),
                'callback_url'     => $this->url->link('extension/payment/plisio/callback', '', true),
                'success_url'      => $this->url->link('extension/payment/plisio/success', '', true),
                'email'            => $order_info['email'],
                'plugin'           => 'opencart',
                'version'          => PLISIO_OPENCART_EXTENSION_VERSION
            );

            $response = $this->plisio->createTransaction($request);

            if ($response && $response['status'] !== 'error' && !empty($response['data'])) {
                $orderData = array(
                    'order_id'          => $order_info['order_id'],
                    'plisio_invoice_id' => $response['data']['txn_id']
                );
                $orderData = array_merge($orderData, $response['data']);
                // Update the existing row, or insert if missing
                if ($plisioOrder) {
                    $this->model_extension_payment_plisio->updateOrder($orderData);
                } else {
                    $this->model_extension_payment_plisio->addOrder($orderData);
                }
            } else {
                // Failed to generate new invoice — send back to order list
                $this->response->redirect($this->url->link('account/order', '', true));
                return;
            }
        }

        // Set session and redirect to the invoice page
        $this->session->data['order_id'] = $order_id;
        $this->response->redirect($this->url->link('extension/payment/plisio/invoice', '', true));
    }
    /**
     * SUCCESS route: called after Plisio redirects back.
     * Hardened so it only advances paid orders — if someone lands here
     * without a real payment (e.g. by typing the URL), they get bounced
     * back to the invoice page.
     */
    public function success()
    {
        $order_id = isset($this->session->data['order_id']) ? (int)$this->session->data['order_id'] : 0;

        if (!$order_id) {
            // No session order — nothing to continue
            $this->response->redirect($this->url->link('common/home', '', true));
            return;
        }

        // HARD GUARD: only proceed if the callback has actually marked it paid.
        // Plisio's postMessage / iframe redirect can fire before the webhook
        // has set the status, so we double-check here. If unpaid, send them
        // back to the invoice page to wait for confirmations.
        if (!$this->orderIsPaid($order_id)) {
            $this->response->redirect($this->url->link('extension/payment/plisio/invoice', '', true));
            return;
        }

        // ── ESCROW BRANCH: flip escrow status to 'held' and show confirmation ──
        if (isset($this->session->data['mi_escrow_choice']) && $this->session->data['mi_escrow_choice'] == 'escrow_15day') {
            $this->db->query("UPDATE `" . DB_PREFIX . "mi_escrow` SET `status` = 'held', `date_modified` = NOW() WHERE `order_id` = '" . (int)$order_id . "'");
            $this->response->redirect($this->url->link('extension/module/mi_escrow/confirm', '', true));
            return;
        }

        // ── NORMAL BRANCH: standard OpenCart success page ──
        $this->response->redirect($this->url->link('checkout/success', '', true));
    }

    function verifyCallbackData($post)
    {
        $secretKey = $this->secretKey;
        if (!isset($post['verify_hash'])) {
            return false;
        }

        $verifyHash = $post['verify_hash'];
        unset($post['verify_hash']);
        ksort($post);
        if (isset($post['expire_utc'])){
            $post['expire_utc'] = (string)$post['expire_utc'];
        }
        if (isset($post['tx_urls'])){
            $post['tx_urls'] = html_entity_decode($post['tx_urls']);
        }
        $postString = serialize($post);
        $checkKey = hash_hmac('sha1', $postString, $secretKey);
        if ($checkKey != $verifyHash) {
            return false;
        }

        return true;
    }

    public function callback()
    {
        $this->setupPlisioClient();
        if ($this->verifyCallbackData($this->request->post)) {
            $this->load->model('checkout/order');
            $this->load->model('extension/payment/plisio');

            $order_id = $this->request->post['order_number'];
            $order_info = $this->model_checkout_order->getOrder($order_id);

            $data = $this->request->post;

            if (!empty($order_info)) {
                $ext_order = $this->model_extension_payment_plisio->getOrder($order_id);
                if (!empty($ext_order) && isset($ext_order['wallet_hash']) && !empty($ext_order['wallet_hash'])) {
                    $data['plisio_invoice_id'] = $data['txn_id'];
                    $data['order_id'] = $order_id;
                    if (isset($data['tx_urls'])){
                        $data['tx_urls'] = html_entity_decode($data['tx_urls']);
                    }
                    $this->model_extension_payment_plisio->updateOrder($data);
                }

                switch ($data['status']) {
                    case 'completed':
                    case 'mismatch':
                        $cg_order_status = 'payment_plisio_paid_status_id';

                        // FULL PAYMENT: start the 20-day escrow counter.
                        // Flips pending/partial -> held. Never touches an
                        // escrow that is already held, released or disputed.
                        $this->db->query("UPDATE `" . DB_PREFIX . "mi_escrow` SET `status` = 'held', `date_modified` = NOW() WHERE `order_id` = '" . (int)$order_id . "' AND `status` NOT IN ('held', 'released', 'disputed')");
                        break;

                    case 'pending':
                        // PARTIAL PAYMENT: mark as partial but DO NOT start the
                        // 20-day counter. Guard: Plisio also fires a 'pending'
                        // callback when the invoice is first created, so we only
                        // mark partial when a broadcast transaction actually
                        // exists (tx_urls) or an incoming amount is reported.
                        if (!empty($data['tx_urls']) || (isset($data['pending_amount']) && (float)$data['pending_amount'] > 0)) {
                            $this->db->query("UPDATE `" . DB_PREFIX . "mi_escrow` SET `status` = 'partial', `date_modified` = NOW() WHERE `order_id` = '" . (int)$order_id . "' AND `status` = 'pending'");
                        }
                        $cg_order_status = NULL; // Keep OpenCart order status unchanged until fully paid
                        break;

                    case 'cancelled':
                        $cg_order_status = 'payment_plisio_canceled_status_id';
                        break;
                    case 'expired':
                        if ($data['source_amount'] > 0) {
                            $cg_order_status = 'payment_plisio_invalid_status_id';
                        } else {
                            $cg_order_status = 'payment_plisio_canceled_status_id';
                        }
                        break;
                    default:
                        $cg_order_status = NULL;
                }

                if (!is_null($cg_order_status)) {
                    $comment = '';
                    if (isset($data['comment']) && !empty($data['comment'])) {
                        $comment = $data['comment'];
                    }
                    $this->model_checkout_order->addOrderHistory($order_id, $this->config->get($cg_order_status), $comment/*, true*/);
                }
            } else {
                $this->log->write('Plisio order with id '. $order_id . ' not found');
            }
            $this->response->addHeader('HTTP/1.1 200 OK');
        } else {
            $this->log->write('Plisio response looks suspicious. Skip updating order');
            $this->response->addHeader('HTTP/1.1 400 Bad Request');
        }
    }

    private function setupPlisioClient()
    {
        $this->load->model('setting/setting');
        $this->secretKey = $this->model_setting_setting->getSettingValue('payment_plisio_api_secret_key');
        $this->plisio = new PlisioClient($this->secretKey);
    }
}