<?php
function goods_import_product($proto, $isSpec=false , &$object){
        if(empty($proto['goods_name'])){
            trigger_error(__('规格货品“').$proto['i_bn'].__('”没有所属的商品存在！'),E_USER_ERROR);
            exit;
        }
        $proto['name'] = $proto['name']?$proto['name']:$proto['goods_name'];
        $proto['bn'] = $proto['i_bn'];
        if(in_array($proto['bn'], $object->globalTmp['p'])){
            trigger_error(__('商品“').$proto['goods_name'].__('”中的货品编号“').$proto['bn'].__('”在文件中重复！'),E_USER_ERROR);
            exit;
        }else{
            if($isSpec){
                $sSpec = $proto['spec'];    //规格值：白色:银白色|38:中码
                $proto['spec'] = array();
                $aSpec = explode('|',$sSpec);
                //ever: 2009-02-16
                if(count($aSpec) == count($proto['goods_spec'])){
                    $i = 0;
                    foreach($proto['goods_spec'] as $spec_id => $v){
                        if(trim($aSpec[$i])){
                            $proto['spec'][$spec_id] = trim($aSpec[$i]);
                        }else{
                            trigger_error(__('商品“').$proto['goods_name'].__('”中的规格值“').$sSpec.__('”不能为空！'),E_USER_ERROR);
                            exit;
                        }
                        $i++;
                    }
                }else{
                    trigger_error(__('商品“').$proto['goods_name'].__('”中的规格值“').$sSpec.__('”跟规格项不一致！'),E_USER_ERROR);
                    exit;
                }
                $ap = $object->db->selectrow('SELECT count(*) AS num FROM sdb_products WHERE bn= \''.$proto['bn'].'\'');
                if(($ap['num'] > 0 && !isset($proto['goods_pdt'][$proto['bn']])) || $ap['num'] > 1){
                    trigger_error(__('商品“').$proto['goods_name'].__('”中的货品编号“').$proto['bn'].__('”在数据库中已存在！'),E_USER_ERROR);
                    exit;
                }
                $pdtDesc = implode(' ',$proto['spec']);
                if(in_array($pdtDesc, $proto['goods_pdt']) && $proto['bn'] != array_search($pdtDesc, $proto['goods_pdt'])){
                    trigger_error(__('商品“').$proto['goods_name'].__('”中的规格值“').$pdtDesc.__('”重复！'),E_USER_ERROR);
                    exit;
                }
                $proto['goods_pdt'][$proto['bn']] = $pdtDesc;
                $proto['pdt_desc'] = $pdtDesc;
//                $proto['props']['spec'] = $proto['spec'];
            }else{
                if($proto['do_goods']){
                    trigger_error(__('商品“').$proto['goods_name'].__('”不应该存在货品！'),E_USER_ERROR);
                    exit;
                }else{
                    $proto['do_goods'] = true;
                }
            }
        }
        if(array_key_exists('marketable', $proto)){
            if($proto['marketable'] == 'N' || $proto['marketable'] == 'n'){
                $proto['marketable'] = 'false';
            }else{
                $proto['marketable'] = 'true';
            }
        }

        $object->globalTmp['p'][] = $proto['bn'];
        $object->csvLog('tmp',$proto);
}
?>