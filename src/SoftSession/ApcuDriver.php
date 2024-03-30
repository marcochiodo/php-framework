<?php

namespace mrblue\framework\SoftSession;

class ApcuDriver implements SoftSessionDriverInterface {

    public readonly string $prefix;
    public readonly int $ttl;

    function __construct(string $prefix, int $ttl = 0) {
        $this->prefix = $prefix;
        $this->ttl = $ttl;
    }

    function get(string $session_id): ?array {

        $success = null;
        $data = apcu_fetch($this->getKey($session_id), $success);

        return $success ? $data : null;
    }

    function set(string $session_id, array $data): bool {

        return apcu_store($this->getKey($session_id), $data, $this->ttl);
    }

    function remove(string $session_id): bool {

        return apcu_delete($this->getKey($session_id));
    }

    protected function getKey(string $session_id): string {

        return $this->prefix . $session_id;
    }
}
