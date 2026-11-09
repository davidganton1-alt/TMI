<?php
// The Miner Island, Session 17: crypto payment discount total.
// Applies a 3% discount on the product sub-total ONLY when the crypto
// (Plisio) payment method is the selected payment method. Shipping is
// not discounted. Because the Plisio invoice amount is taken from the
// order total, this discount automatically reduces the crypto amount too.
class ModelExtensionTotalCryptoDiscount extends Model {
    public function getTotal($total) {
        // Only when the crypto (Plisio) payment method is selected.
        if (empty($this->session->data['payment_method']['code']) || $this->session->data['payment_method']['code'] != 'plisio') {
            return;
        }

        // 3% of the product sub-total (products only, shipping excluded).
        $sub_total = $this->cart->getSubTotal();

        if ($sub_total <= 0) {
            return;
        }

        $discount = round($sub_total * 0.03, 2);

        if ($discount <= 0) {
            return;
        }

        $total['totals'][] = array(
            'code'       => 'crypto_discount',
            'title'      => 'Crypto Payment Discount (3%)',
            'value'      => -$discount,
            'sort_order' => $this->config->get('total_crypto_discount_sort_order')
        );

        $total['total'] -= $discount;
    }
}
