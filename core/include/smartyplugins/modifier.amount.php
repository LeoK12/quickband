<?php
function tpl_modifier_amount($money,$currency=null,$basicFormat = false,$chgval=true,$is_order=false)
{
    $system = &$GLOBALS['system'];
    $cur = &$system->loadModel('system/cur');
    return $cur->amount($money,$currency,$basicFormat,$chgval,$is_order);
}
?>