<?php
/*
* Smarty plugin
* -------------------------------------------------------------
* File:     compiler.tplheader.php
* Type:     compiler
* Name:     tplheader
* Purpose:  Output header containing the source file name and
*           the time it was compiled.
* -------------------------------------------------------------
*/
function tpl_compiler_require($attrs, &$smarty)
{
    //$_to_require = "'user:'.\$this->theme.'/'.{$attrs['file']}";
    $_to_require = "\$this->_get_resource('user:'.\$this->theme.'/'.{$attrs['file']})?('user:'.\$this->theme.'/'.{$attrs['file']}):('shop:'.{$attrs['file']})";

    return " echo \$this->_fetch_compile_include({$_to_require}, array());";
}
?>