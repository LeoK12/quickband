<?php
function dapi_call($func,&$args,&$system){
    $db = $system->database();
    $rs = $db->exec('select * from sdb_dapi where func='.$db->quote($func));
    $r = $db->getRows($rs,1);
    $r = $r[0];
    if(!$r || (time()-$r['last_update']>300)){
        if(!class_exists('http_base')){
            require(CORE_INCLUDE_DIR.'/http.php');
        }
        $http = new http_base;
        $info = $system->version();
        $info['ver'] = '4.8.6';
        //服务器端要把client端的version作为etag的内容
        $code = $http->action('get',constant('DAPI_URL').'?'.http_build_query($info).'&api=liansuo-'.$func,array('If-None-Match'=>$r['checksum']));
        if($http->responseCode==200){
            $sql = $db->getUpdateSQL($rs,array(
                    'func'=>$func,
                    'checksum'=>$http->responseHeader['etag'],
                    'code'=>$code,
                    'last_update'=>time()
                ),1);
            if($sql)$db->exec($sql);
        }elseif($http->responseCode==304){
            $sql = $db->getUpdateSQL($rs,array( 'last_update'=>time()));
            $code = $r['code'];
            if($sql)$db->exec($sql);
        }
    }else{
        $code = $r['code'];
    }

    if(!function_exists($func)){
        eval(' ?>'.$code);
    }
    return call_user_func_array($func,$args);
}
