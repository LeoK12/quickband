<?php
define('SQL_MYISAM_SYNTAX','type = MyISAM DEFAULT CHARACTER SET utf8');
define('SQL_HEAP_SYNTAX','type = HEAP DEFAULT CHARACTER SET utf8');
function database_split_sql($sql,&$object){
    $ret = array();
    $sql          = trim($sql);
    $sql_len      = strlen($sql);
    $char         = '';
    $string_start = '';
    $in_string    = FALSE;

    $sql = trim($sql);
    if($object->dbver == 3){
        $sql = str_replace(SQL_MYISAM_SYNTAX,'',$sql);
        $sql = str_replace(SQL_HEAP_SYNTAX,'',$sql);
    }elseif($object->dbver == 6){
        $sql = str_replace(SQL_MYISAM_SYNTAX,str_replace('type = ','engine = ',SQL_MYISAM_SYNTAX),$sql);
        $sql = str_replace(SQL_HEAP_SYNTAX,str_replace('type = ','engine = ',SQL_HEAP_SYNTAX),$sql);
    }
    preg_match_all('/(INSERT|UPDATE|DELETE|DROP|CREATE)+[^\n]+\;/i',$sql,$matches);
    if(is_array($matches[0])){
        return $matches[0];
    }else{
        return false;
    }
    /*
    for ($i = 0; $i < $sql_len; ++$i) {
        $char = $sql[$i];

        // We are in a string, check for not escaped end of strings except for
        // backquotes that can't be escaped
        if ($in_string) {
            for (;;) {
                $i         = strpos($sql, $string_start, $i);
                // No end of string found -> add the current substring to the
                // returned array
                if (!$i) {
                    $ret[] = $sql;
                    return $ret;
                }
                // Backquotes or no backslashes before quotes: it's indeed the
                // end of the string -> exit the loop
                else if ($string_start == '`' || $sql[$i-1] != '\\') {
                    $string_start      = '';
                    $in_string         = FALSE;
                    break;
                }
                // one or more Backslashes before the presumed end of string...
                else {
                    // ... first checks for escaped backslashes
                    $j                     = 2;
                    $escaped_backslash     = FALSE;
                    while ($i-$j > 0 && $sql[$i-$j] == '\\') {
                        $escaped_backslash = !$escaped_backslash;
                        $j++;
                    }
                    // ... if escaped backslashes: it's really the end of the
                    // string -> exit the loop
                    if ($escaped_backslash) {
                        $string_start  = '';
                        $in_string     = FALSE;
                        break;
                    }
                    // ... else loop
                    else {
                        $i++;
                    }
                } // end if...elseif...else
            } // end for
        } // end if (in string)

        // We are not in a string, first check for delimiter...
        else if ($char == ';') {
            // if delimiter found, add the parsed part to the returned array
            $ret[]      = substr($sql, 0, $i);
            $sql        = ltrim(substr($sql, min($i + 1, $sql_len)));
            $sql_len    = strlen($sql);
            if ($sql_len) {
                $i      = -1;
            } else {
                // The submited statement(s) end(s) here
                return $ret;
            }
        } // end else if (is delimiter)

        // ... then check for start of a string,...
        else if (($char == '"') || ($char == '\'') || ($char == '`')) {
            $in_string    = $ret;
            $string_start = $char;
        } // end else if (is start of string)

        // ... for start of a comment (and remove this comment if found)...
        else if ($char == '#'
            || ($char == ' ' && $i > 1 && $sql[$i-2] . $sql[$i-1] == '--')) {
                // starting position of the comment depends on the comment type
                $start_of_comment = (($sql[$i] == '#') ? $i : $i-2);
                // if no "\n" exits in the remaining string, checks for "\r"
                // (Mac eol style)
                $end_of_comment   = (strpos(' ' . $sql, "\012", $i+2))
                    ? strpos(' ' . $sql, "\012", $i+2)
                    : strpos(' ' . $sql, "\015", $i+2);
                if (!$end_of_comment) {
                    // no eol found after '#', add the parsed part to the returned
                    // array if required and exit
                    if ($start_of_comment > 0) {
                        $ret[]    = trim(substr($sql, 0, $start_of_comment));
                    }
                    return $ret;
                } else {
                    $sql          = substr($sql, 0, $start_of_comment)
                        . ltrim(substr($sql, $end_of_comment));
                    $sql_len      = strlen($sql);
                    $i--;
                } // end if...else
            } // end else if (is comment)
    } //end for

    // add any rest to the returned array
    if (!empty($sql) && ereg('[^[:space:]]+', $sql)) {
        $ret[] = $sql;
    }
    */

    return $ret;
}
?>
