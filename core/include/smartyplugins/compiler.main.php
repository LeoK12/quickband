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
function tpl_compiler_main($attrs, &$smarty)
{
    return " echo  \$this->_fetch_compile_include(\$this->template_exists('user:'.\$this->theme.'/view/'.\$this->_vars['_MAIN_'])?'user:'.\$this->theme.'/view/'.\$this->_vars['_MAIN_']:'shop:'.\$this->_vars['_MAIN_'], array());";
}
?>