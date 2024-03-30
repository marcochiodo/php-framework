<?php

class Request {

    public readonly string $ip;
    public readonly string $proto;
    public readonly string $server_name;
    public readonly string $port;
    public readonly ?string $user_agent;
    public readonly ?string $accept_language;
    public readonly ?string $origin;
    public readonly ?string $referer;
    public readonly ?string $host;

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

        $this->server_name = ($_SERVER['SERVER_NAME'] ?? null) ?: ($this->host) ?: $_SERVER['SERVER_ADDR'];
    }
}
