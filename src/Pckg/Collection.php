<?php namespace Pckg;

use ArrayAccess;
use Countable;
use Exception;
use JsonSerializable;
use LimitIterator;
use Pckg\Collection\Each;
use Pckg\Collection\Iterator;
use Pckg\Database\Object;
use Pckg\Database\Record;
use Throwable;

/**
 * Class Collection
 *
 * @package Pckg\Database
 */
class Collection extends Iterator implements ArrayAccess, JsonSerializable, Countable, CollectionInterface
{

    /**
     * @var
     */
    protected $total;

    /**
     * @param $name
     *
     * @return Collection|Each
     * @throws Exception
     */
    public function __get($name)
    {
        if ($name == 'each') {
            return $this->each();
        }

        throw new Exception('Calling ' . $name . ' on Collection');
    }

    /**
     * @param      $item
     * @param null $key
     *
     * @return $this
     *
     * Add element to end of array.
     */
    public function push($item, $key = null)
    {
        if ($key || $key === 0) {
            $this->collection[$key] = $item;
        } else {
            $this->collection[] = $item;
        }

        return $this;
    }

    /**
     * @param $items
     *
     * @return $this
     */
    public function pushArray($items)
    {
        foreach ($items as $item) {
            $this->push($item);
        }

        return $this;
    }

    /**
     * Remove element from end of array.
     */
    public function pop()
    {
        if (!$this->collection) {
            return null;
        }

        return array_pop($this->collection);
    }

    /**
     * Add element to beginning of array.
     */
    public function prepend($item)
    {
        if (!$this->collection) {
            return null;
        }

        array_unshift($this->collection, $item);
    }

    /**
     * Remove element from beginning of array.
     */
    public function shift()
    {
        if (!$this->collection) {
            return null;
        }

        return array_shift($this->collection);
    }

    /**
     * @param      $offset
     * @param null $length
     * @param null $preserve_keys
     *
     * @return Collection
     */

    public function slice($offset, $length = null, $preserve_keys = null)
    {
        return new static(array_slice($this->collection, $offset, $length, $preserve_keys));
    }

    /**
     * @return array
     */
    public function keys()
    {
        return array_keys($this->collection);
    }

    /**
     * @return array
     * @deprecated
     * @see keys()
     */
    public function getKeys()
    {
        return array_keys($this->collection);
    }

    /**
     * @return int
     */
    public function total()
    {
        return $this->total
            ? $this->total
            : count($this->collection);
    }

    /**
     * @param $item
     * @param $param
     *
     * @return mixed
     */
    protected function getValueOrCallable($item, $param)
    {
        return is_only_callable($param)
            ? $param($item)
            : (is_object($item)
                ? $item->{$param}
                : $item[$param]);
    }

    /**
     * @param $callable
     *
     * @return float|mixed
     */
    public function sum($callable)
    {
        $sum = 0.0;

        foreach ($this->collection as $item) {
            $partial = $this->getValueOrCallable($item, $callable);
            if ($partial > 0 || $partial < 0) {
                $sum += $partial;
            }
        }

        return $sum;
    }

    /**
     * @param $callable
     *
     * @return float
     */
    public function avg($callable)
    {
        return $this->sum($callable) / count($this->collection);
    }

    /**
     * @param $by
     *
     * @return static
     */
    public function chunk($by)
    {
        $chunks = [];
        $index = 0;
        foreach ($this->collection as $item) {
            if (!array_key_exists($index, $chunks)) {
                $chunks[$index] = [];
            }

            $chunks[$index][] = $index;

            if (count($chunks[$index]) == $by) {
                $index++;
            }
        }

        return new static($chunks);
    }

    /**
     * @return $this
     */
    public function shuffle()
    {
        shuffle($this->collection);

        return $this;
    }

