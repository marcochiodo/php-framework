<?php
namespace mrblue\framework\Model;

class ModelList implements \Iterator , \Countable , \ArrayAccess , \JsonSerializable {

	protected int $position = 0;
	protected array $storage = [];
	protected string $class_name;
	
	function __construct( string $class_name , array $items = [] ) {
		$this->class_name = $class_name;
		$this->addItems( $items );
	}

	function addItems( array $items ) :self {
		foreach ( $items as $item ){
			$this->add($item);
		}

		return $this;
	}
	
	function add( array|Model $item , $options = [] ) :self {

		$add = [
			'class' =>  $this->parse($item),
			'attributes' => []
		];

		if( ! empty($options['attributes']) && is_array($options['attributes']) ){
			$add['attributes'] = $options['attributes'];
		}

		$this->storage[] = $add;
		
		return $this;
	}

	function parse( array|Model $item ) :Model {

		$class_name = $this->class_name;

		if( $item instanceof Model ){

			if( ! is_a($item, $class_name) ){
				throw new \InvalidArgumentException("Item of this list must be instance of '$class_name'");
			}

		} else {
		
			$class_name = $class_name::getFinalClass($item);
			$item = new $class_name($item);
		}

		return $item;
	}

	function isEqual( mixed $a , mixed $b , bool $strict = false ) :bool {

		return $strict ? ( $a === $b ) : ( $a == $b );
	}

	function get( mixed $id ) :?Model {
		$primary_field = constant($this->class_name.'::PRIMARY_FIELD');

		if( ! $primary_field ){
			throw new \RuntimeException('get method cannot be called if list model has not PRIMARY_FIELD');
		}

		return $this->search($primary_field,$id,true);
	}
	
	function search( string $field , mixed $is , bool $strict = false ) : ?Model {

		foreach ( $this->storage as $item ){
			$value = $item['class']->{$field} ?? null;

			if( $this->isEqual($value,$is,$strict) ){
				return $item['class'];
			}
		}
		
		return null;
	}

	function remove( Model $Item ) :void {
		foreach ( $this->storage as $offset => $item ){
			if( $Item === $item['class'] ){
				$this->offsetUnset($offset);
				return;
			}
		}

		throw new \InvalidArgumentException('Item to delete not exists');
	}

	function filter( string $field , mixed $is , bool $strict = false ) :static {

		$FilteredList = new static($this->class_name);

		foreach ( $this->storage as $item ){
			$value = $item['class']->{$field} ?? null;

			if( $this->isEqual($value,$is,$strict) ){
				$FilteredList->add( $item['class'] );
			}
		}
		
		return $FilteredList;
	}

	function filterIn( string $field , array $in , bool $strict = false ) :static {
		
		$FilteredList = new static($this->class_name);

		foreach ( $this->storage as $item ){
			$value = $item['class']->{$field} ?? null;


			foreach( $in as $is ){
				if( $this->isEqual($value,$is,$strict) ){
					$FilteredList->add( $item['class'] );
					break;
				}
			}
		}
		
		return $FilteredList;
	}
	
	public function getMax( string $field ) :mixed {
		$max = null;
		
		foreach ( $this->storage as $item ){
			$value = $item['class']->{$field} ?? null;
			if( $max === null || $value > $max ){
				$max = $value;
			}
		}
		
		return $max;
	}
	
	public function getMin( string $field ) :mixed {
		$min = null;
		
		foreach ( $this->storage as $item ){
			$value = $item['class']->{$field} ?? null;
			if( $min === null || $value < $min ){
				$min = $value;
			}
		}
		
		return $min;
	}
	
	function getFieldValues( string $field , bool $exclude_null = true ) :array {
		$values = [];
		
		foreach ( $this->storage as $item ){
			$value = $item['class']->{$field} ?? null;
			if( $exclude_null && $value === null ){
				continue;
			}
			$values[] = $value;
		}
		
		return $values;
	}
	
