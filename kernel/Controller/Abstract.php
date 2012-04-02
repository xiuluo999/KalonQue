<?php 
require_once KALONQUE_BASE_PATH . '/Controller/Exception.php';

abstract class KalonQueControllerAbstract
{
    protected $_store   = NULL;
    
    protected $_monitor = NULL;
    
    protected $_business = NULL;
    
    protected $_stopFlag = false;
    
    protected $_plugins  = array();
    
    protected $_config   = array();
    
    protected $_stopTimeOut = 10;
    
	private $_verbose = true;    
    
    public function __construct($config = '')
    {
        if (is_array($config))
            $this->setConfigs($config);
    }
    
    public function setConfigs(array $config)
    {
        $this->_config = $config;
        return $this;
    }
    
    public function getConfigs()
    {
        return $this->_config;
    }
    
    public function getConfig($keyName)
    {
        return $this->_config[$keyName];
    }
    
    public function setConfig($key, $value)
    {
        $this->_config[$key] = $value;
        return $this;
    }
    
    public function setStore(KalonQueStoreAbstract $store)
    {
        $this->_store = $store;
        return $this;
    }
    
    public function getStore()
    {
        return $this->_store;
    }
    
    public function setMonitor(KalonQueMonitorAbstract $monitor)
    {
        $this->_monitor = $monitor;
        return $this;
    }
    
    public function getMonitor()
    {
        return $this->_monitor;
    }
    
    public function addStoreItem($item)
    {
    	if (!is_array($item))
    	    $item = array($item);

    	for ($i = 0, $max = count($item); $i < $max; $i++) {
    		$item[$i] = rawurlencode($item[$i]);
    	}
    	
    	$item = implode('\n', $item);
    	    
        return $this->_store->addItem($item);
    }
    
    public function deleteStoreItem($itemId)
    {
        return $this->_store->deleteItem($itemId);
    }
    
    public function readStore()
    {
         return $this->_store->read();
    }
    
    public function popStore()
    {
        return $this->_store->pop();
    }
    
    public function updateMonitor()
    {
        return $this->_monitor->update();
    }
    
    public function isMonitorUpdated()
    {
        return $this->_monitor->isUpdated();
    }
    
    public function isStoreEmpty()
    {
        return $this->_store->isEmpty();
    }
    
    public function getBusiness()
    {
        return $this->_business;
    }
    
    public function start()
    {
    	$this->_verboseInfo("LINE");
    	$this->_verboseInfo("Ready to start");
    	
    	if (!$this->_business)
            throw new KalonQueControllerException(__CLASS__ . 
                      ': you need to set business before start');

        $this->_verboseInfo("Business name : " . $this->_business->getBusinessName());
        
        if ($this->getConfig('DAEMON_MODEL')) {
            if($this->isActive()) {
        	    $this->_verboseInfo("Start failed : business is running." );
        	    $this->_verboseInfo("LINE");
        	    die();	
    	    }        	
        	
        	try {
                $pid = $this->_daemonize();
        	} catch(KalonQueControllerException $e) {
        	    $this->_verboseInfo("Start failed : " . $e->getMessage());
        	    $this->_verboseInfo("LINE");
        	    die();	
        	}   
            $this->_verboseInfo("Daemonized with pid : $pid"); 
        }
   
        $this->_verboseInfo("Started at : " . date('Y-m-d H:i:s')); 
        $this->_verboseInfo("Success start!"); 
        $this->_verboseInfo("Go to dispatch loop , waiting for monitor updates... "); 
        
        $this->_verboseInfo("LINE");
        $this->dispatch();
    }
    
    public function stop($force = false)
    {     
    	//$force = true;
    	$this->_verboseInfo("LINE");
    	$this->_verboseInfo("Ready to stop"); 
    	   	      
        //$this->DO_EXIT_CLEAN();      	
    	if (!$this->_business)
            throw new KalonQueControllerException(__CLASS__ . 
                      ': you need to set business before stop');
            
        $this->_verboseInfo("Business name : " . $this->_business->getBusinessName());
        $stopModel = $force ? 'force' : 'normal';
    	$this->_verboseInfo("Stop model : " . $stopModel);
        
    	if ($force) {
    		$this->_verboseInfo("(force model will use kill -9)");    		
    	}
    	
    	if(!$this->isActive()) {
        	$this->_verboseInfo("Stop failed : queue business is not running." );
        	$this->_verboseInfo("LINE");
        	die();	
    	}
    	
    	try {
            $this->_undaemonize($force); 
    	} catch (KalonQueControllerException $e) {
        	$this->_verboseInfo("Stop failed : " . $e->getMessage());
        	$this->_verboseInfo("LINE");
        	die();	
    	}
    	
    	if (!$force) {
    	    $this->_verboseInfo("Stopping...(if it takes too long,please use force mode -f instead)");
    	
        	sleep(1);//sleep for a while to make sure signal handle run success
    	
    	    $this->updateMonitor();    
    	    
    	    $timeSleeped = 0;
            while ($this->isActive()) {
        	    sleep(1);//waiting for business to finish its work
        	    $timeSleeped++;
        	    if ($timeSleeped >= $this->_stopTimeOut) {
        	    	$this->_verboseInfo("Stop failed : time out (". $this->_stopTimeOut."),maybe the business is too busy.");
        	    	$this->_verboseInfo("use force mode -f instead or give a long time out value");
        	    	$this->_verboseInfo("LINE");
        	    	die();
        	    }
            }
    	}
    	
        $this->_verboseInfo("Stopped at : " . date('Y-m-d H:i:s')); 
        $this->_verboseInfo("Success stop!");
    	$this->_verboseInfo("LINE");
    } 

