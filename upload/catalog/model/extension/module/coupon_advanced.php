<?php
class ModelExtensionModuleCouponAdvanced extends Model {
	public function getCoupon($code, $message = null) {// $message will be set to the reason this coupon cannot be used
		if($message === null) $message = new stdClass ();
		$status = true;
		$this->language->load('extension/module/coupon_advanced');

		$coupon_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "coupon` WHERE code = '" . $this->db->escape($code) . "' AND ((date_start = '0000-00-00' OR date_start < NOW()) AND (date_end = '0000-00-00' OR date_end > NOW())) AND status = '1'");
		if(!$coupon_query->num_rows) {
			// check customer coupon
			$coupon_query = $this->db->query("SELECT c.* FROM `" . DB_PREFIX . "coupon` c JOIN `".DB_PREFIX."customer_coupon` cc on c.coupon_id = cc.coupon_id WHERE cc.customer_id = '" . $this->db->escape($code) . "' AND ((cc.date_start = '0000-00-00' OR cc.date_start < NOW()) AND (cc.date_end = '0000-00-00' OR cc.date_end > NOW()))");
		}
		if ($coupon_query->num_rows) {
			// check if correct customer type
			if($coupon_query->row['customer_group_id'] && $coupon_query->row['customer_group_id'] != $this->customer->getGroupId()) {
				$status = false;
				$message->error = $this->language->get('error_type');
			}
			if ($coupon_query->row['total'] > $this->cart->getSubTotal()) {
				$status = false;
				$message->error = $this->language->get('error_total');
			}
			$this->load->model('extension/total/coupon');
			$coupon_total = $this->model_extension_total_coupon->getTotalCouponHistoriesByCoupon($code);

			if ($coupon_query->row['uses_total'] > 0 && ($coupon_total >= $coupon_query->row['uses_total'])) {
				$status = false;
				$message->error = $this->language->get('error_uses');
			}

			if ($coupon_query->row['logged'] && !$this->customer->getId()) {
				$status = false;
				$message->error = $this->language->get('error_logged');
			}

			if ($this->customer->getId()) {
				$customer_total = $this->model_extension_total_coupon->getTotalCouponHistoriesByCustomerId($code, $this->customer->getId());
				
				if ($coupon_query->row['uses_customer'] > 0 && ($customer_total >= $coupon_query->row['uses_customer'])) {
					$status = false;
					$message->error = $this->language->get('error_customer_uses');
				}
			}

			// Products
			$coupon_product_data = array();

			$coupon_product_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "coupon_product` WHERE coupon_id = '" . (int)$coupon_query->row['coupon_id'] . "'");

			foreach ($coupon_product_query->rows as $product) {
				$coupon_product_data[] = $product['product_id'];
			}
			// Product excludes
			$coupon_product_exclude = array();

