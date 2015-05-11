<?php
function tpl_compiler_input($params, &$compiler){
    if(!$params['params'] && strpos($params['type'],'$')===false){
        $type = ($params['type'][0]=='"' || $params['type'][0]=='\'' )?substr($params['type'],1,-1):$params['type'];
        if(substr($type,0,7)=='object:'){
            list(,$object,$params['key']) = explode(':',$type);

            $params['object'] = "'".$object."'";
            $function = $compiler->_plugin_exists('object_'.str_replace('/','_',$object),'input');

            if(!$function){
                $function = $compiler->_plugin_exists('object','input');
            }
        }else{
            $function = $compiler->_plugin_exists($type,'input');
        }

        if(!$function){
            $function = $compiler->_plugin_exists($type='default','input');
        }else{
            unset($params['type']);
        }

        $_args = array();
        foreach($params as $key => $value){
            if (is_bool($value)){
                $value = $value ? 'true' : 'false';
            }elseif (is_null($value)){
                $value = 'null';
            }
            $_args[$key] = "'$key' => $value";
        }

        return 'echo '.$function . '(array(' . implode(',', (array)$_args) . '), $this);';
    }else{
        $compiler->_plugin_exists('default','input');
        if($params['params']){
            $return = '$params = '.$params['params'].';';
        }else{
            $return = '$params = array();';
        }
        unset($params['params']);

        if(!$compiler->_included_input_func_map){
            $return.='$this->input_func_map = '.var_export($compiler->get_plugins_by_type('input'),1).';';
            $compiler->_included_input_func_map = true;
        }
        foreach($params as $key => $value){
            if (is_bool($value)){
                $value = $value ? 'true' : 'false';
            }elseif (is_null($value)){
                $value = 'null';
            }
            $return .= "\$params['$key'] = $value;";
        }
        $return.=<<<EOF
if(substr(\$params['type'],0,7)=='object:'){
    list(,\$params['object'],\$params['key']) = explode(':',\$params['type']);
    \$obj = str_replace('/','_',\$params['object']);
    \$func = 'tpl_input_object_'.\$obj;
    if(!function_exists(\$func)){
        if(isset(\$this->input_func_map['object_'.\$obj])){
            require(CORE_DIR.\$this->input_func_map['object_'.\$obj]);
            \$this->_plugins['input']['object_'.\$obj] = \$func;
        }else{
            \$func = 'tpl_input_object';
            \$params['type'] = 'object';
        }
    }
}else{
    \$func = 'tpl_input_'.\$params['type'];
}
if(function_exists(\$func)){
    echo \$func(\$params,\$this);
}elseif(isset(\$this->input_func_map[\$params['type']])){
    require(CORE_DIR.\$this->input_func_map[\$params['type']]);
    \$this->_plugins['input'][\$params['type']] = \$func;
    echo \$func(\$params,\$this);
}else{
    echo tpl_input_default(\$params,\$this);
}
unset(\$func,\$params);
EOF;
        return $return;
    }
}
?>