    public function isActive()
    {
        $daemon = $this->_loadDaemon();
        return $daemon->isActive();
    }    
    
    protected function _daemonize()
    {
        try {
            $daemon = $this->_loadDaemon();
            $daemon->addSignalHandler(SIGUSR1, array($this, 'signalHandleStop'));
            $daemon->start();
            return $daemon->getDaemonPid();
        } catch (KalonDaemonException $e) {
            //Exception catch in Daemon, throw again
            throw new KalonQueControllerException(__CLASS__ . 
                     ': daemonize failed with message - ' . $e->getMessage());
        }
        return true;        
    }
    
    protected function _undaemonize($force = false)
    {
        try {
            $daemon = $this->_loadDaemon();
            
            if (!$force)
                $daemon->sendSignal(SIGUSR1);
            else
                $daemon->stop(true);
                
        } catch (KalonDaemonException $e) {
            //Exception catch in Daemon, throw again
            throw new KalonQueControllerException(__CLASS__ . 
                     ': daemonize failed with message - ' . $e->getMessage());
        }  
    }
    

    
    protected function _loadDaemon()
    {
        $bName    = $this->_business->getBusinessName();        
        $varPath     = $this->getConfig('KALONQUE_VAR_PATH');
        $pidFilePath = $varPath . '/' . $bName;
        $pidFileName = 'daemon.pid';
        require_once KALONQUE_ROOT_PATH . '/include/KalonDaemon.php';
        $daemon = new KalonDaemon();
        $daemon->setPidFilePath($pidFilePath);
        $daemon->setPidFileName($pidFileName);
        return $daemon;	
    }
    
    public function dispatch()
    {  
       $this->_invokePluginHook('DISPATCH_START');
       
       $this->_business->HOOK_DISPATCH_START(); 
       
       while (!$this->_stopFlag && $this->isMonitorUpdated()) {
            
           $this->_invokePluginHook('LOOP_START');
           
           $this->_business->HOOK_LOOP_START();
           
            do {
                $this->readStore();

                while ($item = $this->popStore()) {
                    $itemId    = key($item);
                    $itemData  = trim($item[$itemId]);
                    $itemBodys = explode('\n', $itemData);

                    foreach ($itemBodys as $itemBody) {
                        $itemBody = rawurldecode($itemBody);
                    	
                        $this->_invokePluginHook('REQUEST_START');
                        
                        $this->_business->HOOK_REQUEST_START();
                        
                        $result = $this->_business->main($itemBody);
                        
                        $this->_invokePluginHook('REQUEST_END');
                        
                        $this->_business->HOOK_REQUEST_END();
                    }
                    
                    $this->deleteStoreItem($itemId);
                }    
            } while (!$this->isStoreEmpty());
            
           $this->_invokePluginHook('LOOP_END');
           
           $this->_business->HOOK_LOOP_END();
       }
       
       $this->_invokePluginHook('DISPATCH_END');
       
       $this->_business->HOOK_DISPATCH_END(); 
    }
    
    protected function _invokePluginHook($hookName)
    {
       for ($i = 0, $max = count($this->_plugins); $i < $max; $i++) {
            $this->_plugins[$i]->$hookName($this);
        }  
    }
    

    public function addPlugin($plugin)
    {
        $pluginInstance = $this->load('Plugin', $plugin);
        if (!$pluginInstance instanceof KalonQuePluginAbstract)
            throw new KalonQueControllerException(__CLASS__ . " :plugin object {$plugin} is not a plugin type"); 
            
        $this->_plugins[] = $pluginInstance;
        return true;       
    }

