<?php
/**
 * Kalon Daemon -> A Unix Daemon for PHP5
 *     This is a free daemon tool, you can use it anyway you like.
 * 
 * NOTICE:
 * 1:This tool must run in cli sapi, any other sapis will cause a 
 *   KalonDaemonException thrown.so you need to use this tool in a
 *   command line interface,command such as: /path/to/php mydaemon.php
 * 
 * 2:Daemon needs pcntl and posix extension support. Make sure your cli
 *   sapi has loaded these two extension.The posix is compiled in php by
 *   default, while pcntl must be compiled or dynamic load by yourself.
 *   Missing anyone of these extension will cause a KalonDaemonException 
 *   thrown.
 * 
 * USAGE:
 * 
 *put the code below in mydaemon.php 
 * 
require_once '/path/to/KalonDaemon.php';
declare(ticks = 1);
$toDo = $_SERVER['argv'][1];
$daemonConf = array('pidFileName' => 'mydaemon.pid',
                    'verbose'     => true);
function myHandler1()
{
    sleep(5);
    echo "This handler1 works.\n";
}
function myHandler2()
{
   echo "This handler2 works.\n";
}
try {
    $daemon = new KalonDaemon($daemonConf);
    if ($toDo == 'start') {
        $daemon->addSignalHandler(SIGUSR1, 'myHandler1');
        $daemon->addSignalHandler(SIGUSR2, 'myHandler2');
        $daemon->start();
        for (;;) {
            echo "running.\n";
            sleep(1000);
        }
    } elseif ($toDo == 'stop') {
        $daemon->stop();
    } else {
        die("unknown action.");
    }
} catch (KalonDaemonException $e) {
    echo $e->getMessage();
    echo "\n";
}
 * 
 * then open a command shell:
 * start daemon:
 * /path/to/phpcli/php /path/to/mydaemon.php start
 * 
 * stop daemon:
 * /path/to/phpcli/php /path/to/mydaemon.php stop
 * 
 * 
 * 
 * @author 玉面修罗  - Kalon
 * @version 1.0
 * @site: http://blog.csdn.net/phpkernel
 * E-mail/MSN: xiuluo-999@163.com
 */


class KalonDaemon 
{
	/**
	 * path of pid file
	 *
	 * @var string
	 */
	private $_pidFilePath = "/var/run";
	
	/**
	 * name of pid file
	 *
	 * @var string
	 */
	private $_pidFileName = "daemon.pid";
	
	/**
	 * out put run information
	 *
	 * @var boolean
	 */
	private $_verbose = false;
	
	/**
	 * default singleton model
	 *
	 * @var boolean
	 */
	private $_singleton = true; 
	
	/**
	 * close file handle STDIN STDOUT STDERR
	 * NOTICE: we do not close STDIN STDOUT STDERR indeed for some reason. 
	 * @var boolean
	 */
	private $_closeStdHandle = true;
	
	/**
	 * pid of daemon
	 *
	 * @var int
	 */
	private $_pid = 0;
	
	/**
	 * exec file 
	 *
	 * @var string
	 */
	private $_execFile = "";
	

	/**
	 * function handlers for signal number
	 *
	 * @var array
	 */
    private $_signalHandlerFuns = array();

	
	/**
	 * set config
	 *
	 * @param array $configs
	 */
	public function __construct($configs = array())
	{   
	    //load config
        if (is_array($configs))
            $this->setConfigs($configs);
	}
	
	/**
	 * pctntl is needed,and only works in cli sapi
	 */
    public function _checkRequirement()
    {
        //check if pctnl loaded
        if (!extension_loaded('pcntl'))
            throw new KalonDaemonException("daemon needs support of pcntl extension, please enable it.");

        //check sapi name,only for cli    
        if ('cli' != php_sapi_name())
            throw new KalonDaemonException("daemon only works in cli sapi.");    
    }
	
    /**
     * set configs
     * pidFilePath: path of pid file
     * pidFileName: name of pid file
     * verbose    : output process information
     * singleton  : singleton model,only one instance of daemon at one time
     * closeStdHandle : close STDIN STDOUT STDERR when daemon run success
     * 
     * @param array $configs
     */
	public function setConfigs($configs)
	{
        foreach ((array) $configs as $item => $config) {
            switch ($item) {
                case "pidFilePath":
                    $this->setPidFilePath($config);
                    break;
                case "pidFileName":
                    $this->setPidFileName($config);
                    break;
                 case "verbose":
                    $this->setVerbose($config);
                    break;
                 case "singleton":
                    $this->setSingleton($config);
                    break;
                 case "closeStdHandle";
                    $this->setCloseStdHandle($config); 
                    break;                                        
                default:
                    throw new KalonDaemonException("Unknown config item {$item}");
                    break;
            }
	    }
	}
	
