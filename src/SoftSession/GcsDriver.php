<?php

namespace mrblue\framework\SoftSession;

use Google\Cloud\Storage\Bucket;
use Google\Cloud\Storage\StorageClient;

class GcsDriver implements SoftSessionDriverInterface {

    public readonly StorageClient $StorageClient;
    public readonly Bucket $Bucket;
    public readonly string $bucket;
    public readonly string $path_prefix;

    function __construct(StorageClient $StorageClient, string $bucket, string $path_prefix) {
        $this->StorageClient = $StorageClient;
        $this->bucket = $bucket;
        $this->path_prefix = $path_prefix;

        $this->Bucket = $StorageClient->bucket($bucket);
    }

    function get(string $session_id): ?array {

        try {
            $Object = $this->Bucket->object($this->getPath($session_id));
            $data = json_decode($Object->downloadAsString(), true);
        } catch (\Google\Cloud\Core\Exception\NotFoundException $nfe) {
            return null;
        }

        return $data;
    }

    function set(string $session_id, array $data): bool {

        $this->Bucket->upload(json_encode($data), [
            'name' => $this->getPath($session_id)
        ]);

        return true;
    }

    function remove(string $session_id): bool {

        try {
            $this->Bucket->object($this->getPath($session_id))->delete();
        } catch (\Google\Cloud\Core\Exception\NotFoundException $nfe) {
            return false;
        }

        return true;
    }

    protected function getPath(string $session_id): string {

        return rtrim($this->path_prefix, '/') . '/' . $session_id;
    }
}
