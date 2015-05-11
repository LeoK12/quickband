<?php
function shop_match_network ($nets, $ip, $first=false) {
    $return = false;
    if (!is_array ($nets)) $nets = array ($nets);

    foreach ($nets as $net) {
        
        if(($rev = ($net{0}=='!'))){
            $net = substr($net,1);
        }
        $ip_arr  = explode('/', $net);
        $net_long = ip2long($ip_arr[0]);
        $x        = ip2long($ip_arr[1]);
        $mask    = long2ip($x) == $ip_arr[1] ? $x : 0xffffffff << (32 - $ip_arr[1]);
        $ip_long  = ip2long($ip);

        if ($rev) {
            if (($ip_long & $mask) == ($net_long & $mask)) return false;
        } else {
            if (($ip_long & $mask) == ($net_long & $mask)) $return = true;
            if ($first && $return) return true;
        }
    }
    return $return;
}
?>