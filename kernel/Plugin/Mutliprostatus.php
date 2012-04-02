<?php 

require_once KALONQUE_BASE_PATH . '/Plugin/Abstract.php';

require_once KALONQUE_BASE_PATH . '/Plugin/Exception.php';

class KalonQuePluginMutliprostatus extends KalonQuePluginAbstract
{    
    public function LOOP_START(KalonQueControllerAbstract $controller)
    {
        $business = $controller->getBusiness();
        $bName    = $business->getBusinessName();
        $pIndex   = $controller->getProcessIndex();
        
        $varPath  = $controller->getConfig('KALONQUE_VAR_PATH');
        $statsFilePath = $varPath . '/'. $bName;
        if (!is_dir($statsFilePath))
            if (!mkdir($statsFilePath, 0777))
                throw new KalonQuePluginException("mkdir {$statsFilePath} failed"); 

        $statsFile = $statsFilePath . '/stats_' . $pIndex;
        if (!$fp = fopen($statsFile, 'w'))
            throw new KalonQuePluginException("fopen {$statsFilePath} failed"); 

        if (!fwrite($fp, KalonQueControllerMultiprocess::PROCESS_STATUS_BUSY))
            throw new KalonQuePluginException("fwrite {$statsFilePath} failed");      
    }
    
    public function LOOP_END(KalonQueControllerAbstract $controller)
    {
        $business = $controller->getBusiness();
        $bName    = $business->getBusinessName();
        $pIndex   = $controller->getProcessIndex();
        
        $varPath  = $controller->getConfig('KALONQUE_VAR_PATH');
        $statsFilePath = $varPath . '/'. $bName;

        $statsFile = $statsFilePath . '/stats_' . $pIndex;
        if (!$fp = fopen($statsFile, 'w'))
            throw new KalonQuePluginException("fopen {$statsFilePath} failed"); 

        if (!fwrite($fp, KalonQueControllerMultiprocess::PROCESS_STATUS_FREE))
            throw new KalonQuePluginException("fwrite {$statsFilePath} failed");
    }    
}
?>