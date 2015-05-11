<?php
function core_upgrade(&$system){
    if($_GET['_ajax']){
        $url = 'index.php';
        $output =<<<EOF
<script>
var href = top.location.href;
var pos = href.indexOf('#') + 1;
window.location.href="$url"+(pos ? ('&return='+encodeURIComponent(href.substr(pos))) : '');
</script>
EOF;
        echo $output;
        exit;
    }
    $upgrade = &$system->loadModel('system/upgrade');
    $upgrade->exec($_GET['act']);
}
?>