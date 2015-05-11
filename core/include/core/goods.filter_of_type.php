<?php
function goods_filter_of_type($p , &$object){
        $cat = $object->system->loadModel('goods/productCat');
        if(!$object->catMap){
            $object->catMap = $cat->getMapTree(0,'');
        }

        $return['cats'] = $object->catMap;
        $cat_id=$p['type_id'];
        if($p = goods_getfilterProperty($cat_id,$object)){
            $return['props'] = $p['props'];
            $brand = $object->system->loadModel('goods/brand');
            $return['brands'] = $brand->getAll();
            $return['cat_id'] = $p['cat_id'];

            $row = $object->db->selectrow('SELECT max(price) as max,min(price) as min FROM sdb_goods where type_id='.intval($cat_id));
        }else{
            $brand = $object->system->loadModel('goods/brand');
            $return['brands'] = $brand->getAll();

            $row = $object->db->selectrow('SELECT max(price) as max,min(price) as min FROM sdb_products ');
        }
        $modTag = $object->system->loadModel('system/tag');
        $return['type_id'] = $cat_id;
        $return['tags'] = $modTag->tagList('goods');
        $return['prices'] = steprange($row['min'],$row['max'],5);
        return $return;
}

function goods_getfilterProperty($type_id,&$object){
        $sqlString = 'SELECT t.props,t.schema_id,t.setting,t.type_id FROM sdb_goods_type t WHERE t.type_id = '.intval($type_id);

        $row = $object->db->selectrow($sqlString);
        if($row['props']) $row['props'] = unserialize($row['props']);
        //if($row['tabs']) $row['tabs'] = unserialize($row['tabs']);
        if($row['setting']) $row['setting'] = unserialize($row['setting']);

        if($row['type_id']){
            $row['brand'] = $object->db->select('SELECT b.brand_id,b.brand_name,brand_url,brand_logo FROM sdb_type_brand t
                    LEFT JOIN sdb_brand b ON b.brand_id=t.brand_id
                    WHERE disabled="false" AND t.type_id='.$row['type_id'].' ORDER BY brand_order');
        }else{
            $oBrand = $object->system->loadModel('goods/brand');
            $row['brand'] = $oBrand->getList('*', '', 0, -1);
        }

        $dftList = array(
                __('图文列表')=>'list',
                __('橱窗')=>'grid',
                __('文字')=>'text',
            );
        if(isset($row['setting']['list_tpl']) && is_array($row['setting']['list_tpl']))
            foreach($row['setting']['list_tpl'] as $k=>$tpl){
                if(!in_array($tpl,$dftList)){
                    if(!file_exists(SCHEMA_DIR.$row['schema_id'].'/view/'.$tpl.'.html')){
                        unset($row['setting']['list_tpl'][$k]);
                    }
                }
            }
        if(!isset($row['setting']['list_tpl']) || !is_array($row['setting']['list_tpl']) || count($row['setting']['list_tpl'])==0){
            $row['setting']['list_tpl'] = $dftList;
        }

        if($view=='index')$view = current($row['setting']['list_tpl']);
        if(in_array($view,$dftList)){
            if (defined('CUSTOM_CORE_DIR') && file_exists(CUSTOM_CORE_DIR.'/shop/view/gallery/type/'.$view.'.html'))
                $row['tpl'] = realpath(CUSTOM_CORE_DIR.'/shop/view/gallery/type/'.$view.'.html');
            else
                $row['tpl'] = realpath(CORE_DIR.'/shop/view/gallery/type/'.$view.'.html');
        }else{
            $row['tpl'] = realpath(SCHEMA_DIR.$row['schema_id'].'/view/'.$view.'.html');
        }

        $row['dftView'] = $view;
        $row['setting']['list_tpl'][key($row['setting']['list_tpl'])] = 'index';
        return $row;
}
?>