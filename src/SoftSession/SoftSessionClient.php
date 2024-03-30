<?php

namespace mrblue\framework\SoftSession;

use mrblue\framework\SoftSession\SoftSessionDriverInterface as Driver;

class SoftSessionClient {

    public readonly string $session_id;
    public readonly Driver $Driver;
    protected ?Driver $SecondDriver = null;

    function __construct(string $session_id, Driver $Driver) {
        $this->session_id = $session_id;
        $this->Driver = $Driver;
    }

    function setSecondDriver(?Driver $Driver): self {
        $this->SecondDriver = $Driver;
        return $this;
    }

    function getSecondDriver(): ?Driver {
        return $this->SecondDriver;
    }

    function getSessionData(): array {
        $data = $this->Driver->get($this->session_id);

        if (isset($data)) {
            return $data;
        }


        $data = [];

        if ($this->SecondDriver) {
            $data = $this->SecondDriver->get($this->session_id);
            if (is_null($data)) {
                $data = [];
            }
        }

        $this->Driver->set($this->session_id, $data);

        return $data;
    }

    function setSessionData(array $data): bool {
        if ($this->SecondDriver) {
            $this->SecondDriver->set($this->session_id, $data);
        }

        $this->Driver->set($this->session_id, $data);
        return true;
    }
}