	function generateKeyValueMap( string $field_key , string $field_value , bool $exclude_key_null = true , bool $exclude_value_null = false , bool $create_array_for_duplicated = false ) :array {
		$map = [];
		
		foreach ( $this->storage as $item ){
			$key = $item['class']->{$field_key} ?? null;
			if( $exclude_key_null && $key === null ){
				continue;
			}
			$value = $item['class']->{$field_value} ?? null;
			if( $exclude_value_null && $value === null ){
				continue;
			}
			if( array_key_exists($key , $map) && $create_array_for_duplicated ){
				$map[$key] = (array) $map[$key];
				$map[$key][] = $value;
			} else {
				$map[$key] = $value;
			}
		}
		
		return $map;
	}
	
	/**
	 * 
	 * @param string|callable $arg1 Model key or callback as php usort() function.
	 * Callback must accept two params. Every param is an array with prop class that contain model
	 * @param int $direction 1 for asc (default), -1 for desc
	 * @param bool $null_before if true null > any other value, default false
	 * @param bool $use_attributes if true sort happen based on attributes instead class properties, default false
	 * @return \Intoway\Core\Lists\AbstractList
	 */
	function sort( $arg1 , $direction = 1 , $null_greater = false , $use_attributes = false ) :self {
		if( is_string($arg1)) {
			usort( $this->storage , function($a , $b) use ($arg1,$direction,$null_greater,$use_attributes){
				if( $use_attributes ){
					$el1 = $a['attributes'][$arg1] ?? null;
					$el2 = $b['attributes'][$arg1] ?? null;
				} else {
					$el1 = $a['class']->{$arg1} ?? null;
					$el2 = $b['class']->{$arg1} ?? null;
				}
				
				$Abest = $el1 >= $el2 ? 1 : -1;
				if( $el1 === null && $null_greater ){
					$Abest = 1;
				} elseif( $el2 === null && $null_greater ){
					$Abest = -1;
				}
				
				return $Abest * ( $direction > 0 ? 1 : -1 );
			});
		} elseif( is_callable($arg1) ){
			usort( $this->storage , function($a , $b) use($arg1){
				return $arg1( $a , $b );
			});
		} else {
			throw new \RuntimeException('Argument 1 must be string or callable');
		}
		
		return $this;
	}
	
	function export() :array {
		$export = [];
		foreach( $this->storage as $item ){
			$export[] = $item['class']->export();
		}

		return $export;
	}
	
	// Iterator methods
	
	function rewind() : void {
		$this->position = 0;
	}
	
	function current() : mixed {
		return $this->storage[$this->position]['class'];
	}
	
	function key() : mixed {
		return $this->position;
	}
	
	function next() : void {
		++$this->position;
	}
	
	function valid() : bool {
		return isset($this->storage[$this->position]);
	}

	// Countable methods
	
	function count() : int {
		return count($this->storage);
	}

	// ArrayAccess methods

	function offsetExists(mixed $offset): bool {
		return array_key_exists($offset,$this->storage);
	}
	function offsetGet(mixed $offset): mixed {
		return $this->storage[$offset]['class'] ?? null;
	}
	function offsetSet(mixed $offset, mixed $value): void {
		$this->storage[$offset]['class'] = $this->parse($value);
	}
	function offsetUnset(mixed $offset): void {
		array_splice($this->storage,$offset,1);
		if( $this->position > 0 && $this->position >= $offset ){
			--$this->position;
		}
	}

	function hasAttribute( $key ) {
		return array_key_exists($key,$this->storage[$this->position]['attributes']);
	}

	function getAttribute( $key ) {
		return $this->storage[$this->position]['attributes'][$key] ?? null;
	}

	function setAttribute( $key , $value ) {
		$this->storage[$this->position]['attributes'][$key] = $value;
		return $this;
	}

	function setAttributes( $values ) {
		$this->storage[$this->position]['attributes'] = $values + $this->storage[$this->position]['attributes'];
		return $this;
	}

	function jsonSerialize(): mixed {
		$export = [];
		foreach( $this->storage as $item ){
			$export[] = $item['class']->jsonSerialize();
		}

		return $export;
	}
}

