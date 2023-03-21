<?php
namespace mrblue\framework\Event;

use Closure;

class EventListener {

    CONST DEFAULT_PRIORITY = 100;

    public readonly string $event_name;
    protected readonly closure $function;
    public readonly int $priority;
    
    function __construct( string $event_name , closure $function , int $priority = self::DEFAULT_PRIORITY ) {
        
        $this->event_name = $event_name;
        $this->function = $function;
        $this->priority = $priority;
    }

    function trigger( Event $Event ) {
        return ($this->function)( $Event );
    }
}