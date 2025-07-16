<?php
namespace Nocto\Tools\CSSFlattener;

class CSSParser {
    private $pos = 0;
    private $len = 0;
    private $input = '';

    public function parse($css) {
        $this->input = $this->stripComments($css);
        $this->len = strlen($this->input);
        $this->pos = 0;
        $rules = [];

        while ($this->pos < $this->len) {
            $this->skipWhitespace();
            if ($this->peek() === '@') {
                $rules[] = $this->parseAtRule();
            } elseif ($this->peek() !== '') {
                $rules[] = $this->parseRule();
            }
        }

        return $rules;
    }

    private function stripComments($css) {
        return preg_replace('#/\*.*?\*/#s', '', $css);
    }

	private function readUntilOpeningBrace(): string {
		$start = $this->pos;
		$parenDepth = 0;

		while ($this->pos < $this->len) {
			$char = $this->input[$this->pos];

			if ($char === '(') {
				$parenDepth++;
			} elseif ($char === ')') {
				$parenDepth--;
			} elseif ($char === '{' && $parenDepth === 0) {
				break;
			}

			$this->pos++;
		}

		return trim(substr($this->input, $start, $this->pos - $start));
	}


    private function parseAtRule() {
		$start = $this->pos;
		$this->expect('@');
		$name = $this->readUntil([' ', '(', '{']);
		$this->skipWhitespace();
		if(in_array($name, ['import', 'charset'])) {
			$params = $this->readUntil([';']);
			$rule = new CSSAtRule($name, trim($params));
			$rule->isBlock = false;
		}
		else {
			$params = $this->readUntilOpeningBrace();
			$this->expect('{');

			$rule = new CSSAtRule($name, trim($params));
			$rule->isBlock = true;

			while ($this->peek() !== '}') {
				$this->skipWhitespace();
				if ($this->peek() === '}') {
					break;
				}

				if ($this->peek() === '@') {
					$rule->children[] = $this->parseAtRule();
				} elseif ($this->isNextSelectorBlock()) {
					$rule->children[] = $this->parseRule();
				} else {
					$decl = $this->readUntil([';']);
					$this->expect(';');
					if (strpos($decl, ':') !== false) {
						[$prop, $val] = explode(':', $decl, 2);
						$rule->children[] = new CSSDeclaration(trim($prop), trim($val));
					}
				}
			}

			$this->expect('}');
		}
		return $rule;
    }

    private function parseRule( $debug = false ) {
        $selector = $this->readUntil(['{']);
if($debug) var_dump(['rule selector?', $selector]);
        $this->expect('{');
        $rule = new CSSRule($selector);
        while (true) {
            $this->skipWhitespace();
            if ($this->peek() === '}') {
                $this->expect('}');
                break;
            }
			if ($this->peek() === '@') {
				$rule->children[] = $this->parseAtRule();
			} elseif ($this->isNextSelectorBlock()) {
				$rule->children[] = $this->parseRule();
			} else {
                $decl = $this->readUntil([';']);
                $this->expect(';');
                if (strpos($decl, ':') !== false) {
                    [$prop, $val] = explode(':', $decl, 2);
                    $rule->declarations[] = new CSSDeclaration($prop, $val);
                }
            }
        }
        return $rule;
    }

    private function parseBlock() {
        $rules = [];
        while ($this->pos < $this->len && $this->peek() !== '}') {
            $this->skipWhitespace();
            if ($this->peek() === '@') {
                $rules[] = $this->parseAtRule();
            } else {
                $rules[] = $this->parseRule();
            }
        }
        return $rules;
    }

    private function skipWhitespace() {
        while ($this->pos < $this->len && ctype_space($this->input[$this->pos])) {
            $this->pos++;
        }
    }

    private function readUntil(array $chars) {
        $start = $this->pos;
        while ($this->pos < $this->len && !in_array($this->input[$this->pos], $chars)) {
            $this->pos++;
        }
        return trim(substr($this->input, $start, $this->pos - $start));
    }

    private function peek() {
        return $this->pos < $this->len ? $this->input[$this->pos] : '';
    }

	private function isNextSelectorBlock(): bool {
		$tmp = $this->pos;
		$this->skipWhitespace();
		while ($this->pos < $this->len && $this->input[$this->pos] !== '{' && $this->input[$this->pos] !== ';' && $this->input[$this->pos] !== '}') {
			$this->pos++;
		}
		$result = ($this->pos < $this->len && $this->input[$this->pos] === '{');
		$this->pos = $tmp;
		return $result;
	}

	private function getErrorPosition(): array {
		$before = substr($this->input, 0, $this->pos);
		$line = substr_count($before, "\n") + 1;

		$lastNewline = strrpos($before, "\n");
		$lineStart = $lastNewline === false ? 0 : $lastNewline + 1;
		$col = $this->pos - $lineStart + 1;

		$lineEnd = strpos($this->input, "\n", $lineStart);
		if ($lineEnd === false) $lineEnd = strlen($this->input);
		$lineText = substr($this->input, $lineStart, $lineEnd - $lineStart);

		return [$line, $col, $lineText];
	}

	private function expect($char) {
		$this->skipWhitespace();
		if ($this->peek() !== $char) {
			[$line, $col, $snippet] = $this->getErrorPosition();
			throw new \Exception("Erreur de parsing CSS : attendu '$char', trouvé '" . $this->peek() .
				"' à la ligne $line, colonne $col :\n\n" .
				$snippet . "\n" .
				str_repeat(' ', $col - 1) . "↑");
		}
		$this->pos++;
	}

}
