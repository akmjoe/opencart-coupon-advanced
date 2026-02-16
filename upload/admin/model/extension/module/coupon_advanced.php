<?php 

class ModelExtensionModuleCouponAdvanced extends Model {
    
        public function getCouponAdvanced($coupon_id) {
            $query = $this->db->query("select customer_group_id, repeating, max_discount from ".DB_PREFIX."coupon where coupon_id = '".(int)$coupon_id."'");
            return $query->row;
        }

	public function getCouponProductsExclude($coupon_id) {
		$coupon_product_data = array();

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "coupon_product_exclude WHERE coupon_id = '" . (int)$coupon_id . "'");

		foreach ($query->rows as $result) {
			$coupon_product_data[] = $result['product_id'];
		}

		return $coupon_product_data;
	}

	public function getCouponCategoriesExclude($coupon_id) {
		$coupon_category_data = array();

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "coupon_category_exclude WHERE coupon_id = '" . (int)$coupon_id . "'");

		foreach ($query->rows as $result) {
			$coupon_category_data[] = $result['category_id'];
		}

		return $coupon_category_data;
	}
        
        public function saveCouponAdvanced($coupon_id, $data) {
                $this->db->query("update ".DB_PREFIX."coupon set customer_group_id = '".(int)$data['customer_group_id']."', repeating = '".(isset($data['repeating'])?1:0)."', max_discount = '".floatval($data['max_discount'])."' where coupon_id = '".(int)$coupon_id."'");
		// save product/category excludes
		$this->db->query("DELETE FROM " . DB_PREFIX . "coupon_product_exclude WHERE coupon_id = '" . (int)$coupon_id . "'");
		if (isset($data['coupon_product_exclude'])) {
			foreach ($data['coupon_product_exclude'] as $product_id) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "coupon_product_exclude SET coupon_id = '" . (int)$coupon_id . "', product_id = '" . (int)$product_id . "'");
			}
		}

		$this->db->query("DELETE FROM " . DB_PREFIX . "coupon_category_exclude WHERE coupon_id = '" . (int)$coupon_id . "'");

		if (isset($data['coupon_category_exclude'])) {
			foreach ($data['coupon_category_exclude'] as $category_id) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "coupon_category_exclude SET coupon_id = '" . (int)$coupon_id . "', category_id = '" . (int)$category_id . "'");
			}
		}
        }
}