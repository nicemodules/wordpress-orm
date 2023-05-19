<?php

namespace NiceModules\ORM;

class Logger extends Singleton
{
    protected array $log = [];
    protected bool $enabled = false;
    protected bool $showEnabled = false;
    protected bool $logBacktraceChain = false;
    protected float $timeBefore;

    protected function __construct()
    {
        $this->timeBefore = array_sum(explode(' ', microtime()));

        if (defined('WP_DEBUG') && WP_DEBUG) {
            $this->enabled = true;
        }
    }

    public function log($something, $addBacktraceChain = false)
    {
        if (!$this->enabled) {
            return;
        }

        if (is_object($something) || is_array($something)) {
            $something = print_r($something, 1);
        }
        
        if($addBacktraceChain && $this->logBacktraceChain){
            $something.= PHP_EOL . $this->getBacktraceChain();
        }
        
        $logEntry = date('Y-m-d H:i:s') . ' [' . $this->getCurrentTime() . '] => ' . $something;
        
        $this->log[] = $logEntry;
        
        if($this->showEnabled){
            print(PHP_EOL);
            print($logEntry);
            print(PHP_EOL);
        }
    }


    public function getCurrentTime(): string
    {
        $timeAfter = array_sum(explode(' ', microtime()));
        return round($timeAfter - $this->timeBefore, 3) . 's';
    }

    /**
     * @return array
     */
    public function getLog(): array
    {
        return $this->log;
    }

    /**
     * @return array
     */
    public function getLogString(): array
    {
        return implode(PHP_EOL, $this->log);
    }

    /**
     * @param bool $enabled
     */
    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    /**
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }
    
    public function getBacktraceChain(){
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

        $backtrace = array_reverse($backtrace);
        
        $backtraceData = [];
        
        $niceModules = 'NiceModules\\';
        
        foreach ($backtrace as $data){
            if(!isset($data['class'])){
                continue;
            }
            
            if(strpos($data['class'], $niceModules) === false || strpos($data['class'], '\ORM\Logger') !== false){
                continue;
            }

            $data['class'] = str_replace($niceModules, '', $data['class']);

            $backtraceData[] = $data['class'].$data['type'].$data['function']; 
        }
        
        return implode(PHP_EOL."\t". '=> ',  $backtraceData);
    }

    /**
     * @param bool $showEnabled
     */
    public function setShowEnabled(bool $showEnabled): void
    {
        $this->showEnabled = $showEnabled;
    }

    /**
     * @return bool
     */
    public function isShowEnabled(): bool
    {
        return $this->showEnabled;
    }

    /**
     * @param bool $logBacktraceChain
     */
    public function setLogBacktraceChain(bool $logBacktraceChain): void
    {
        $this->logBacktraceChain = $logBacktraceChain;
    }

    /**
     * @return bool
     */
    public function isLogBacktraceChain(): bool
    {
        return $this->logBacktraceChain;
    }
}