<?php

class Trades {
    private $loop;
    private $log;
    private $redis;
    
    function __construct($loop, $log, $redis) {
        $this -> loop = $loop;
        $this -> log = $log;
        $this -> redis = $redis;
        
        $this -> log -> debug('Initialized trades module');
    }
    
    public function updateTrades($pairid) {
        $this -> redis -> keys('spot:trades:'.$pairid.':*') -> then(
            function($keys) use($th)
                $th -> redis -> unlink(...$keys);
            }
        );
    }
    
    public function rebuildTrades() {
        $this -> log -> info('Rebuilding all trades');
        
        $this -> redis -> keys('spot:trades:*') -> then(
            function($keys) use($th)
                $th -> redis -> unlink(...$keys);
            }
        );
    }
}

?>