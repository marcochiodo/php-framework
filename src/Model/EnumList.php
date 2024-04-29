<?php

namespace mrblue\framework\Model;

class EnumList implements \Iterator, \Countable, \ArrayAccess, \JsonSerializable {

    protected int $position = 0;
    protected array $storage = [];
    protected string $class_name;
    protected bool $throw_if_non_existent;

    function __construct(string $class_name, array $items = [], bool $throw_if_non_existent = true) {
        if (!is_a($class_name, \BackedEnum::class, true)) {
            throw new \InvalidArgumentException('class_name is not a BackedEnum');
        }
        $this->class_name = $class_name;
        $this->throw_if_non_existent = $throw_if_non_existent;
        $this->addItems($items);
    }

    function addItems(array $items): self {
        foreach ($items as $item) {
            $this->add($item);
        }

        return $this;
    }

    function add(int|string|\BackedEnum $item, $options = []): self {

        $item = $this->parse($item);

        if (!$item) {
            return $this;
        }

        $add = [
            'class' =>  $item,
            'attributes' => []
        ];

        if (!empty($options['attributes']) && is_array($options['attributes'])) {
            $add['attributes'] = $options['attributes'];
        }

        $this->storage[] = $add;

        return $this;
    }

    function parse(int|string|\BackedEnum $item): ?\BackedEnum {

        $class_name = $this->class_name;

        if (!$item instanceof $class_name) {

            $item = $this->throw_if_non_existent ? $class_name::from($item) : $class_name::tryFrom($item);
        }

        return $item;
    }

    function has(\BackedEnum $Item): bool {

        foreach ($this->storage as $item) {

            if ($item['class'] === $Item) {
                return true;
            }
        }

        return false;
    }

    function remove(\BackedEnum $Item): void {
        foreach ($this->storage as $offset => $item) {
            if ($Item === $item['class']) {
                $this->offsetUnset($offset);
                return;
            }
        }

        throw new \InvalidArgumentException('Item to delete not exists');
    }

    function export(): array {
        $export = [];
        foreach ($this->storage as $item) {
            $export[] = $item['class']->value;
        }

        return $export;
    }

    // Iterator methods

    function rewind(): void {
        $this->position = 0;
    }

    function current(): mixed {
        return $this->storage[$this->position]['class'];
    }

    function key(): mixed {
        return $this->position;
    }

    function next(): void {
        ++$this->position;
    }

    function valid(): bool {
        return isset($this->storage[$this->position]);
    }

    // Countable methods

    function count(): int {
        return count($this->storage);
    }

    // ArrayAccess methods

    function offsetExists(mixed $offset): bool {
        return array_key_exists($offset, $this->storage);
    }
    function offsetGet(mixed $offset): mixed {
        return $this->storage[$offset]['class'] ?? null;
    }
    function offsetSet(mixed $offset, mixed $value): void {
        $this->storage[$offset]['class'] = $this->parse($value);
    }
    function offsetUnset(mixed $offset): void {
        array_splice($this->storage, $offset, 1);
        if ($this->position > 0 && $this->position >= $offset) {
            --$this->position;
        }
    }

    function hasAttribute($key) {
        return array_key_exists($key, $this->storage[$this->position]['attributes']);
    }

    function getAttribute($key) {
        return $this->storage[$this->position]['attributes'][$key] ?? null;
    }

    function setAttribute($key, $value) {
        $this->storage[$this->position]['attributes'][$key] = $value;
        return $this;
    }

    function setAttributes($values) {
        $this->storage[$this->position]['attributes'] = $values + $this->storage[$this->position]['attributes'];
        return $this;
    }

    function jsonSerialize(): mixed {

        return $this->export();
    }
}
