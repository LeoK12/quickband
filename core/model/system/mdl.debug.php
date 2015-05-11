<?php
class mdl_debug extends modelFactory{

    function startShopMode(){
        if(defined('MODE_SWITCHER')){
            $mode_switcher = MODE_SWITCHER;
            require_once(PLUGIN_DIR.'/functions/'.$switcher.'.php');
            $switcher = new $mode_switcher;
            if(false===$mode_switcher->start())return false;
        }
        unlink(HOME_DIR.'/notice.html');
        return true;
    }

    function stopShopMode($data){
        if(defined('MODE_SWITCHER')){
            $mode_switcher = MODE_SWITCHER;
            require_once(PLUGIN_DIR.'/functions/'.$switcher.'.php');
            $switcher = new $mode_switcher;
            if(false===$mode_switcher->stop())return false;
        }
        file_put_contents(HOME_DIR.'/notice.html',$data);
        return true;
    }

    function clearData(){
        $data = unserialize($this->system->getConf("system.test.database"));
        $brand_file=MEDIA_DIR.'/brand_list.data';
        $cat_file=MEDIA_DIR.'/goods_cat.data';
        $virtual_cat=MEDIA_DIR.'/goods_virtual_cat.data';
        if(is_file($brand_file)){
            unlink($brand_file);
        }
        if(is_file($cat_file)){
            unlink($cat_file);
        }
        if(is_file($virtual_cat)){
            unlink($virtual_cat);
        }
        if(is_array($data)){
            $this->db->exec('delete from sdb_articles where article_id <='.$data['article']['max']);
            $this->db->exec('delete from sdb_goods where goods_id <='.$data['goods']['max']);
            $this->db->exec('delete from sdb_goods_cat where cat_id <='.$data['goods_cat']['max']);
            $this->db->exec('delete from sdb_goods_lv_price');
            $this->db->exec('delete from sdb_goods_rate');
            $this->db->exec('delete from sdb_goods_memo where goods_id <='.$data['goods_memo']['max']);
            $this->db->exec("delete from sdb_goods_type where is_def='false' and type_id <=".$data['goods_type']['max']);
            $this->db->exec('delete from sdb_package_product where product_id <='.$data['package_product']['max']);
            $this->db->exec('delete from sdb_product_memo');
            $this->db->exec('delete from sdb_products where product_id <='.$data['products']['max']);
            $this->db->exec('delete from sdb_spec_values where spec_value_id <='.$data['spec_values']['max']);
            $this->db->exec('delete from sdb_specification where spec_id <='.$data['specification']['max']);
            $this->db->exec('delete from sdb_type_brand where type_id <='.$data['type_brand']['max']);
            $this->db->exec('delete from sdb_brand where brand_id <='.$data['brand']['max']);
            $this->db->exec('delete from sdb_gnotify');
            $this->db->exec('delete from sdb_goods_virtual_cat where virtual_cat_id <='.$data['goods_virtual_cat']['max']);
            $this->db->exec('delete from sdb_status where last_update <='.$data['status']['max']);
            $this->db->exec('delete from sdb_gimages where gimage_id <='.$data['gimages']['max']);
        }else{
            $this->db->exec('delete from sdb_articles');
            $this->db->exec('delete from sdb_goods');
            $this->db->exec('delete from sdb_goods_cat');
            $this->db->exec('delete from sdb_goods_lv_price');
            $this->db->exec('delete from sdb_goods_rate');
            $this->db->exec('delete from sdb_goods_memo');
            $this->db->exec("delete from sdb_goods_type where is_def='false'");
            $this->db->exec('delete from sdb_package_product');
            $this->db->exec('delete from sdb_product_memo');
            $this->db->exec('delete from sdb_products');
            $this->db->exec('delete from sdb_spec_values');
            $this->db->exec('delete from sdb_specification');
            $this->db->exec('delete from sdb_type_brand');
            $this->db->exec('delete from sdb_brand');
            $this->db->exec('delete from sdb_gnotify');
            $this->db->exec('delete from sdb_goods_virtual_cat');
            $this->db->exec('delete from sdb_status');
            $this->db->exec('delete from sdb_gimages');
        }


    }
}
?>
