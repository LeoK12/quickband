<?php
/*
* Smarty plugin
* -------------------------------------------------------------
* File:     modifier.t.php
* Type:     modifier
* Name:     capitalize
* Purpose:  capitalize words in the string
* -------------------------------------------------------------
*/
function tpl_modifier_timeleft($string)
{
    $diff = ($string - time())/60;
    $abs_diff = abs($diff);

    if($abs_diff<60){
        $t  =round($abs_diff).'分钟';
    }elseif($abs_diff<1440){
        $t  =round($abs_diff/60).'小时';
    }else{
        $t = round($abs_diff/1440).'天';
    }
    return $diff>0?$t:('已过去'.$t);
}
?>
