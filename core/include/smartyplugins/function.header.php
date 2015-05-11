<?php
function tpl_function_header($params, &$smarty)
{
    $system = &$GLOBALS['system'];
    $data['TITLE'] = &$smarty->title;
    $data['KEYWORDS'] = &$smarty->keywords;
    $data['DESCRIPTION'] = &$smarty->desc;
    $data['headers'] = &$system->ctl->header;
    $output = &$system->loadModel('system/frontend');
    $data['theme_dir'] = $system->base_url().'themes/'.$output->theme;
    if($theme_info=($system->getConf('site.theme_'.$system->getConf('system.ui.current_theme').'_color'))){
        $data['theme_color_href']=$system->base_url().'themes/'.$system->getConf('system.ui.current_theme').'/'.$theme_info;
    }
        
    //echo $system->getConf('site.theme_'.$system->getConf('system.ui.current_theme').'_color');
    $shop=array('set'=>array());
    $shop['set']['path'] = substr(PHP_SELF, 0, strrpos(PHP_SELF, '/') + 1);
    $shop['set']['buytarget']=$system->getConf('site.buy.target');    //ajax加入购物车
    $shop['set']['dragcart']=$system->getConf('ux.dragcart');;    //拖动购物
    $shop['set']['refer_timeout']=$system->getConf('site.refer_timeout');;    //refer过期时间
    $shop['url']['addcart'] = $system->mkUrl('cart','ajaxadd');
    $shop['url']['shipping'] = $system->mkUrl('cart','shipping');
    $shop['url']['payment'] = $system->mkUrl('cart','payment');
    $shop['url']['total'] = $system->mkUrl('cart','total');
    $shop['url']['viewcart'] = $system->mkUrl('cart','view');
    $shop['url']['ordertotal'] = $system->mkUrl('cart','total');
    $shop['url']['applycoupon'] = $system->mkUrl('cart','applycoupon');
    $shop['url']['diff'] = $system->mkUrl('product','diff');
    

    $data['shopDefine'] = json_encode($shop);


    /*if(constant('DEBUG_JS')){
        $data['scripts'] = find(BASE_DIR.'/statics/headjs_src','js','statics/headjs_src');
    }elseif(constant('GZIP_JS')){
        $data['scripts'] = array('statics/head.jgz');
    }else{
        $data['scripts'] = array('statics/head.js');
    }*/
    echo $smarty->_fetch_compile_include('shop:common/header.html',$data);
}
?>
