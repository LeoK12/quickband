<?php
function goods_update_price($pricedata , &$object){

        foreach( $pricedata as $updateName => $data ){
            if( in_array( $updateName , array( 'price', 'cost','mktprice' ) ) ) {
                foreach( $data as $goodsId => $goodsItem ){
                    foreach( $goodsItem as $proId => $price ){
                        $object->db->exec( 'UPDATE sdb_products SET '.$updateName.' = '.floatval($price).' WHERE product_id = '.intval($proId) );
                    }
                    $minPrice = $object->db->selectrow('SELECT MIN(price) AS mprice FROM sdb_products WHERE goods_id = '.intval($goodsId) );
                    if($updateName=='price')
                    $object->db->exec( 'UPDATE sdb_goods SET '.$updateName.' = '.floatval($minPrice['mprice']).' WHERE goods_id = '.intval($goodsId) );
                    else
                    $object->db->exec( 'UPDATE sdb_goods SET '.$updateName.' = '.floatval($price).' WHERE goods_id = '.intval($goodsId) );
                }
            }else{
                foreach( $data as $goodsId => $goodsItem )
                    foreach( $goodsItem as $proId => $price ){
                        if( $price == null || $price == '' ){
                            $object->db->exec('DELETE FROM sdb_goods_lv_price WHERE product_id = '.intval($proId).' AND level_id = '.intval($updateName).' AND goods_id = '.intval($goodsId));
                            continue;
                        }
                        $datarow = $object->db->selectrow('SELECT count(*) as c FROM sdb_goods_lv_price WHERE product_id = '.intval($proId).' AND level_id = '.intval($updateName).' AND goods_id = '.intval($goodsId));
                        if($datarow['c'] > 0)
                            $object->db->exec('UPDATE sdb_goods_lv_price SET price = '.floatval($price).' WHERE product_id = '.intval($proId).' AND level_id = '.intval($updateName).' AND goods_id = '.intval($goodsId));
                        else
                            $object->db->exec('INSERT INTO sdb_goods_lv_price (product_id, level_id, goods_id, price ) VALUES ( '.intval($proId).', '.intval($updateName).', '.intval($goodsId).', '.floatval($price).' )');
                    }
            }
        }
        return true;
}
?>
