<?php 

require_once KALONQUE_BASE_PATH . '/Business/Exception.php';

abstract class KalonQueBusinessAbstract 
{
    protected $_name  = '';
    
    protected $_DATA_LOAD = '';
    
    protected $_monitorType = '';
    
    protected $_storeType   = '';
    
    protected $_controller  = NULL;
    
    protected $_plugins = array();
    
    protected $_logPath = '';
    
    public function __construct()
    {
    	$this->_init();
    }
    
    protected function _init()
    {
    	
    }
    
    public function initLogPath()
    {
    	$logBasePath = $this->_controller->getConfig('KALONQUE_LOG_PATH');
    	$logPath = $logBasePath . '/' . $this->_name;
    	if (!is_dir($logPath)) {
    		if(!@mkdir($logPath, 0777))
    		    throw new KalonQueBusinessException("init log path failed:" . $logPath);
      	}
      	$this->_logPath = $logPath;
    }
    
    public function getLogPath()
    {
    	return $this->_logPath; 
    }
    
    public function setBusinessName($name)
    {
        $this->_name = strtolower($name);
        return $this;
    }
    
    public function getBusinessName()
    {
        return  $this->_name; 
    }
    
    public function setController(KalonQueControllerAbstract $controller)
    {
        $this->_controller = $controller;
        return $this;
    }
    
    public function getController()
    {
        return $this->_controller;
    }
    
    public function getPlugins()
    {
        return $this->_plugins;
    }
    
    public function addPlugin($plugin, $index = "")
    {
    	$index = (int) $index;
    	if ($index)
    	    $this->_plugins[$index] = $plugin;
    	else
    	    $this->_plugins[] = $plugin;    
    }
    
    public function getMonitorType()
    {
        return $this->_monitorType;
    }
    
    public function getStoreType()
    {
        return $this->_storeType;
    }
    
    public function initStore()
    {
        
    }
    
    public function initMonitor()
    {
        
    }
    
    public function main($item)
    {
        $this->_DATA_LOAD = $this->PARSE_DATA($item);
        $this->_DATA  = $this->_DATA_LOAD;
        
        $this->_preMain();
        
        $result = $this->_main($item);
        
        $this->_postMain();
        
        return $result;
    }  
    
    public function getDataLoad()
    {
    	return $this->_DATA_LOAD;
    }
    
    public function sendToQueue($bName, $data)
    {    	
        $controller = new KalonQueControllerDefault($this->_controller->getConfigs());
        $controller->setBusiness($bName);
        $controller->addStoreItem($data);
        $ret = $controller->updateMonitor();
        return $ret;    	
    }
    
    protected function _main()
    {
    	
    }

    protected function _preMain()
    {
    	
    }
    
    protected function _postMain()
    {
    	
    }
    
    public function PARSE_DATA($item)
    {
    	return $item;
    }    

    public function PHP_ERROR_HANDLE($errno, $errstr, $errfile, $errline)
    {
    	
    }    
    
    public function HOOK_DISPATCH_START()
    {
        
    }
    
    public function HOOK_DISPATCH_END()
    {
        
    }
    
    public function HOOK_LOOP_START()
    {
        
    }
    
    public function HOOK_LOOP_END()
    {
        
    }
    
    public function HOOK_REQUEST_START()
    {
        
    }
    
    public function HOOK_REQUEST_END()
    {
        
    }
    
    public function DO_EXIT_CLEAN()
    {
        
    }    
}
?>