<?php
namespace mrblue\framework\Event;

class EventManager {

    protected \SplPriorityQueue $Listeners;

    function __construct() {
        $this->Listeners = new \SplPriorityQueue;
    }
    
    function register( EventListener $EventListener ) :self {
        $this->Listeners->insert($EventListener , $EventListener->priority);
        return $this;
    }

    function trigger( Event $Event ) :void {

        $Listeners = clone $this->Listeners;
        foreach( $Listeners as $EventListener ){
            /** @var EventListener $EventListener */
            if( $EventListener->event_name !== $Event->name ){
                continue;
            }
            $EventListener->trigger($Event);
            if( $Event->stop_event_manager ){
                break;
            }
        }
    }
}