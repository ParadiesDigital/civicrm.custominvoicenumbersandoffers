<?php
namespace PhpArrayDocument;

class Parser {

  private $tokens;

  private $pos = 0;

  private $currentToken;

  /**
   * Delete me.
   * When using xdebug, it's handy to see token with symbolic ID (instead of version-dependent #s).
   * @var string
   */
  private $currentTokenId;

  public function parse($code) {
    $tokens = Tokenizer::getTokens($code);
    $this->tokens = is_array($tokens) ? $tokens : iterator_to_array($tokens);
    $this->pos = 0;
    $this->nextToken();
    return $this->parseDocument();
  }

  private function parseDocument() {
    $document = new PhpArrayDocument();

    $this->expect(T_OPEN_TAG)->skipWhitespace();

    while ($this->currentToken[0] == T_USE) {
      foreach ($this->parseUse() as $alias => $class) {
        $document->addUse($class, $alias);
        $this->skipWhitespace();
      }
    }

    $document->setOuterComments($this->parseComments());

    $this->expect(T_RETURN)->skipWhitespace();

    $document->setRoot($this->parseValue());
    $this->skipWhitespace();

    $this->expect(';')->skipWhitespace();

    return $document;
  }

  private function parseUse() {
    $this->expect(T_USE)->skipWhitespace();

    $className = $this->parseClassName();
    $this->skipWhitespace();

    if ($this->currentToken[0] == T_AS) {
      $this->nextToken()->skipWhitespace();
      if ($this->isToken(T_STRING)) {
        $alias = $this->currentToken[1];
        $this->nextToken()->skipWhitespace();
      }
      else {
        $this->unexpectedToken();
      }
    }
    else {
      $parts = explode('\\', $className);
      $alias = array_pop($parts);
    }

    $this->expect(';')->skipWhitespace();

    return [$alias => $className];
  }

  private function parseValue() {
    if ($this->isScalar($this->currentToken)) {
      return new ScalarNode($this->parseScalar());
    }
    elseif ($this->isArray($this->currentToken)) {
      return new ArrayNode($this->parseArrayItems());
    }
    elseif ($this->isToken(T_FN)) {
      $this->expectSequence([T_FN, "(", ")", T_DOUBLE_ARROW]);
      $result = $this->parseValue();
      $result->setDeferred(TRUE);
      return $result;
    }
    elseif ($this->isToken(T_FUNCTION)) {
      $this->expectSequence([T_FUNCTION, "(", ")", "{", T_RETURN]);
      $result = $this->parseValue();
      $result->setDeferred(TRUE);
      $this->expectSequence([';', '}']);
      return $result;
    }
    elseif ($this->isToken(T_STRING)) {
      $factory = $this->parseFactory();
      $this->expect('(')->skipWhitespace();
      $result = $this->parseValue();
      $this->expect(')')->skipWhitespace();
      if ($result->getFactory() !== NULL) {
        throw new ParseException('Cannot use multiple factories: ' . json_encode([$result->getFactory(), $factory]));
      }
      $result->setFactory($factory);
      return $result;
    }

    $this->unexpectedToken();
  }

  private function parseArrayItems() {
    $result = [];
    $num = 0;

    if ($this->isToken(T_ARRAY)) {
      $this->nextToken()->skipWhitespace();
      $openClose = ['(', ')'];
    }
    else {
      $openClose = ['[', ']'];
    }

    $this->expect($openClose[0]);
    while (!$this->isToken($openClose[1])) {
      $arrayItem = $this->parseArrayItem();
      if ($arrayItem->getKey() === NULL) {
        $arrayItem->setKey($num++);
      }
      $result[] = $arrayItem;
      $this->skipWhitespace();

      if ($this->isToken(',')) {
        $this->nextToken()->skipWhitespace();
      }
      elseif (!$this->isToken($openClose[1])) {
        $this->unexpectedToken();
      }
    }
    $this->expect($openClose[1]);

    return $result;
  }

  private function parseArrayItem() {
    $this->skipWhitespace();

    $comments = $this->parseComments();

    if ($this->isScalar($this->currentToken)) {
      $first = $this->parseScalar();
      $this->skipWhitespace();
      if ($this->isToken(T_DOUBLE_ARROW)) {
        $this->nextToken()->skipWhitespace();
        $key = $first;
        $value = $this->parseValue();
      }
      else {
        $key = NULL;
        $value = new ScalarNode($first);
      }
    }
    else {
      $key = NULL;
      $value = $this->parseValue();
    }
    $this->skipWhitespace();

    if (!empty($comments)) {
      $value->setOuterComments($comments);
    }

    $item = new ArrayItemNode($key, $value);
    return $item;
  }

