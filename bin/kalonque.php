<?php
require_once dirname(dirname(__FILE__)) . '/conf/kalonque_base.php';
require_once KALONQUE_INCLUDE_PATH . '/KalonGetopt.php';

declare(ticks=1);

$KAQUE_ACTION_MAP = array('start'   => 'kalonque_action_start',
                          'stop'    => 'kalonque_action_stop',
                          'restart' => 'kalonque_action_restart',
                          'invoke'  => 'kalonque_action_invoke',
                          'add'     => 'kalonque_action_add');

$KAQUE_ACTION_HELPS = array();
$KAQUE_ACTION_HELPS['start']   = "start a queue business";
$KAQUE_ACTION_HELPS['stop']    = "stop a queue business\n	[-f] if sets,use force mode to stop";
$KAQUE_ACTION_HELPS['restart'] = 'restart a queue business';
$KAQUE_ACTION_HELPS['invoke']  = 'invoke a queue business';
$KAQUE_ACTION_HELPS['add']     = "add data to queue business\n	[-d data] sets the data to add to queue business";


$business = strtolower($_SERVER['argv'][2]);
$action   = strtolower($_SERVER['argv'][1]);

if ((count($_SERVER['argv']) < 3) || !$business || !$action) {
    KalonqueHelpMessage();
    exit;
}

try {
	$kalonGetOpt = new KalonGetopt("vhfd:");
	$kalonGetOpt->parse();	
	
    require_once KALONQUE_BASE_PATH . '/Controller/Default.php';
    $controller = new KalonQueControllerDefault($KALONQUE_CONF);
    $controller->setBusiness($business);
    
    if (!array_key_exists($action, $KAQUE_ACTION_MAP)) {
        throw new Exception("unknow action {$action}");
    }
    
    if (isset($kalonGetOpt->h)) {
    	 die("Action: $action \r\n $KAQUE_ACTION_HELPS[$action]\r\n");
    }
    
    if (isset($kalonGetOpt->h)) {
    	die("KalonQue version " . KALONQUE_VERSION . "\r\n");
    }
    
    $KAQUE_ACTION_MAP[$action]($controller, $kalonGetOpt);
  
} catch (Exception $e) {
    echo $e->getMessage() . "\n";
}


function kalonque_action_start(KalonQueControllerAbstract $controller, KalonGetopt $opt)
{
	$controller->start();
	return;
}

function kalonque_action_stop(KalonQueControllerAbstract $controller, KalonGetopt $opt)
{
	$controller->stop();
	return;
}

function kalonque_action_restart(KalonQueControllerAbstract $controller, KalonGetopt $opt)
{
    $controller->stop();
    $controller->start();
	return;
}

function kalonque_action_invoke(KalonQueControllerAbstract $controller, KalonGetopt $opt)
{
	$controller->updateMonitor();
	return;
}

function kalonque_action_add(KalonQueControllerAbstract $controller, KalonGetopt $opt)
{
	$data = $opt->d;
	if (!$controller->addStoreItem($data)) {
		throw new Exception("kalonque add failed.");
	}
	return;
}

function KalonqueHelpMessage()
{
	global $KAQUE_ACTION_HELPS;
    $msg = "Cmd : /path/to/phpcli/php " . __FILE__ . " action business [options...]\r\n";
    
    foreach ($KAQUE_ACTION_HELPS as $action => $help) {
    	$msg .= "Action: $action \r\n $help\r\n"; 
    }
    echo $msg;
}
?>