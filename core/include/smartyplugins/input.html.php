<?php
function tpl_input_html($params,$smarty){
    $smarty->template_dir=$smarty->template_dir?$smarty->template_dir:(CORE_DIR.'/admin/view/');
    $id = 'mce_'.substr(md5(rand(0,time())),0,6);
    $system = &$GLOBALS['system'];
    $editor_type=$system->getConf("system.editortype");
    $editor_type==''?$editor_type='textarea':$editor_type='wysiwyg';
    if($editor_type =='textarea'||$params['editor_type']=='textarea'){
       echo $smarty->_fetch_compile_include('editor/style_2.html',array('var'=>$id,'for'=>$id));
    }else{
       echo $smarty->_fetch_compile_include('editor/style_1.html',array('var'=>$id,'for'=>$id,'includeBase'=>($params['includeBase']?$params['includeBase']:true)));
    }
    $params['id']=$id;
    $params['editor_type']=$params['editor_type']?$params['editor_type']:$editor_type;
    echo $smarty->_fetch_compile_include('editor/body.html',$params);
}