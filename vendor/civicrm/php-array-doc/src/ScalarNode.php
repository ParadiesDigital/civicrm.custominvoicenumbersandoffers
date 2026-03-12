<?php
namespace PhpArrayDocument;

class ScalarNode extends BaseNode {

  /**
   * @var scalar
   */
  private $scalar;

  /**
   * @param scalar $scalar
   */
  public function __construct($scalar = NULL) {
    $this->scalar = $scalar;
  }

  public static function create($scalar = NULL): ScalarNode {
    return new static($scalar);
  }

  /**
   * @return bool|float|int|string
   */
  public function getScalar() {
    return $this->scalar;
  }

  /**
   * @param bool|float|int|string $scalar
   * @return $this
   */
  public function setScalar($scalar) {
    $this->scalar = $scalar;
    return $this;
  }

}
