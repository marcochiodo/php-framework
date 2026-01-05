<?php

namespace mrblue\framework\Db;

use mrblue\framework\Model\Model;
use mrblue\framework\Model\ModelList;
use mrblue\framework\Model\MongodbModel;

class MongodbManager {

	const SEQUENCES_PATH = '_sequences';

	const TYPE_MAP = [
		'array' => 'array',
		'document' => 'array',
		'root' => 'array',
	];

	public readonly \MongoDB\Collection $Collection;
	public readonly string $model_class;
	public readonly ?string $list_class;
	protected array $options = [];

	public ?\MongoDB\InsertOneResult $lastInsertOneResult = null;
	public ?\MongoDB\UpdateResult $lastUpdateResult = null;
	public ?\MongoDB\DeleteResult $lastDeleteResult = null;

	function __construct(\MongoDB\Collection $Collection, string $model_class, ?string $list_class = null, array $options = []) {
		$this->Collection = $Collection;
		$this->model_class = $model_class;
		$this->list_class = $list_class;
		$this->options = $options + $this->options;
	}

	function get(mixed $_id): ?MongodbModel {
		return $this->queryOne(['_id' => $_id]);
	}

	function queryOne(mixed $query, array $options = []): ?MongodbModel {
		$document = $this->Collection->findOne($query, [
			'typeMap' => self::TYPE_MAP
		] + ($options['db_options'] ?? []));

		if (!$document) {
			return null;
		}

		$class_name = $this->model_class::getFinalClass($document);
		return new $class_name($document);
	}

	function query(mixed $query, array $options = []): ModelList {
		$Cursor = $this->Collection->find($query, [
			'typeMap' => self::TYPE_MAP
		] + ($options['db_options'] ?? []));

		if ($this->list_class) {
			return new $this->list_class($Cursor->toArray());
		} else {
			return new ModelList($this->model_class, $Cursor->toArray());
		}
	}

	function aggregate(array $pipeline, array $options = []): ModelList {
		$Results = $this->Collection->aggregate($pipeline, [
			'typeMap' => self::TYPE_MAP,
			'useCursor' => true
		] + ($options['db_options'] ?? []))->toArray();

		if ($this->list_class) {
			return new $this->list_class($Results);
		} else {
			return new ModelList($this->model_class, $Results);
		}
	}

	function count(mixed $query): int {
		return $this->Collection->countDocuments($query);
	}

	function insert(MongodbModel $Model, array $options = []): bool|MongodbExecution {

		$dry_run = $options['dry_run'] ?? false;
		$dry_run_name = $options['dry_run_name'] ?? null;

		$MongodbExecution = new MongodbExecution(
			type: MongodbExecution::INSERT_ONE,
			retrieve_query: null,
			write_query: $Model->getDbInsertQuery(),
			options: $options['db_options'] ?? [],
			name: $dry_run_name
		);

		if ($dry_run) {
			return $MongodbExecution;
		} else {
			return $this->execute($MongodbExecution);
		}
	}

	function update(MongodbModel $Model, array $options = []): null|bool|MongodbExecution {

		$dry_run = $options['dry_run'] ?? false;
		$dry_run_name = $options['dry_run_name'] ?? null;

		$update_query = $Model->getDbUpdateQuery();

		if (!$update_query) {
			return null;
		}

		$retrieve_query = $Model->getDbRetrieveQuery();

		if (array_key_exists('if_match', $options) && $options['if_match']) {
			$retrieve_query = array_replace_recursive($options['if_match'], $retrieve_query);
		}

		$MongodbExecution = new MongodbExecution(
			type: MongodbExecution::UPDATE_ONE,
			retrieve_query: $retrieve_query,
			write_query: $update_query,
			options: $options['db_options'] ?? [],
			name: $dry_run_name
		);

		if ($dry_run) {
			return $MongodbExecution;
		} else {
			return $this->execute($MongodbExecution);
		}
	}

	function getSeq(MongodbModel $Model, string $field): int|float|null {

		$options = [
			'typeMap' => self::TYPE_MAP,
			'projection' => [
				static::SEQUENCES_PATH . '.' . $field => 1
			]
		];

		return $this->Collection->findOne(
			$Model->getDbRetrieveQuery(),
			$options
		)[static::SEQUENCES_PATH][$field] ?? null;
	}

