<?php
function goods_check_gspec(&$proto , &$object){
        if($list = $object->getList('goods_id,spec,pdt_desc,spec_desc',array('bn'=>$proto['bn']))){    //编辑商品
            $proto['goods_id'] = $list[0]['goods_id'];
            if($aEditSpec = unserialize($list[0]['spec_desc'])){
                foreach($aEditSpec as $spec_id => $items){
                    $aTmpid[] = $spec_id;
                    $data_spec[$spec_id] = $items;
                }
                $objSpec = $object->system->loadModel('goods/specification');
                $aRet = $objSpec->getArrayById($aTmpid);
                foreach($aTmpid as $specid){
                    foreach($aRet as $id => $v){
                        if($id == $specid){
                            $aSpec[$id] = $v;
                            break;
                        }
                    }
                }
            }else{
                $aSpec = unserialize($list[0]['spec']);
                $data_spec = $object->get_arr_specid_by_name($aSpec);
            }

            if($proto['spec'] == '-' || (is_array($proto['spec']) && count($proto['spec']) == 0)){
                $proto['spec']='';
                $proto['spec_desc']='';
            }
            if(implode('|',$aSpec) != $proto['spec']){    //比较规格项是否一致
                trigger_error(__('商品“').$proto['name'].__('”的规格跟原来的不一致！'),E_USER_ERROR);
                exit;
            }else{
                if($proto['spec']){
                    $objPdt = $object->system->loadModel('goods/finderPdt');
                    if($ap = $objPdt->getList('bn,pdt_desc',array('goods_id'=>$proto['goods_id']),0,-1)){
                        foreach($ap as $row){
                            $proto['goods_pdt'][$row['bn']] = $row['pdt_desc'];    //商品中含有的物品
                        }
                    }
                    $proto['spec'] = $aSpec;
                    $proto['spec_desc'] = $data_spec;
                }
            }
        }else{        //新商品
            $proto['goods_id'] = 0;
            if($proto['spec']!='-' && $proto['spec']){
                $aSpec = explode('|',$proto['spec']);
                $aSpec = $object->get_arr_specid_by_name($aSpec);
                $proto['spec'] = $aSpec;
                foreach($aSpec as $k=>$v){
                    $proto['spec_desc'][$k] = $v;
                    if(!trim($v)){
//                        $proto['spec'][$k+1] = trim($v);
//                    }else{
                        trigger_error(__('商品“').$proto['name'].__('”的规格格式不正确！'),E_USER_ERROR);
                        exit;
                    }
                }
            }
        }
}
?>