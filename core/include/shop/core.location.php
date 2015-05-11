<?php
function shop_core_location(){
    if($_POST){
        $html="<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Strict//EN\"
            \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd\">
            <html xmlns=\"http://www.w3.org/1999/xhtml\" xml:lang=\"en-US\" lang=\"en-US\" dir=\"ltr\">
            <head></header><body>Redirecting...";
        $html .= '<form id="splash" action="'.$url.'" method="post">'.$this->_build_post($_POST);
        $html.=<<<EOF
</form><script language="javascript">
document.getElementById('splash').submit();
</script></html>
EOF;
        echo $html;
        exit();
    }else{
        header('Location: '.$url);
        exit();
    }
}
?>