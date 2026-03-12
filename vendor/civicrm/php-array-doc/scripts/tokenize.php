#!/usr/bin/env php
<?php

namespace PhpArrayDocument;

require_once dirname(__DIR__) . '/vendor/autoload.php';

$files = $argv;
array_shift($files);
$files = empty($files) ? ['php://stdin'] : $files;
foreach ($files as $file) {
  echo "=== Tokenize $file ===\n";
  $tokens = Tokenizer::getTokens(file_get_contents($file));
  foreach ($tokens as $token) {
    if (is_array($token)) {
      $token[0] = Tokenizer::getName($token[0]);
    }
    printf("%s\n", json_encode($token));
  }
}
