<?php
namespace mrblue\framework\Utils\DbValue;

class DbValue {

    public readonly bool $exists;
    public readonly mixed $value;
    public readonly ?\DateTimeImmutable $ExpireAt;
    public readonly bool $expired;

    function __construct( bool $exists , mixed $value = null , ?\DateTimeImmutable $expire_at = null ) {
        $this->exists = $exists;
        $this->value = $exists ? $value : null;
        $this->ExpireAt = $exists ? $expire_at : null;
        $this->expired = $expire_at && ($expire_at <= new \DateTimeImmutable());
    }
}