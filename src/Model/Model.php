<?php

namespace mrblue\framework\Model;

abstract class Model implements \JsonSerializable {

	const PRIMARY_FIELD = 'id';

	const DB_FIELDS_MAP = [];

	const IGNORE_FIELDS = [
		'__ignore_fields' => true,
		'__class_properties' => true,
		'__updated_fields' => true,
		'__listBulkpdateEnabled' => true,
		'__internalStorage' => true
	];

	protected array $__ignore_fields = self::IGNORE_FIELDS;
	/**
	 * @var null|\ReflectionProperty[]
	 */
	private ?array $__class_properties = null;
	protected array $__updated_fields = [];
	protected bool $__listBulkpdateEnabled = false;
	public readonly \ArrayObject $__internalStorage;

	function __construct(array $data) {

		$this->__internalStorage = new \ArrayObject();

		$class_properties = $this->getClassProperties();
		foreach ($class_properties as $ReflectionProperty) {
			$name = $ReflectionProperty->getName();
			$possible_name = static::DB_FIELDS_MAP[$name] ?? null;
			if (
				!array_key_exists($name, $data) &&
				!($possible_name && array_key_exists($possible_name, $data))
			) {
				$type = self::getReflectionPropertyType($ReflectionProperty);
				if ($ReflectionProperty->hasDefaultValue()) {
					$data[$name] = $ReflectionProperty->getDefaultValue();
				} elseif ($type && is_a($type, ModelList::class, true) && !$ReflectionProperty->getType()->allowsNull()) {
					$data[$name] = [];
				} elseif ($type && is_a($type, EnumList::class, true) && !$ReflectionProperty->getType()->allowsNull()) {
					$data[$name] = [];
				} else {
					$data[$name] = null;
				}
			}
		}

		$this->import($data);
	}

	function import(array $data) {
		$class_properties = $this->getClassProperties();
		foreach ($data as $key => $value) {
			$key = array_search($key, static::DB_FIELDS_MAP, true) ?: $key;
			$ReflectionProperty = $class_properties[$key] ?? null;
			if (!$ReflectionProperty) {
				continue;
			}
			if ($value !== null) {

				$ReflectionType = $ReflectionProperty->getType();

				if ($ReflectionType instanceof \ReflectionNamedType && !$ReflectionType->isBuiltin()) {
					$type = $ReflectionType->getName();

					if (!is_a($value, $type)) {
						if (is_a($type, self::class, true)) {
							$class_target = $type::getFinalClass($value);
							$value = new $class_target($value);
						} else {
							$value = $this->valueFrom($value, $type);
						}
					}
				}
			}


			$this->setProp($key, $value);
		}
	}

	protected function setProp(string $key, mixed $value) {
		$this->{$key} = $value;
	}

	function export(): array {
		$export = [];
		foreach ($this->getClassProperties() as $ReflectionProperty) {
			$key = $ReflectionProperty->getName();
			$value = $this->{$key};
			if ($value instanceof Model || $value instanceof ModelList || $value instanceof EnumList) {
				$value = $value->export();
			}

			$export[$key] = $value;
		}
		return $export;
	}

	function exportToDb(bool $updated_only = false): array {
		$export = [];
		foreach ($this->getClassProperties() as $ReflectionProperty) {
			$key = $ReflectionProperty->getName();
			$prop_type = self::getReflectionPropertyType($ReflectionProperty);
			$value = $this->{$key};

			$is_list = $value instanceof ModelList || ($prop_type && is_a($prop_type, ModelList::class, true));

			if ($is_list && !$this->isListBulkpdateEnabled()) {
				continue;
			}

			$submodel_update_only = false;

			if ($updated_only && !in_array($key, $this->__updated_fields)) {

				if (!$value instanceof Model) {
					continue;
				}

				$submodel_update_only = true;
			}

			if ($value instanceof Model) {

				$value->setListBulkpdateEnabled($this->isListBulkpdateEnabled());
				$value = $value->exportToDb(updated_only: $submodel_update_only);
				if (!$value) {
					if ($submodel_update_only) {
						continue;
					} else {
						$value = new \stdClass;
					}
				}
			} elseif ($value instanceof ModelList) {
				$list_of_values = [];
				foreach ($value as $ListModel) {
					$ListModel->setListBulkpdateEnabled($this->isListBulkpdateEnabled());
					$value_item = $ListModel->exportToDb(updated_only: false);
					if (!$value_item) {
						continue;
					}
					$list_of_values[] = $value_item;
				}
				$value = $list_of_values;
			}

			if (isset(static::DB_FIELDS_MAP[$key])) {
				$key = static::DB_FIELDS_MAP[$key];
			}

			$export[$key] = $this->valueToDb($value);
		}
		return $export;
	}

