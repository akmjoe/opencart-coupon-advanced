<?php

/* 
 * Events to handle product level stock handling
 * (can backorder and can order)
 */
class ControllerEventCouponAdvanced extends Controller {
    
	public function get_total(&$route, &$data, &$output = null) {
		if(!$this->config->get('module_coupon_advanced_status')) return;
		$this->load->model('extension/module/coupon_advanced');
		return $this->model_extension_module_coupon_advanced->getTotal($data[0]);
	}
	
	public function get_coupon(&$route, &$data, &$output = null) {
		if(!$this->config->get('module_coupon_advanced_status')) return;
		$this->load->model('extension/module/coupon_advanced');
		
		$return = $this->model_extension_module_coupon_advanced->getCoupon($data[0]);
		$this->log->write($return);
		return $return;
	}
	
	// before model/checkout/order/addOrderHistory
	public function before_confirm(&$route, &$data = array(), &$output = '') {
		if(!$this->config->get('module_coupon_advanced_status')) return;
		// get current order status
		$this->load->model('checkout/order');
		$order_info = $this->model_checkout_order->getOrder($data[0]);
		$this->session->data['pre_order_status_id'] = $order_info['order_status_id'];
	}
	// after model/checkout/order/addOrderHistory
	public function confirm(&$route, &$data = array(), &$output = '') {
		if(!$this->config->get('module_coupon_advanced_status')) return;
		if($this->session->data['pre_order_status_id'] == 0 && (int)$data[1]) {
			// went from unconfirmed to confirmed - generate coupon
			$this->load->model('extension/module/coupon_advanced');
			$this->model_extension_module_coupon_advanced->cloneCoupon();
			unset($this->session->data['pre_order_status_id']);
		}
	}
    
}
