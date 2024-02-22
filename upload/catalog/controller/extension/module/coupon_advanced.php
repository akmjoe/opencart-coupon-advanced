<?php
class ControllerExtensionModuleCouponAdvanced extends Controller {
	public function index() {
		if ($this->config->get('total_coupon_status')) {
			$this->load->language('extension/total/coupon');

			if (isset($this->session->data['coupon'])) {
				$data['coupon'] = $this->session->data['coupon'];
			} else {
				$data['coupon'] = '';
			}

			return $this->load->view('extension/module/coupon', $data);
		}
	}

	public function coupon() {
		$this->load->language('extension/total/coupon');

		$json = array();

		$this->load->model('extension/module/coupon_advanced');

		if (isset($this->request->post['coupon'])) {
			$coupon = $this->request->post['coupon'];
		} else {
			$coupon = '';
		}
		
		$message = new stdClass();
		
		$coupon_info = $this->model_extension_module_coupon_advanced->getCoupon($coupon, $message);

		if (empty($this->request->post['coupon'])) {
			$json['error'] = $this->language->get('error_empty');

			unset($this->session->data['coupon']);
		} elseif ($coupon_info) {
			if(isset($message->warning)) $json['error'] = $message->warning;
			$this->session->data['coupon'] = $this->request->post['coupon'];

			$json['message'] = $this->language->get('text_success');

		} else {
			$json['error'] = isset($message->error)?$message->error:$this->language->get('error_coupon');
			
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
	
	public function clear() {
		$this->load->language('extension/total/coupon');
		$this->load->language('extension/module/coupon_advanced');

		$json = array();

		unset($this->session->data['coupon']);
		
		$json['message'] = $this->language->get('message_coupon_clear');

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
