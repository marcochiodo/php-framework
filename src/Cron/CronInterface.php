<?php

namespace mrblue\framework\Cron;

interface CronInterface {

    function run(): CronResponse;
}
