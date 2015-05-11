<?php
function tpl_block_safehtml($params, $content, &$template_object){
  if(null!==$content){
    return preg_replace('/<(\s*)(script|object|iframe|embed)(.*?)>/is','&lt;$1$2$3&gt;',$content);
  }
}

?>
