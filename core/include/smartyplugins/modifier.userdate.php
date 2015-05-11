<?php
function tpl_modifier_userdate($timestamp)
{
    if(!$GLOBALS['site_dateformat']){
        $system = &$GLOBALS['system'];
        if(!($GLOBALS['site_dateformat']=$system->getConf('site.dateFormat')))
            $GLOBALS['site_dateformat'] = "Y-m-d";
    }
    return mydate($GLOBALS['site_dateformat'],$timestamp);
}
?>
