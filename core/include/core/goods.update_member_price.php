<?php
function goods_update_member_price($goods_id, $updateLvId, $updateValue, $operator=null , $fromName = null , &$object){
        $aallProductId = $object->db->select('SELECT product_id,goods_id FROM sdb_products WHERE goods_id IN ('.implode(',',$goods_id).')');
        $aupdateProductId = $object->db->select('SELECT product_id,goods_id FROM sdb_goods_lv_price WHERE goods_id IN ('.implode(',',$goods_id).') AND level_id = '.$updateLvId);

        $allProductId = array();
        $updateProductId = array();
        foreach( $aallProductId as $allv )
            $allProductId[$allv['product_id']] = $allv['goods_id'];
        foreach( $aupdateProductId as $alluv )
            $updateProductId[$alluv['product_id']] = $alluv['goods_id'];
        unset($aallProductId, $aupdateProductId);
        $insertProductId = array_diff_assoc( $allProductId, $updateProductId);

        if( $operator ){
            if( $updateValue ){
                if( $fromName && is_numeric($fromName) ){        //用会员价修改会员价
                    foreach( $updateProductId as $upProId => $upGoodsId ){
                        $dataRow = $object->db->selectrow('SELECT price FROM sdb_goods_lv_price WHERE level_id = '.$fromName.' AND product_id = '.$upProId.' AND goods_id = '.$upGoodsId);
                        $object->db->exec('UPDATE sdb_goods_lv_price SET price = '.$dataRow['price'].$operator.floatval($updateValue).' WHERE goods_id = '.$upGoodsId.' AND level_id = '.$updateLvId.' AND product_id = '.$upProId);
                    }
                    foreach( $insertProductId as $inProId => $inGoodsId ){
                        $dataRow = $object->db->selectrow('SELECT price FROM sdb_goods_lv_price WHERE level_id = '.$fromName.' AND product_id = '.$inProId.' AND goods_id = '.$inGoodsId);
                        $object->db->exec('INSERT INTO sdb_goods_lv_price ( product_id, level_id, goods_id, price ) VALUES ('.$inProId.', '.$updateLvId.', '.$inGoodsId.', '.$dataRow['price'].$operator.floatval($updateValue).')');
                    }
                }else{          //用市场价、销售价、成本价修改会员价
                    foreach( $updateProductId as $upProId => $upGoodsId ){
                        $dataRow = array();
                        if( $fromName == 'price' )
                            $dataRow = $object->db->selectrow('SELECT '.$fromName.' AS price FROM sdb_products WHERE product_id = '.$upProId);
                        else
                            $dataRow = $object->db->selectrow('SELECT '.$fromName.' AS price FROM sdb_goods WHERE goods_id = '.$upGoodsId);
                        $object->db->exec('UPDATE sdb_goods_lv_price SET price = '.$dataRow['price'].$operator.floatval($updateValue).' WHERE product_id = '.$upProId.' AND goods_id = '.$upGoodsId.' AND level_id = '.$updateLvId);
                    }
                    foreach( $insertProductId as $inProId => $inGoodsId ){
                        $dataRow = array();
                        if( $fromName == 'price' )
                            $dataRow = $object->db->selectrow('SELECT '.$fromName.' AS price FROM sdb_products WHERE product_id = '.$inProId);
                        else
                            $dataRow = $object->db->selectrow('SELECT '.$fromName.' AS price FROM sdb_goods WHERE goods_id = '.$inGoodsId);
                        $object->db->exec('INSERT INTO sdb_goods_lv_price ( product_id, level_id, goods_id, price ) VALUES ('.$inProId.', '.$updateLvId.', '.$inGoodsId.', '.$dataRow['price'].$operator.floatval($updateValue).')');
                     }
                }
            }

        }else{
             if( $updateValue != null && $updateValue !='' ){
                foreach( $updateProductId as $upProId => $upGoodsId ){
                    $object->db->exec( 'UPDATE sdb_goods_lv_price SET price = '.floatval($updateValue).' WHERE goods_id = '.intval($upGoodsId).' AND level_id = '.intval($updateLvId).' AND product_id = '.intval($upProId));
                }
                foreach( $insertProductId as $inProId => $inGoodsId ){
                    $object->db->exec( 'INSERT INTO sdb_goods_lv_price ( product_id, level_id, goods_id, price ) VALUES ('.intval($inProId).', '.intval($updateLvId).', '.intval($inGoodsId).', '.floatval($updateValue).')') ;
                }
             }else{
                $object->db->exec('DELETE FROM sdb_goods_lv_price WHERE goods_id IN ( '.implode(',',$goods_id).' ) AND level_id = '.intval($updateLvId));
             }
        }
        return true;
}
?>