  private function parseFactory() {
    $symbol = '';
    while ($this->isToken([T_STRING, T_NS_SEPARATOR, T_DOUBLE_COLON])) {
      $symbol .= $this->currentToken[1];
      $this->nextToken()->skipWhitespace();
    }
    return $symbol;
  }

  private function parseScalar() {
    if ($this->isToken(T_LNUMBER)) {
      $result = (int) $this->currentToken[1];
      $this->nextToken()->skipWhitespace();
    }
    elseif ($this->isToken(T_DNUMBER)) {
      $result = (double) $this->currentToken[1];
      $this->nextToken()->skipWhitespace();
    }
    elseif ($this->isToken(T_CONSTANT_ENCAPSED_STRING)) {
      // Ugh. Proper quoting rules would be a pain to write.
      // On the upside: by definition, T_CONSTANT_ENCAPSED_STRING doesn't have any executable $'s or similar.
      // $result = stripcslashes(substr($this->currentToken[1], 1, -1));
      $result = eval('return ' . $this->currentToken[1] . ';');
      $this->nextToken()->skipWhitespace();
    }
    elseif ($this->isToken(T_STRING)) {
      $upperValue = strtoupper($this->currentToken[1]);
      $constants = ['FALSE' => FALSE, 'TRUE' => TRUE];
      if ($upperValue === 'NULL') {
        $result = NULL;
        $this->nextToken()->skipWhitespace();
      }
      elseif (isset($constants[$upperValue])) {
        $result = $constants[$upperValue];
        $this->nextToken()->skipWhitespace();
      }
      else {
        $this->unexpectedToken();
      }
    }
    else {
      $this->unexpectedToken();
    }
    return $result;
  }

  private function parseClassName() {
    $className = '';

    $nameTypes = version_compare(PHP_VERSION, '8.0', '>=')
      ? [T_NS_SEPARATOR, T_STRING, T_NAME_QUALIFIED]
      : [T_NS_SEPARATOR, T_STRING];

    while (in_array($this->currentToken[0], $nameTypes)) {
      $className .= $this->currentToken[1];
      $this->nextToken();
    }

    return $className;
  }

  private function isScalar($token) {
    if (in_array($token[0], [T_CONSTANT_ENCAPSED_STRING, T_LNUMBER, T_DNUMBER])) {
      return TRUE;
    }
    if ($token[0] == T_STRING) {
      return in_array(strtolower($token[1]), ['true', 'false', 'null']);
    }
    return FALSE;
  }

  private function isArray($token) {
    return $token === '[' || ($token[0] ?? NULL) == T_ARRAY;
  }

  private function isToken($charOrTypeOptions, $token = NULL): bool {
    $charOrTypeOptions = (array) $charOrTypeOptions;
    $token = $token ?: $this->currentToken;
    foreach ($charOrTypeOptions as $charOrType) {
      if ($token === $charOrType) {
        return TRUE;
      }
      if (is_array($token) && $this->currentToken[0] == $charOrType) {
        return TRUE;
      }
    }
    return FALSE;
  }

  private function nextToken() {
    $this->currentToken = $this->tokens[$this->pos++] ?? [NULL, NULL];
    $this->currentTokenId = Tokenizer::getName($this->currentToken[0]);
    return $this;
  }

  private function expect($token) {
    if ($this->currentToken[0] == $token || $this->currentToken == $token) {
      $this->nextToken();
    }
    else {
      $this->unexpectedToken();
    }
    return $this;
  }

  private function expectSequence(array $tokens, bool $skipWhitespace = TRUE) {
    foreach ($tokens as $token) {
      $this->expect($token);
      if ($skipWhitespace) {
        $this->skipWhitespace();
      }
    }
    return $this;
  }

  private function skipWhitespace() {
    while ($this->currentToken[0] == T_WHITESPACE) {
      $this->nextToken();
    }
    return $this;
  }

  private function unexpectedToken() {
    $token = $this->currentToken;
    if ($token === [NULL, NULL]) {
      throw new ParseException('Unexpected end of content');
    }

    if (is_array($token)) {
      $token[0] = Tokenizer::getName($token);
    }
    throw new ParseException('Unexpected token: ' . json_encode($token));
  }

  /**
   * @return array
   */
  private function parseComments(): array {
    $comments = [];
    while ($this->isToken([T_COMMENT, T_DOC_COMMENT, T_WHITESPACE])) {
      $comment = ltrim($this->currentToken[1], ' \t');
      if ($this->isToken(T_DOC_COMMENT) && substr($comment, 0, 3) === '/**') {
        $comment = preg_replace("/^[ \t]+\*/m", ' *', $comment);
      }
      $this->nextToken();
      if ($this->isToken(T_WHITESPACE)) {
        $comment .= rtrim($this->currentToken[1], " ");
        $this->nextToken();
      }
      $comments[] = $comment;
    }
    return $comments;
  }

}
