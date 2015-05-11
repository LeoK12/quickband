<?php
function goods_get_filter($p , &$object){

        /*
        $return['cats']=1;
        $return['brands']=2;
        $return['tags']=3;
        $return['prices']=4;
        return $return;
        */
        $cat = &$object->system->loadModel('goods/productCat');
        if(!$object->catMap){
            $object->catMap = $cat->getMapTree(0,'');
        }

        $return['cats'] = $object->catMap;

        if($cat_id = $p['cat_id']){

            $p = $cat->get($cat_id);
            $return['props'] = $p['props'];
            $brand = $object->system->loadModel('goods/brand');
            $return['brands'] = $brand->getAll();
            $return['cat_id'] = $p['cat_id'];

            $row = $object->db->selectrow('SELECT max(price) as max,min(price) as min FROM sdb_goods where cat_id='.intval($cat_id));
        }else{
            $brand = $object->system->loadModel('goods/brand');
            $return['brands'] = $brand->getAll();

            $row = $object->db->selectrow('SELECT max(price) as max,min(price) as min FROM sdb_products ');
        }

        $modTag = $object->system->loadModel('system/tag');
        $return['tags'] = $modTag->tagList('goods');
        
        $supplier = $this->system->loadModel('distribution/supplier');
        $return['supplier'] = $supplier->getList('supplier_id,supplier_brief_name','',0,-1);

        if($p['goods_id']){
            $oGoods = $object->system->loadModel('trading/goods');
            $return['keywords'] = $oGoods->getKeywords($p['goods_id']);
        }

        $return['prices'] = steprange($row['min'],$row['max'],5);
        return $return;
}
?>