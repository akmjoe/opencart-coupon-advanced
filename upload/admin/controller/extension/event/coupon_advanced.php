<?php
class controllerExtensionEventCouponAdvanced extends Controller {
	
	public function view(&$view, &$data, &$output) {// triggered before view coupon form
		if(!$this->config->get('module_coupon_advanced_status')) {
                    return;
                }

		$this->language->load('extension/module/coupon_advanced', 'coupon_advanced');
                $data['tab_restriction'] = $this->language->get('coupon_advanced')->get('tab_restriction');
                $data['entry_customer_group'] = $this->language->get('coupon_advanced')->get('entry_customer_group');
                $data['entry_repeating'] = $this->language->get('coupon_advanced')->get('entry_repeating');
                $data['entry_expires'] = $this->language->get('coupon_advanced')->get('entry_expires');
                $data['entry_coupon'] = $this->language->get('coupon_advanced')->get('entry_coupon');
                $data['entry_max'] = $this->language->get('coupon_advanced')->get('entry_max');
                $data['help_customer_group'] = $this->language->get('coupon_advanced')->get('help_customer_group');
                $data['help_repeating'] = $this->language->get('coupon_advanced')->get('help_repeating');
                $data['help_customer'] = $this->language->get('coupon_advanced')->get('help_customer');
                $data['help_max'] = $this->language->get('coupon_advanced')->get('help_max');

                $this->load->model('extension/module/coupon_advanced');
                
                $data['customer_group_id'] = 0;
                $data['repeating'] = 0;
                $data['max_discount'] = 0;
		if($this->request->server['REQUEST_METHOD'] == 'POST') {
			// override with post
			$data['customer_group_id'] = $this->request->post['customer_group_id'];
			$data['repeating'] = $this->request->post['repeating']?1:0;
			$data['max_discount'] = $this->request->post['max_discount'];
		} elseif(isset($this->request->get['coupon_id'])) {
                        $info = $this->model_extension_module_coupon_advanced->getCouponAdvanced($this->request->get['coupon_id']);
                        $data['customer_group_id'] = (int)$info['customer_group_id'];
                        $data['repeating'] = (int)$info['repeating'];
                        $data['max_discount'] = (int)$info['max_discount'];
                }
		// get list of customer groups
		$this->load->model('customer/customer_group');
		$data['customer_groups'] = $this->model_customer_customer_group->getCustomerGroups();
		// get product excludes
		$this->load->model('extension/module/coupon_advanced');
		if (isset($this->request->post['coupon_product_exclude'])) {
			$products = $this->request->post['coupon_product_exclude'];
		} elseif (isset($this->request->get['coupon_id'])) {
			$products = $this->model_extension_module_coupon_advanced->getCouponProductsExclude($this->request->get['coupon_id']);
		} else {
			$products = array();
		}

		$this->load->model('catalog/product');

		$data['coupon_product_exclude'] = array();

		foreach ($products as $product_id) {
			$product_info = $this->model_catalog_product->getProduct($product_id);

			if ($product_info) {
				$data['coupon_product_exclude'][] = array(
					'product_id' => $product_info['product_id'],
					'name'       => $product_info['model'].' '.$product_info['name']
				);
			}
		}
		// get category excludes
		if (isset($this->request->post['coupon_category_exclude'])) {
			$categories = $this->request->post['coupon_category_exclude'];
		} elseif (isset($this->request->get['coupon_id'])) {
			$categories = $this->model_extension_module_coupon_advanced->getCouponCategoriesExclude($this->request->get['coupon_id']);
		} else {
			$categories = array();
		}

		$this->load->model('catalog/category');

		$data['coupon_category_exclude'] = array();

		foreach ($categories as $category_id) {
			$category_info = $this->model_catalog_category->getCategory($category_id);

			if ($category_info) {
				$data['coupon_category_exclude'][] = array(
					'category_id' => $category_info['category_id'],
					'name'        => ($category_info['path'] ? $category_info['path'] . ' &gt; ' : '') . $category_info['name']
				);
			}
		}
		// load tab template
		$data['header'] .= $this->load->view('extension/module/coupon_advanced_form', $data);
	}
	
	public function save(&$route, &$data, &$output = null) {
		if(!$this->config->get('module_coupon_advanced_status')) return;
		if((int)$output) {
			$coupon_id = $output;
			$temp = $data[0];
		} else {
			$temp = $data[1];
			$coupon_id = $data[0];
		}
                $this->load->model('extension/module/coupon_advanced');
                $this->model_extension_module_coupon_advanced->saveCouponAdvanced($coupon_id, $temp);
                return;
		// save extra parameters
		$this->db->query("update ".DB_PREFIX."coupon set customer_group_id = '".(int)$temp['customer_group_id']."', repeating = '".(int)$temp['repeating']."', max_discount = '".floatval($temp['max_discount'])."' where coupon_id = '".(int)$coupon_id."'");
		// save product/category excludes
		$this->db->query("DELETE FROM " . DB_PREFIX . "coupon_product_exclude WHERE coupon_id = '" . (int)$coupon_id . "'");
		if (isset($temp['coupon_product_exclude'])) {
			foreach ($temp['coupon_product_exclude'] as $product_id) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "coupon_product_exclude SET coupon_id = '" . (int)$coupon_id . "', product_id = '" . (int)$product_id . "'");
			}
		}

		$this->db->query("DELETE FROM " . DB_PREFIX . "coupon_category_exclude WHERE coupon_id = '" . (int)$coupon_id . "'");

		if (isset($temp['coupon_category_exclude'])) {
			foreach ($temp['coupon_category_exclude'] as $category_id) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "coupon_category_exclude SET coupon_id = '" . (int)$coupon_id . "', category_id = '" . (int)$category_id . "'");
			}
		}
	}
}
