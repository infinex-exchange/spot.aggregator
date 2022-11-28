<?php

function updateMarket($pairid, $price, $amount, $total) {
    global $debug, $pdo;
     
    $task = array(
        ':pairid' => $pairid,
        ':price' => $price,
        ':price2' => $price,
        ':amount' => $amount,
        ':total' => $total
    );
    
    $sql = 'UPDATE spot_tickers_v2_data
            SET refresh_time = current_timestamp,
                previous = price,
                price = :price,
                change = ROUND(
                    (:price2 - change_reference)
                    / change_reference
                    * 100
                ),
                vol_base = vol_base + :amount,
                vol_quote = vol_quote + :total
            WHERE pairid = :pairid';
    
    $q = $pdo -> prepare($sql);
    $q -> execute($task);
    
    if($debug) echo 'Updated market '.$pairid."\n";
}

?>