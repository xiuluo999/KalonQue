<?php 

require_once  KALONQUE_BASE_PATH . '/Business/Default.php';
class KalonQueBusinessMultiple extends  KalonQueBusinessDefault
{    
    public function main($item)
    {
       $pIndex = $this->_controller->getFirstFreeChildIndex();
       if ($pIndex === false) {
           try {
               $pIndex = $this->_controller->createChild(); 
           } catch (KalonQueControllerException $e) {
               $pIndex = rand(1, $this->_controller->getMaxProcessNum());
           }    
       }
       
       require_once KALONQUE_BASE_PATH . '/Controller/Default.php';
       $childController = new KalonQueControllerDefault($this->_controller->getConfigs());
       $childController->setProcessIndex($pIndex);
       $childController->setBusiness($this->_controller->getChildBusinessName());
       $childController->addStoreItem($item);
       $childController->updateMonitor();
    }
    
    public function setController(KalonQueControllerMultiprocess $controller)
    {
        $this->_controller = $controller;
        return $this;
    }
    
    public function initStore()
    {
        $store     = $this->_controller->getStore();
        $bName     = $this->_controller->getChildBusinessName();
        $dataPath  = $this->_controller->getConfig('KALONQUE_DATA_PATH');
        $storeBasePath = $dataPath . '/'. $bName;
        if (!is_dir($storeBasePath))
            if (!mkdir($storeBasePath, 0777))
                throw new KalonQueBusinessException("cannot make item base dir {$storeBasePath}");
                    
        $storePath = $storeBasePath . '/main';
        $store->setItemFilePath($storePath);
        return $store;
    }
    
    public function initMonitor()
    {
        $monitor  = $this->_controller->getMonitor();
        $bName    = $this->_controller->getChildBusinessName();
        $varPath  = $this->_controller->getConfig('KALONQUE_VAR_PATH');
        $fifoPath = $varPath . '/'. $bName . '/' . $bName . '.fifo';
        
        $monitor->setFifoPath($fifoPath);
        return $monitor;
    }    
}
?>