			$coupon_product_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "coupon_product_exclude` WHERE coupon_id = '" . (int)$coupon_query->row['coupon_id'] . "'");

			foreach ($coupon_product_query->rows as $product) {
				$coupon_product_exclude[] = $product['product_id'];
			}

			// Categories
			$coupon_category_data = array();

			$coupon_category_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "coupon_category` cc LEFT JOIN `" . DB_PREFIX . "category_path` cp ON (cc.category_id = cp.path_id) WHERE cc.coupon_id = '" . (int)$coupon_query->row['coupon_id'] . "'");

			foreach ($coupon_category_query->rows as $category) {
				$coupon_category_data[] = $category['category_id'];
			}
			// Category excludes
			$coupon_category_exclude = array();

			$coupon_category_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "coupon_category_exclude` cc LEFT JOIN `" . DB_PREFIX . "category_path` cp ON (cc.category_id = cp.path_id) WHERE cc.coupon_id = '" . (int)$coupon_query->row['coupon_id'] . "'");

			foreach ($coupon_category_query->rows as $category) {
				$coupon_category_exclude[] = $category['category_id'];
			}

			$product_data = array();
			$product_exclude = array();
			$sub_total = 0;
			
			if ($coupon_product_data || $coupon_category_data || $coupon_product_exclude || $coupon_category_exclude) {
				foreach ($this->cart->getProducts() as $product) {
					if (in_array($product['product_id'], $coupon_product_exclude)) {
						// specifically excluded
						$product_exclude[] = $product['product_id'];
						continue;
					}
					if (in_array($product['product_id'], $coupon_product_data)) {
						// specifically allowed
						$product_data[] = $product['product_id'];
						$sub_total += $product['total'];
						
						continue;
					}
					foreach ($coupon_category_exclude as $category_id) {
						$coupon_category_query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "product_to_category` WHERE `product_id` = '" . (int)$product['product_id'] . "' AND category_id = '" . (int)$category_id . "'");

						if ($coupon_category_query->row['total']) {
							// category excluded
							$product_exclude[] = $product['product_id'];
							continue;
						}
					}

					foreach ($coupon_category_data as $category_id) {
						$coupon_category_query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "product_to_category` WHERE `product_id` = '" . (int)$product['product_id'] . "' AND category_id = '" . (int)$category_id . "'");

						if ($coupon_category_query->row['total']) {
							// cattegory allowed
							$product_data[] = $product['product_id'];
							$sub_total += $product['total'];

							continue;
						}
					}
					// not allowed, check if excluded
					if($coupon_product_data || $coupon_category_data) {
						// not in allowed list, must be excluded
						$product_exclude[] = $product['product_id'];
					} else {
						// no allowed list, include by default
						$product_data[] = $product['product_id'];
						$sub_total += $product['total'];
					}
				}
				
				if($coupon_query->row['shipping'] && count($product_exclude)) {
					$items = array();
					$this->load->model('catalog/product');
					foreach($product_exclude as $exclude) {
						$items[] = $this->model_catalog_product->getProduct($exclude)['model'];
					}
					$message->warning = $this->language->get('error_shipping', implode(', ', $items));
				}

				if (!$product_data) {
					$status = false;
					$message->error = $this->language->get('error_products');
				}
				
				if ($coupon_query->row['total'] > $sub_total && $this->config->get('module_coupon_advanced_total')) {
					$status = false;
					$message->error = sprintf($this->language->get('error_total'),$coupon_query->row['total'],$sub_total);
				}
			}
		} else {
			$message->error = $this->language->get('error_not_found');
			$status = false;
		}

		if ($status) {
			return array(
				'coupon_id'     => $coupon_query->row['coupon_id'],
				'code'          => $coupon_query->row['code'],
				'name'          => $coupon_query->row['name'],
				'type'          => $coupon_query->row['type'],
				'discount'      => $coupon_query->row['discount'],
				'shipping'      => $coupon_query->row['shipping'],
				'total'         => $coupon_query->row['total'],
				'product'       => $product_data,
				'exclude'       => $product_exclude,
				'date_start'    => $coupon_query->row['date_start'],
				'date_end'      => $coupon_query->row['date_end'],
				'uses_total'    => $coupon_query->row['uses_total'],
				'uses_customer' => $coupon_query->row['uses_customer'],
				'status'        => $coupon_query->row['status'],
				'date_added'    => $coupon_query->row['date_added'],
				'repeating'		=> $coupon_query->row['repeating'],
			);
		}
		return false;
	}

	public function getTotal($total) {
		// first loop through and unset default
		foreach($total['totals'] as &$totals) {
			if($totals['code'] == 'coupon') unset($totals);
		}
		if (isset($this->session->data['coupon'])) {
			$this->load->language('extension/total/coupon', 'coupon');

			$coupon_info = $this->getCoupon($this->session->data['coupon']);

			if ($coupon_info) {
				$discount_total = 0;

				if (!$coupon_info['product']) {
					$sub_total = $this->cart->getSubTotal();
				} else {
					$sub_total = 0;

					foreach ($this->cart->getProducts() as $product) {
						if (in_array($product['product_id'], $coupon_info['product'])) {
							$sub_total += $product['total'];
						}
					}
				}

				if ($coupon_info['type'] == 'F' && $coupon_info['repeating'] && $coupon_info['total']) {// repeating discount
					$coupon_info['discount'] = min(floor($sub_total/$coupon_info['total'])*$coupon_info['discount'], $sub_total);
				} elseif ($coupon_info['type'] == 'F') {
					$coupon_info['discount'] = min($coupon_info['discount'], $sub_total);
				}

				foreach ($this->cart->getProducts() as $product) {
					$discount = 0;

					if (!$coupon_info['product']) {
						$status = true;
					} else {
						$status = in_array($product['product_id'], $coupon_info['product']);
					}

					if ($status) {
						if ($coupon_info['type'] == 'F') {
							$discount = $coupon_info['discount'] * ($product['total'] / $sub_total);
						} elseif ($coupon_info['type'] == 'P') {
							$discount = $product['total'] / 100 * $coupon_info['discount'];
						}

						if ($product['tax_class_id']) {
							$tax_rates = $this->tax->getRates($product['total'] - ($product['total'] - $discount), $product['tax_class_id']);

							foreach ($tax_rates as $tax_rate) {
								if ($tax_rate['type'] == 'P') {
									$total['taxes'][$tax_rate['tax_rate_id']] -= $tax_rate['amount'];
								}
							}
						}
					}

					$discount_total += $discount;
				}
				/** Free shipping **/
				if ($coupon_info['shipping'] && isset($this->session->data['shipping_method']) && !count($coupon_info['exclude'])) {// disabled if excluded products
					if($this->config->get('module_coupon_advanced_shipping')) {
						// Get lowest shipping cost
						$shipping = $this->session->data['shipping_method']['cost'];
						foreach($this->session->data['shipping_methods'] as $shipping_methods) {
							foreach($shipping_methods['quote'] as $quote) {
								if($quote['cost'] < $shipping)  $shipping = $quote['cost'];
							}
						}
						if (!empty($this->session->data['shipping_method']['tax_class_id'])) {
							$tax_rates = $this->tax->getRates($shipping, $this->session->data['shipping_method']['tax_class_id']);

							foreach ($tax_rates as $tax_rate) {
								if ($tax_rate['type'] == 'P') {
									$total['taxes'][$tax_rate['tax_rate_id']] -= $tax_rate['amount'];
								}
							}
						}

						$discount_total += $shipping;

					} elseif ($coupon_info['shipping'] && isset($this->session->data['shipping_method'])) {
						if (!empty($this->session->data['shipping_method']['tax_class_id'])) {
							$tax_rates = $this->tax->getRates($this->session->data['shipping_method']['cost'], $this->session->data['shipping_method']['tax_class_id']);

							foreach ($tax_rates as $tax_rate) {
								if ($tax_rate['type'] == 'P') {
									$total['taxes'][$tax_rate['tax_rate_id']] -= $tax_rate['amount'];
								}
							}
						}

						$discount_total += $this->session->data['shipping_method']['cost'];
					}
				}

				// If discount greater than total
				if ($discount_total > $total['total']) {
					$discount_total = $total['total'];
				}

				if ($discount_total > 0) {
					$total['totals'][] = array(
						'code'       => 'coupon',
						'title'      => sprintf($this->language->get('coupon')->get('text_coupon'), $this->session->data['coupon']),
						'value'      => -$discount_total,
						'sort_order' => $this->config->get('total_coupon_sort_order')
					);

					$total['total'] -= $discount_total;
				}
			}
		}
		return false;
	}
	
	public function cloneCoupon() {
		if(!(int)$this->config->get('module_coupon_advanced_coupon_'.$this->customer->getGroupId())) return;
		$result = $this->db->query('select * from '.DB_PREFIX.'coupon where coupon_id = '.(int)$this->config->get('module_coupon_advanced_coupon_'.$this->customer->getGroupId()));
		$data = $result->row;
		$data['date_start'] = date('Y-m-d');
		$data['date_end'] = date('Y-m-d',strtotime($this->config->get('module_coupon_advanced_expires_'.$this->customer->getGroupId()).' days'));
		$data['customer_id'] = $this->customer->getId();
		$result = $this->db->query('select customer_id from '.DB_PREFIX.'customer_coupon where customer_id = '.$this->customer->getId());
		if($result->num_rows) {
			$this->cCouponEdit($data);
		} else {
			$this->cCouponAdd($data);
		}
	}
	
	public function customerCoupon($message = null) {
		if($message === null) $message = new stdClass ();
		
		if(isset($this->session->data['coupon']) && $this->session->data['coupon']) {// there already is a coupon - abort
			$message->notice = $this->language->get('error_coupon_set');
			return false;
		}
		// try to set the customer coupon
		
		if(!$this->customer->getId()) {
			$message->notice = $this->language->get('error_logged');
			return false;
		}
		$coupon = $this->getCoupon($this->customer->getId(), $message);
		$this->log->write($coupon);
		
		if($coupon) {
			$this->session->data['coupon'] = $this->customer->getId();
			$message->success = $this->language->get('text_auto');
			return true;
		}
	}
	
	public function cCouponAdd($data) {
		$this->db->query("insert into ".DB_PREFIX."customer_coupon set customer_id = '".(int)$data['customer_id']."', coupon_id = '".(int)$data['coupon_id']."', date_start = '".$this->db->escape($data['date_start'])."', date_end = '".$this->db->escape($data['date_end'])."'");
	}
	
	public function cCouponEdit($data) {
		$this->db->query("update ".DB_PREFIX."customer_coupon set coupon_id = '".(int)$data['coupon_id']."', date_start = '".$this->db->escape($data['date_start'])."', date_end = '".$this->db->escape($data['date_end'])."' where customer_id = '".(int)$data['customer_id']."'");
	}
}