	function jsonSerialize(): mixed {
		$export = [];
		foreach ($this->getClassProperties() as $ReflectionProperty) {
			$key = $ReflectionProperty->getName();
			$value = $this->{$key};
			if ($value instanceof \JsonSerializable) {
				$export[$key] = $value->jsonSerialize();
			} else {
				$export[$key] = $this->valueToJson($value);
			}
		}
		return $export;
	}

	function update(array $data): self {
		$this->import($data);
		$this->updateSignal(array_keys($data));
		return $this;
	}

	function updateSignal(array $fields) {
		$this->__updated_fields = array_unique(array_merge(
			$this->__updated_fields,
			self::filterDataValues($fields, array_keys($this->getClassProperties()))
		));
	}

	/**
	 * @return \ReflectionProperty[] 
	 */
	function getClassProperties(): array {
		if ($this->__class_properties === null) {
			$ReflectionClass = new \ReflectionClass($this);
			$class_properties = $ReflectionClass->getProperties();
			$this->__class_properties = [];
			foreach ($class_properties as $ReflectionProperty) {
				$prop_name = $ReflectionProperty->getName();
				if (!isset($this->__ignore_fields[$prop_name])) {
					$this->__class_properties[$ReflectionProperty->getName()] = $ReflectionProperty;
				}
			}
		}

		return $this->__class_properties;
	}

	function valueFrom($value, string $type) {

		if (is_a($type, \BackedEnum::class, true)) {
			return $type::from($value);
		} elseif (is_a($type, \DateTimeInterface::class, true)) {
			$class_target = $type === \DateTimeInterface::class ? \DateTimeImmutable::class : $type;
			if ($value instanceof \DateTimeInterface) {
				return $class_target::createFromInterface($value);
			} else {
				return new $class_target($value);
			}
		} elseif (class_exists($type)) {
			return new $type($value);
		} else {
			return $value;
		}
	}

	function valueToDb($value) {
		if ($value instanceof \BackedEnum) {
			return $value->value;
		} elseif ($value instanceof \DateTimeZone) {
			return $value->getName();
		} elseif ($value instanceof EnumList) {
			return $value->export();
		} else {
			return $value;
		}
	}

	function valueToJson($value) {
		if ($value instanceof \BackedEnum) {
			return $value->value;
		} elseif ($value instanceof \DateTimeInterface) {
			return $value->format(\DateTimeInterface::ATOM);
		} elseif ($value instanceof \DateTimeZone) {
			return $value->getName();
		} else {
			return $value;
		}
	}

	function setListBulkpdateEnabled(bool $enabled): self {
		$this->__listBulkpdateEnabled = $enabled;
		return $this;
	}

	function isListBulkpdateEnabled(): bool {
		return $this->__listBulkpdateEnabled;
	}

	static function getFinalClass(array $data): string {
		return static::class;
	}

	static function filterDataKeys(array $data, array $allowed): array {

		return array_filter($data, fn ($key) => in_array($key, $allowed), ARRAY_FILTER_USE_KEY);
	}

	static function filterDataValues(array $data, array $allowed): array {

		return array_filter($data, fn ($value) => in_array($value, $allowed));
	}

	static function getReflectionPropertyType(\ReflectionProperty $ReflectionProperty): string {

		$ReflectionType = $ReflectionProperty->getType();

		if ($ReflectionType instanceof \ReflectionNamedType) {
			return $ReflectionType->getName();
		} else {
			return null;
		}
	}
}
