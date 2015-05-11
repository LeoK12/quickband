<?php
function tpl_compiler_in_array($attrs, &$smarty)
{

$return=<<<EOF
    if (is_array({$attrs['array']}))
    {
        if (in_array({$attrs['match']}, {$attrs['array']}))
        {
            echo {$attrs['returnvalue']};
        }
    }
EOF;
    return $return;
}
?>