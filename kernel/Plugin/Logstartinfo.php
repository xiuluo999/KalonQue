<?php 
require_once KALONQUE_BASE_PATH . '/Plugin/Abstract.php';
require_once KALONQUE_BASE_PATH . '/Plugin/Exception.php';

class KalonQuePluginLogstartinfo extends KalonQuePluginAbstract
{    
	const LOG_FILE_NAME = 'start_info.log';	
	
    public function DISPATCH_START(KalonQueControllerAbstract $controller)
    {
    	$business = $controller->getBusiness();
    	$logFile  = $business->getLogPath() . '/' . self::LOG_FILE_NAME;

        $msg      = 'Business:' . $business->getBusinessName() . '; ';
        $msg     .= 'Pid:' . posix_getpid() . '; ';  
        //$msg     .= 'EUid:' . posix_geteuid(). ';';
        $msg     .= 'Started at: ' . date('Y-m-d H:i:s') . '; ' . PHP_EOL;
        
        if (!$fp = fopen($logFile, 'a'))
            return false;
            
        if (false === fwrite($fp, date('[Y-m-d H:i:s] ') . $msg))    
            return false;	
    }
    
    public function DISPATCH_END(KalonQueControllerAbstract $controller)
    {
    	$business = $controller->getBusiness();
    	$logFile  = $business->getLogPath() . '/' . self::LOG_FILE_NAME;

        $msg      = 'Business:' . $business->getBusinessName() . '; ';
        $msg     .= 'Pid:' . posix_getpid() . '; ';  
        //$msg     .= 'EUid:' . posix_geteuid(). ';';
        $msg     .= 'Stopped at: ' . date('Y-m-d H:i:s') . '; ' . PHP_EOL;
        
        if (!$fp = fopen($logFile, 'a'))
            return false;
            
        if (false === fwrite($fp, date('[Y-m-d H:i:s] ') . $msg))    
            return false;	
    }
}
?>