<?php
$constants = array(
        'OBJ_PRODUCT'=>1,
        'OBJ_ARTICLE'=>2,
        'OBJ_SHOP'=>0,
        'MIME_HTML'=>'text/html',
        'P_ENUM'=>1,
        'P_SHORT'=>2,
        'P_TEXT'=>3,
        'SCHEMA_DIR'=>BASE_DIR.'/plugins/schema/',
        'HOOK_BREAK_ALL'=>-1,
        'HOOK_FAILED'=>0,
        'HOOK_SUCCESS'=>1,
        'MSG_OK'=>true,
        'MSG_WARNING'=>E_WARNING,
        'MSG_ERROR'=>E_ERROR,
        'MNU_LINK'=>0,
        'PAGELIMIT'=>20,
        'MNU_BROWSER'=>1,
        'MNU_PRODUCT'=>2,
        'MNU_ARTICLE'=>3,
        'MNU_ART_CAT'=>4,
        'PLUGIN_BASE_URL'=>'plugins',
        'MNU_TAG'=>5,
        'TABLE_REGEX'=>'([]0-9a-z_\:\"\`\.\@\[-]*)',
        'PMT_SCHEME_PROMOTION'=>0,
        'PMT_SCHEME_COUPON'=>1,
        'APP_ROOT_PHP'=>'',
        'SET_T_STR'=>0,
        'SET_T_INT'=>1,
        'SET_T_ENUM'=>2,
        'SET_T_BOOL'=>3,
        'SAFE_MODE'=>false,
        'SET_T_TXT'=>4,
        'SET_T_FILE'=>5,
        'SET_T_DIGITS'=>6,
        'LC_MESSAGES'=>6,
        'BASE_LANG'=>'zh_CN',
        'DEFAULT_LANG'=>'zh_CN',
        'DEFAULT_INDEX'=>'',
        'ACCESSFILENAME'=>'.htaccess',
        'DEBUG_TEMPLETE'=>false,
        'PRINTER_FONTS'=>'',
        'PHP_SELF'=>(isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : $_SERVER['SCRIPT_NAME']),
        'APP_WLTX_ID'=>'se_001',
        'APP_WLTX_VERSION'=>'ve_001',
        'APP_WLTX_URL'=>'http://service.shopex.cn/openapi/api.php',
        'GENERALIZE_URL'=>'http://b2b.fenxiaowang.com',
        'OUTER_SERVICE_URL'=>'http://allinweb-service.shopex.cn',
        'RSC_PORTAL'=>'http://app.shopex.cn/exuser',
        'RSC_RPC'=>'http://rpc.app.shopex.cn',
        'TPL_INSTALL_URL'=>'http://addons.shopex.cn/templates/',
        'APP_INSTALL_URL'=>'http://app.shopex.cn/exuser/',
        'DAPI_URL'=>'http://rpc.app.shopex.cn/dapi',
    );

foreach($constants as $k=>$v){
    if(!defined($k))define($k,$v);
}

$_SERVER['REQUEST_URI'] = 'http://'.$_SERVER['HTTP_HOST'].PHP_SELF.($_SERVER["QUERY_STRING"]?'?'.$_SERVER["QUERY_STRING"]:'');


define('API_DIR', CORE_DIR.'/api');
define("VERIFY_APP_ID","shopex_b2c");

define('PLATFORM','platform');
define('PLATFORM_HOST','api-b2c.shopex.cn');
define('PLATFORM_PATH','/api.php');
define('PLATFORM_PORT',80);
define('IMAGESERVER','imgserver');
define('IMAGESERVER_HOST','imgsrvs.shopex.cn');
define('IMAGESERVER_PATH','/api.php');
define('IMAGESERVER_PORT',80);
define('SERVER_PLATFORM_NOTICE','/maintenance.txt');

define('API_VERSION','3.0');
