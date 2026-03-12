<?php
namespace PhpArrayDocument;

/**
 * A PHP array. There are a few ways to access its children:
 *
 * - $array[$key]: Short-hand to read/write the value of an item.
 * - $array->getItem($key): Returns the metadata (ArrayItem),
 *     which includes the key, docblocks, and value.
 * - $array->walkNodes(): Recursively the subtree
 *
 */
class ArrayNode extends BaseNode implements \ArrayAccess, \IteratorAggregate, \Countable {

  /**
   * @var ArrayItemNode[]
   */
  private $items = [];

  public function __construct($items = []) {
    $this->items = $items;
  }

  public static function create(): ArrayNode {
    return new static();
  }

  /**
   * Take a basic data-array. Load it into the document.
   *
   * @param array $data
   *   Simple collection of arrays and scalars. They do not have any comments, deferrals, factories, etc.
   *
   *   Optionally, if $allowNodes is enabled, then you may mix-in specific instances of
   *   `ScalarNode` or `ArrayNode` with additional metadata.
   * @param bool $allowNodes
   *   If TRUE, then any raw instances of {Base,Scalar,Array}Node may be imported directly.
   * @return $this
   */
  public function importData(array $data, bool $allowNodes = TRUE): ArrayNode {
    foreach ($data as $key => $value) {
      if ($allowNodes && $value instanceof BaseNode) {
        $this[$key] = $value;
      }
      elseif (is_array($value)) {
        if (!isset($this[$key])) {
          $this[$key] = ArrayNode::create();
        }
        $this[$key]->importData($value);
      }
      else {
        if (!isset($this[$key])) {
          $this[$key] = ScalarNode::create();
        }
        $this[$key]->setScalar($value);
      }
    }
    return $this;
  }

  public function exportData(): array {
    $result = [];
    foreach ($this->items as $item) {
      /** @var \PhpArrayDocument\ArrayItemNode $item */
      $v = $item->getValue();
      $result[$item->getKey()] = ($v instanceof ArrayNode) ? $v->exportData() : $v->getScalar();
    }
    return $result;
  }

  public function walkNodes(string $type = BaseNode::class) {
    yield from parent::walkNodes($type);
    foreach ($this->items as $arrayItem) {
      yield from $arrayItem->walkNodes($type);
    }
  }

  /**
   * @return \PhpArrayDocument\ArrayItemNode[]
   */
  public function getItems(): array {
    return $this->items;
  }

  public function getItem($key): ?ArrayItemNode {
    foreach ($this->items as $arrayItem) {
      if ($arrayItem->getKey() == $key) {
        return $arrayItem;
      }
    }
    return NULL;
  }

  public function getItemPosition($key) {
    foreach ($this->items as $arrayItem) {
      if ($arrayItem->getKey() == $key) {
        return $arrayItem;
      }
    }
    return NULL;
  }

  public function getIterator(): \Traversable {
    return new \ArrayIterator($this->items);
  }

  public function count(): int {
    return count($this->items);
  }

  public function offsetExists($offset): bool {
    return $this->getItem($offset) !== NULL;
  }

  #[\ReturnTypeWillChange]
  public function offsetGet($offset) {
    $item = $this->getItem($offset);
    return $item ? $item->getValue() : NULL;
  }

  public function offsetSet($offset, $value): void {
    if (!($value instanceof ScalarNode || $value instanceof ArrayNode)) {
      $type = gettype($value);
      if ($type === 'object') {
        $type = get_class($value);
      }
      throw new \RuntimeException(sprintf("Cannot add object (%s) to ArrayValueNode", $type));
    }
    if ($item = $this->getItem($offset)) {
      $item->setValue($value);
    }
    else {
      $this->items[] = new ArrayItemNode($offset, $value);
    }
  }

  public function offsetUnset($offset): void {
    $pos = $this->getItemPosition($offset);
    if ($pos !== NULL) {
      unset($this->items[$pos]);
    }
  }

}