	/**
	 * set Pid File Path
	 *
	 * @param  string $path
	 * @return boolean
	 */
	public function setPidFilePath($path)
	{
	    if (empty($path))
	        return false;
	        
	    if(!is_dir($path))
	        if (!mkdir($path, 0777))
	            throw new KalonDaemonException("setPidFilePath: cannnot make dir {$path}.");

	    $this->_pidFilePath = rtrim($path, "/");
	    return true;    
	}
	
	/**
	 * get Pid File Path
	 *
	 * @return string
	 */
	public function getPidFilePath()
	{
	    return $this->_pidFilePath;
	}
	
	/**
	 * set Pid File Name
	 *
	 * @param string $name
	 * @return boolean
	 */
	public function setPidFileName($name)
	{
	    if (empty($name))
	        return false;
	    
	    $this->_pidFileName = trim($name);
	    return true;    
	}
	
	/**
	 * get Pid File Name
	 *
	 * @return string
	 */
	public function getPidFileName()
	{
	    return $this->_pidFileName;
	}
	
	/**
	 * set Open Output
	 *    if sets to true,daemon will output start and stop information ,etc
	 * 
	 * @param  boolean $open
	 * @return boolean
	 */
	public function setVerbose($open = true)
	{
	    $this->_verbose = (boolean) $open;
	    return true;
	}
	
	/**
	 * get Open Output
	 *
	 * @return boolean
	 */
	public function getVerbose()
	{
	    return $this->_verbose;
	}
	
	/**
	 * set Singleton
	 *     if sets to true, daemon will keep singleton,which means that there is only one 
	 * instance of daemon at one time.    
	 * 
	 * @param  boolean $singleton
	 * @return boolean
	 */
	public function setSingleton($singleton = true)
	{
	    $this->_singleton = (boolean) $singleton;
	    return true;
	}
	
	/**
	 * get Singleton
	 *
	 * @return boolean
	 */
	public function getSingleton()
	{
	    return $this->_singleton;
	}
	
	/**
	 * set Close Std Handle
	 *
	 * @param  boolean $close
	 * @return boolean
	 */
	public function setCloseStdHandle($close = true)
	{
	    $this->_closeStdHandle = (boolean) $close;
	    return true;
	}
	
	/**
	 * get Close Std Handle
	 *
	 * @return boolean
	 */
	public function getCloseStdHandle()
	{
	    return $this->_closeStdHandle;
	}
	
	/**
	 * start daemon
	 * 1.daemonize 
	 * 2.setup signal handlers
	 * 3.close STDIN STDOUT STDERR
	 * 
	 * @return boolean
	 */
	public function start()
	{
	    //this line used to put in the __construct,for some reason I move it here.
	    $this->_checkRequirement();
	    
	    //do daemon
		$this->_daemonize();
 
        //default handler for stop
	    if(!pcntl_signal(SIGTERM,  array($this,"signalHandler")))
	    	throw new KalonDaemonException("Cannot setup signal handler for signo {$signo}");	  
        
        
	    //close file handle STDIN STDOUT STDERR
	    //notic!!!This makes no use in PHP4 and some early version of PHP5
	    //if we close these handle without dup to /dev/null,php process will die 
	    //when operating on them.
	    if ($this->_closeStdHandle) {
	        //fclose(STDIN);
	        //fclose(STDOUT);
	        //fclose(STDERR);
	    }
	    
	    return true;
	}
	
	/**
	 * stop daemon
	 * 1.get daemon pid from pid file
	 * 2.send signal to daemon
	 * 
	 * @param  boolean $force  kill -9 or kill
	 * @return boolean
	 */
	public function stop($force = false)
	{
	    if ($force) 
	        $signo = SIGKILL; //kill -9
	    else  
	        $signo = SIGTERM; //kill 
	            
	    //only use in singleton model    
	    if (!$this->_singleton)
	        throw new KalonDaemonException("'stop' only use in singleton model.");
	        	   
		if (false === ($pid = $this->_getPidFromFile()))
		    throw new KalonDaemonException("daemon is not running,cannot stop.");
		
		if (!posix_kill($pid, $signo)) {
            throw new KalonDaemonException("Cannot send signal $signo to daemon.");	
		}
		
		$this->_unlinkPidFile();
		
		$this->_out("Daemon stopped with pid {$pid}...");
		return true;
	}
	
	/**
	 * restart daemon
	 */
	public function restart()
	{
		$this->stop();
		//sleep to wait
		sleep(1);
		
		$this->start();
	}
		
	/**
	 * get daemon pid
	 * @return int
	 */
	public function getDaemonPid()
	{
		return $this->_getPidFromFile();
	}
	
