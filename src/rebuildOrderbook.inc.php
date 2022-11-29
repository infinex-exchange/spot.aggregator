<?php

function rebuildOrderbook() {
    global $debug, $pdo;
    
    if($debug) echo "Rebuilding orderbook\n";
    
    $pdo -> beginTransaction();
    
    $sql = 'TRUNCATE TABLE spot_aggregated_orderbook_v2';
    $pdo -> query($sql);
    
    $sql = 'INSERT INTO spot_aggregated_orderbook_v2(
                pairid,
                side,
                price,
                amount
            )
            SELECT pairid,
                   side,
                   price,
                   SUM(amount-filled)
            FROM spot_orderbook
            GROUP BY pairid,
                     side,
                     price';
    $pdo -> query($sql);
    
    $pdo -> commit();
}

?>