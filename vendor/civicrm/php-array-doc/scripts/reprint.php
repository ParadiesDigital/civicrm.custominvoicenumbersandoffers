#!/usr/bin/env php
<?php
namespace PhpArrayDocument;

require_once dirname(__DIR__) . '/vendor/autoload.php';

$files = $argv;
array_shift($files);
$files = empty($files) ? ['php://stdin'] : $files;
foreach ($files as $file) {
  echo "=== Parse $file ===\n";
  $parser = new Parser();
  $document = $parser->parse(file_get_contents($file));
  $printer = new Printer();
  echo $printer->print($document);
  echo "\n";
}
