<?php

namespace mrblue\framework\SoftSession;

use mrblue\framework\Utils\DbValue\DbValueManagerInterface;

class DbValueDriver implements SoftSessionDriverInterface {

    function __construct(
        public readonly DbValueManagerInterface $DbValueManager
    ) {
    }

    function get(string $session_id): ?array {

        return $this->DbValueManager->get($session_id)->value;
    }

    function set(string $session_id, array $data): bool {

        $this->DbValueManager->set($session_id, $data);

        return true;
    }

    function remove(string $session_id): bool {

        return $this->DbValueManager->delete($session_id);
    }
}
