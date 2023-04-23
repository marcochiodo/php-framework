<?php
namespace mrblue\framework\Utils\DbValue;

interface DbValueManagerInterface {

    function set( string $key , mixed $value , ?int $ttl = null ) : DbValue;

    function get( string $key ) : DbValue;

    function delete( string $key ) : bool;

    function inc( string $key , int $amount = 1 , ?int $ttl = null ) : DbValue;
}