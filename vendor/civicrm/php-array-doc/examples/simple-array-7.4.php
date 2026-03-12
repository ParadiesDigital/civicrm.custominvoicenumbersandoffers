<?php

return [
  'name' => 'hello',
  'label' => 'Hello World!',
  'active' => TRUE,
  'html' => [
    'bold' => TRUE,
  ],
  'details' => fn() => [
    'zero' => 0,
    'zero-ish' => '0',
    'one' => 1,
    'one-ish' => '1',
    'null' => NULL,
    'null-ish' => 'NULL',
    'null-ishish' => 'null',
    'true' => TRUE,
    'true-ish' => 'TRUE',
    'true-ishish' => 'true',
    'false' => FALSE,
    'false-ish' => 'FALSE',
    'false-ishish' => 'false',
    'float' => 12.3,
    'float-ish' => '45.6',
    'int' => 123,
    'int-ish' => '123',
    'fun\\ny \'b"u`siness' => 'a\\b\'c"d`e',
    'array-empty' => [],
    'array-short-seq' => [3, 2, 1],
    'array-shorter-seq' => [3],
    'array-long-seq' => [
      '123456789 123456789 123456789 123456789 123456789 123456789 ',
    ],
    'array-kv' => [
      'k' => 'v',
    ],
  ],
];
