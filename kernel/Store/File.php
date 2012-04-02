<?php 

require_once KALONQUE_BASE_PATH . '/Store/Abstract.php';

require_once KALONQUE_BASE_PATH . '/Store/Exception.php';

class KalonQueStoreFile extends KalonQueStoreAbstract 
{   
    private $_itemFilePath = '';
    
    private $_itemList    = array();
    
    const OkFileSuffix    = 'ok';
    
    const LOCKFileSuffix  = 'lck';
    
    public function __construct($path = '')
    {
        if ($path != '')
            $this->setItemFilePath($path);
    }
    
    public function addItem($item)
    {
        $itemId = $this->_makeItemId();
        $itemFile = $this->_itemFilePath . '/' . $itemId;
        $item = trim($item);
        
        if (!($fp = fopen($itemFile, 'w')))
            throw new KalonQueStoreException(__CLASS__ . ": cannot open item file {$itemFile}");

        if (strlen($item) != fwrite($fp, $item))
            throw new KalonQueStoreException(__CLASS__ . ": cannot write to item file {$itemFile}");  

         //flag file, means that item already stored   
         $itemOkFile = $itemFile . '.' . self::OkFileSuffix;
         if (!(($fp = fopen($itemOkFile, 'w')) && fwrite($fp, "1")))
             throw new KalonQueStoreException(__CLASS__ . ": cannot create flag file {$itemOkFile}");

         return $itemId;    
    }
    
    public function deleteItem($itemId)
    {
        $itemFile = $this->_itemFilePath . '/' . $itemId;
        if (!@unlink($itemFile))
            throw new KalonQueStoreException(__CLASS__ . ": cannot delete item file {$itemFile}"); 

        $itemLckFile = $itemFile . '.' . self::LOCKFileSuffix;
        if (!@unlink($itemLckFile))
            throw new KalonQueStoreException(__CLASS__ . ": cannot delete flag file {$itemLckFile}");

        return true;    
    }
    
    public function read()
    {
        if (!is_dir($this->_itemFilePath))
            throw new KalonQueStoreException(__CLASS__ . ":{$this->_itemFilePath} is not a dir ");

        clearstatcache();    
        if (!$dh = opendir($this->_itemFilePath))
            throw new KalonQueStoreException(__CLASS__ . ": can not open dir {$this->_itemFilePath} "); 

        //get item file list    
        $fileList = array();
        $count    = 0;    
        while ($fileName = readdir($dh)) {
            if ($fileName != '.' && $fileName != '..' && 
               preg_match("/^[0-9a-f]{32,32}\." . self::OkFileSuffix . "$/i", $fileName) == 1) {
                if ($count++ >= $this->_maxItemRead)
                    break;
                    
                $fileList[] = $fileName;
            }    
        }
        closedir($dh);

        //get item data list
        $itemList = array();
        for ($i = 0, $max = count($fileList); $i < $max; $i++) {
            $itemId = rtrim($fileList[$i], '.' . self::OkFileSuffix);
            $itemFileName = $this->_itemFilePath . '/' . $itemId;
            
            if (file_exists($itemFileName)) {
                if (!$fp = fopen($itemFileName, 'r'))
                    throw new KalonQueStoreException(__CLASS__ . ": cannot open item file  {$itemFileName}"); 

                if (!$itemData = fread($fp, filesize($itemFileName)))
                    throw new KalonQueStoreException(__CLASS__ . ": cannot read item file  {$itemFileName}");

                 $itemList[$itemId] = $itemData;

                 //rename tag file
                 @rename($itemFileName . '.' . self::OkFileSuffix, $itemFileName . '.' . self::LOCKFileSuffix);
            }
        }
        
        $this->_itemList = $itemList;
        return true;
    }
    
    public function pop()
    {
        $result = array();
        if (false !== ($tmp = each($this->_itemList))) {
            $result[$tmp['key']] = $tmp['value'];
            return $result;
        } else {
            return false;
        }
    }
    
    public function isEmpty()
    {
        if (!is_dir($this->_itemFilePath))
            throw new KalonQueStoreException(__CLASS__ . ":{$this->_itemFilePath} is not a dir ");

        clearstatcache();    
        if (!$dh = opendir($this->_itemFilePath))
            throw new KalonQueStoreException(__CLASS__ . ": can not open dir {$this->_itemFilePath} "); 

        //get item file list    
        $fileList = array();
        $count    = 0;    
        while ($fileName = readdir($dh)) {
            if ($fileName != '.' && $fileName != '..' && 
               preg_match("/^[0-9a-f]{32,32}\." . self::OkFileSuffix . "$/i", $fileName) == 1) {
                   return false; 
            }    
        }
        closedir($dh);
        return true; 
    }
    
    public function setItemFilePath($path)
    {
        if ($path != '') {
            $path = rtrim($path, '/');
            if (!is_dir($path))
                if (!mkdir($path, 0777)) 
                    throw new KalonQueStoreException(__CLASS__ . ": cannot make item dir {$path}");

            @chmod($path, 0777);
            $this->_itemFilePath = $path;   
        }
        return $this;
    }
    
    public function getItemFilePath()
    {
        return $this->_itemFilePath;
    }    
}
?>