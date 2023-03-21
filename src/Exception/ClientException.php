<?php
namespace mrblue\framework\Exception;

class ClientException extends \Exception implements \JsonSerializable {

    const BAD_REQUEST = 400000;
    const UNAUTHORIZED = 401000;
    const PAYMENT_REQUIRED = 402000;
    const FORBIDDEN = 403000;
    const NOT_FOUND = 404000;
    const CONFLICT = 409000;
    const TOO_MANY_REQUESTS = 429000;
    const INTERNAL_SERVER_ERROR = 500000;
    const BAD_GATEWAY = 502000;
    const SERVICE_UNAVAILABLE = 503000;
    const GATEWAY_TIMEOUT = 504000;

    public readonly string $text_code;
    public readonly int $http_status_code;
    public readonly array $data;

    function __construct( int $code , array $data = [] ) {

        $Class = new \ReflectionClass($this);

        $text_code = array_search($code , $Class->getConstants());
        if( ! $text_code ) {
            throw new \InvalidArgumentException("Code '$code' not exists");
        }

        parent::__construct($text_code , $code);

        $this->text_code = $text_code;

        if( $code < 100 ){
            $this->http_status_code = 500;
        } elseif( $code < 1000 ){
            $this->http_status_code = $code;
        } else {
            $divide_by = 1;
            do {
                $divide_by*= 10;
                $result = floor($code / $divide_by);
            } while( $result >= 1000 );
            $this->http_status_code = $result;
        }

        $this->data = $data;
    }

    function export() {
        return [
            'code' => $this->getCode(),
            'text_code' => $this->text_code,
            'http_status_code' => $this->http_status_code,
            'data' => $this->data
        ];
    }

    function jsonSerialize() :mixed {
        return $this->export();
    }
}