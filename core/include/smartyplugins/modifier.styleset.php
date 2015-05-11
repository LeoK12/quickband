<?php
function tpl_modifier_styleset($style)
{
    switch($style){
        case 1:
            return 'font-weight: bold;';
        break;
        case 2:
            return 'font-style: italic;';
        break;
        case 3:
            return 'text-decoration: line-through;';
        break;
    }

}
?>
