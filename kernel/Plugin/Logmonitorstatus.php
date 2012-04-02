<?php 
require_once KALONQUE_BASE_PATH . '/Plugin/Abstract.php';
require_once KALONQUE_BASE_PATH . '/Plugin/Exception.php';

class KalonQuePluginLogMonitorstatus extends KalonQuePluginAbstract
{    
	const LOG_FILE_NAME = 'monitor_status.log';
	
	private $_itemNum = 0;
	
    public function LOOP_START(KalonQueControllerAbstract $controller)
    {
    	$business = $controller->getBusiness();
    	$logFile  = $business->getLogPath() . '/' . self::LOG_FILE_NAME;
        $msg      = 'Monitor wake up.' . PHP_EOL;
        
        if (!$fp = fopen($logFile, 'a'))
            return false;
            
        if (false === fwrite($fp, date('[Y-m-d H:i:s] ') . $msg))    
            return false;	
        
    	$this->_itemNum = 0;
    }
    
    
    public function LOOP_END(KalonQueControllerAbstract $controller)
    {
    	$business = $controller->getBusiness();
    	$logFile  = $business->getLogPath() . '/' . self::LOG_FILE_NAME;
        $msg      = 'Monitor sleep. Received item number:' . $this->_itemNum . PHP_EOL;
        
        if (!$fp = fopen($logFile, 'a'))
            return false;
            
        if (false === fwrite($fp, date('[Y-m-d H:i:s] ') . $msg))    
            return false;	 
    }

    public function REQUEST_START(KalonQueControllerAbstract $controller)
    {
    	$this->_itemNum++;
    }
    
    
    
}
?>