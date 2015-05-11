<?php
function core_time_auth(&$system){
    if($_POST['api_url'] == 'time_auth'){
        header("Content-type:text/html;charset=utf-8");
        $shopex_auth=&$system->loadModel('service/certificate');
        if($shopex_auth->check_api()){
            echo json_encode($shopex_auth->show_pack_data());
            exit;
        }
    }
}
