<?php

namespace mrblue\framework\Utils\DbValue;

class S3ValueManager implements DbValueManagerInterface {

    public array $options = [
        // default values
        'name_prefix' => '',
        'acl' => 'private',
        'write_async' => false
    ];

    public readonly string $np;

    function __construct(
        public readonly \Aws\S3\S3Client $S3Client,
        public readonly string $bucket,
        array $options = [],
    ) {
        $this->options = $options + $this->options;

        $this->np = rtrim($this->options['name_prefix'], '/') . '/';
    }

    function set(string $key, mixed $value, ?int $ttl = null): DbValue {

        $obj_content = [
            'value' => $value,
        ];

        if ($ttl) {
            $ExpireAt = new \DateTimeImmutable('+' . $ttl . ' seconds');
            $obj_content['expire_at'] = $ExpireAt->getTimestamp();
        }

        $json_content = json_encode($obj_content);

        $put_data = [
            'ACL' => $this->options['acl'],
            'Bucket' => $this->bucket,
            'ContentType' => 'applciation/json',
            'ContentMD5' => base64_encode(hex2bin(md5($json_content))),
            'Body' => $json_content,
            'Key' => $this->getName($key)
        ];

        if ($this->options['write_async']) {
            $this->S3Client->putObjectAsync($put_data);
        } else {
            $this->S3Client->putObject($put_data);
        }

        return $this->createDbValue($obj_content);
    }

    function get(string $key): DbValue {

        return $this->getDbValueByObject($this->getObject($key));
    }

    function delete(string $key): bool {

        try {
            $this->S3Client->deleteObject([
                'Bucket' => $this->bucket,
                'Key' => $this->getName($key)
            ]);
        } catch (\Aws\S3\Exception\S3Exception $th) {
            if ($th->getStatusCode() == 404) {
                return false;
            } else {
                throw $th;
            }
        }

        return true;
    }

    function inc(string $key, int $amount = 1, ?int $ttl = null): DbValue {

        $object = $this->getObject($key);

        $DbValue = $this->getDbValueByObject($object);

        $obj_content = [
            'value' => $amount
        ];

        if ($DbValue->exists) {
            if (is_int($DbValue->value)) {
                $obj_content['value'] += $DbValue->value;
            }
        }

        if ($ttl) {
            $ExpireAt = $DbValue->ExpireAt ?? new \DateTimeImmutable('+' . $ttl . ' seconds');
            $obj_content['expire_at'] = $ExpireAt->getTimestamp();
        }

        $json_content = json_encode($obj_content);
        $this->S3Client->putObject([
            'ACL' => $this->options['acl'],
            'Bucket' => $this->bucket,
            'ContentType' => 'applciation/json',
            'ContentMD5' => base64_encode(hex2bin(md5($json_content))),
            'Body' => $json_content,
            'Key' => $this->getName($key)
        ]);

        return $this->createDbValue($obj_content);
    }

    protected function getName(string $key): string {
        return $this->np . $key;
    }

    protected function getObject(string $key): ?array {

        try {
            return $this->S3Client->getObject([
                'Bucket' => $this->bucket,
                'Key' => $this->getName($key)
            ])->toArray();
        } catch (\Aws\S3\Exception\S3Exception $th) {
            if ($th->getStatusCode() == 404) {
                return null;
            } else {
                throw $th;
            }
        }
    }

    protected function getDbValueByObject(?array $object): DbValue {

        if ($object) {
            return $this->createDbValue($object['Body']);
        } else {
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
