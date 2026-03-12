<?php

namespace PhpArrayDocument;

class Printer {

  private $useFn;

  public function __construct() {
    $this->useFn = version_compare(PHP_VERSION, '7.4.0', '<');
    // $this->useFn = TRUE;
  }

  public function print(PhpArrayDocument $document): string {
    $buf[] = '<' . "?php";
    foreach ($document->getUses() as $alias => $class) {
      $defaultAlias = array_reverse(explode("\\", $class))[0];
      if ($alias === $defaultAlias) {
        $buf[] = sprintf('use %s;', $class);
      }
      else {
        $buf[] = sprintf('use %s as %s;', $class, $alias);
      }
    }
    $buf[] = '';
    if ($document->getOuterComments()) {
      $buf[] = rtrim(implode("", $document->getOuterComments()), "\n");
    }
    $buf[] = 'return ' . $this->printNode($document->getRoot()) . ";\n";
    return implode("\n", $buf);
  }

  private function printNode(BaseNode $node, int $indent = 0): string {
    $prefix = $suffix = '';
    if ($node->getFactory()) {
      $prefix .= $node->getFactory() . '(';
      $suffix = "$suffix)";
    }
    if ($node->isDeferred()) {
      if ($this->useFn) {
        $prefix .= "function() {\n";
        $prefix .= str_repeat(' ', $indent * 2) . "  return ";
        $suffix = ";\n" . str_repeat(' ', $indent * 2) . "}" . $suffix;
        $indent++;
      }
      else {
        $prefix .= 'fn() => ';
      }
    }

    if ($node instanceof ScalarNode) {
      $value = var_export($node->getScalar(), TRUE);
      if (in_array($value, ['false', 'true', 'null'])) {
        $value = strtoupper($value);
      }
      return $prefix . $value . $suffix;
    }
    elseif ($node instanceof ArrayNode) {
      if (count($node->getItems()) <= 1) {
        echo '';
      }

      $keys = array_map(
        function ($i) {
          return $i->getKey();
        },
        $node->getItems()
      );

      $isSeq = count($keys) === 0 || $keys === range(0, count($node->getItems()) - 1);
      $isShort = array_reduce($node->getItems(), function ($carry, $item) {
        return $carry && ($item->getValue() instanceof ScalarNode) && empty($item->getValue()->getOuterComments()) && strlen($item->getValue()->getScalar()) < 15;
      }, count($node->getItems()) < 5);

      $parts = [];
      $parentIndent = str_repeat(' ', $indent * 2);
      $childIndent = str_repeat(' ', (1 + $indent) * 2);
      foreach ($node->getItems() as $item) {
        $part = '';
        if ($item->getValue()->getOuterComments()) {
          $part .= $item->getValue()->renderComments($childIndent);
        }
        if (!($isSeq && $isShort)) {
          $part .= $childIndent;
        }
        if (!$isSeq) {
          $part .= (var_export($item->getKey(), TRUE) . ' => ');
        }
        $part .= $this->printNode($item->getValue(), 1 + $indent);
        $parts[] = $part;
      }

      if ($isSeq && $isShort) {
        return $prefix . '[' . implode(', ', $parts) . ']' . $suffix;
      }
      else {
        return $prefix . sprintf("[\n%s,\n%s]", implode(",\n", $parts), $parentIndent) . $suffix;
      }
    }
    else {
      throw new \Exception("Unrecognized node type: " . get_class($node));
    }
  }

}
