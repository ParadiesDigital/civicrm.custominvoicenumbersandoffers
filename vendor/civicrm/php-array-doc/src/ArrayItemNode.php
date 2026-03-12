<?php
namespace PhpArrayDocument;

class ArrayItemNode extends BaseNode {

  private $key;

  /**
   * @var \PhpArrayDocument\ScalarNode|\PhpArrayDocument\ArrayNode|null
   */
  private $value;

  public function __construct($key, BaseNode $value) {
    $this->key = $key;
    $this->value = $value;
  }

  public function walkNodes(string $type = BaseNode::class) {
    if ($type === NULL || $this instanceof $type) {
      yield $this;
    }
    if ($this->value) {
      yield from $this->value->walkNodes($type);
    }
  }

  /**
   * @return mixed
   */
  public function getKey() {
    return $this->key;
  }

  /**
   * @param mixed $key
   * @return $this
   */
  public function setKey($key) {
    $this->key = $key;
    return $this;
  }

  /**
   * @return \PhpArrayDocument\ArrayNode|\PhpArrayDocument\ScalarNode|null
   */
  public function getValue() {
    return $this->value;
  }

  /**
   * @param \PhpArrayDocument\ArrayNode|\PhpArrayDocument\ScalarNode|null $value
   * @return $this
   */
  public function setValue($value) {
    $this->value = $value;
    return $this;
  }

}