	/**
	 * signalHander for dameon
	 *
	 * @param int $signo
	 */
	public function signalHandler($signo)
	{	
		$signFuns = $this->_signalHandlerFuns[$signo];
		if (is_array($signFuns)) {
		    foreach ($signFuns as $fun) {
	            call_user_func($fun);
	        }
		}
		
		//default action
		switch ($signo) {
			case SIGTERM:
				exit;
				break;
			default:
				// handle all other signals
		}		
		
	}
	
	public function addSignalHandler($signo, $fun)
	{
	    if (is_string($fun)) {
	    	if (!function_exists($fun)) {
	    		throw new KalonDaemonException("handler function {$fun} not exists");
	    	}
	    }elseif (is_array($fun)) {
	    	if (!@method_exists($fun[0], $fun[1])) {
                throw new KalonDaemonException("handler method not exists");
	    	}    
	    } else {
	        throw new KalonDaemonException("error handler.");
	    }

	    if(!pcntl_signal($signo,  array($this,"signalHandler")))
	    	    throw new KalonDaemonException("Cannot setup signal handler for signo {$signo}");

	    $this->_signalHandlerFuns[$signo][] = $fun;
	    return $this;    
	}
	
	public function sendSignal($signo)
	{
		if (false === ($pid = $this->_getPidFromFile()))
		    throw new KalonDaemonException("daemon is not running,cannot send signal.");
		
		if (!posix_kill($pid, $signo)) {
            throw new KalonDaemonException("Cannot send signal $signo to daemon.");	
		}
		
		//$this->_out("Send signal $signo to pid $pid...");
		return true;
	}
	
	/**
	 * daemon is active?
	 * @return boolean
	 */
	public function isActive()
	{
		try {
			$pid = $this->_getPidFromFile();
		} catch (KalonDaemonException $e) {
			return false;
		}
		if (false === $pid)
		    return false;
		    
		if (false === ($active = @pcntl_getpriority($pid)))
		    return false;
        else
            return true;
	}
	
	
	/**
	 * daemonize 
	 * 1.check running , if singaleton model
	 * 2.forck process
	 * 3.detach from controlling terminal
	 * 4.log pid
	 * 
	 * @return boolean
	 */
	private function _daemonize()
	{
		//single model, first check if running
		if ($this->_singleton) {
		    $isRunning  = $this->_checkRunning();
            if ($isRunning) 
			    throw new KalonDaemonException("Daemon already running");
		}
		
		//fork current process
		$pid = pcntl_fork();
		
		if ($pid == -1) {
			//fork error
			throw new KalonDaemonException("Error happened while fork process");
		} elseif ($pid) {
			//parent exit
			exit();
		} else {
			//child, get pid
			$this->_pid = posix_getpid();
		}
		
		$this->_out("Daemon started with pid {$this->_pid}...");
		
		//detach from controlling terminal
		if (!posix_setsid())
			throw new KalonDaemonException("Cannot detach from terminal"); 
		
		//log pid in singleton model	
		if ($this->_singleton)	
		    $this->_logPid();
		
		return $this->_pid;
	}
	
	/**
	 * get Pid From File
	 *
	 * @return int
	 */
	private function _getPidFromFile()
	{
	    //if is set
	    if ($this->_pid)
	        return (int)$this->_pid;
	        
		$pidFile = $this->_pidFilePath . "/" . $this->_pidFileName;
		//no pid file,it's the first time of running
		if (!file_exists($pidFile))
		    return false;
		    
		if (!$handle = fopen($pidFile, "r")) 
			throw new KalonDaemonException("Cannot open pid file {$pidFile} for read"); 

		if (($pid = fread($handle, 1024)) === false) 
			throw new KalonDaemonException("Cannot read from pid file {$pidFile}"); 
	
		fclose($handle);
		
		return $this->_pid = (int) $pid;
	}
	
	/**
	 * _checkRunning
	 *  in singleton mode ,we check if daemon running
	 * 
	 * @return boolean
	 */
	private function _checkRunning()
	{
		$pid = $this->_getPidFromFile();
		
		//no pid file,not running
		if(false === $pid)
		    return false;
		
		//get exe file path from pid
	    switch(strtolower(PHP_OS))
		{
			case "freebsd":
				$strExe = $this->_getFreebsdProcExe($pid);
				if($strExe === false)
					return false;
				$strArgs = $this->_getFreebsdProcArgs($pid);
				break;
				
			case "linux":
				$strExe = $this->_getLinuxProcExe($pid);
				if($strExe === false)
					return false;
				$strArgs = $this->_getLinuxProcArgs($pid);
				break;
				
			default:
				return false;
		}
		
		$exeRealPath = $this->_getDaemonRealPath($strArgs, $pid);
		
		//get exe file path from command
		if ($strExe != PHP_BINDIR . "/php")
		    return false;
		 
	    $selfFile = "";
	    $sapi = php_sapi_name();
		switch($sapi)
		{
			case "cgi":
			case "cgi-fcgi":
				$selfFile = $_SERVER['argv'][0];
				break;
			default:
				$selfFile = $_SERVER['PHP_SELF'];
				break;
		}
		$currentRealPath = realpath($selfFile);
		
		
		//compare two path
		if ($currentRealPath != $exeRealPath)
		    return false;
	    else 
	        return true;
	}
	
