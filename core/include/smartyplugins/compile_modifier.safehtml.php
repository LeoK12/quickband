<?php
function tpl_compile_modifier_safehtml($content,&$compiler){
    list($content) = explode(',',$content);
    return 'preg_replace(\'/<(\s*)(script|object|iframe|embed))(.*?)>/is\',\'&lt;$1$2$3&gt;\','.$content.')';
}
?>