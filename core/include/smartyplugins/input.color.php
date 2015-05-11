<?php
function tpl_input_color($params,$ctl){
    if(!$params['id']){
        $domid = 'colorPicker_'.substr(md5(rand(0,time())),0,6);
        $params['id'] = $domid;
    }else{
        $domid = $params['id'];
    }
    if($params['value']==''){
       $params['value']='default';
    }
    return buildTag($params,'input autocomplete="off"').' <input type="button" id="c_'.$domid.'" style="width:22px;height:22px;background-color:'.$params['value'].';border:0px #ccc solid;cursor:pointer"/><script>
    new GoogColorPicker("c_'.$domid.'",{
       onSelect:function(hex,rgb,el){
          $("'.$domid.'").set("value",hex);
          el.setStyle("background-color",hex);
       }
    })</script>';
}