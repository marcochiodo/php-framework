<?php

namespace mrblue\framework\Utils;

class Request {

    static private ?self $globalInstance = null;

    public readonly ?string $ip;
    public readonly ?string $proto;
    public readonly ?string $server_name;
    public readonly ?string $port;
    public readonly ?string $user_agent;
    public readonly ?string $accept_language;
    public readonly ?string $origin;
    public readonly ?string $referer;
    public readonly ?string $host;

    public readonly ?string $request_uri;

    public readonly ?string $request_scheme;
    public readonly ?string $request_host;
    public readonly ?int $request_port;
    public readonly ?string $request_user;
    public readonly ?string $request_pass;
    public readonly ?string $request_path;
    public readonly ?string $request_query;
    public readonly ?string $request_fragment;


    function __construct() {
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $this->ip = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
        } else {
            $this->ip = $_SERVER['REMOTE_ADDR'] ?? null;
        }

        $this->proto = ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? null) ?: ($_SERVER['REQUEST_SCHEME'] ?? null);

        if (!empty($_SERVER['HTTP_X_FORWARDED_PORT'])) {
            $this->port = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_PORT'])[0]);
        } else {
            $this->port = $_SERVER['SERVER_PORT'] ?? null;
        }

        $this->user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $this->accept_language = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? null;
        $this->origin = $_SERVER['HTTP_ORIGIN'] ?? null;
        $this->referer = $_SERVER['HTTP_REFERER'] ?? null;
        $this->host = $_SERVER['HTTP_HOST'] ?? null;

        if (! empty($_SERVER['SERVER_NAME'])) {
            $this->server_name = $_SERVER['SERVER_NAME'];
        } elseif ($this->host) {
            $this->server_name = $this->host;
        } elseif (! empty($_SERVER['SERVER_ADDR'])) {
            $this->server_name = $_SERVER['SERVER_ADDR'];
        } else {
            $this->server_name = null;
        }

        $this->request_uri = $_SERVER['REQUEST_URI'] ?? null;
        $request_uri_parts = $this->request_uri ? parse_url($this->request_uri) : [];

        foreach (
            [
                'scheme',
                'host',
                'port',
                'user',
                'pass',
                'path',
                'query',
                'fragment'
            ] as $part
        ) {
            $this->{'server_' . $part} = $request_uri_parts[$part] ?? null;
        }
    }

    static function getGlobalInstance(): self {
        if (!self::$globalInstance) {
            self::$globalInstance = new self();
        }

        return self::$globalInstance;
    }
}
