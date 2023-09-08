<?php

class Trades {
    private $loop;
    private $log;
    private $pdo;
    private $redis;
    
    function __construct($loop, $log, $pdo, $redis) {
        $this -> loop = $loop;
        $this -> log = $log;
        $this -> pdo = $pdo;
        $this -> redis = $redis;
        
        $this -> log -> debug('Initialized trades module');
    }
    
    function updateTrades($pairid) {
        global $debug, $redis;
        
        if($debug) echo "Update trades $pairid\n";
        
        if($redis) {
            $redis -> unlink($redis -> keys('spot:trades:'.$pairid.':*'));
        }
    }
    
    function rebuildTrades() {
        global $debug, $redis;
        
        if($debug) echo "Rebuilding trades\n";
        
        if($redis) {
            $redis -> unlink($redis -> keys('spot:trades:*'));
        }
    }
}

?>