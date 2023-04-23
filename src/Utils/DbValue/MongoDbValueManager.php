<?php
namespace mrblue\framework\Utils\DbValue;

class MongoDbValueManager implements DbValueManagerInterface {

    function __construct(
        public readonly \MongoDB\Collection $MongoDBCollection
    ) {}

    function set( string $key , mixed $value , ?int $ttl = null ) : DbValue {

        $update_query = [
            '$set' => [
                'value' => $value,
            ]
        ];

        if( $ttl ){
            $ExpireAt = new \MongoDB\BSON\UTCDateTime( new \DateTimeImmutable('+'.$ttl.' seconds') );
            $update_query['$set']['expire_at'] = $ExpireAt;
        } else {
            $update_query['$unset']['expire_at'] = true;
        }

        $this->MongoDBCollection->updateOne(['_id' => $key],$update_query,[
            'upsert' => true
        ]);

        return $this->createDbValue([
            'value' => $value,
            'expire_at' => $ExpireAt ?? null
        ]);
    }

    function get( string $key ) : DbValue {
        return $this->createDbValue( $this->MongoDBCollection->findOne(['_id' => $key]) );
    }

    function delete( string $key ) : bool {
        return (bool) $this->MongoDBCollection->deleteOne(['_id' => $key])->getDeletedCount();
    }

    function inc( string $key , int $amount = 1 , ?int $ttl = null ) : DbValue {

        $update_query = [
			'$inc' => [
				'value' => $amount
			]
        ];

        if( $ttl ){
            $update_query['$setOnInsert']['expire_at'] = new \MongoDB\BSON\UTCDateTime( new \DateTimeImmutable('+'.$ttl.' seconds') );
        }

        return $this->createDbValue( $this->MongoDBCollection->findOneAndUpdate(['_id' => $key],$update_query,[
            'upsert' => true,
			'returnDocument' => \MongoDB\Operation\FindOneAndUpdate::RETURN_DOCUMENT_AFTER
		]) );
    }

    protected function createDbValue( \stdClass|array|null $data ) : DbValue {

        if( ! $data ){
            return new DbValue(false);
        }

        $data = (array) $data;

        if( isset($data['expire_at']) && $data['expire_at'] instanceof \MongoDB\BSON\UTCDateTime ){
            $ExpireAt = \DateTimeImmutable::createFromMutable($data['expire_at']->toDateTime());
        }

        return new DbValue(
            exists: true,
            value: $data['value']??null,
            expire_at: $ExpireAt ?? null
        );
    }
}