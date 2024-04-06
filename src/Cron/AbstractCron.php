<?php

namespace mrblue\framework\Cron;

class AbstractCron {

    protected int $start_seconds;
    protected int $start_nanoseconds;

    function __construct(
        public CronConfig $CronConfig
    ) {
        if ($CronConfig->max_duration) {
            list($this->start_seconds, $this->start_nanoseconds) = hrtime();
        }
    }

    /**
     * 
     * @return int value in nanoseconds
     */
    function pastTime(): int {
        $hrtime = hrtime();

        return (
            ($hrtime[0] - $this->start_seconds) * 1000000000 +
            ($hrtime[1] - $this->start_nanoseconds)
        );
    }

    function maxDurationReached(): bool {
        return $this->pastTime() >= $this->CronConfig->max_duration;
    }
}
