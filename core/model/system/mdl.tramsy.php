<?php
class mdl_tramsy{

    var $left_delimiter            = "<{";
    var $right_delimiter            = "}>";
    var $enable_strip_whitespace = false;
    var $_vars            =    array();
    var $_plugins            =    array();    // stores all internal plugins
    var $_file            =    "";        // the current file we are processing
    var $_literal            =    array();    // stores all literal blocks
    var $_foreachelse_stack        =    array();
    var $_for_stack            =    0;
    var $_if_stack            =   '';
    var $_sectionelse_stack     =   array();    // keeps track of whether section had 'else' part
    var $_switch_stack        =    array();
    var $_tag_stack            =    array();
    var $_require_stack        =    array();    // stores all files that are "required" inside of the template
    var $_head_stack = array();

    function mdl_tramsy(){
        $this->_path_lib = array(
            '1'=>'/'.basename(CORE_INCLUDE_DIR).'/smartyplugins/',
            '2'=>'/admin/smartyplugin/'
        );
    }

    function &_compile_file(&$file_contents){
        $this->_plugins = $this->_parent->_plugins;
        $this->_if_stack = '';


        foreach($this->_parent->_plugins['prefilter'] as $prefilter){
            if(is_array($prefilter)){
                $class = &$prefilter[0];
                $func = $prefilter[1];
                $file_contents = $class->$func($file_contents);
            }else{
                $file_contents = $prefilter($file_contents);
            }
        }

        $ldq = preg_quote($this->left_delimiter,'!');
        $rdq = preg_quote($this->right_delimiter,'!');
        $file_contents = preg_replace("!{$ldq}\*.*?\*{$rdq}!se",'',$file_contents);
        $file_contents = preg_replace("!(\<\?|\?\>)!",'<?php echo \'\1\'; ?>',$file_contents);
        if($this->bundle_vars){
            foreach($this->bundle_vars as $k=>$v){
                if(!$v){
                    unset($this->bundle_vars[$k]);
                }
            }
            if($this->bundle_vars){
                $this->bundle_vars_re = '!\$('.implode('|',$this->bundle_vars).')!';
            }
        }

        foreach(preg_split('!'.$ldq.'(\s*(?:\/|)[a-z\_]+|)(.*?)'.$rdq.'!is',$file_contents,-1,PREG_SPLIT_DELIM_CAPTURE) as $i=>$v){
            $i = $i%3;
            if($i==0){
                if(strpos($this->_if_stack,'2')===false){
                    $compiled_text.=$v;
                }
            }elseif($i==1){
                $func = trim($v);
            }else{

                $bundle_var_only = null;
                $argments = $this->_parse($v,$bundle_var_only);
                if($bundle_var_only===null){
                    $bundle_var_only = strpos(str_replace('$this->bundle_vars','',$argments),'$')===false;
                }

                if($func){
                    $_result = $this->_parse_function($func,$argments,$bundle_var_only);
                    if(strpos($this->_if_stack,'2')===false){
                        $compiled_text.=$_result;
                    }
                }elseif(strpos($this->_if_stack,'2')===false){
                    ob_start();
                    $argments = $this->_fix_modifier($argments,true,$bundle_var_only);
                    $a = ob_get_contents();
                    ob_end_clean();
                    if($bundle_var_only){
                        if($argments){
                            eval('$out = '.$argments.';');
                        }else{
                            echo $a;
                        }
                        $compiled_text.= $out ;
                    }else{
                        $compiled_text.='<?php echo '.$argments.'; ?>';
                    }
                }
            }
        }
        return $compiled_text;
    }

    function post_compile(&$compiled_text){
        if($this->_require_stack){
            $require_text = '';
            foreach ($this->_require_stack as $key => $value){
                if(substr($key,0,7)!='compile'){
                    $require_text .= 'if(!function_exists(\''.$value[2].'\')){ require(CORE_DIR.\''. $this->_get_plugin_dir($key.'.php') . $key . '.php\'); } ';
                }
            }
            foreach ($this->_head_stack as $key => $value){
                $require_text.=$key.';';
            }
            $compiled_text = '<?php '.$require_text.' ?>'.$compiled_text;
        }

        $this->_require_stack = array();
        $this->_head_stack = array();
        if($this->enable_strip_whitespace){
            $compiled_text = $this->strip_whitespace($compiled_text);
        }
        $compiled_text = preg_replace(array('/\<\?php\s*\?\>/','/\?\>\s*\<\?php/'),'',$compiled_text);
    }


