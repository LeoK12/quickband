<?php
function get_class_struct($content,$classname){
    if(preg_match('/class\s+'.preg_quote($classname).'\s*[^\{]*\{.*/is',str_replace('{$','{',$content),$match)){
        $tags = token_get_all('<?php '.$match[0].'?>');

        $in_func_ready = $propname = $in_func = $in_string = false;

        $funcs = array();
        $props = array();

        foreach($tags as $t){
            if($in_string){
                if($in_string=='"' && $t=='"'){
                    $in_string = false;
                }
            }else{
                if($in_func){
                    if($t[0]=='{'){
                        $in_func++;
                    }elseif($t[0]=='}'){
                        $in_func--;
                    }
                }elseif($in_func_ready){
                    switch($t[0]){
                        case T_STRING:
                            $t[1] = strtolower($t[1]);
                            $funcs[$t[1]] = $t[1];
                        break;

                        case '{':
                            $in_func = 1;
                            $in_func_ready = false;
                        break;
                    }
                }elseif($t=='}'){
                    break;
                }elseif(is_array($t)){
                    if($propname){
                        switch($t[0]){
                            case T_WHITESPACE:
                                break;
                            case T_STRING:
                            case T_LNUMBER:
                            case T_DNUMBER:
                            case T_STRING:
                            case T_NUM_STRING:
                                 $props[$propname] = $t[1];
                            case T_CONSTANT_ENCAPSED_STRING:
                                if($t[1]{0}=='\'' || $t[1]{0}=='\"'){
                                    $props[$propname] = substr($t[1],1,-1);
                                }elseif(defined($t[1])){
                                    $props[$propname] = constant($t[1]);
                                }else{
                                    $props[$propname] = $t[1];
                                }
                            default:
                                $propname = null;
                        }
                    }else{
                        switch($t[0]){
                            case T_VARIABLE:
                              $propname=substr($t[1],1);
                            break;

                            case T_FUNCTION:
                                $in_func_ready = true;
                            break;
                        }
                    }
                }
            }
        }
        return array('class_name'=>$classname,'props'=>$props,'funcs'=>$funcs);
    }else{
        return false;
    }
}
?>