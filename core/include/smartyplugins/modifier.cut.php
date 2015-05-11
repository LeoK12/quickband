<?php
function tpl_modifier_cut($string, $length = 80, $etc = '...',
                                  $break_words = false, $middle = false)
{
    if ($length == 0)
        return '';

    if (isset($string{$length+1})) {

        $length -= min($length, strlen($etc));

        if (!$break_words && !$middle) {
            $string = preg_replace('/\s+?(\S+)?$/', '', utftrim(substr($string, 0, $length+1)));
        }
        if(!$middle) {
            return utftrim(substr($string, 0, $length)) . $etc;
        } else {
            return utftrim(substr($string, 0, $length/2)) . $etc . utftrim(substr($string, -$length/2));
        }
    } else {
        return $string;
    }
}

function utftrim($str)
{
    $found = false;
    for($i=0;$i<4&&$i<strlen($str);$i++)
    {
        $ord = ord(substr($str,strlen($str)-$i-1,1));
        if($ord> 192)
        {
            $found = true;
            break;
        }
    }
    if($found)
    {
        if($ord>240)
        {
            if($i==3) return $str;
            else return substr($str,0,strlen($str)-$i-1);
        }
        elseif($ord>224)
        {
            if($i==2) return $str;
            else return substr($str,0,strlen($str)-$i-1);
        }
        else
        {
            if($i==1) return $str;
            else return substr($str,0,strlen($str)-$i-1);
        }
    }
    else return $str;
}
?>
