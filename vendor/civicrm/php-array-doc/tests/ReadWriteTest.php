<?php

use PhpArrayDocument\Parser;
use PhpArrayDocument\Printer;

class ReadWriteTest extends \PHPUnit\Framework\TestCase {

  public function getExamples() {
    $es = [];

    $phpVer = function ($op, $tgt): bool {
      return version_compare(PHP_VERSION, $tgt, $op);
    };

    if ($phpVer('>=', '7.3.alpha') && $phpVer('<', '7.4')) {
      $es[] = ['setting-definition-7.3.php'];
      $es[] = ['simple-array-7.3.php'];
    }
    if ($phpVer('>=', '7.4.alpha')) {
      $es[] = ['setting-definition-7.4.php'];
      $es[] = ['simple-array-7.4.php'];
    }

    return $es;
  }

  /**
   * Read an example file... then print it back out... and see if it matches.
   *
   * @param string $example
   * @dataProvider getExamples
   */
  public function testReadAndWrite(string $example): void {
    $file = dirname(__DIR__) . '/examples/' . $example;

    $input = file_get_contents($file);

    $parser = new Parser();
    $document = $parser->parse($input);
    $printer = new Printer();
    $output = $printer->print($document);

    $this->assertEquals($input, $output, "Parsed input and generated output should match");
  }

  public function testPreferSingleQuotes() {
    $input = '<' . '?php return ["ab cd"];';
    $expect = '<' . "?php\n\nreturn ['ab cd'];\n";
    $doc = (new Parser())->parse($input);
    $output = (new Printer())->print($doc);
    $this->assertEquals($expect, $output);
  }

  public function getExampleData(): array {
    $es = [];
    $es[] = [[]];
    $es[] = [['a' => 'b']];
    $es[] = [['a', 'b', 'c']];
    $es[] = [
      [
        'a1' => 'a one',
        'a2' => ['b1' => []],
        'a3' => ['b2' => 'b two'],
        'a4' => ['b3' => ['c1' => 'c one', 'c2' => 'c two']],
      ],
    ];
    return $es;
  }

  /**
   * @param array $exampleData
   * @dataProvider getExampleData
   */
  public function testBasicDataReadAndWrite(array $exampleData): void {
    $doc = \PhpArrayDocument\PhpArrayDocument::create();
    $doc->getRoot()->importData($exampleData);
    $exported = $doc->getRoot()->exportData();
    $this->assertEquals($exampleData, $exported);
  }

}
