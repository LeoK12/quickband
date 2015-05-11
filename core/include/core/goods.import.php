<?php
function goods_import(&$proto,$gtype , &$object){
        if(empty($proto['name'])){
            trigger_error(__('编号为“').$proto['bn'].__('”的商品没有名称！'),E_USER_ERROR);
            exit;
        }
        if(in_array($proto['bn'], $object->globalTmp['g'])){
            trigger_error(__('商品“').$proto['name'].__('”的编号在文件中重复！'),E_USER_ERROR);
            exit;
        }else{
            $member_price = array();
            $t_params = array();
            $brand = $object->system->loadModel('goods/brand');
            $cat = $object->system->loadModel('goods/productCat');
            //参数表对应
            $params = $gtype['params'];
            $props = $gtype['props'];
            foreach($proto as $k=>$v){
                $tag = substr($k,0,2);
                switch($tag){
                case 'p_':    //属性处理,属性在CSV中的属性可以颠倒或者跳过
                    $temp = explode('_',$k);
                    if($props[$temp[1]]['type']=='select'){
                        $interp = array_flip($props[$temp[1]]['options']);
                        $alias = $props[$temp[1]]['optionAlias'];
                        foreach($alias as $k1=>$v1){
                            if(!empty($v1)){
                                $the_alias = explode('|',$v1);
                                foreach($the_alias as $v2){
                                    $interp[$v2] = $k1;
                                }
                            }
                        }
                        if(array_key_exists($v, $interp)){
                            $proto[$k] = $interp[$v];
                        }else{
                            if($v){
                                trigger_error(__('商品“').$proto['name'].__('”中的属性值“').$v.__('”并不存在！'),E_USER_ERROR);
                                exit;
                            }
                        }
                    }
                    break;
                case 'a_':   //参数处理
                    $temp = explode('_',$k);
                    $t_params = explode('->',$temp[1]);
                    $params[$t_params[0]][$t_params[1]] = $v;
                    unset($proto[$k]);
                    break;
                default:
                    if($k=='brand'){
                        if(!$v){
                            $proto['brand_id'] = 0;
                            $proto['brand'] = '';
                        }elseif($b = $brand->getBrandbyAlias('brand_id',trim($v))){
                            $proto['brand_id'] = $b['brand_id'];
                            $proto['brand'] = $v;
                        }else{
                            $proto['brand_id'] = 0;
                            $object->csvLog('warning',__('品牌错误'));
                        }
                    }
                    if($k=='cat_id'){
                        if($catid = $cat->getCatidbyAlias($v)){
                            $proto['cat_id'] = $catid;
                        }else{
                            $proto['cat_id'] = 0;
                            $object->csvLog('warning',__('商品“').$proto['name'].__('”的分类不存在，导入后的商品分类为空'));
                        }
                    }
                    if($k=='marketable'){
                        if($v=='Y'||$v=='y'||$v=='TRUE'||$v=='true'){
                            $proto['marketable'] = 'true';
                            $proto['downtime'] = time();
                        }else{
                            $proto['marketable'] = 'false';
                            $proto['downtime'] = time();
}
                    }
                }
            }
            $proto['params'] = $params;
            $proto['goods_name'] = $proto['name'];
            $object->checkGoodsSpec($proto);
        }
        $object->globalTmp['g'][] = $proto['bn'];
        $object->csvLog('tmp',$proto);
        return $proto['bn'];
}
?>