    function _parse_function($function,$arguments,$bundle_var_only=false){
        switch ($function) {
        case 't':
        case '/t':
            return;

        case 'ldelim':
            return $this->left_delimiter;

        case 'rdelim':
            return $this->right_delimiter;

        case 'dump':
            $args = $this->_parse_arguments($arguments,false);
            return '<?php var_'.'dump('.$args['var'].'); ?>';
        case 'foreachelse':
            return "<?php }else{ ?>";

        case 'break':
        case 'continue':
            return '<?php ' . $function . '; ?>';
            break;

        case 'if':
            $arguments = $this->_arguments_if($arguments,$bundle_var_only);
            if($bundle_var_only){
                eval('$arguments=('.$arguments.');');
                $this->_if_stack = ($arguments?'106':'206').$this->_if_stack;
                return;
            }else{
                $this->_if_stack = '000'.$this->_if_stack;
                return '<?php if(' . $arguments . '){ ?>';
            }
        case 'else':
            if($this->_if_stack{1}=='5'){
                $this->_if_stack{0} = '2';
                return;
            }elseif($this->_if_stack{0}=='0'){
                $this->_if_stack{1} = '5';
                return '<?php }else{ ?>';
            }elseif($this->_if_stack{0}=='1'){
                $this->_if_stack{0} = '2';
                $this->_if_stack{1} = '5';
                return;
            }elseif($this->_if_stack{0}=='2'){
                $this->_if_stack{0} = '1';
                return;
            }
        case 'elseif':
            if($this->_if_stack{1}=='5'){
                $this->_if_stack{0} = '2';
                return;
            }elseif($this->_if_stack{0}=='0'){ //之前存在if判断
                $arguments = $this->_arguments_if($arguments,$bundle_var_only);
                if($bundle_var_only){
                    eval('$arguments=('.$arguments.');');
                    if($arguments){
                        return $this->_parse_function('else',$arguments,$bundle_var_only);
                    }else{
                        $this->_if_stack{0}='2';
                        $this->_if_stack{2}='6';
                        return;
                    }
                }else{
                    return '<?php }elseif('. $arguments . '){ ?>';
                }
            }elseif($this->_if_stack{0}=='2'){ //之前为否
                $this->_parse_function('/if',$arguments,$bundle_var_only);
                return $this->_parse_function('if',$arguments,$bundle_var_only);
            }elseif($this->_if_stack{0}=='1'){ //之前为绝对是， 禁掉今后所有
                return $this->_parse_function('else',$arguments,$bundle_var_only);
            }
        case '/if':
            if($this->_if_stack{2} == '6'){
                $_result = '';
            }else{
                $_result = '<?php } ?>';
            }
            $this->_if_stack = isset($this->_if_stack{5})?substr($this->_if_stack,3):'';
            return $_result;

        case 'foreach':
            $_args = $this->_parse_arguments($arguments,$bundle_var_only);
            if (!isset($_args['from'])){
                $this->trigger_error("missing 'from' attribute in 'foreach'", E_USER_ERROR, __FILE__, __LINE__);
            }
            if (!isset($_args['value']) && !isset($_args['item'])){
                $this->trigger_error("missing 'value' attribute in 'foreach'", E_USER_ERROR, __FILE__, __LINE__);
            }
            if (isset($_args['value'])){
                $_args['value'] = $this->_dequote($_args['value']);
            }elseif (isset($_args['item'])){
                $_args['value'] = $this->_dequote($_args['item']);
            }
            isset($_args['key']) ? $_args['key'] = "\$this->_vars['".$this->_dequote($_args['key'])."'] => " : $_args['key'] = '';
            if($_args['name']){
                array_push($this->_foreachelse_stack, $_args['name']);
                $_result = '<?php $this->_env_vars[\'foreach\']['.$_args['name'].']=array(\'total\'=>count('.$_args['from'].'),\'iteration\'=>0);foreach ((array)' . $_args['from'] . ' as ' . $_args['key'] . '$this->_vars[\'' . $_args['value'] . '\']){
                    $this->_env_vars[\'foreach\']['.$_args['name'].'][\'first\'] = ($this->_env_vars[\'foreach\']['.$_args['name'].'][\'iteration\']==0);
                    $this->_env_vars[\'foreach\']['.$_args['name'].'][\'iteration\']++;
                    $this->_env_vars[\'foreach\']['.$_args['name'].'][\'last\'] = ($this->_env_vars[\'foreach\']['.$_args['name'].'][\'iteration\']==$this->_env_vars[\'foreach\']['.$_args['name'].'][\'total\']);
?>';
            }else{
                array_push($this->_foreachelse_stack, false);
                $_result = '<?php foreach ((array)' . $_args['from'] . ' as ' . $_args['key'] . '$this->_vars[\'' . $_args['value'] . '\']){ ?>';
            }
            return $_result;

        case '/foreach':
            if ($name = array_pop($this->_foreachelse_stack)){
                return '<?php } unset($this->_env_vars[\'foreach\']['.$name.']); ?>';
            }else{
                return '<?php } ?>';
            }

        case 'literal':
        case 'for':
        case '/for':
        case 'section':
        case 'sectionelse':
        case '/section':
        case 'while':
        case '/while':
            return;

        case 'switch':
            $_args = $this->_parse_arguments($arguments,$bundle_var_only);
            if (!isset($_args['from'])){
                $this->trigger_error("missing 'from' attribute in 'switch'", E_USER_ERROR, __FILE__, __LINE__);
            }
            array_push($this->_switch_stack, array("matched" => false, "var" => $this->_dequote($_args['from'])));
            return;

        case '/switch':
            array_pop($this->_switch_stack);
            return '<?php break; endswitch; ?>';

        case 'case':
            if (count($this->_switch_stack) > 0){
                $_result = "<?php ";
                $_args = $this->_parse_arguments($arguments,$bundle_var_only);
                $_index = count($this->_switch_stack) - 1;
                if (!$this->_switch_stack[$_index]["matched"])
                {
                    $_result .= 'switch(' . $this->_switch_stack[$_index]["var"] . '): ';
                    $this->_switch_stack[$_index]["matched"] = true;
                }else{
                    $_result .= 'break; ';
                }
                if (!empty($_args['value']))
                {
                    $_result .= 'case '.$_args['value'].': ';
                }else{
                    $_result .= 'default: ';
                }
                return $_result . ' ?>';
            }else{
                $this->trigger_error("unexpected 'case', 'case' can only be in a 'switch'", E_USER_ERROR, __FILE__, __LINE__);
            }
            break;

        case 'assign':
            $_args = $this->_parse_arguments($arguments,$bundle_var_only);

            if (!isset($_args['var'])){
                $this->trigger_error("missing 'var' attribute in 'pagedata'", E_USER_ERROR, __FILE__, __LINE__);
            }
            if (!isset($_args['value'])){
                $this->trigger_error("missing 'value' attribute in 'pagedata'", E_USER_ERROR, __FILE__, __LINE__);
            }
            if(false===$_args['value']){
                $_args['value']='false';
            }elseif(null===$_args['value']){
                $_args['value']='null';
            }elseif(''===$_args['value']){
                $_args['value']='';
            }
            return '<?php $this->_vars[' . $_args['var'] . ']='. $_args['value'].'; ?>';

        case 'include':
            if (!function_exists('compile_include')){
                require(CORE_INCLUDE_DIR . "/template/compile.include.php");
            }
            return compile_include($arguments, $this);

        default:
            $_result = "";
            if ($this->_compile_compiler_function($function, $arguments,$bundle_var_only, $_result)){
                return $_result;
            }elseif($this->_compile_custom_block($function, $arguments, $_result)){
                return $_result;
            }elseif ($this->_compile_custom_function($function, $arguments, $_result)){
                return $_result;
            }else{
                $this->trigger_error($function." function does not exist", E_USER_ERROR, __FILE__, __LINE__);
            }
        }
    }

    function _arguments_if($arguments,$bundle_var_only){
        if(!$this->_if_replace){
            $to_replace = array(
                'is\s+not\s+odd'=>'%2==0',
                'is\s+odd'=>'%2==1',
                'neq'=>'!=',
                'eq'=>'==',
                'ne'=>'!=',
                'lt'=>'<',
                'gt'=>'>',
                'lte'=>'<=',
                'le'=>'<=',
                'ge'=>'>=',
                'and'=>'&&',
                'not'=>'!',
                'mod'=>'%',
                'is'=>'==',
            );
            foreach($to_replace as $k=>$v){
                $this->_if_replace[0][] = '!(\s+)'.$k.'(\s+)!i';
                $this->_if_replace[1][] = '\1'.$v.'\2';
            }
        }

        $this->_begin_fix_quote($arguments);
        $arguments = str_replace('||',' or ',$arguments);
        $arguments = preg_replace($this->_if_replace[0],$this->_if_replace[1],$arguments.' ');
        $a = explode(' ',$arguments);
        foreach($a as $i=>$line){
            $a[$i] = $this->_fix_modifier($line,false,$bundle_var_only);
        }
        $arguments = implode(' ',$a);
        $this->_end_fix_quote($arguments);

        return $arguments;
    }

    function _parse_arguments($arguments,$bundle_var_only=false){
        preg_match_all('/([a-z0-9\_]+)=(\'|"|)(.*?([^\\\\]|))\2\s/is',$arguments.' ',$matches,PREG_SET_ORDER);
        $ret = array();
        foreach($matches as $match){
            if($match[2]){
                $ret[$match[1]] = $match[2].$match[3].$match[2];
            }else{
                $ret[$match[1]] = $this->_fix_modifier($match[3],true,$bundle_var_only);
            }
        }
        return $ret;
    }

    function _prepare_fix_quote($match){
        $this->_fix_quotes[$this->_fix_quotes_seq] = $match[0];
        return '_!ok'.($this->_fix_quotes_seq++).'!_';
    }

    function _restone_fix_quote($match){
        return $this->_fix_quotes[$match[1]];
    }

    function _begin_fix_quote(&$variable){
        $this->_fix_quotes_seq=0;
        $this->_fix_quotes = array();
        $variable = preg_replace_callback('/([\'"]).*?(?:[^\\\\]|)\1/',array(&$this,'_prepare_fix_quote'),$variable);
    }

    function _end_fix_quote(&$variable){
        if($this->_fix_quotes){
            $variable = preg_replace_callback('/_!ok([0-9]+)!_/',array(&$this,'_restone_fix_quote'),$variable);
        }
    }

    function _fix_modifier($variable,$fix_quote = true,&$bundle_var_only){
        if(strpos($variable,'|')){
//            $bundle_var_only = false;
            if($fix_quote)$this->_begin_fix_quote($variable);
            $_mods = explode('|',$variable);
            $variable = array_shift($_mods);
            foreach($_mods as $mod){
                if($p=strpos($mod,':')){
                    $_arg = $variable.str_replace(':',',',substr($mod,$p));
                    $mod = substr($mod,0,$p);
                }else{
                    $_arg = $variable;
                }
                if($mod{0}=='@'){
                    $mod = substr($mod,1);
                }
                if($func = $this->_plugin_exists($mod,'compile_modifier')){
                    $variable = $func($_arg,$this,$bundle_var_only);
                }elseif(function_exists($mod)){
                    $variable = $mod.'('.$_arg.')';
                }elseif($func = $this->_plugin_exists($mod, "modifier")){
                    $variable = $func.'('.$_arg.')';
                }else{
                    $variable = "\$this->trigger_error(\"'" . $mod . "' modifier does not exist\", E_USER_NOTICE, __FILE__, __LINE__);";
                }
            }
            if($fix_quote)$this->_end_fix_quote($variable);
        }

        return $variable;
    }

    function _parse($cmd_line,&$bundle_var_only){
        $this->i = 0;
        $this->lib=array();
        $res = preg_replace_callback('!(\$[a-z0-9\_\.\$\[\]\"\\\']+)!is',array(&$this,'_in_str'),$cmd_line);
        foreach($this->lib as $i=>$var){
            $var_ns = '';
            if($p=strpos($var,'.')){
                $first = substr($var,0,$p);

                if($first=='$smarty'){
                    $first='$env';
                    $var = '$env'.substr($var,7);
                    $p=4;
                }

                if($first=='$env'){
                    $bundle_var_only = false;

                    $p = strpos($var,'.',$p+1);
                    if(!$p){
                        $p = strlen($var);
                    }
                    $second = strtoupper(substr($var,5,$p-5));

                    switch($second){
                    case 'CONF':
                        $var = '$this->system->getConf(\''.substr($var,$p+1).'\')';
                        $var_ns = -1;
                        break;
                    case 'GET':
                    case 'POST':
                    case 'COOKIE':
                    case 'ENV':
                    case 'SERVER':
                    case 'SESSION':
                        if($p){
                            $var = substr($var,$p+1);
                        }else{
                            $var = '';
                        }
                        $var_ns = '$_'.$second;
                        break;
                    case 'NOW':
                        $var = '';
                        $var_ns = 'time()';
                        break;
                    case 'SECTION':
                        $var = substr($var,$p+1);
                        $var_ns = '$this->_sections';
                        break;
                    case 'LDELIM':
                        $var = '';
                        $var_ns = '$this->left_delimiter';
                        break;
                    case 'RDELIM':
                        $var = '';
                        $var_ns = '$this->right_delimiter';
                        break;
                    case 'TEMPLATE':
                        $var = '';
                        $var_ns = '$this->_file';
                        break;
                    case 'CONST':
                        $var_ns = 'constant(\''.substr($var,$p+1).'\')';
                        $var = '';
                        break;
                    case 'FOREACH':
                        $var = substr($var,$p+1);
                        $var_ns = '$this->_env_vars[\'foreach\']';
                        break;
                    default:
                        $var_ns = '$this->_env_vars[\''.substr($var,5,$p-5).'\']';
                        $var = substr($var,$p+1);
                        break;
                    }
                }else{
                    $first = substr($first,1);
                    if(isset($this->bundle_vars[$first])){
                        $var_ns = '$this->bundle_vars[\''.$first.'\']';
                    }else{
                        $var_ns = '$this->_vars[\''.$first.'\']';
                    }
                    $var = substr($var,$p+1);
                }
                if($var_ns!=-1){
                    $a = preg_split('/(\.|\[[\\\'a-z0-9\_\"]+\])/',$var,-1,PREG_SPLIT_DELIM_CAPTURE);
                    if($a){
                        $var = '';
                        foreach($a as $k=>$l){
                            if($k%2==1){
                                if($l!='.'){
                                    $var.=$l;
                                }
                            }else{
                                if(isset($l{0})){
                                    if($l{0}!='$' && $l{0}!='"' && $l{0}!='\''){
                                        $l = "'".$l."'";
                                    }
                                    $var.='['.$l.']';
                                }
                            }
                        }
                    }
                }
            }

            if($var_ns!=-1){
                if($this->bundle_vars){
                   $var = $var_ns.preg_replace(array($this->bundle_vars_re,'!\$([a-z0-9\_]+)!i'),array('$this->bundle_vars[\'\1\']','$this->_vars[\'\1\']'),$var);
                }else{
                    $var = $var_ns.preg_replace('!\$([a-z0-9\_]+)!i','$this->_vars[\'\1\']',$var);
                }
            }

            $var = preg_replace_callback('!"_s([0-9]+)s_"!',array(&$this,'_rest'),$var);
            $this->lib[$i] = $var;
        }
        $res = preg_replace_callback('!"_s([0-9]+)s_"!',array(&$this,'_rest_root'),$res);
        $this->lib=array();
        return $res;
    }

    function _rest_root($match){
        return $this->lib[$match[1]];
    }

    function _rest($match){
        return $this->lib[$match[1]];
    }

    function _in_str($varstr){
        $varstr = $varstr[1];
        $varstr = preg_replace_callback('/\[(\$[a-z0-9\_\.]+)\]/i',array(&$this,'_in_sub_str'),$varstr);
        $this->lib[$this->i] = $varstr;
        return '"_s'.($this->i++).'s_"';
    }

    function _in_sub_str($varstr){
        $varstr = $varstr[1];
        $varstr = preg_replace_callback('/\[(\$[a-z0-9\_\.]+)\]/i',array(&$this,__METHOD__),$varstr);
        $this->lib[$this->i] = $varstr;
        return '."_s'.($this->i++).'s_"';
    }

    function strip_whitespace($tpl_source){
        $a = preg_split('/(<\s*(?:pre|script|textarea).*?>.*?<\s*\/\s*(?:pre|script|textarea)\s*>)/is',$tpl_source,-1,PREG_SPLIT_DELIM_CAPTURE);
        $token = 'o-o-o-o';
        $r = '';
        $tpl_source = '';
        foreach($a as $k=>$v){
            if($k % 2 == 0){
                $r.=$v.$token;
                unset($a[$k]);
            }
        }
        $r = preg_replace('/\s+/s',' ',$r);
        foreach(explode($token,$r) as $i=>$txt){
            $tpl_source.=$txt;
            if(isset($a[2*$i+1])){
                $tpl_source.=$a[2*$i+1];
            }
        }
        return $tpl_source;
    }

    function _dequote($string){
        if (($string{0} == "'" || $string{0} == '"') && $string{strlen($string)-1} == $string{0}){
            return substr($string, 1, -1);
        }else{
            return $string;
        }
    }

    function _plugin_exists($function, $type){
        if(!isset($this->_plugins_lib[$function.'_'.$type])){
            $to_require = null;
            $path = $this->_plugin_load($function,$type,$to_require);
            $this->_plugins_lib[$function.'_'.$type] = array($path,$to_require);
        }
        if(($to_require=$this->_plugins_lib[$function.'_'.$type][1]) && !isset($this->_require_stack[$to_require[0].'.'.$to_require[1]])){
            $this->_require_stack[$to_require[0].'.'.$to_require[1]] = $to_require;
        }
        return $this->_plugins_lib[$function.'_'.$type][0];
    }

    function _plugin_load($function, $type, &$to_require){
        if(isset($this->_plugins[$type][$function])){
            if(is_array($this->_plugins[$type][$function]) && is_object($this->_plugins[$type][$function][0]) && method_exists($this->_plugins[$type][$function][0], $this->_plugins[$type][$function][1])){
                return '$this->_plugins[\'' . $type . '\'][\'' . $function . '\'][0]->' . $this->_plugins[$type][$function][1];
            }elseif(function_exists($this->_plugins[$type][$function])){
                return $this->_plugins[$type][$function];
            }else{
                return false;
            }
        }elseif($dir = $this->_get_plugin_dir($type . '.' . $function . '.php')){
            $func = 'tpl_' . $type . '_' . $function;
            if(!function_exists($func)){
                require(CORE_DIR.$this->_get_plugin_dir($type . '.' . $function . '.php') . $type . '.' . $function . '.php');
            }
            $to_require = array($type, $function, $func);
            return ($func);
        }else{
            return false;
        }
    }

    function _compile_compiler_function($function, $arguments,$bundle_var_only, &$_result){
        if ($function = $this->_plugin_exists($function, "compiler")){
            $_args = $this->_parse_arguments($arguments,$bundle_var_only);
            eval('$_result =\'<\'.\'?php \'.'.$function.'($_args,$this,$bundle_var_only).\'?>\';');
            return true;
        }else{
            return false;
        }
    }

    function _compile_custom_function($function,$arguments, &$_result){
        if (!function_exists('compile_compile_custom_function')){
            require(CORE_INCLUDE_DIR . "/template/compile.compile_custom_function.php");
        }
        return compile_compile_custom_function($function, $arguments, $_result, $this);
    }

    function _compile_custom_block($function, $arguments, &$_result){
        if (!function_exists('compile_compile_custom_block')){
            require(CORE_INCLUDE_DIR . "/template/compile.compile_custom_block.php");
        }
        return compile_compile_custom_block($function, $arguments, $_result, $this);
    }


    function _get_plugin_dir($plugin_name){
        if(!$this->_plugin_path){
            require(CORE_INCLUDE_DIR.'/template/compile.plugin_path.php');
            if(defined('__ADMIN__')){
                require(CORE_INCLUDE_DIR.'/template/compile.admin_plugin_path.php');
                $path = array_merge($path,$adminpath);
            }
            $this->_plugin_path = &$path;
        }
        if($this->_plugin_path[$plugin_name]){
            return $this->_path_lib[$this->_plugin_path[$plugin_name]];
        }else{
            return false;
        }
    }

    function get_plugins_by_type($type){
        if(!$this->_plugin_path){
            require(CORE_INCLUDE_DIR.'/template/compile.plugin_path.php');
            if(defined('__ADMIN__')){
                require(CORE_INCLUDE_DIR.'/template/compile.admin_plugin_path.php');
                $path = array_merge($path,$adminpath);
            }
            $this->_plugin_path = &$path;
        }
        foreach($this->_plugin_path as $k=>$v){
            if($p = strpos($k,'.')){
                if(substr($k,0,$p)==$type){
                    $ret[substr($k,$p+1,-4)] = $this->_path_lib[$v].$k;
                }
            }
        }
        return $ret;
    }

    function trigger_error($error_msg, $error_type = E_USER_ERROR, $file = null, $line = null){
        trigger_error($error_msg, $error_type, $file, $line);
    }

}
