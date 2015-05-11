<?php
function admin_menu_filter(&$system,$part=null){

    require(CORE_INCLUDE_DIR.'/adminSchema.php');

    $role = &$system->loadModel('admin/adminroles');
    $opt = &$role->rolemap();
    $operator = &$system->loadModel('admin/operator');
    $addons = &$system->loadModel('system/addons');
    if(!constant('SAFE_MODE')){
        foreach($addons->getList('plugin_name,plugin_ident',array('plugin_type'=>'app')) as $r){
            $app_names[$r['plugin_ident']] = $r['plugin_name'];
            if($app_c = $addons->load($r['plugin_ident'],'app')) $app_c->getMenu($menu);
        }
    }

    foreach($addons->getList('plugin_struct,plugin_ident,plugin_package,plugin_id',array('plugin_type'=>'admin')) as $r){
        $info = unserialize($r['plugin_struct']);
        $grpname = isset($app_names[$r['plugin_package']])?$app_names[$r['plugin_package']]:'插件';
        //$menu_group[$info['props']['workground']][$grpname][] = array('type'=>'menu','label'=>$info['props']['name'],'link'=>'index.php?ctl=plugins/'.$r['plugin_ident'].'&act=index');
    }
    foreach($menu_group as $k=>$wgs){
        foreach($wgs as $name=>$group){
            $menu[$k]['items'][] = array('type'=>'group','label'=>$name,'items'=>$group);
        }
    }


    if($part){
        if(!$system->op_is_super){
            foreach($menu[$part]['items'] as $k=>$v){
                if($v['super_only']){
                    unset($menu[$part]['items'][$k]);
                }
            }
        }
        return $menu[$part]['items'];
    }else{
        if(!$system->op_is_super){
            $allow_wground = $operator->getActions($system->op_id);
            foreach($menu as $k=>$v){
                if(!isset($allow_wground[$opt[$k]])){
                    unset($menu[$k]);
                }
            }
        }
        return $menu;
    }
}
