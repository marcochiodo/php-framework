<?php

namespace mrblue\framework\Db;

use MongoDB\BSON\Document;
use MongoDB\Collection;
use MongoDB\DeleteResult;
use MongoDB\InsertOneResult;
use MongoDB\UpdateResult;

class MongodbExecution {

    const INSERT_ONE = 'insert_one';
    const UPDATE_ONE = 'update_one';
    const DELETE_ONE = 'delete_one';
    const FIND_ONE_AND_UPDATE = 'find_one_and_update';

    public readonly bool $success;
    public readonly mixed $server_result;

    function __construct(
        public readonly string $type,
        public readonly ?array $retrieve_query,
        public readonly ?array $write_query,
        public array $options = [],
        public ?string $name = null
    ) {
    }

    function execute(Collection $Collection, array $execution_options = []): self {

        $options = $this->options;
        if ($execution_options) {
            $options = array_replace($options, $execution_options);
        }

        if ($this->type === self::INSERT_ONE) {
            $this->server_result = $Collection->insertOne($this->write_query, $options);
        } elseif ($this->type === self::UPDATE_ONE) {
            $this->server_result = $Collection->updateOne($this->retrieve_query, $this->write_query, $options);
        } elseif ($this->type === self::DELETE_ONE) {
            $this->server_result = $Collection->deleteOne($this->retrieve_query, $options);
        } elseif ($this->type === self::FIND_ONE_AND_UPDATE) {
            $this->server_result = $Collection->findOneAndUpdate($this->retrieve_query, $this->write_query, $options);
        }

        $this->parseServerResult();
        return $this;
    }

    protected function parseServerResult(): self {

        if ($this->server_result instanceof InsertOneResult) {
            $this->success = (bool) $this->server_result->getInsertedCount();
        } elseif ($this->server_result instanceof UpdateResult) {
            $this->success = (bool) $this->server_result->getMatchedCount();
        } elseif ($this->server_result instanceof DeleteResult) {
            $this->success = (bool) $this->server_result->getDeletedCount();
        } elseif ($this->type === self::FIND_ONE_AND_UPDATE) {
            $this->success = (bool) $this->server_result;
        } else {
            $this->success = false;
        }

        return $this;
    }
}
