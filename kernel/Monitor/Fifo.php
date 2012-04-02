<?php 

require_once KALONQUE_BASE_PATH . '/Monitor/Abstract.php';

require_once KALONQUE_BASE_PATH . '/Monitor/Exception.php';

class KalonQueMonitorFifo extends KalonQueMonitorAbstract
{
    private $_fifoPath   = '';
    
    private $_inited     = false;
    
    private $_user       = 'www';
    
    private $_group      = 'www';
    
    private $_flagData   = '1';
    
    public function __construct($path = '')
    {
        if ($path)
            $this->setFifoPath($path);
    }
    
    public function init()
    {
        if (!$this->_fifoPath)
            throw new KalonQueMonitorException(__CLASS__ . " empty fifo path");
        
        $fifoDir = dirname($this->_fifoPath);
        if (!is_dir($fifoDir))
            if (!mkdir($fifoDir, 0777)) 
                throw new KalonQueMonitorException(__CLASS__ . " cannot make fifo dir {$fifoDir}"); 

        //give permission to read and write        
        @chmod($fifoDir, 0777);
        @chown($fifoDir, $this->_user);
        @chgrp($fifoDir, $this->_group);

        //file exists
        if (file_exists($this->_fifoPath)) {
            if (!($fileStat = stat($this->_fifoPath)))
                 throw new KalonQueMonitorException(__CLASS__ . " stat error, file {$this->_fifoPath}");
            
            //not a fifo,unlink
            if (($fileStat['mode'] & 0010000) == 0)
                  unlink($this->_fifoPath);
            else 
                  return true;  
        }
  
        //make fifo
        umask(0);
        if (false === posix_mkfifo($this->_fifoPath, 0777))
            throw new KalonQueMonitorException(__CLASS__ . " cannot make fifo {$this->_fifoPath}"); 

        //force to give permission again
        @chmod($this->_fifoPath, 0777);
        @chown($this->_fifoPath, $this->_user);
        @chgrp($this->_fifoPath, $this->_group);
        
        //set the flag
        $this->_inited = true;
        return true;
    }
    
    public function update()
    {
        if (!$this->_inited)
            $this->init(); 

        if(!($fp = @fopen($this->_fifoPath, 'a+')))
            throw new KalonQueMonitorException(__CLASS__ .
                       " update error: cannot open fifo {$this->_fifoPath}"); 
       
         //write flag to fifo   
         stream_set_write_buffer($fp, 1024);

         if (false === ($len = fputs($fp, $this->_flagData)))   
            throw new KalonQueMonitorException(__CLASS__ .
                       " update error: cannot write flag data to fifo {$this->_fifoPath}"); 
         return true;    
    }
    
    public function isUpdated()
    {
        if (!$this->_inited)
            $this->init();
   
        if (false === ($fp = fopen($this->_fifoPath, 'r')))
            throw new KalonQueMonitorException(__CLASS__ . " isUpdated error: cannot open fifo {$this->_fifoPath}"); 
            
        $dataLen = strlen($this->_flagData);
        $content = '';
        
        //blocking mode read   
        stream_set_blocking($fp, 1);

        $fps = array($fp);
        if (false === ($changedNums = stream_select($fps, $write = NULL, $except = NULL, NULL)))
            throw new KalonQueMonitorException(__CLASS__ . " isUpdated stream_select error"); 
        elseif ($changedNums > 0)
         {
            if (false === ($dataRead = fread($fps[0], 1024)))
                throw new KalonQueMonitorException(__CLASS__ . 
                         " isUpdated error:cannot read flag data from fifo {$this->_fifoPath}");
    
            if (strlen($dataRead) != 0) {
               $content .= $dataRead;   
            }       
        } else {
           // echo "time out"; 
        }
        
        if ($content == '')
            return false;
        else 
            return true;
    }
    
    public function setFifoPath($path)
    {
        $this->_fifoPath = trim($path);
        return $this;
    }
    
    public function getFifoPath()
    {
        return $this->_fifoPath;
    }

    public function DO_EXIT_CLEAN()
    {
        @unlink($this->_fifoPath);
    }
}
?>