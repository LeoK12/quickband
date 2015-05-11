<?php
function tpl_function_link($params,&$smarty){

    $args=isset($params['args'])?$params['args']:array();
    foreach($params as $key=>$val){
        if(preg_match('/^arg([0-9]+)$/',$key,$matches)){
            $args[$matches[1]]=str_replace('-', '@', $val);    //字符串中含有“-”替换成@,临时解决
        }
    }

//error_log(var_export($args,1),3,'c:/args.txt');

    return $smarty->system->mkUrl($params['ctl'],$params['act'],$args,$params['extname']?$params['extname']:'html');

}
?>