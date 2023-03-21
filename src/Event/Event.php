<?php
namespace mrblue\framework\Event;

class Event {

    public readonly string $name;
    public mixed $data;
    public bool $stop_event_manager = false;
    
    function __construct( string $name , mixed $data ) {
        $this->name = $name;
        $this->data = $data;
    }
}