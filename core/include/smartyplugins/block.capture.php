<?php
function tpl_block_capture($params, $content, &$tpl)
{
    if(null!==$content){
        $tpl->_env_vars['capture'][isset($params['name'])?$params['name']:'default'] = &$content;
        if (isset($params['assign'])) {
            $tpl->_vars[$params['assign']] = &$content;
        }
    }
    return null;
}