    public function setBusiness($business, $pathLookup = '')
    {
        if (!$pathLookup)
            $pathLookup = $this->getConfig('KALONQUE_BUSI_PATH');
            
        $busInstance = $this->load('Business', $business, $pathLookup);
        if (!$busInstance instanceof KalonQueBusinessAbstract)
             throw new KalonQueControllerException(__CLASS__ . " : {$business} is not a business type"); 
             
        $busInstance->setBusinessName($business);
        $busInstance->setController($this);
       
        $monitorType = $busInstance->getMonitorType();
        $storeType   = $busInstance->getStoreType();
        
        $monitorInstance = $this->load('Monitor', $monitorType);
        if (!$monitorInstance instanceof KalonQueMonitorAbstract)
            throw new KalonQueControllerException(__CLASS__ . " : {$monitorType} is not a monitor type"); 
        else 
            $this->setMonitor($monitorInstance);
            
        $storeInstance   = $this->load('Store', $storeType);
        if (!$storeInstance instanceof KalonQueStoreAbstract)
            throw new KalonQueControllerException(__CLASS__ . " : {$storeType} is not a store type"); 
        else 
            $this->setStore($storeInstance);
         
        $busInstance->initStore();
        $busInstance->initMonitor();    
        
        $plugins = $busInstance->getPlugins();
        for ($i = 0, $max = count($plugins); $i < $max; $i++)
            $this->addPlugin($plugins[$i]);
        
        $busInstance->initLogPath();    
            
        //set_error_handler(array($this,'phpErrorHandle'));
        
        $this->_business = $busInstance;
            
        return $this;
    }    
    
    public function load($module, $name, $path = '')
    {
        $name   = ucfirst(strtolower($name));
        $module = ucfirst(strtolower($module));
        if ($path)
            $file   = rtrim($path, '/') . '/' . $name . '.php';
        else    
            $file   = KALONQUE_BASE_PATH . '/' . $module . '/' . $name . '.php';
        
        if (!file_exists($file))
            throw new KalonQueControllerException(__CLASS__ . " :load failed.$file not exist");
        
        require_once $file;    
        $class  = 'KalonQue' . $module . $name;
        if (!class_exists($class))
            throw new KalonQueControllerException(__CLASS__ . " :load failed. class {$class} not declare");

        return $instance = new $class();    
    }
    
    public function signalHandleStop()
    {
    	$this->_stopFlag = true;
    }
    
    public function phpErrorHandle($errno, $errstr, $errfile, $errline)
    {
    	$errLogPath = $this->_business->getLogPath();
    	if (!$errLogPath)
    	    return false;
    	  
    	$errCodes = array(E_ERROR            => 'E_ERROR',
    	                  E_WARNING          => 'E_WARNING',
    	                  E_PARSE            => 'E_PARSE',
    	                  E_NOTICE           => 'E_NOTICE',
    	                  E_CORE_ERROR       => 'E_CORE_WARNING',
    	                  E_COMPILE_ERROR    => 'E_COMPILE_ERROR',
    	                  E_COMPILE_WARNING  => 'E_COMPILE_WARNING',
    	                  E_USER_ERROR       => 'E_USER_ERROR',
    	                  E_USER_WARNING     => 'E_USER_WARNING',
    	                  E_USER_NOTICE      => 'E_USER_NOTICE',
    	                  E_STRICT           => 'E_STRICT',
    	                  E_RECOVERABLE_ERROR=> 'E_RECOVERABLE_ERROR',
    	                  E_DEPRECATED       => 'E_DEPRECATED',
    	                  E_USER_DEPRECATED  => 'E_USER_DEPRECATED ',
    	                  E_ALL              => 'E_ALL');    
    	    
    	$errLogFile = $errLogPath . "/php_error.log";    
    	
    	$errno   = $errCodes[$errno];
    	
    	$errStr  = "[" . date('Y-m-d H:i:s') . "] $errno: $errstr in $errfile on line $errline" . PHP_EOL;

    	if(!$fp = fopen($errLogFile, 'a'))
    	    return false;    
    	    
    	if (false === fwrite($fp, $errStr))
    	    return false;      
    	    
        switch ($errno) {
            case E_USER_ERROR:
                exit(1);
                break;
            default:
                break;
        }
        
        return true;
    }
    
    public function DO_EXIT_CLEAN()
    {   
        $this->_monitor->DO_EXIT_CLEAN();
        
        $this->_store->DO_EXIT_CLEAN();
        
        $this->_business->DO_EXIT_CLEAN();
    } 

    protected function _verboseInfo($msg)
    {
    	if ($this->_verbose) {
    		if ($msg == 'LINE')
    		    $msg = '';//$msg = '-----------------------------------------------------------------';
    	    echo $msg . PHP_EOL;
    	}    
    }
}
?>