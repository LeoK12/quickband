<?php
function tpl_modifier_usertime($timestamp)
{
    if(!$GLOBALS['site_timeformat']){
        $system = &$GLOBALS['system'];
        if(!($GLOBALS['site_timeformat']=$system->getConf('site.timeFormat')))
            $GLOBALS['site_timeformat'] = "Y-m-d H:i:s";
    }
    return mydate($GLOBALS['site_timeformat'],$timestamp);
}
?>
