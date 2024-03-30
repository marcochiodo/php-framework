<?php

namespace mrblue\framework\SoftSession;

interface SoftSessionDriverInterface {

    function get(string $session_id): ?array;

    function set(string $session_id, array $data): bool;

    function remove(string $session_id): bool;
}