	function getAllSeq(MongodbModel $Model): ?array {

		$options = [
			'typeMap' => self::TYPE_MAP,
			'projection' => [
				static::SEQUENCES_PATH => 1
			]
		];

		return $this->Collection->findOne(
			$Model->getDbRetrieveQuery(),
			$options
		)[static::SEQUENCES_PATH] ?? null;
	}

	function incSeq(MongodbModel $Model, string $field, int|float $amount, array $options = []): int|float|false|MongodbExecution {

		$dry_run = $options['dry_run'] ?? false;
		$dry_run_name = $options['dry_run_name'] ?? null;

		$retrieve_query = $this->composeRetrieveQuery($Model->getDbRetrieveQuery(), $options);

		$update_query = [
			'$inc' => [static::SEQUENCES_PATH . '.' . $field => $amount]
		];
		$db_options = [
			'projection' => [
				static::SEQUENCES_PATH . '.' . $field => 1
			],
			'returnDocument' => \MongoDB\Operation\FindOneAndUpdate::RETURN_DOCUMENT_AFTER
		] + ($options['db_options'] ?? []);

		$MongodbExecution = new MongodbExecution(
			type: MongodbExecution::FIND_ONE_AND_UPDATE,
			retrieve_query: $retrieve_query,
			write_query: $update_query,
			options: $db_options,
			name: $dry_run_name
		);

		if ($dry_run) {
			return $MongodbExecution;
		} else {
			$this->execute($MongodbExecution);

			return $MongodbExecution->server_result[static::SEQUENCES_PATH][$field] ?? false;
		}
	}

	function setSeq(MongodbModel $Model, string $field, int|float $value, array $options = []): bool|MongodbExecution {

		$dry_run = $options['dry_run'] ?? false;
		$dry_run_name = $options['dry_run_name'] ?? null;

		$retrieve_query = $this->composeRetrieveQuery($Model->getDbRetrieveQuery(), $options);
		$update_query = [
			'$set' => [static::SEQUENCES_PATH . '.' . $field => $value]
		];

		$MongodbExecution = new MongodbExecution(
			type: MongodbExecution::UPDATE_ONE,
			retrieve_query: $retrieve_query,
			write_query: $update_query,
			options: $options['db_options'] ?? [],
			name: $dry_run_name
		);

		if ($dry_run) {
			return $MongodbExecution;
		} else {
			return $this->execute($MongodbExecution);
		}
	}

	function inc(MongodbModel $Model, array $values, array $options = []): bool {

		if (!$values) {
			throw new \InvalidArgumentException('values cannot be empty');
		}

		$projection = [];
		foreach ($values as $field => $amount) {
			$Model->{$field};
			if ((!is_int($amount) && !is_float($amount)) || $amount === 0) {
				throw new \InvalidArgumentException('amount must be int or float != 0');
			}
			$projection[$field] = 1;
		}

		$document = $this->Collection->findOneAndUpdate($Model->getDbRetrieveQuery(), [
			'$inc' => $values
		], [
			'projection' => $projection,
			'returnDocument' => \MongoDB\Operation\FindOneAndUpdate::RETURN_DOCUMENT_AFTER
		] + ($options['db_options'] ?? []));

		if (!$document) {
			return false;
		}

		foreach ($values as $field => $amount) {
			$Model->{$field} = $document[$field];
		}

		return true;
	}

	function delete(MongodbModel $Model, array $options = []): bool|MongodbExecution {

		$dry_run = $options['dry_run'] ?? false;
		$dry_run_name = $options['dry_run_name'] ?? null;

		$delete_query = $Model->getDbRetrieveQuery();

		if (array_key_exists('if_match', $options) && $options['if_match']) {
			$delete_query = array_replace_recursive($options['if_match'], $delete_query);
		}

		$MongodbExecution = new MongodbExecution(
			type: MongodbExecution::DELETE_ONE,
			retrieve_query: $delete_query,
			write_query: null,
			options: $options['db_options'] ?? [],
			name: $dry_run_name
		);

		if ($dry_run) {
			return $MongodbExecution;
		} else {
			return $this->execute($MongodbExecution);
		}
	}

