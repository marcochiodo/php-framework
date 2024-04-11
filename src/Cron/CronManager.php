<?php

namespace mrblue\framework\Cron;

use mrblue\framework\Utils\DbValue\DbValueManagerInterface;
use mrblue\framework\Utils\DbValue\MongoDbValueManager;

/**
 * 
 * @package Utils\Cron v2
 */

class CronManager {

    const MIN_INTERVAL_AFTER_FAIL = 180;

    static public $db_value_prefix = 'cron_manager_';

    /** @var CronConfig[] */
    protected array $configs = [];
    /** @var ?CronResponse[] */
    public array $run_responses;

    protected $onRunException = null;

    function __construct(
        public readonly DbValueManagerInterface $DbValueManager
    ) {
    }

    function register(CronConfig $CronConfig): self {
        if (isset($this->configs[$CronConfig->name])) {
            throw new \InvalidArgumentException('CronConfig with name "' . $CronConfig->name . '" just exists');
        }
        $this->configs[$CronConfig->name] = $CronConfig;
        return $this;
    }

    function registerOnRunException(callable $function): self {
        $this->onRunException = $function;
        return $this;
    }

    function run() {

        $onRunException = $this->onRunException;
        $run_responses = [];
        foreach ($this->configs as $CronConfig) {

            $run_responses[$CronConfig->name] = null;

            $db_value_key = self::$db_value_prefix . $CronConfig->name;

            $DbValue = $this->DbValueManager->get($db_value_key);

            if ($DbValue->exists) {
                $LastStartedRun = $this->parseTimestamp($DbValue->value['last_started_run'] ?? null);
                $LastSuccessfulRun = $this->parseTimestamp($DbValue->value['last_successful_run'] ?? null);

                if ($LastStartedRun) {

                    $interval = $CronConfig->interval;

                    $last_run_fail = !$LastSuccessfulRun || ($LastStartedRun > $LastSuccessfulRun);

                    if ($last_run_fail && $interval < self::MIN_INTERVAL_AFTER_FAIL) {
                        $interval = self::MIN_INTERVAL_AFTER_FAIL;
                    }

                    if (($LastStartedRun->getTimestamp() + $interval) > time()) {
                        continue;
                    }
                }
            }

            $new_db_value['last_started_run'] = $this->exportTimestamp(new \DateTimeImmutable);
            $new_db_value['last_successful_run'] = $DbValue->value['last_successful_run'] ?? null;

            $this->DbValueManager->set($db_value_key, $new_db_value);

            $class = $CronConfig->cron_class;

            try {
                $Instance = new $class($CronConfig);
                if (!$Instance instanceof CronInterface) {
                    throw new \RuntimeException($class . ' not extends ' . CronInterface::class);
                    continue;
                }
                $CronResponse = $Instance->run();
            } catch (\Throwable $th) {
                $run_responses[$CronConfig->name] = false;
                if ($onRunException) {
                    $onRunException($th);
                }
                continue;
            }

            if ($CronResponse->success) {
                $new_db_value['last_successful_run'] = $this->exportTimestamp(new \DateTimeImmutable);
                $this->DbValueManager->set($db_value_key, $new_db_value);
            }

            $run_responses[$CronConfig->name] = $CronResponse;
        }

        $this->run_responses = $run_responses;
    }

    private function parseTimestamp(mixed $value): null|\DateTimeImmutable|int {

        if (!$value) {
            return null;
        }

        if ($this->DbValueManager instanceof MongoDbValueManager) {
            return \DateTimeImmutable::createFromMutable($value->toDateTime());
        } else {
            return new \DateTimeImmutable('@' . $value);
        }
    }

    private function exportTimestamp(\DateTimeImmutable $DTI): mixed {

        if ($this->DbValueManager instanceof MongoDbValueManager) {
            return new \MongoDB\BSON\UTCDateTime($DTI);
        } else {
            return $DTI->getTimestamp();
        }
    }
}
