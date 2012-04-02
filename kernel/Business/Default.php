<?php

require_once  KALONQUE_BASE_PATH . '/Business/Abstract.php';

class KalonQueBusinessDefault extends KalonQueBusinessAbstract
{
    protected $_monitorType = 'fifo';
    
    protected $_storeType   = 'file';
    
    protected $_plugins = array();
    
    public function initStore()
    {
        $store     = $this->_controller->getStore();
        $bName     = $this->_name;
        $dataPath  = $this->_controller->getConfig('KALONQUE_DATA_PATH');
        $storePath = $dataPath . '/' . $bName;  
        $store->setItemFilePath($storePath);
        return $store;
    }
    
    public function initMonitor()
    {
        $monitor  = $this->_controller->getMonitor();
        $bName    = $this->_name;
        $varPath  = $this->_controller->getConfig('KALONQUE_VAR_PATH');
        $fifoPath = $varPath . '/'. $bName . '/' . $bName . '.fifo';
        $monitor->setFifoPath($fifoPath);
        return $monitor;
    }

    public function PARSE_DATA($item)
    {
    	$tmp = unserialize($item);
    	if (false !== $tmp)
    	    return $tmp;
    	else
    	    return $item;    
    }    
    
}
?>