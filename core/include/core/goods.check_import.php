<?php
function goods_check_import($aData,$aFile, &$object){
        if($aData['type']=='csv'){
            if(substr($aFile['upload']['name'],-4)!='.csv'){
                trigger_error(__('文件格式有误'),E_USER_ERROR);
                exit;
            }
            $content = file_get_contents($aFile['upload']['tmp_name']);
            if(substr($content,0,3)=="\xEF\xBB\xBF"){
                $content = substr($content,3);    //去BOM头
                $handle = fopen($aFile['upload']['tmp_name'],'wb');
                fwrite($handle,$content);
                fclose($handle);
            }
            $handle = fopen($aFile['upload']['tmp_name'],'r');
        }elseif(substr($aData['type'],0,4)=='site'){    //review: 有什么意思？
            $handle['url'] = $aData['url'];
            $handle['count'] = 0;
        }

        $addons = $object->system->loadModel('system/addons');
        $exporter = $addons->load($aData['type'],'io');
        $g = $object->system->loadModel('goods/gtype');
        while($data = $exporter->import_row($handle)){
            $goMark = true;
            foreach($data as $v){
                if(trim($v)){
                    $goMark = false;
                    break;
                }
            }
            if($goMark){
                continue;
            }
            if($data[0]{0}=='*'){    //检测类型定义行
                $type_name = explode(':',$data[0],2);
                if($gtype = $g->getTypebyAlias('*',$type_name[1])){    //if exist goods type for $type_name[1]
                    $type_valid = true;
                    $type_id = $gtype['type_id'];
                    $gtype['props'] = unserialize($gtype['props']);
                    $gtype['params'] = unserialize($gtype['params']);
                    $title_array = $object->getTypeExportTitle($gtype);
                    $title_array_flip = array_flip($title_array);
                    unset($proto);
                    unset($rel);
                    $proto['type_id'] = $type_id;

                    //进行数据赋值
                    foreach($data as $k=>$v){
                        //echo $v.'%'.$title_array_flip[$v].'|';
                        if(strstr($v,'props:') && !isset($title_array_flip[$v])){
                            trigger_error(__('商品类型“').$gtype['name'].__('”中的“').$v.__('”属性并不存在！'),E_USER_ERROR);
                            exit;
                        }
                        if(strstr($v,'params:') && !isset($title_array_flip[$v])){
                            trigger_error(__('商品类型“').$gtype['name'].__('”中的“').$v.__('”参数并不存在！'),E_USER_ERROR);
                            exit;
                        }
                        if($v!=''&&$title_array_flip[$v]){
                            $proto[$title_array_flip[$v]] = &$rel[$k] ;
                        }
                    }
                }else{
                    $type_valid = false;
                    $object->csvLog('warning',__('商品类型“').$type_name[1].__('”在商店中并不存在，该类型下的商品数据不能导入！'));
                }
                continue;
            }
            //开始检测商品数据行，前提是：必须有商品类型，商品数据才可以继续读取
            if($type_valid && $type_id){
                foreach($data as $k=>$v){
                    $rel[$k] = trim($v);
                }

                //含有物品记录时，必须要该物品对应的商品记录
                if($proto['i_bn']==''){ //review: 商品也已经有了货品编号
                    unset($proto['goods_pdt']);
                    unset($proto['goods_spec']);
                    if($last_g_bn){
                        $object->writeData();
                    }
                    $last_g_bn = $object->importGoods($proto,$gtype);
                    $proto['do_goods'] = false;
                    //判断是否是单货品商品
                    if($proto['spec']=='-' || $proto['spec']=='' || (is_array($proto['spec'])&&!$proto['spec'])){
                        $proto['i_bn'] = $proto['bn'];
                        $object->importProduct($proto);
                    }else{
                        $proto['goods_spec'] = $proto['spec'];  //商品规格数组array(spec_id=>spec_name)
                    }
                }else{
                    $object->importProduct($proto, true);
                }
                //Ever: 记录商品标识，当到达下一个商品时，将前一个商品的spec值（根据他下面的货品）补充完整
            }
            $iLoop++;

            usleep(20);
        }

        if($last_g_bn){
            $object->writeData();
        }

        return true;
}
?>