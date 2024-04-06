<?php

namespace mrblue\framework\Cron;

class CronResponse {

    function __construct(
        public bool $success,
        public array $data = []
    ) {
    }
}
