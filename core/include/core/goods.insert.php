<?php
function goods_insert(&$object){
    $objSpec = $object->system->loadModel('goods/specification');
    //todo：插入商品行
    $aGoodsData = file(HOME_DIR.'/tmp/uploadGoodsCsvTmp');
    unlink (HOME_DIR.'/tmp/uploadGoodsCsvTmp');

    $object->globalTmp['tmp']['g'] = unserialize($aGoodsData[1]);
    $object->globalTmp['tmp']['p'] = array();
    foreach($aGoodsData as $k => $p_data){
        if($k > 1) $object->globalTmp['tmp']['p'][] = unserialize($p_data);
    }

    if($object->globalTmp['tmp']['g']){
        $aGoods = $object->globalTmp['tmp']['g'];
        $aGoods['spec'];    //todo: 按照这个数组（规格）的顺序将规格值依次附加进去
        foreach($object->globalTmp['tmp']['p'] as $data){
            $i = 0;
            foreach($data['spec'] as $v){
                $aTmp[$i][] = $v;   //key 是1，2，3，4 和货品数组的key相同
                $i++;
            }
            //todo：插入货品行
        }
        $i = 0;
        $aNewSpec = array();
        foreach($aGoods['spec_desc'] as $spec_id => $item){     //遍历规格
            if(is_array($item)){   //如果商品原来已经存在规格
                $aNewSpec[$spec_id] = $item;
                foreach($aTmp[$i] as $k => $v){      //红色：玫瑰红|黄色：土黄
                    if(strstr($v, ":")){
                        $aDef = explode(':', $v);
                        $true_name = $aDef[0];
                        $alias_name = $aDef[1];
                    }else{
                        $true_name = $v;
                        $alias_name = $v;
                    }
                    $v_id = $objSpec->getValueidByName($spec_id, $true_name) + 0;
                    $tmp_mark = true;
                    foreach($item as $s => $spec_value){  //是否存在商品规格组当中
                        if($spec_value['spec_value_id'] == $v_id && $spec_value['spec_value'] == $alias_name){
                            if($aExist[$v_id][$alias_name]){
                                $uniqid = $aExist[$v_id][$alias_name];
                            }else{
                                $uniqid = strtoupper(uniqid('spec'));
                                $aNewSpec[$spec_id][$uniqid] = $spec_value;
                                $aNewSpec[$spec_id][$uniqid]['sign'] = 'Y';    //标识新的，否则删除
                            }
                            $object->globalTmp['tmp']['p'][$k]['props']['spec'][$spec_id] = $true_name;
                            $object->globalTmp['tmp']['p'][$k]['props']['spec_value_id'][$spec_id] = $v_id;
                            $object->globalTmp['tmp']['p'][$k]['props']['spec_private_value_id'][$spec_id] = $uniqid;
                            $tmp_mark = false;
                            $aExist[$v_id][$alias_name] = $uniqid;
                            break;
                        }
                    }
                    if($tmp_mark){
                        if($aExist[$v_id][$alias_name]){
                            $m = $aExist[$v_id][$alias_name];
                        }else{
                            $m = strtoupper(uniqid('spec'));
                            $aNewSpec[$spec_id][$m]['spec_value_id'] = $v_id;
                            $aNewSpec[$spec_id][$m]['spec_value'] = $alias_name;
                            $aNewSpec[$spec_id][$m]['sign'] = 'Y';    //标识新的，否则删除
                            $aExist[$v_id][$alias_name] = $m;
                        }
                        $object->globalTmp['tmp']['p'][$k]['props']['spec'][$spec_id] = $true_name;
                        $object->globalTmp['tmp']['p'][$k]['props']['spec_value_id'][$spec_id] = $v_id;
                        $object->globalTmp['tmp']['p'][$k]['props']['spec_private_value_id'][$spec_id] = $m;
                    }
                }
            }else{
                $aExist = array();
                foreach($aTmp[$i] as $k => $v){      //红色：玫瑰红|黄色：土黄
                    if(strstr($v, ":")){
                        $aDef = explode(':', $v);
                        $true_name = $aDef[0];
                        $alias_name = $aDef[1];
                    }else{
                        $true_name = $v;
                        $alias_name = $v;
                    }
                    $v_id = $objSpec->getValueidByName($spec_id, $true_name);
                    if($aExist[$v_id][$alias_name]){
                        $uniqid = $aExist[$v_id][$alias_name];
                    }else{
                        $uniqid = strtoupper(uniqid('spec'));
                        $aExist[$v_id][$alias_name] = $uniqid;
                        $aNewSpec[$spec_id][$uniqid]['spec_value_id'] = $v_id;
                        $aNewSpec[$spec_id][$uniqid]['spec_value'] = $alias_name;
                        $aNewSpec[$spec_id][$uniqid]['sign'] = 'Y';    //标识
                    }
                    $object->globalTmp['tmp']['p'][$k]['props']['spec'][$spec_id] = $true_name;
                    $object->globalTmp['tmp']['p'][$k]['props']['spec_value_id'][$spec_id] = $v_id;
                    $object->globalTmp['tmp']['p'][$k]['props']['spec_private_value_id'][$spec_id] = $uniqid;
                }
            }
            $i++;
        }

        foreach($aNewSpec as $spec_id => $spec_item){
            foreach($spec_item as $k => $spec_value){
                if($spec_value['sign']){
                    unset($aNewSpec[$spec_id][$k]['sign']);
                }else{
                    unset($aNewSpec[$spec_id][$k]);
                }
            }
        }
        $aGoods['spec_desc'] = $aNewSpec;

        $object->csvLog('data',array('name'=>'goods','content'=>$aGoods));
        foreach($object->globalTmp['tmp']['p'] as $data){
            $object->csvLog('data',array('name'=>'product','content'=>$data));
        }
        unset($object->globalTmp['tmp']);
    }
}
?>