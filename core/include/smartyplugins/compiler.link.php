<?php
function tpl_compiler_link($params, &$smarty)
{
    $system = &$GLOBALS['system'];
    $extname = '.'.($system->seoEmuFile?$system->seoEmuFile:'html');
    if(isset($params['args'])){
        $mod = '';
        foreach($params as $key=>$val){
            if($key!='args' && substr($key,0,3)=='arg' && is_numeric($k=substr($key,3))){
                $mod.='$arr['.($k).']='.$val.';';
            }
        }
        $method = isset($params['act'])?$params['act']:'$this->system->request[\'action\'][\'method\']';
        
        if( !$system->getConf('system.seo.emuStatic') ){
            return <<<EOF
            \$arr = (array){$params['args']};
            {$mod}
            array_unshift(\$arr,\$this->system->request['action']['controller']);
            if(isset(\$arr{1}) && !is_numeric(end(\$arr{1}))){
                array_push(\$arr,{$method});
            }
            echo \$this->_env_vars['base_url'],
                 implode('-',\$arr),
                 '{$extname}';
            \$arr=null;
            unset(\$arr);
EOF;

        }else{
        return <<<EOF
            \$arr = (array){$params['args']};
            {$mod}
            array_unshift(\$arr,\$this->system->request['action']['controller']);
            if(isset(\$arr{1}) && !is_numeric(end(\$arr{1}))){
                array_push(\$arr,{$method});
            }
            echo implode('-',\$arr),
                 '{$extname}';
            \$arr=null;
            unset(\$arr);
EOF;
        }
    }else{
        if(!$params['act'])$params['act']="'index'";
        $array = array($params['ctl']);
        foreach($params as $key=>$val){
            if(substr($key,0,3)=='arg' && $val){
                $array[] = $val;
            }
        }

        $lastVal = false;
        foreach($array as $key=>$val){
            if($val{0}=='$'){
                $array[$key] = '{'.$val.'}';
            }elseif($val{0}=='"' || $val{0}=='\''){
                $array[$key] = substr($val,1,-1);
            }
            $lastVal = $val;
        }
        if(!$system->getConf('system.seo.emuStatic')){
            return 'echo $this->_env_vars[\'base_url\'],"'.implode('-',$array).'",'.(
                $lastVal!==false ?
                    "(((is_numeric({$lastVal}) && 'index'=={$params['act']})  || !{$params['act']})?'':'-'.{$params['act']}),"
                  : (($params['act']!='index' && $params['act'] && $params['ctl']!='index')? "'-',{$params['act']}," : '')
            ).'\''.$extname.'\';';
        }else{
            return 'echo "'.implode('-',$array).'",'.(
                $lastVal!==false ?
                    "(((is_numeric({$lastVal}) && 'index'=={$params['act']})  || !{$params['act']})?'':'-'.{$params['act']}),"
                  : (($params['act']!='index' && $params['act'] && $params['ctl']!='index')? "'-',{$params['act']}," : '')
            ).'\''.$extname.'\';';
        }
    }
}
