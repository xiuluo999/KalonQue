<?php 

require_once KALONQUE_BASE_PATH . '/Store/Interface.php';

abstract class KalonQueStoreAbstract implements KalonQueStoreInterface
{
    protected $_maxItemRead  = 1000;
    
    public  function addItem($item)
    {
        
    }
    
    public  function deleteItem($itemId)
    {
        
    }
    
    public  function read()
    {
        
    }
    
    public  function isEmpty()
    {
        
    }
    
    public function pop()
    {
    	
    }
    
    public function setMaxItemRead($num)
    {
        if ($num > 0)
            $this->_maxItemRead = (int) $num;

        return $this;    
    }
    
    public function getMaxItemRead()
    {
        return $this->_maxItemRead;
    }
    
    protected  function _makeItemId()
    {
        return md5(uniqid(mt_rand(), true));
    }
    
    public function DO_EXIT_CLEAN()
    {
        
    } 
    
}
?>