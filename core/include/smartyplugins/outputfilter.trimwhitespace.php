<?php
function template_outputfilter_trimwhitespace($tpl_source, &$template_object)
{
    $a = preg_split('/(<\s*(?:script|textarea).*?>.*?<\s*\/\s*(?:script|textarea)\s*>)/is',$tpl_source,-1,PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY );
    $token = 'oooooo';
    $r = '';
    $tpl_source = '';
    foreach($a as $k=>$v){
        if($k % 2 == 0){
            $r.=$v.$token;
            unset($a[$k]);
        }
    }
    $r = preg_replace('/\s+/s',' ',$r);
    foreach(explode($token,$r) as $i=>$txt){
        $tpl_source.=$txt;
        if(isset($a[2*$i+1])){
            $tpl_source.=$a[2*$i+1];
        }
    }
    return $tpl_source;
}
?>
