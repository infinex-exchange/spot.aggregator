<?php

function updateTrades($pairid) {
    global $debug, $redis;
    
    if($debug) echo "Update trades $pairid\n";
    
    /*if($redis) {
        $redis -> unlink($redis -> keys('spot:trades:'.$pairid.':*'));
    }*/
}

function rebuildTrades() {
    global $debug, $redis;
    
    if($debug) echo "Rebuilding trades\n";
    
    /*if($redis) {
        $redis -> unlink($redis -> keys('spot:trades:*'));
    }*/
}

?>