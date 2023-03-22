<?php
namespace mrblue\framework\Model;

abstract class MongodbModel extends Model {

	CONST DB_FIELDS_MAP = [
		'id' => '_id'
	];

	function getDbRetrieveQuery() : mixed {
		if( ! static::PRIMARY_FIELD ){
			throw new \RuntimeException(self::class.' without PRIMARY_FIELD cannot be retrieved');
		}

		$db_field = static::DB_FIELDS_MAP[static::PRIMARY_FIELD] ?? static::PRIMARY_FIELD;

		return [
			$db_field => $this->{static::PRIMARY_FIELD}
		];
	}

	function getDbInsertQuery() : mixed {
		return $this->exportToDb();
	}

	function getDbUpdateQuery() : mixed {
		$update_data = self::formatUpdateFields($this->exportToDb(true));

		$query = [];

		foreach( $update_data as $key => $value ){
			if( is_null($value) ){
				$query['$unset'][$key] = $value;
			} else {
				$query['$set'][$key] = $value;
			}
		}

		return $query ? : [];
	}

	function exportToDb( bool $updated_only = false ) :array {

        $export = parent::exportToDb($updated_only);

		/**
		 * remove null fields in insert context
		 */
		if( ! $updated_only ){
			$export = array_filter($export,fn($value) => isset($value));
		}

        return $export;
	}

	function valueFrom( $value , string $type ){

		if( $value instanceof \MongoDB\BSON\UTCDateTime ){
			$value = $value->toDateTime();
		}

		return parent::valueFrom($value,$type);
	}

	function valueToDb( $value ) {
		$value = parent::valueToDb($value);

		if( $value instanceof \DateTimeInterface ){
			return new \MongoDB\BSON\UTCDateTime($value);
		} else {
			return $value;
		}
	}

	/**
	 * 
	 * @todo remove
	 */
	static function mergeSubmodelUpdateQuery( array $parent_query , string $path , array $submodel_query ) {

		$query = $parent_query;

		foreach ($submodel_query as $modifier => $data){
			foreach ($data as $key => $value){
				$query[$modifier][$path.'.'.$key] = $value;
			}
		}

		return $query;
	}

	static function formatUpdateFields( array $data , ?string $parent_key = null ) :array {
	
		$result = [];

		foreach( $data as $key => $value ){

			$new_key = $parent_key ? $parent_key.'.'.$key : $key;
			if( is_array($value) && ! array_is_list($value) ){
				$result+= self::formatUpdateFields($value,$new_key);
			} else {
				$result[$new_key] = $value;
			}
		}
		return $result;
	}

}