	/**
	 * log Pid
	 */
	private function _logPid()
	{
		$pidFile = $this->_pidFilePath . "/" . $this->_pidFileName;
		if (!$handle = fopen($pidFile, "w")) {
			throw new KalonDaemonException("Cannot open pid file {$pidFile} for write"); 
		}
		if (fwrite($handle, $this->_pid) == false) {
			throw new KalonDaemonException("Cannot write to pid file {$pidFile}"); 
		}
		fclose($handle);
	}
	
	/**
	 * unlink pid file
	 *    in singleton mode, unlink pid file while daemon stop
	 * 
	 * @return boolean
	 */
    private function _unlinkPidFile()
    {
        $pidFile = $this->_pidFilePath . '/' . $this->_pidFileName;
        return @unlink($pidFile);
    }
	
	/**
	 * get Daemon RealPath
	 *
	 * @param string $daemonFile
	 * @param int    $daemonPid
	 * @return string
	 */
	private function _getDaemonRealPath($daemonFile, $daemonPid)
	{
		$daemonFile = trim($daemonFile);
		if(substr($daemonFile,0,1) !== "/") {
			$cwd = $this->_getLinuxProcCwd($daemonPid);
			$cwd = rtrim($cwd, "/");
			$cwd = $cwd . "/" . $daemonFile;
			$cwd = realpath($cwd);
			return $cwd;
		}

		return realpath($daemonFile);
	}
	
	/**
	 * get Freebsd ProcExe
	 *
	 * @param  int $pid
	 * @return string
	 */
	private function _getFreebsdProcExe($pid)
	{
		$strProcExeFile = "/proc/" . $pid . "/file";
		if (false === ($strLink = @readlink($strProcExeFile))) {
            //throw new KalonDaemonException("Cannot read link file {$strProcExeFile}");
            return false;	
		}
        
		return $strLink;
	}
	
	/**
	 * get Linux Proc Exe
	 *
	 * @param  int    $pid
	 * @return string
	 */
	private function _getLinuxProcExe($pid)
	{
		$strProcExeFile = "/proc/" . $pid . "/exe";
		if (false === ($strLink = @readlink($strProcExeFile))) {
           //throw new KalonDaemonException("Cannot read link file {$strProcExeFile}");
            return false; 
		}
        
		return $strLink;
	}	
	
	/**
	 * get Freebsd Proc Args
	 *
	 * @param   int    $pid
	 * @return  string
	 */
	private function _getFreebsdProcArgs($pid)
	{
		return $this->_getLinuxProcArgs($pid);
	}
	
	/**
	 * get Linux Proc Args
	 *
	 * @param   int  $pid
	 * @return  string
	 */
	private function _getLinuxProcArgs($pid)
	{
		$strProcCmdlineFile = "/proc/" . $pid . "/cmdline";
		
		if (!$fp = @fopen($strProcCmdlineFile, "r")) {
		    throw new KalonDaemonException("Cannot open file {$strProcCmdlineFile} for read");
		     	
		}
		if (!$strContents = fread($fp, 4096)) {
			 throw new KalonDaemonException("Cannot read or empty file {$strProcCmdlineFile}"); 
		}
		fclose($fp);
		
		$strContents = preg_replace("/[^\w\.\/\-]/", " "
			, trim($strContents));
		$strContents = preg_replace("/\s+/", " ", $strContents);
		
		$arrTemp = explode(" ", $strContents);
		if(count($arrTemp) < 2) {
		    throw new KalonDaemonException("Invalid content in {$strProcCmdlineFile}"); 
		}
		
		return trim($arrTemp[1]);
	}
	
	/**
	 * get Linux Proc Cwd
	 *
	 * @param   int    $pid
	 * @return  string
	 */
	private function _getLinuxProcCwd($pid)
	{
		$strProcExeFile = "/proc/" . $pid . "/cwd";
		if (false === ($strLink = @readlink($strProcExeFile))) {
            throw new KalonDaemonException("Cannot read link file {$strProcExeFile}"); 	
		}
		
		return $strLink;
	}
	
	/**
	 * out put process info
	 *   if open _openOutput
	 * 
	 * @param  string $str
	 * @return boolean
	 */
	private function _out($str)
	{
	    if ($this->_verbose) {
	        fwrite(STDOUT, $str . "\n");
	    } 
	    return true;    
	}
	
}

/**
 * Exception for KalonDaemon
 */
class KalonDaemonException extends Exception 
{
    
}
?>
