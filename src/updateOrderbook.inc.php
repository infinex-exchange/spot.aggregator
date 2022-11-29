<?php

use Decimal\Decimal;

function updateOrderbook($pairid, $side, $price, $sign, $amount) {
    global $debug, $pdo;
    
    if($sign != '-' && $sign != '+') return;
    
    $pdo -> beginTransaction();
     
    $task = array(
        ':pairid' => $pairid,
        ':side' => $side,
        ':price' => $price,
        ':amount' => $amount
    );
    
    $sql = 'UPDATE spot_aggregated_orderbook_v2
            SET amount = amount '.$sign.' :amount
            WHERE pairid = :pairid
            AND side = :side
            AND price = :price
            RETURNING amount';
    
    $q = $pdo -> prepare($sql);
    $q -> execute($task);
    $row = $q -> fetch(PDO::FETCH_ASSOC);
    
    $newAmount = NULL;
    
    if($row) {
        $newAmount = $row['amount'];
        
        $amountDec = new Decimal($row['amount']);
        if($amountDec -> isZero()) {
            $task = array(
                ':pairid' => $pairid,
                ':side' => $side,
                ':price' => $price
            );
            
            $sql = 'DELETE FROM spot_aggregated_orderbook_v2
                    WHERE pairid = :pairid
                    AND side = :side
                    AND price = :price';
            
            $q = $pdo -> prepare($sql);
            $q -> execute($task);
        }
    }
    
    else {
        $newAmount = $amount;
        
        $task = array(
            ':pairid' => $pairid,
            ':side' => $side,
            ':price' => $price,
            ':amount' => $amount
        );
        
        $sql = 'INSERT INTO spot_aggregated_orderbook_v2(
                    pairid,
                    side,
                    price,
                    amount
                )
                VALUES(
                    :pairid,
                    :side,
                    :price,
                    :amount
                )';
        
        $q = $pdo -> prepare($sql);
        $q -> execute($task);
    }
    
    $pdo -> commit();
    
    emitAggOrderbook($pairid, $side, $price, $newAmount);
    
    if($debug) echo 'Updated orderbook for '.$pairid."\n";
}

?>