#!/usr/bin/env php
<?php

include_once __DIR__.'/config.inc.php';
require __DIR__.'/vendor/autoload.php';
include __DIR__.'/src/onTrade.inc.php';
include __DIR__.'/src/updateMarket.inc.php';
include __DIR__.'/src/updateAllMarkets.inc.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exchange\AMQPExchangeType;
use PhpAmqpLib\Wire;

// Debug mode

$debug = false;
if(defined('DEBUG_MODE') || (isset($argv[1]) && $argv[1] == '-d'))
    $debug = true;

// Global variables

$loop = null;
$rmq = null;
$channel = null;
$pdo = null;

while(true) {
    try {
        // ----- Init event loop -----
        if($debug) echo "Initializing event loop\n";
        
        if($loop !== null)
            $loop -> stop();
        
        $loop = React\EventLoop\Factory::create();

        // ----- Init RabbitMQ -----
        if($debug) echo "Initializing RMQ connection\n";
        
        try {
            if($rmq !== null)
                $rmq -> close();
        }
        catch(Exception $e) {
        }
        
        $rmq = null;
        $channel = null;
    
        while(true) {
            try {
                $rmq = new AMQPStreamConnection(RMQ_HOST, RMQ_PORT, RMQ_USER, RMQ_PASS);
                $channel = $rmq -> channel();
                
                $channel -> exchange_declare('outEvents', AMQPExchangeType::HEADERS, false, true); // durable
                $queueName = 'aggregator';
                $channel -> queue_declare($queueName, false, false, false, true); // auto delete
                    
                $channel -> queue_bind($queueName, 'outEvents', '', false, new Wire\AMQPTable([
                    'event' => 'trade'
                ]));
                    
                $channel -> basic_consume($queueName, "ct_$queueName", false, false, false, false, 'onTrade');
                
                break;
            }
            catch(Exception $e) {
                echo 'Exception: ' . $e -> getMessage() . PHP_EOL;
                sleep(1);
            }
        }

        $loop -> addPeriodicTimer(0.0001, function () use ($channel) {
            $channel -> wait(null, true);
        });
        
        // ----- Init PostgreSQL connection -----
        if($debug) echo "Connecting to PostgreSQL\n";

        $pdo = null;

        while(true) {
            try {
                $pdo = new PDO('pgsql:host='.DB_HOST.';dbname='.DB_NAME, DB_USER, DB_PASS);
                $pdo -> setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $pdo -> setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
                break;
            }
            catch(Exception $e) {
                echo 'Exception: ' . $e -> getMessage() . PHP_EOL;
                sleep(1);
            }
        }

        if($debug) echo "Initializing timers\n";
        
        // ----- EVERY 30 SEC: database ping -----
        $loop->addPeriodicTimer(30, function () {
            global $pdo, $debug;
    
            if($debug) echo "Ping database\n";
            $pdo -> query('SELECT 1');
        });
        
        // ----- EVERY 60 SEC: update all markets -----
        $loop->addPeriodicTimer(60, updateAllMarkets);
        updateAllMarkets();

        // ----- Main loop -----
        if($debug) echo "Starting event loop\n";
        $loop->run();
    }
    catch(Exception $e) {
        echo 'Exception: ' . $e -> getMessage() . PHP_EOL;
    }
}
?>