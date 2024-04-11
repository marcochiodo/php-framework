<?php

namespace mrblue\framework\Utils\DbValue;

use Google\Cloud\Storage\StorageObject;

class GcsValueManager implements DbValueManagerInterface {

    public readonly \Google\Cloud\Storage\Bucket $Bucket;
    public array $options = [
        // default values
        'name_prefix' => '',
        'useCustomTimeForExpire' => false
    ];

    function __construct(
        public readonly \Google\Cloud\Storage\StorageClient $GcsClient,
        public readonly string $bucket,
        array $options = [],
    ) {
        $this->Bucket = $GcsClient->bucket($bucket);
        $this->options = $options + $this->options;
    }

    function set(string $key, mixed $value, ?int $ttl = null): DbValue {

        $obj_content = [
            'value' => $value,
        ];

        $insert_options = [
            'name' => $this->getName($key),
        ];

        if ($ttl) {
            $ExpireAt = new \DateTimeImmutable('+' . $ttl . ' seconds');
            $obj_content['expire_at'] = $ExpireAt->getTimestamp();
            if ($this->options['useCustomTimeForExpire']) {
                $insert_options['metadata']['customTime'] = $ExpireAt->format(\DateTimeInterface::RFC3339);
            }
        }

        $this->Bucket->upload(json_encode($obj_content), $insert_options);

        return $this->createDbValue($obj_content);
    }

    function get(string $key): DbValue {

        $Object = $this->getObject($key);

        return $this->getDbValueByObject($Object);
    }

    function delete(string $key): bool {

        $Object = $this->getObject($key);

        try {
            $Object->delete();
        } catch (\Google\Cloud\Core\Exception\NotFoundException $th) {
            return false;
        }

        return true;
    }

    function inc(string $key, int $amount = 1, ?int $ttl = null): DbValue {

        $Object = $this->getObject($key);

        $DbValue = $this->getDbValueByObject($Object);

        $obj_content = [
            'value' => $amount
        ];

        $insert_options = [
            'name' => $this->getName($key),
        ];

        if ($DbValue->exists) {
            if (is_int($DbValue->value)) {
                $obj_content['value'] += $DbValue->value;
            }
        }

        if ($ttl) {
            $ExpireAt = $DbValue->ExpireAt ?? new \DateTimeImmutable('+' . $ttl . ' seconds');
            $obj_content['expire_at'] = $ExpireAt->getTimestamp();
            if ($this->options['useCustomTimeForExpire']) {
                $insert_options['metadata']['customTime'] = $ExpireAt->format(\DateTimeInterface::RFC3339);
            }
        }

        $this->Bucket->upload(json_encode($obj_content), $insert_options);

        return $this->createDbValue($obj_content);
    }

    protected function getName(string $key): string {
        return rtrim($this->options['name_prefix'], '/') . '/' . $key;
    }

    protected function getObject(string $key): StorageObject {
        return $this->Bucket->object($this->getName($key));
    }

    protected function getDbValueByObject(StorageObject $Object): DbValue {
        try {
            return $this->createDbValue($Object->downloadAsString());
        } catch (\Google\Cloud\Core\Exception\NotFoundException $th) {
            return $this->createDbValue(null);
        }
    }

    protected function createDbValue(string|array|null $data): DbValue {

        if (!$data) {
            return new DbValue(false);
        }

        if (is_string($data)) {
            $data = json_decode($data, true);
        }

        if (isset($data['expire_at']) && is_int($data['expire_at'])) {
            $ExpireAt = new \DateTimeImmutable('@' . $data['expire_at']);
        }

        return new DbValue(
            exists: true,
            value: $data['value'] ?? null,
            expire_at: $ExpireAt ?? null
        );
    }
}
