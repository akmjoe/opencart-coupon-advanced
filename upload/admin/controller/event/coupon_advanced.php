<?php
class ControllerEventCouponAdvanced extends Controller {
	
	public function view(&$view, &$data, &$output) {// triggered before view product form
		if(!$this->config->get('module_coupon_advanced_status')) return;
		// build insert html
		$info = $this->language->load('extension/module/coupon_advanced');
		if(isset($this->request->get['coupon_id']) && $this->request->get['coupon_id']) {
			$query = $this->db->query("select customer_group_id, repeating from ".DB_PREFIX."coupon where coupon_id = '".(int)$this->request->get['coupon_id']."'");
			$info = array_merge($query->row, $info);
		} else {
			$info['customer_group_id'] = 0;
			$info['repeating'] = 0;
		}
		if($this->request->server['REQUEST_METHOD'] == 'POST') {
			// override with post
			$info['customer_group_id'] = $this->request->post['customer_group_id'];
			$info['repeating'] = $this->request->post['repeating']?1:0;
		}
		/** repeating discount **/
		$insert = '<div class="form-group" id="div-repeat"'.($data['type'] == 'F'?'':'style="display:none"').'><label class="col-sm-2 control-label">';
		$insert .= '	<span data-toggle="tooltip" title="'.$this->language->get('help_repeating').'">';
		$insert .= $this->language->get('entry_repeating');
		$insert .= '	</span></label>';
		$insert .= '	<div class="col-sm-10">';
		$insert .= '		<input type="checkbox" name="repeating" value="1"';
		$insert .= ((int)$info['repeating'])?' checked="checked">':'>';
		$insert .= '	</div>';
		$insert .= '</div>';
		/** restrictions tab **/
		// get list of customer groups
		$this->load->model('customer/customer_group');
		$info['customer_groups'] = $this->model_customer_customer_group->getCustomerGroups();
		$info['user_token'] = $data['user_token'];
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

		$info['coupon_product_exclude'] = array();

		foreach ($products as $product_id) {
			$product_info = $this->model_catalog_product->getProduct($product_id);

			if ($product_info) {
				$info['coupon_product_exclude'][] = array(
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

		$info['coupon_category_exclude'] = array();

		foreach ($categories as $category_id) {
			$category_info = $this->model_catalog_category->getCategory($category_id);

			if ($category_info) {
				$info['coupon_category_exclude'][] = array(
					'category_id' => $category_info['category_id'],
					'name'        => ($category_info['path'] ? $category_info['path'] . ' &gt; ' : '') . $category_info['name']
				);
			}
		}
		// load tab template
		$tab = $this->load->view('extension/module/coupon_advanced_form', $info);
		// add html to page
		$this->load->helper('simple_html_dom');
		$html = str_get_html($output);
		$html->find('#input-type',0)->onchange = "document.getElementById('div-repeat').style.display = (this.value == 'F')?'':'none'";
		$html->find('#input-discount',0)->parent()->parent()->outertext .= $insert;
		
		$html->find('#tab-general',0)->outertext .= $tab;
		$html->find('a[href="#tab-general"]',0)->parent()->outertext .= '<li><a href="#tab-restriction" data-toggle="tab">'.$this->language->get('tab_restriction').'</a></li>';
		$output = $html->save();
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
		// save extra parameters
		$this->db->query("update ".DB_PREFIX."coupon set customer_group_id = '".(int)$temp['customer_group_id']."', repeating = '".(int)$temp['repeating']."' where coupon_id = '".(int)$coupon_id."'");
		// save product/category excludes
		$this->db->query("DELETE FROM " . DB_PREFIX . "coupon_product_exclude WHERE coupon_id = '" . (int)$coupon_id . "'");
		$this->log->write($temp);
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
