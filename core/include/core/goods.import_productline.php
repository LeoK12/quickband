<?php
function goods_import_productline(&$aData, &$aPdtDesc , &$object){
        if($list = $object->db->selectrow('select product_id from sdb_products where bn=\''.$aData['bn'].'\'')){
            $aData['product_id'] = $list['product_id'];
        }
        $aData['last_modify'] = time();
        if(!$aData['price'])$aData['price'] = 0;
        if($aData['product_id']){
            $rs = $object->db->query('SELECT * FROM sdb_products WHERE product_id='.$aData['product_id']);
            $sql = $object->db->GetUpdateSQL($rs, $aData);
            if($sql && !$object->db->exec($sql)){
                trigger_error('SQL Error:'.$sql,E_USER_NOTICE);
                return false;
            }
        }else{
            $aData['uptime'] = time();
            $rs = $object->db->query('SELECT * FROM sdb_products WHERE 0=1');
            $sql = $object->db->GetInsertSQL($rs, $aData);
            if($sql && !$object->db->exec($sql)){
                trigger_error('SQL Error:'.$sql,E_USER_NOTICE);
                return false;
            }
            $aData['product_id'] = $object->db->lastInsertId();
        }
        //非单货品商品，则处理商品表货品定义列
        if($aData['pdt_desc']){
            $aPdtDesc['pdt_desc'][$aData['product_id']] = $aData['pdt_desc'];
            if($aPdtDesc['store'] === '' || $aPdtDesc['store'] === NULL || $aData['store'] === '' || $aData['store'] === NULL){
                $aPdtDesc['store'] = '';
            }else{
                $aPdtDesc['store'] = $aData['store'];
            }
        }
        //处理会员价
        $mprice[0]['goods_id'] = $aData['goods_id'];
        $mprice[0]['product_id'] = $aData['product_id'];
        $mprice[0]['price'] = array();
        foreach($aData as $k=>$v){
            if(substr($k,0,2)=='m_'){
                $mprice[0]['price'][intval(substr($k,2))] = $v;
            }
        }
        $goods = $object->system->loadModel('trading/goods');

        $goods->addMemberPrice($mprice);
}
?>