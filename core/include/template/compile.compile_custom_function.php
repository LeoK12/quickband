<?php
/**
 * Template Lite compile custom function - template internal module
 *
 * Type:     template
 * Name:     compile_custom_function
 */

function compile_compile_custom_function($function,$arguments, &$_result, &$object)
{
    if ($function = $object->_plugin_exists($function, "function"))
    {
        $_args = $object->_parse_arguments($arguments);
        foreach($_args as $key => $value)
        {
            if (is_bool($value)){
                $value = $value ? 'true' : 'false';
            }elseif (is_null($value)){
                $value = 'null';
            }
            $_args[$key] = "'$key' => $value";
        }
        $_result = '<?php echo ';
        $_result .= $function . '(array(' . implode(',', (array)$_args) . '), $this);';
        $_result .= '?>';
        return true;
    }
    else
    {
        return false;
    }
}

?>