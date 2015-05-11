<?php
function tpl_input_select($params,$ctl){

    if($params['stype']=='arrow'){
        $params['options']= array('arrow_1.gif'=>'箭头1','arrow_2.gif'=>'箭头2','arrow_3.gif'=>'箭头3','arrow_4.gif'=>'箭头4','arrow_5.gif'=>'箭头5',6=>'自定义');
    }
    if($params['stype']=='rank'){
        $params['options']= array('view_w_count'=>'周访问次数','view_count'=>'总访问次数','buy_w_count'=>'周购买次数','buy_count'=>'总购买次数'
        ,'comments_count'=>'评论次数');
    }
    if($params['stype']=='fontset'){
        $params['options']= array('0'=>'默认','1'=>'粗体','2'=>'斜体','3'=>'中线');
    }

    $class = $params['class'];
    if(is_string($params['options'])){
        if(!$params['id'])$params['id'] = $ctl->new_dom_id();
        $params['remote_url'] = $params['options'];
        $params['options'] = array($params['value']=>$params['value']);
        $script='<script>$(\''.$params['id'].'\').addEvent(\'focus\',window.init_select)</script>';
    }
    if($params['rows']){
        foreach($params['rows'] as $r){
            $step[$r[$params['valueColumn']]]=intval($r['step']);
            $options[$r[$params['valueColumn']]] = $r[$params['labelColumn']];
        }
        unset($params['valueColumn'],$params['labelColumn'],$params['rows']);
    }else{
        $options = $params['options'];
        unset($params['options']);
    }
    $params['name'] = $params['search']?'_'.$params['name'].'_search':$params['name'];
    $value = $params['value'];
    unset($params['value']);
    $html=buildTag($params,'select class="x-input-select '.$class.' inputstyle"',false);
    if($params['nulloption']==1){
        $html.='<option value="">请选择</option>';
    }
    if($params['nulloption']==0){
        $html.='';
    }

    foreach($options as $k=>$item){
        if($k==='0' || $k===0){
            $selected = ($value==='0' || $value===0);
        }else{
            $selected = ($value==$k);
        }
        $t_step=$step[$k]?str_repeat('&nbsp;',($step[$k]-1)*3):'';
        $html.='<option'.($selected?' selected="selected"':'').' value="'.htmlspecialchars($k).'">'.$t_step.htmlspecialchars($item).'</option>';
    }
    $html.='</select>';
    return $html.$script;
}
