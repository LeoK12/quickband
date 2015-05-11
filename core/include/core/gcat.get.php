<?php
function gcat_get($cat_id,$view,$type_id=null , &$object){
        if(!is_array($cat_id)){
            $cat_id=array($cat_id);
        }
        if($type_id){
            $sqlString = 'SELECT t.props,t.schema_id,t.setting,t.type_id,t.spec FROM sdb_goods_type t
                WHERE type_id ='.$type_id;
        }else{
            if($cat_id[0]){
                $cat_id='('.implode($cat_id,' OR ').')';
                $sqlString = 'SELECT c.cat_id,c.cat_name,c.tabs,c.addon,t.props,t.schema_id,t.setting,t.type_id,t.spec FROM sdb_goods_cat c
                    LEFT JOIN sdb_goods_type t ON c.type_id = t.type_id
                    WHERE cat_id in '.$cat_id;
            }
        }
        if($sqlString) $row = $object->db->selectrow($sqlString);
        if($row['props']) {
            $row['props'] = unserialize($row['props']);
            $row['ordernum'] = $object->propsort($row['props']);
        }
        if($row['tabs']) $row['tabs'] = unserialize($row['tabs']);
        if($row['setting']) $row['setting'] = unserialize($row['setting']);
        if($row['spec']) $row['spec'] = unserialize($row['spec']);

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
        if($view=='index') $view = current($row['setting']['list_tpl']);
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