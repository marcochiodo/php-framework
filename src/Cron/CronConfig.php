<?php

namespace mrblue\framework\Cron;

/**
 * 
 * @property int $max_duration in nanoseconds ( 1 / 1.000.000.000 of second )
 *
 */
class CronConfig {

    function __construct(
        public string $name,
        public string $cron_class,
        public int $interval,
        public ?int $max_duration = null
    ) {
        if ($interval < 60) {
            throw new \InvalidArgumentException('interval value must be >= 60');
        }
    }
}
