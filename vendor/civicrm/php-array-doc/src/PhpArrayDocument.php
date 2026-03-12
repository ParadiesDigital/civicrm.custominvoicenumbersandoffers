<?php
namespace PhpArrayDocument;

class PhpArrayDocument {

  /**
   * @var array
   *   Ex: ['ClassAlias' => 'Full\Class\Name']
   */
  private $use = [];

  use CommentableTrait;

  /**
   * @var \PhpArrayDocument\ArrayNode|\PhpArrayDocument\ScalarNode|null
   */
  private $root = NULL;

  /**
   * @return \PhpArrayDocument\ArrayNode|\PhpArrayDocument\ScalarNode|null
   */
  public function getRoot() {
    return $this->root;
  }

  /**
   * @param \PhpArrayDocument\ArrayNode|\PhpArrayDocument\ScalarNode|null $root
   * @return PhpArrayDocument
   */
  public function setRoot($root) {
    $this->root = $root;
    return $this;
  }

  /**
   * @return $this
   */
  public static function create() {
    $result = new static();
    $result->root = ArrayNode::create();
    return $result;
  }

  /**
   * @param string $class
   *   Ex: 'Full\Class\Name'
   * @param string|null $alias
   *   Ex: 'ClassAlias'
   * @return $this
   */
  public function addUse(string $class, ?string $alias = NULL) {
    if ($alias === NULL) {
      $parts = explode('\\', $class);
      $alias = array_pop($parts);
    }
    $this->use[$alias] = $class;
    return $this;
  }

  /**
   * @param string $class
   *   Ex: 'Full\Class\Name'
   * @return $this
   */
  public function removeUse(string $class) {
    foreach (array_keys($this->use) as $alias) {
      if ($this->use[$alias] === $class) {
        unset($this->use[$alias]);
      }
    }
    return $this;
  }

  /**
   * @return array
   *   Ex: ['ClassAlias' => 'Full\Class\Name']
   */
  public function getUses(): array {
    return $this->use;
  }

  /**
   * Find expressions like "E::ts()" and turn them into "CRM_Foo_ExtensionInfo::ts()".
   *
   * @return $this
   */
  public function dereferenceClassAliases() {
    foreach ($this->root->walkNodes(ScalarNode::class) as $node) {
      if (!empty($node->getFactory())) {
        $parts = explode('::', $node->getFactory(), 2);
        if (count($parts) === 2 && isset($this->use[$parts[0]])) {
          $node->factory = $this->use[$parts[0]] . '::' . $parts[1];
        }
      }
    }
    return $this;
  }

  /**
   * Find expressions like "CRM_Foo_ExtensionInfo::ts()" and turn them into "E::ts()" .
   *
   * @return $this
   */
  public function useClassAliases() {
    foreach ($this->root->walkNodes(ScalarNode::class) as $node) {
      if (!empty($node->getFactory())) {
        $parts = explode('::', $node->getFactory(), 2);
        if (count($parts) === 2) {
          $alias = array_search($parts[0], $this->use);
          if ($alias) {
            $node->factory = $alias . '::' . $parts[1];
          }
        }
      }
    }
    return $this;
  }

}