	function insertSubDocument(MongodbModel $Model, array $parent_chain, array $options = []): bool|MongodbExecution {

		$dry_run = $options['dry_run'] ?? false;
		$dry_run_name = $options['dry_run_name'] ?? null;

		/**
		 * attualmente non abbiamo un modo per impedire l'inserimento di documenti duplicati
		 * solo a livello dell'array target. Abilitando la prevenzione si blocca la duplicazione
		 * su tutti i livelli paralleli. Non è stata trovata una soluzione per ora.
		 */
		$prevent_duplicated_child = boolval($options['prevent_duplicated_child'] ?? true);

		$parent_definition = self::getParentDefinition($parent_chain);

		$query_path = implode('.', $parent_definition['query_path']);
		$update_path = implode('.', $parent_definition['update_path']);

		$retrieve_query = $parent_definition['retrieve_query'];

		if ($prevent_duplicated_child) {
			$retrieve_query += [
				'$nor' => [
					[
						$query_path => [
							'$elemMatch' => $Model->getDbRetrieveQuery()
						]
					]
				]
			];
		}

		$query_options = [
			'arrayFilters' => $parent_definition['array_filters'] ?? []
		] + ($options['db_options'] ?? []);

		$update_query = [
			'$push' => [
				$update_path => $Model->getDbInsertQuery()
			]
		];

		$MongodbExecution = new MongodbExecution(
			type: MongodbExecution::UPDATE_ONE,
			retrieve_query: $retrieve_query,
			write_query: $update_query,
			options: $query_options,
			name: $dry_run_name
		);

		if ($dry_run) {
			return $MongodbExecution;
		} else {
			return $this->execute($MongodbExecution);
		}
	}

	function updateSubDocument(MongodbModel $Model, array $parent_chain, array $options = []): null|bool|MongodbExecution {

		$dry_run = $options['dry_run'] ?? false;
		$dry_run_name = $options['dry_run_name'] ?? null;

		$update_query = $Model->getDbUpdateQuery();
		if (!$update_query) {
			return null;
		}
		$model_retrieve_query = $Model->getDbRetrieveQuery();
		$model_retrieve_query_key = key($model_retrieve_query);
		$model_retrieve_query_value = current($model_retrieve_query);

		$parent_definition = self::getParentDefinition($parent_chain);

		$query_path = implode('.', $parent_definition['query_path']);
		$update_path = implode('.', $parent_definition['update_path']);

		$retrieve_query = $parent_definition['retrieve_query'] + [
			$query_path => [
				'$elemMatch' => $model_retrieve_query
			]
		];

		foreach ($update_query as $modifier => $data) {
			foreach ($data as $key => $value) {
				$update_query[$modifier][$update_path . '.$[last].' . $key] = $value;
				unset($update_query[$modifier][$key]);
			}
		}

		$query_options = [
			'arrayFilters' => $parent_definition['array_filters'] ?? []
		] + ($options['db_options'] ?? []);

		$query_options['arrayFilters'][]['last.' . $model_retrieve_query_key] = $model_retrieve_query_value;

		$MongodbExecution = new MongodbExecution(
			type: MongodbExecution::UPDATE_ONE,
			retrieve_query: $retrieve_query,
			write_query: $update_query,
			options: $query_options,
			name: $dry_run_name
		);

		if ($dry_run) {
			return $MongodbExecution;
		} else {
			return $this->execute($MongodbExecution);
		}
	}

