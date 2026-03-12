<?php
namespace PhpArrayDocument;

abstract class BaseNode {

  use CommentableTrait;

  /**
   * @var string|null
   *   Ex: 'ts' or 'E::ts' or 'Some\Class\Name::ts'
   */
  private $factory = NULL;

  /**
   * Does this data use deferred construction (`fn() => [...data..]`)?
   *
   * @var bool
   */
  private $deferred = FALSE;

  /**
   * @template T of BaseNode
   * @param class-string<T> $type
   * @return Generator<T>
   */
  public function walkNodes(string $type = BaseNode::class) {
    if ($type === NULL || $this instanceof $type) {
      yield $this;
    }
  }

  /**
   * @return string|null
   */
  public function getFactory(): ?string {
    return $this->factory;
  }

  /**
   * @param string|null $factory
   * @return $this
   */
  public function setFactory(?string $factory) {
    $this->factory = $factory;
    return $this;
  }

  /**
   * @return bool
   */
  public function isDeferred(): bool {
    return $this->deferred;
  }

  /**
   * @param bool $deferred
   * @return $this
   */
  public function setDeferred(bool $deferred) {
    $this->deferred = $deferred;
    return $this;
  }

}
