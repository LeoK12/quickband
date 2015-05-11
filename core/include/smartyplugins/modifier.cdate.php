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
function tpl_modifier_cdate($string,$type)
{
    $system = &$GLOBALS['system'];
    $time = $string?intval($string):time();
    $time += ($system->getConf('system.timezone.default')-SERVER_TIMEZONE)*3600;
    if(!$GLOBALS['site_dateformat']){
        $system = &$GLOBALS['system'];
        if(!($GLOBALS['site_dateformat']=$system->getConf('site.dateFormat')))
            $GLOBALS['site_dateformat'] = "Y-m-d";
    }
    switch($type){
        case 'FDATE':
            $dateFormat = 'Y-m-d';
            break;
        case 'SDATE':
            $dateFormat = 'y-m-d';
            break;
        case 'DATE':
            $dateFormat = 'm-d';
            break;
        case 'FDATE_FTIME':
            $dateFormat = 'Y-m-d H:i:s';
            break;
        case 'FDATE_STIME':
            $dateFormat = 'Y-m-d H:i';
            break;
        case 'SDATE_FTIME':
            $dateFormat = 'y-m-d H:i:s';
            break;
        case 'SDATE_STIME':
            $dateFormat = 'y-m-d H:i';
            break;
        case 'DATE_FTIME':
            $dateFormat = 'm-d H:i:s';
            break;
        case 'DATE_STIME':
            $dateFormat = 'm-d H:i';
            break;
        default:
            $dateFormat = $GLOBALS['site_dateformat'];
    }

    return mydate($dateFormat,$time);
}
?>