	function deleteSubDocument(MongodbModel $Model, array $parent_chain, array $options = []): bool|MongodbExecution {

		$dry_run = $options['dry_run'] ?? false;
		$dry_run_name = $options['dry_run_name'] ?? null;

		$parent_definition = self::getParentDefinition($parent_chain);

		$query_path = implode('.', $parent_definition['query_path']);
		$update_path = implode('.', $parent_definition['update_path']);

		$retrieve_query = $parent_definition['retrieve_query'];

		$query_options = [
			'arrayFilters' => $parent_definition['array_filters'] ?? []
		] + ($options['db_options'] ?? []);

		$update_query = [
			'$pull' => [
				$update_path => $Model->getDbRetrieveQuery()
			]
		];
		$MongodbExecution = new MongodbExecution(
			type: MongodbExecution::UPDATE_ONE,
			retrieve_query: $retrieve_query,
			write_query: $update_query,
			options: $query_options,
			name: $dry_run_name
		);

		if ($dry_run) {
			return $MongodbExecution;
		} else {
			return $this->execute($MongodbExecution);
		}
	}


	function execute(MongodbExecution $MongodbExecution, array $execution_options = []): bool {

		$MongodbExecution->execute($this->Collection, $execution_options);

		if ($MongodbExecution->server_result instanceof \MongoDB\InsertOneResult) {
			$this->lastInsertOneResult = $MongodbExecution->server_result;
		} elseif ($MongodbExecution->server_result instanceof \MongoDB\UpdateResult) {
			$this->lastUpdateResult = $MongodbExecution->server_result;
		} elseif ($MongodbExecution->server_result instanceof \MongoDB\DeleteResult) {
			$this->lastDeleteResult = $MongodbExecution->server_result;
		}

		return $MongodbExecution->success;
	}

	static function getParentDefinition(array $parent_chain): array {

		if (!$parent_chain || !array_is_list($parent_chain) || (count($parent_chain) % 2) !== 0) {
			throw new \InvalidArgumentException('parent_chain is empty or is not a list or number of elements is not a multiple of 2');
		}

		$query_path = [];
		$update_path = [];
		$retrieve_query = [];
		$array_filters = [];

		$last = count($parent_chain) - 1;

		$i = 0;
		$positional_i = 0;

		while ($i < $last) {
			$is_first = $i === 0;
			$is_last = $i + 1 === $last;

			/** @var MongodbModel */
			$ParentModel = $parent_chain[$i];
			$parent_path = $parent_chain[$i + 1];
			if (!$ParentModel instanceof MongodbModel || !is_string($parent_path)) {
				throw new \InvalidArgumentException("Bad parameter at indexed $i and " . ($i + 1));
			}
			$ReflectionClass = new \ReflectionClass($ParentModel);

			if ($is_first) {
				$retrieve_query = $ParentModel->getDbRetrieveQuery();
			}


			// validate parent_path vartype

			$ReflectionProperty = $ReflectionClass->getProperty($parent_path);
			$ReflectionType = $ReflectionProperty->getType();

			$parent_path_vartype = null;
			if ($ReflectionType instanceof \ReflectionNamedType) {
				$parent_path_vartype = $ReflectionType->getName();
			}

			if (is_a($parent_path_vartype, Model::class, true)) {
				$query_path[] = $update_path[] = $parent_path;
			} elseif (is_a($parent_path_vartype, ModelList::class, true)) {
				$query_path[] = $update_path[] = $parent_path;

				if (!$is_last) {
					/** @var MongodbModel */
					$NextModel = $parent_chain[$i + 2];
					$model_retrieve_query = $NextModel->getDbRetrieveQuery();
					$model_retrieve_query_key = key($model_retrieve_query);
					$model_retrieve_query_value = current($model_retrieve_query);
					$retrieve_query[implode('.', $query_path)]['$elemMatch'] = $model_retrieve_query;
					$update_path[] = '$[i' . (++$positional_i) . ']';
					$array_filters[]['i' . $positional_i . '.' . $model_retrieve_query_key] = $model_retrieve_query_value;
				}
			} else {
				throw new \InvalidArgumentException("Bad parameter at indexed $i and " . ($i + 1));
			}

			$i += 2;
		}

		return [
			'query_path' => $query_path,
			'update_path' => $update_path,
			'retrieve_query' => $retrieve_query,
			'array_filters' => $array_filters
		];
	}

	protected function composeRetrieveQuery(array $base_query, array $options): array {

		if (array_key_exists('if_match', $options) && $options['if_match']) {
			$base_query = array_replace_recursive($options['if_match'], $base_query);
		}

		return $base_query;
	}
}