    /**
     * @param $condition
     *
     * @return bool
     */
    public function has($condition)
    {
        foreach ($this->collection as $item) {
            if (is_string($condition)) {
                return in_array($condition, $this->collection);
            } else if (is_only_callable($condition) && $condition($item)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param $total
     *
     * @return $this
     */
    public function setTotal($total)
    {
        $this->total = $total;

        return $this;
    }

    /**
     * @return static
     */
    public function copy()
    {
        return new static($this->collection);
    }

    /**
     * @param CollectionInterface $collection
     */
    public function copyTo(CollectionInterface $collection)
    {
        $this->each(
            function($item) use ($collection) {
                $collection->push($item);
            }
        );
    }

    /**
     * @param callable $callback
     * @param bool     $preserveKeys
     *
     * @return Collection
     */
    public function reduce(callable $callback, $preserveKeys = false)
    {
        $collection = new self();

        foreach ($this->collection as $key => $item) {
            if ($callback($item)) {
                $collection->push($item, $preserveKeys ? $key : null);
            }
        }

        return $collection;
    }

    /**
     * @param $object
     * @param $key
     *
     * @return mixed
     * @throws Exception
     */
    protected function getValue($object, $key, $index = null)
    {
        if (is_only_callable($key)) {
            return $key($object, $index);
        } else if (is_object($object) && method_exists($object, $key)) {
            return $object->{$key}();
        } else if (is_object($object) && isset($object->{$key})) {
            return $object->{$key};
        } else if (is_array($object) && array_key_exists($key, $object)) {
            return $object[$key];
        }

        throw new Exception("Cannot find key $key in " .
                            (is_object($object) ? ' object ' . get_class($object) : 'array'));
    }

    /**
     * @param $key
     *
     * @return mixed
     */
    public function getKey($key, $default = null)
    {
        return array_key_exists($key, $this->collection) ? $this->collection[$key] : $default;
    }

    /**
     * @param $key
     *
     * @return bool
     * @deprecated
     */
    public function keyExists($key)
    {
        return $this->hasKey($key);
    }

    /**
     * @param $key
     *
     * @return bool
     */
    public function hasKey($key)
    {
        return array_key_exists($key, $this->collection);
    }

    /* strategies */
    /**
     * @return Collection
     */
    public function getIdAsKey()
    {
        $return = [];

        foreach ($this->collection AS $row) {
            $return[$row->id] = $row;
        }

        return new static($return);
    }

    /**
     * @return Collection
     */
    public function getList()
    {
        $return = [];

        foreach ($this->collection AS $i => $row) {
            if (!is_array($row)) {
                $row = $row->__toArray();
            }

            foreach (["title", "slug", "name", "email", "key", "id"] AS $key) {
                if (isset($row[$key])) {
                    $return[] = $row[$key];
                    break;
                }
            }
        }

        return new static($return);
    }

    /**
     * @return Collection
     */
    public function getListID()
    {
        $return = [];

        foreach ($this->collection AS $i => $row) {
            if (!is_array($row)) {
                $row = $row->__toArray();
            }

            foreach (["title", "slug", "name", "email", "key", "id"] AS $key) {
                if (isset($row[$key])) {
                    $return[$row['id']] = $row[$key];
                    break;
                }
            }
        }

        return new static($return);
    }

    /**
     * @param $callback
     *
     * @return Collection
     */
    public function getCustomList($callback)
    {
        $return = [];

        foreach ($this->collection AS $i => $row) {
            $realRow = $callback($row);

            if ($realRow) {
                $return[$row->getId()] = $realRow;
            }
        }

        return new static($return);
    }

    /**
     * @param $foreign
     *
     * @return Collection
     */
    public function getTree($foreign, $primary = 'id')
    {
        $tree = new Collection\Tree($this->collection);

        return new static($tree->getHierarchy($foreign, $primary));
    }

    /**
     * @param        $foreign
     * @param        $primary
     * @param string $key
     *
     * @return static
     */
    public function tree($foreign, $primary, $key = 'getChildren')
    {
        $children = [];
        $parents = [];
        $items = [];
        if (is_array($this->first())) {
            foreach ($this->collection as &$item) {
                $parentId = $this->getValue($item, $foreign);
                $primaryId = $this->getValue($item, $primary);
                $items[$primaryId] = &$item;
                if ($parentId) {
                    $children[$parentId][] = &$item;
                } else {
                    $parents[] = &$item;
                }
            }

            foreach ($items as $primaryId => &$item) {
                $item[$key] = &$children[$primaryId] ?? [];
            }
        } else {
            foreach ($this->collection as $item) {
                $parentId = $this->getValue($item, $foreign);
                $items[$this->getValue($item, $primary)] = $item;
                if ($parentId) {
                    $children[$parentId][] = $item;
                } else {
                    $parents[] = $item;
                }
            }

            foreach ($items as $primaryId => $item) {
                $item->{$key} = $children[$primaryId] ?? [];
            }
        }

        return new static($parents);
    }

    /**
     * @param $sortBy
     *
     * @return Collection
     */
    public function sortBy($sortBy)
    {
        $arrSort = [];

        foreach ($this->groupAndSort($sortBy) AS $group) {
            foreach ($group AS $row) {
                $arrSort[] = $row;
            }
        }

        return new static($arrSort);
    }

    /**
     * @param $sortBy
     *
     * @return array
     */
    protected function groupAndSort($sortBy)
    {
        $arr = [];

        foreach ($this->collection AS $row) {
            $arr[is_only_callable($sortBy) ? $sortBy($row) : ($row->{$sortBy}())][] = $row;
        }

        ksort($arr);

        return $arr;
    }

    /**
     * @return null
     */
    public function random()
    {
        if (!$this->collection) {
            return null;
        }

        return $this->collection[array_rand($this->collection)];
    }

    /**
     * @param $groupBy
     *
     * @return Collection
     */
    public function groupBy($groupBy)
    {
        $arrGroupped = [];

        foreach ($this->collection AS $row) {
            if (is_only_callable($groupBy)) {
                $arrGroupped[$groupBy($row)][] = $row;
            } else {
                $arrGroupped[$this->getValue($row, $groupBy)][] = $row;
            }
        }

        return new static($arrGroupped);
    }

    /**
     * @param        $filterBy
     * @param        $value
     * @param string $comparator
     *
     * @return Collection
     */
    public function filter($filterBy, $value = true, $comparator = '==')
    {
        $arrFiltered = [];

        foreach ($this->collection AS $i => $row) {
            if (is_only_callable($filterBy)) {
                if ($filterBy($row, $i)) {
                    $arrFiltered[] = $row;
                }
            } else {
                $objectValue = $this->getValue($row, $filterBy);

                if ((($comparator == '==')
                     && ((is_array($value) && in_array($objectValue, $value))
                         || ($objectValue == $value)
                     )
                     || (($comparator == '===')
                         && ($objectValue === $value)
                     )
                     || (($comparator == '<=')
                         && ($objectValue <= $value)
                     )
                     || (($comparator == '>=')
                         && ($objectValue >= $value)
                     )
                     || (($comparator == '!=')
                         && ($objectValue != $value)
                     )
                     || (($comparator == '!==')
                         && ($objectValue !== $value)
                     )
                )
                ) {
                    $arrFiltered[] = $row;
                }
            }
        }

        return new static($arrFiltered);
    }

    /**
     * @param     $limitCount
     * @param int $limitOffset
     *
     * @return Collection
     */
    public function limit($limitCount, $limitOffset = 0)
    {
        $arrLimited = [];

        foreach (new LimitIterator($this, $limitOffset, $limitCount) AS $row) {
            $arrLimited[] = $row;
        }

        return new static($arrLimited);
    }

    /**
     * @param $key
     *
     * @return static
     */
    public function keyBy($key)
    {
        $collection = new static();
        foreach ($this->collection as $item) {
            $collection->push(
                $item,
                is_only_callable($key)
                    ? $key($item)
                    : (
                is_object($item)
                    ? $item->{$key}
                    : $item[$key]
                )
            );
        }

        return $collection;
    }

    /**
     * @return $this
     */
    public function removeEmpty()
    {
        foreach ($this->collection as $key => $item) {
            if (!$item) {
                unset($this->collection[$key]);
            }
        }

        return $this;
    }

    /**
     * @return null|mixed|Record
     */
    public function first(callable $filter = null)
    {
        if (!$this->collection) {
            return null;
        }

        if (!$filter) {
            return $this->collection[array_keys($this->collection)[0]];
        }

        foreach ($this->collection as $item) {
            if ($filter($item)) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @return null
     */
    public function last()
    {
        return $this->collection
            ? $this->collection[array_reverse(array_keys($this->collection))[0]]
            : null;
    }

    /**
     * @return array
     */
    public function all()
    {
        return $this->collection;
    }

    /**
     * @param callable|null $callback
     *
     * @return $this|Each
     */
    public function each(callable $callback = null)
    {
        if (!$callback) {
            return new Each($this);
        }

        foreach ($this->collection as $i => $item) {
            $callback($item, $i);
        }

        return $this;
    }

    /**
     * @param      $callback
     * @param bool $preserveKey
     *
     * @return static
     *
     * @deprecated
     * @see $this->map($callback)
     */
    public function eachNew($callback, $preserveKey = true)
    {
        $collection = new static();
        foreach ($this->collection as $i => $item) {
            $collection->push($callback($item, $i), $preserveKey ? $i : null);
        }

        return $collection;
    }

    /**
     * @param $callback
     *
     * @return static
     */
    public function eachManual($callback)
    {
        $collection = new static();
        foreach ($this->collection as $i => $item) {
            $callback($item, $i, $collection);
        }

        return $collection;
    }

    /**
     * @param $key
     *
     * @return static
     */
    public function flat($key)
    {
        $collection = new static();
        $this->each(
            function($item) use ($collection, $key) {
                $item->{$key}->each(
                    function($item) use ($collection) {
                        $collection->push($item);
                    }
                );
            }
        );

        return $collection;
    }

    /**
     * @return Collection
     */
    public function trim()
    {
        return $this->map(
            function($item) {
                return trim($item);
            }
        );
    }

    /**
     * @param $item
     * @param $mapper
     *
     * @return array
     */
    private function privateMap($item, $mapper)
    {
        $data = [];
        foreach ($mapper as $f => $k) {
            /**
             * Map partial relation
             */
            if (is_array($k)) {
                $data[$f] = $this->privateMap($item->{$f}, $k);
                continue;
            }
            /**
             * Map full relation.
             */
            if ($k == '*') {
                $data[$f] = $item->toArray();
                continue;
            }
            /**
             * Map field.
             */
            $data[$k] = $this->getValue($item, $k);
        }

        return $data;
    }

    /**
     * @param $field
     *
     * @return static
     */
    public function map($field)
    {
        $collection = new static();

        if (is_array($field)) {
            foreach ($this->collection as $i => $item) {
                $collection->push($this->privateMap($item, $field), $i);
            }
        } else {
            foreach ($this->collection as $i => $item) {
                $newItem = !is_string($field) && is_only_callable($field)
                    ? $field($item, $i)
                    : (is_object($item) ? $item->{$field} : $item[$field]);
                $collection->push($newItem, $i);
            }
        }

        return $collection;
    }

    /**
     * @param $keys
     *
     * @return static
     */
    public function only($keys)
    {
        $collection = new static();

        $this->each(function($item, $key) use ($keys, $collection) {
            $collection->push(only($item, $keys), $key);
        });

        return $collection;
    }

    /**
     * @param $rules
     *
     * @return array
     */
    public function transform($rules)
    {
        return $this->map($rules)->all();
    }

    /**
     * @return static
     */
    public function unique()
    {
        return new static(array_unique($this->collection));
    }

    /**
     * @param null $field
     *
     * @return mixed|null|Record
     */
    public function min($field = null)
    {
        if (!$this->collection) {
            return null;
        }

        $collection = $this;
        if ($field) {
            $collection = $this->map($field);
        }

        if ($collection->count() == 1) {
            return $collection->first();
        }

        return min(...$collection->all());
    }

    /**
     * @param null $field
     *
     * @return mixed|null|Record
     */
    public function max($field = null)
    {
        if (!$this->collection) {
            return null;
        }

        $collection = $this;
        if ($field) {
            $collection = $this->map($field);
        }

        if ($collection->count() == 1) {
            return $collection->first();
        }

        return max(...$collection->all());
    }

    /**
     * @param string $separator
     * @param null   $lastSeparator
     *
     * @return null|string
     */
    public function implode($separator = '', $lastSeparator = null)
    {
        if (!$this->collection) {
            return null;
        } elseif (count($this->collection) == 1) {
            return (string)$this->first();
        } elseif ($lastSeparator) {
            return implode(
                       $separator,
                       $this->slice(0, $this->count() - 1)->all()
                   ) . $lastSeparator . (string)$this->last();
        }

        return implode($separator, $this->collection);
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value)
    {
        if (is_null($offset)) {
            $this->collection[] = $value;
        } else {
            $this->collection[$offset] = $value;
        }
    }

    /**
     * @param mixed $offset
     *
     * @return bool
     */
    public function offsetExists($offset)
    {
        return isset($this->collection[$offset]);
    }

    /**
     * @param mixed $offset
     */
    public function offsetUnset($offset)
    {
        unset($this->collection[$offset]);
    }

    /**
     * @param mixed $offset
     *
     * @return mixed
     * @throws Exception
     */
    public function offsetGet($offset)
    {
        if (!array_key_exists($offset, $this->collection)) {
            throw new Exception(
                'Key ' . $offset . ' doesn\'t exist in collection ' . substr(
                    implode(',', array_keys($this->collection)),
                    0,
                    20
                )
            );
        }

        return $this->collection[$offset];
    }

    /**
     * @param null $values
     * @param int  $depth
     *
     * @return array
     */
    public function toArray($values = null, $depth = 6)
    {
        return $this->__toArray($values, $depth);
    }

    /**
     * @param int $depth
     *
     * @return string
     */
    public function toJSON($depth = 6)
    {
        try {
            $json = json_encode((array)$this->__toArray(null, $depth), JSON_OBJECT_AS_ARRAY | JSON_NUMERIC_CHECK);
        } catch (Throwable $e) {
        }

        return $json ?? 'null';
    }

    /**
     * @return array
     */
    public function __toArray($values = null, $depth = 6)
    {
        $return = [];

        if (!$depth) {
            return;
        }

        if (!$values) {
            $values = $this->collection;
        }

        if (is_string($values) || is_numeric($values)) {
            return $values;
        } else if ($values instanceof Record) {
            $return = $values->__toArray(null, $depth - 1);
        } else if ($values instanceof Object) {
            $return = $this->__toArray($values->data(), $depth - 1);
        } else if (is_array($values) || object_implements($values, CollectionInterface::class)) {
            foreach ($values as $key => $value) {
                if (is_object($value)) {
                    $return[$key] = $this->__toArray($value, $depth - 1);
                } else if (is_array($value)) {
                    $return[$key] = $value
                        ? $this->__toArray($value, $depth - 1)
                        : $value;
                } else {
                    $return[$key] = $value;
                }
            }
        }

        return $return;
    }

    /**
     * @return array|Exception
     */
    public function jsonSerialize()
    {
        try {
            $serialize = $this->__toArray();
        } catch (Throwable $e) {
            return exception($e);
        }

        if (!$serialize) {
            return [];
        }

        return $serialize;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return json_encode($this->jsonSerialize());
    }

    /**
     * @param $count
     *
     * @return $this|Collection
     */
    public function multiply($count)
    {
        if ($count < 0) {
            return $this;
        }

        $items = [];

        for ($i = 0; $i < $count; $i++) {
            foreach ($this->collection as $item) {
                $items[] = $item;
            }
        }

        return new Collection($items);
    }

}