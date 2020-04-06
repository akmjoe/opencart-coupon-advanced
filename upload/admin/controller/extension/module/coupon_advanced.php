<?php
class ControllerExtensionModuleCouponAdvanced extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('extension/module/coupon_advanced');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');
		
		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->model_setting_setting->editSetting('module_coupon_advanced', $this->request->post);

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true));
		}

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
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
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/module/coupon_advanced', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['action'] = $this->url->link('extension/module/coupon_advanced', 'user_token=' . $this->session->data['user_token'], true);

		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);

		if (isset($this->request->post['module_coupon_advanced_shipping'])) {
			$data['module_coupon_advanced_shipping'] = $this->request->post['module_coupon_advanced_shipping'];
		} else {
			$data['module_coupon_advanced_shipping'] = $this->config->get('module_coupon_advanced_shipping');
		}

		if (isset($this->request->post['module_coupon_advanced_total'])) {
			$data['module_coupon_advanced_total'] = $this->request->post['module_coupon_advanced_total'];
		} else {
			$data['module_coupon_advanced_total'] = $this->config->get('module_coupon_advanced_total');
		}
		
		if (isset($this->request->post['module_coupon_advanced_status'])) {
			$data['module_coupon_advanced_status'] = $this->request->post['module_coupon_advanced_status'];
		} else {
			$data['module_coupon_advanced_status'] = $this->config->get('module_coupon_advanced_status');
		}
		
		if (isset($this->request->post['module_coupon_advanced_voucher'])) {
			$data['module_coupon_advanced_voucher'] = $this->request->post['module_coupon_advanced_voucher'];
		} else {
			$data['module_coupon_advanced_voucher'] = $this->config->get('module_coupon_advanced_voucher');
		}
		
		$this->load->model('customer/customer_group');
		$data['customer_groups'] = $this->model_customer_customer_group->getCustomerGroups();
		foreach($data['customer_groups'] as &$customer_group) {
			// coupon code
			if (isset($this->request->post['module_coupon_advanced_coupon_'.$customer_group['customer_group_id']])) {
				$customer_group['coupon'] = $this->request->post['module_coupon_advanced_coupon_'.$customer_group['customer_group_id']];
			} else {
				$customer_group['coupon'] = $this->config->get('module_coupon_advanced_coupon_'.$customer_group['customer_group_id']);
			}
			// expiration date
			if (isset($this->request->post['module_coupon_advanced_expires_'.$customer_group['customer_group_id']])) {
				$customer_group['expires'] = $this->request->post['module_coupon_advanced_expires_'.$customer_group['customer_group_id']];
			} else {
				$customer_group['expires'] = $this->config->get('module_coupon_advanced_expires_'.$customer_group['customer_group_id']);
			}
			// errors
			if (isset($this->error['coupon']) && isset($this->error['coupon'][$customer_group['customer_group_id']])) {
				$customer_group['error'] = $this->error['coupon'][$customer_group['customer_group_id']];
			} else {
				$customer_group['error'] = '';
			}
		}
		
		$this->load->model('marketing/coupon');
		$data['coupons'] = $this->model_marketing_coupon->getCoupons();
		
		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->load->model('setting/event');
		$this->response->setOutput($this->load->view('extension/module/coupon_advanced', $data));
	}
	
	public function install() {
		// add db fields
		$this->db->query('ALTER TABLE `'.DB_PREFIX.'coupon` ADD `repeating` tinyint(1) not null default 0');
		$this->db->query('ALTER TABLE `'.DB_PREFIX.'coupon` ADD `customer_group_id` int(11) not null default 0');
		$this->db->query('CREATE TABLE `'.DB_PREFIX.'coupon_category_exclude` (`coupon_id` int(11) NOT NULL,`category_id` int(11) NOT NULL,PRIMARY KEY (`coupon_id`,`category_id`)) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;');
		$this->db->query('CREATE TABLE `'.DB_PREFIX.'coupon_product_exclude` (`coupon_product_id` int(11) NOT NULL AUTO_INCREMENT,`coupon_id` int(11) NOT NULL,`product_id` int(11) NOT NULL,PRIMARY KEY (`coupon_product_id`)) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;');
		$this->db->query('CREATE TABLE `'.DB_PREFIX.'customer_coupon` (`customer_id` int(11) NOT NULL, `coupon_id` int(11) NOT NULL, date_start date NOT NULL, date_end date NOT NULL,PRIMARY KEY (`customer_id`)) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;');
		// set up event handlers
		$this->load->model('setting/event');
		// Modify admin page
		$this->model_setting_event->addEvent('coupon_advanced', 'admin/view/marketing/coupon_form/after', 'extension/event/coupon_advanced/view');
		$this->model_setting_event->addEvent('coupon_advanced', 'admin/model/marketing/coupon/addCoupon/after', 'extension/event/coupon_advanced/save');
		$this->model_setting_event->addEvent('coupon_advanced', 'admin/model/marketing/coupon/editCoupon/after', 'extension/event/coupon_advanced/save');
		// Modify total/coupon model
		$this->model_setting_event->addEvent('coupon_advanced', 'catalog/model/extension/total/coupon/getCoupon/before', 'extension/event/coupon_advanced/get_coupon');
		$this->model_setting_event->addEvent('coupon_advanced', 'catalog/model/extension/total/coupon/getTotal/before', 'extension/event/coupon_advanced/get_total');
		// catch order confirm
		$this->model_setting_event->addEvent('coupon_advanced','catalog/model/checkout/order/addOrderHistory/before','extension/event/coupon_advanced/before_confirm');
		$this->model_setting_event->addEvent('coupon_advanced','catalog/model/checkout/order/addOrderHistory/after','extension/event/coupon_advanced/confirm');
		// Add coupon to payment checkout tab
		$this->model_setting_event->addEvent('coupon_advanced','catalog/view/checkout/payment_method/after','extension/event/coupon_advanced/payment');
	}
	
	public function uninstall() {
		$this->load->model('setting/event');
		$this->model_setting_event->deleteEventByCode('coupon_advanced');
		$this->db->query('ALTER TABLE `'.DB_PREFIX.'coupon` drop `repeating`');
		$this->db->query('ALTER TABLE `'.DB_PREFIX.'coupon` drop `customer_group_id`');
		$this->db->query('DROP TABLE `'.DB_PREFIX.'coupon_category_exclude`');
		$this->db->query('DROP TABLE `'.DB_PREFIX.'coupon_product_exclude`');
		$this->db->query('DROP TABLE `'.DB_PREFIX.'customer_coupon`');
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/module/coupon_advanced')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}
		$this->load->model('customer/customer_group');
		
		foreach($this->model_customer_customer_group->getCustomerGroups() as $group) {
			if(!$this->request->post['module_coupon_advanced_coupon_'.$group['customer_group_id']]) continue;
			$result = $this->db->query('select * from '.DB_PREFIX.'coupon where coupon_id = '.(int)$this->request->post['module_coupon_advanced_coupon_'.$group['customer_group_id']]);
			if($result->row['customer_group_id'] && $result->row['customer_group_id'] != $group['customer_group_id']) {
				$this->error['coupon'][$group['customer_group_id']] = $this->language->get('error_coupon');
			}
			if(!$this->request->post['module_coupon_advanced_expires_'.$group['customer_group_id']]) {
				$this->error['coupon'][$group['customer_group_id']] = $this->language->get('error_expires');
			}
		}

		return !$this->error;
	}
}