<?php

class EventHandler {
    private $loop;
    private $log;
    private $ticker;
    private $orderbook;
    private $trades;
    
    function __construct($loop, $log, $ticker, $orderbook, $trades) {
        $this -> loop = $loop;
        $this -> log = $log;
        $this -> ticker = $ticker;
        $this -> orderbook = $orderbook;
        $this -> trades = $trades;
        
        $this -> log -> debug('Initialized matching engine event handler');
    }
    
    public function bind($amqp) {
        $th = $this;
        
        $amqp -> sub(
            'spot_trade',
            function($body, $headers) use($th) {
                return $th -> onTrade($body, $headers);
            },
            'aggregator_trade',
            true
        );
        
        $amqp -> sub(
            'spot_order_accepted',
            function($body, $headers) use($th) {
                return $th -> onOrderAccepted($body, $headers);
            },
            'aggregator_order_accepted',
            true
        );
        
        $amqp -> sub(
            'spot_order_update',
            function($body, $headers) use($th) {
                return $th -> onOrderUpdate($body, $headers);
            },
            'aggregator_order_update',
            true
        );
    }
    
    function onTrade($body, $headers) {
        $this -> ticker -> updateMarket($headers['pairid'], $body['price'], $body['amount'], $body['total']);
        $this -> orderbook -> updateOrderbook($headers['pairid'], $body['makerSide'], $body['price'], '-', $body['amount']);
        $this -> trades -> updateTrades($headers['pairid']);
    }
    
    function onOrderAccepted($body, $headers) {
        if(isset($body['rest']))
            $this -> orderbook -> updateOrderbook($headers['pairid'], $body['side'], $body['price'], '+', $body['rest']);
    }
    
    function onOrderUpdate($body, $headers) {
        $sign = NULL;
        
        if(isset($body['triggered']) && $body['triggered'] == true)
            $sign = '+';
        else if(isset($body['status']) && $body['status'] == 'CANCELED')
            $sign = '-';
        
        if($sign !== NULL)
            $this -> orderbook -> updateOrderbook($headers['pairid'], $body['side'], $body['price'], $sign, $body['amount']);
    }
}

?>