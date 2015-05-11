<?php
function shop_core_debugger($system){
    $system->_succ=true;
    $data = array('html'=>'','body'=>'','date'=>date("Y-m-d H:i:s (T)"),'fatal'=>null);
    $errorlevels = array(
        2048 => 'Error',
        1024 => 'Notice',
        512 => 'Warning',
        256 => 'Error',
        128 => 'Warning',
        64 => 'Error',
        32 => 'Warning',
        16 => 'Error',
        8 => 'Notice',
        4 => 'Error',
        2 => 'Warning',
        1 => 'Error');

    while(ob_get_level()>0){
        $data['body'] .= ob_get_contents();
        ob_end_clean();
        if($data['body']){
            $data['body'] = $data['body'];
        }
    }

    if(!$halt){
        if($pos = strrpos($data['body'],'Fatal error')){
            $data['fatal'].=substr($data['body'],$pos);
        }elseif($pos = strrpos($data['body'],'Parse error')){
            $data['fatal'].=substr($data['body'],$pos);
        }
    }else{
        $err = array_pop($system->_errArr);
        $data['fatal'].= "<li class=\"err_{$err['no']}\"><b class=\"no\">{$errorlevels[$err['no']]}:</b> <span class=\"body\">{$err['msg']}<span>{$err['file']}:{$err['line']}</li>";
    }

    foreach($system->_errArr as $err){
        $data['html'].= "<li class=\"err_{$err['no']}\"><b class=\"no\">{$errorlevels[$err['no']]}:</b> <span class=\"body\">{$err['msg']} <span>{$err['file']}:{$err['line']}</li>";
    }


    if(function_exists('debug_backtrace')){
        $data['backtrace'] = null;
        $lastfile = null;
        foreach(debug_backtrace() as $trace){
            if(isset($trace['file']) && $trace['file']!=$lastfile){
                $lastfile = $trace['file'];
                $data['backtrace'].= '<div>'.$trace['file'].':'.$trace['line'].'</div>';
            }
            $data['backtrace'].= '<li>'.(isset($trace['class'])?$trace['class']:'php').'::'.$trace['function'].'()</li>';
        }
    }

    if($data['fatal']){
        $system->responseCode(500);
        $data['msg'] = is_file(HOME_DIR.'/upload/error500.html')?file_get_contents(HOME_DIR.'/upload/error500.html'):__('系统暂时发生错误，请回到首页重新访问');
        $html  = file_get_contents(CORE_DIR.'/shop/view/page/system-error.html');
        foreach($data as $k=>$v){
            $html = str_replace('%'.$k.'%',$v,$html);
        }
        echo $html;
    }else{
        echo $data['html'].$data['body'];
    }
}
?>