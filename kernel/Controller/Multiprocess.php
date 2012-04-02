<?php 

require_once  KALONQUE_BASE_PATH . '/Controller/Abstract.php';

class KalonQueControllerMultiprocess extends KalonQueControllerAbstract
{
    protected $_maxProcessNum = 5;
    
    protected $_childBusinessName = '';
    
    protected $_childProcessList = array();
    
    const PROCESS_STATUS_BUSY = 1;
    
    const PROCESS_STATUS_FREE = 0;
    
    public function setMaxProcessNum($num)
    {
        $this->_maxProcessNum = (int) $num;
        return $this;
    }
    
    public function getMaxProcessNum()
    {
        return $this->_maxProcessNum;
    }
    
    public function setBusiness($bussiness)
    {
        $this->_childBusinessName = $bussiness;        
        parent::setBusiness('Multiple', KALONQUE_BASE_PATH . '/Business');
    }
    
    public function getChildBusinessName()
    {
        return $this->_childBusinessName; 
    }
    
    public function setChildBusinessName($bName)
    {
        $this->_childBusinessName = $bName;
        return $this;
    }
    
    protected function _daemonize()
    {
        require_once KALONQUE_ROOT_PATH . '/include/KalonDaemon.php';
        $daemon = new KalonDaemon();
        
        $bName       = $this->getChildBusinessName();
        $varPath     = $this->getConfig('KALONQUE_VAR_PATH');
        $pidFilePath = $varPath . '/' . $bName . '/';
        $pidFileName = 'daemon.pid';
        try {
            $daemon->setPidFilePath($pidFilePath);
            $daemon->setPidFileName($pidFileName);
            $daemon->start();
        } catch (KalonDaemonException $e) {
            //Exception catch in Daemon, throw again
            throw new KalonQueControllerException(__CLASS__ . 
                     ': daemonize failed with message - ' . $e->getMessage());
        }
        return true;        
    }   
    
    protected function _undaemonize()
    {
        $bName    = $this->getChildBusinessName();
                
        $varPath     = $this->getConfig('KALONQUE_VAR_PATH');
        $pidFilePath = $varPath . '/' . $bName . '/';
        $pidFileName = 'daemon.pid';
        
        //ready for daemonize
        try {
            require_once KALONQUE_ROOT_PATH . '/include/KalonDaemon.php';
            $daemon = new KalonDaemon();
            $daemon->setPidFilePath($pidFilePath);
            $daemon->setPidFileName($pidFileName);
            $daemon->sendSignal(SIGUSR1);
            $daemon->stop(true);
        } catch (KalonDaemonException $e) {
            //Exception catch in Daemon, throw again
            throw new KalonQueControllerException(__CLASS__ . 
                     ': daemonize failed with message - ' . $e->getMessage());
        }  
    }     
    
    public function createChild()
    {
        $existedChilds = count($this->_childProcessList);
        if ($existedChilds >= $this->_maxProcessNum)
            throw new KalonQueControllerException("Create child failed,max child num is {$this->_maxProcessNum}");

        require_once  KALONQUE_BASE_PATH . '/Controller/Default.php';
        $pid = pcntl_fork();
        if ($pid == -1) {
            throw new KalonQueControllerException("Create child failed,error happened while fork process");
        } elseif ($pid == 0) {
            $childController = new KalonQueControllerDefault($this->getConfigs());
            $childIndex      = ++$existedChilds;
            $childController->setProcessIndex($childIndex);
            $childController->setProcessId(posix_getpid());
            $childController->setBusiness($this->_childBusinessName);
            //this plugin is needed!!
            $childController->addPlugin('Mutliprostatus');
            $childController->setConfig('DAEMON_MODEL', false);
            //DO CLEAN directly!!
            $childController->setConfig('DO_EXIT_CLEAN', true);
            $childController->start();
        } else {
            //waiting for child process
            usleep(1000);
            $this->_childProcessList[++$existedChilds] = $pid;
            return $existedChilds;
        } 
    }
    
    protected function _getChildStatusByIndex($pIndex)
    {
        $varPath   = $this->getConfig('KALONQUE_VAR_PATH');
        $statsFile = $varPath . '/'. $this->_childBusinessName . '/stats_' . $pIndex;
        if (!file_exists($statsFile))
            return false;
            
        if ($fp = fopen($statsFile, 'r'))
            if(false !== ($status = fread($fp , 1024)))
                return (int)$status;    
            
        return false;        
    }
    
    public function getFirstFreeChildIndex()
    {
        foreach ($this->_childProcessList as $index => $pid) {
        	$status = $this->_getChildStatusByIndex($index);
        	if ($status === 0)
        	    return $index;
        	if ($status === false)
        	    return false;    
        }
        return false;
    }
    
    
    public function stopAllChildren()
    {
        foreach ($this->_childProcessList as $index => $pid) {
            echo $pid . "\n";
            $this->stopOneChild($index);
        }
    }
    
    public function stopOneChild($childIndex)
    {
        require_once  KALONQUE_BASE_PATH . '/Controller/Default.php';
        try {
            $childController = new KalonQueControllerDefault($this->getConfigs());
            $childController->setProcessIndex($childIndex);
            $childController->setProcessId($pid = $this->_childProcessList[$childIndex]);
            $childController->setBusiness($this->_childBusinessName);
            
            if (!posix_kill($pid, SIGKILL))
                throw new KalonQueControllerException("cannot stop child.");
                
            $childController->DO_EXIT_CLEAN();
            
        } catch (Exception $e) {
            throw new KalonQueControllerException("stop child process pid {$pid} failed
                      ,error msg is {$e}");
        }
        return true;    
    }
    
    public function DO_EXIT_CLEAN()
    {
        $this->stopAllChildren();
        
        $varPath   = $this->getConfig('KALONQUE_VAR_PATH');
        //move child status file
        foreach ($this->_childProcessList as $pIndex => $pId) {
            @unlink($varPath . '/'. $this->_childBusinessName . '/stats_' . $pIndex);
        }
        
        parent::DO_EXIT_CLEAN();
    }
}
?>