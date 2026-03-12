<?php

namespace PhpArrayDocument;

class WalkNodesTest extends \PHPUnit\Framework\TestCase {

  public function testWalkUnfiltered() {
    $doc = \PhpArrayDocument\PhpArrayDocument::create();
    $doc->getRoot()->importData([
      'a1' => 'a one',
      'a2' => ['b1' => []],
      'a3' => ['b2' => 'b two'],
      'a4' => ['b3' => ['c1' => 'c one', 'c2' => 'c two']],
    ]);

    $count = 0;
    foreach ($doc->getRoot()->walkNodes() as $node) {
      $count++;
    }
    $this->assertEquals(1 + 2 + 4 + 4 + 8, $count);
  }

  public function testWalkArrayItems() {
    $doc = \PhpArrayDocument\PhpArrayDocument::create();
    $doc->getRoot()->importData([
      'a1' => 'a one',
      'a2' => ['b1' => []],
      'a3' => ['b2' => 'b two'],
      'a4' => ['b3' => ['c1' => 'c one', 'c2' => 'c two']],
    ]);

    $log = [];
    foreach ($doc->getRoot()->walkNodes(ArrayItemNode::class) as $node) {
      $log[] = $node->getKey();
    }
    $this->assertEquals(['a1', 'a2', 'b1', 'a3', 'b2', 'a4', 'b3', 'c1', 'c2'], $log);
  }


}
