<?php
namespace Dottyfix\CSSFlattener;

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
            if ($this->peek() === '') {
                break;
            }

            if ($this->peek() === '@') {
                // Délégation du parsing à la classe CSSAtRule
                $rules[] = CSSAtRule::parse($this);
            } elseif ($this->peek() !== '') {
                $rules[] = $this->parseRule();
            } else {
                [$line, $col, $snippet, $arrowPos] = $this->getErrorPosition();
                throw new \Exception("Erreur de parsing CSS : caractère inattendu en début de fichier ou après bloc. " .
                    "Trouvé '" . $this->peek() .
                    "' à la ligne $line, colonne $col :\n\n" .
                    "Extrait : '" . $snippet . "'\n" .
                    str_repeat(' ', $arrowPos) . "↑");
            }
        }

        $this->skipWhitespace();
        if ($this->pos < $this->len) {
             [$line, $col, $snippet, $arrowPos] = $this->getErrorPosition();
             throw new \Exception("Erreur de parsing CSS : caractères restants inattendus après le parsing complet. " .
                 "Trouvé '" . $this->peek() .
                 "' à la ligne $line, colonne $col :\n\n" .
                 "Extrait : '" . $snippet . "'\n" .
                 str_repeat(' ', $arrowPos) . "↑");
        }
        return $rules;
    }
    
    // Ajout de méthodes d'accès publiques pour que CSSAtRule::parse puisse interagir
    public function getPos(): int { return $this->pos; }
    public function setPos(int $pos): void { $this->pos = $pos; }
    public function getLen(): int { return $this->len; }
    public function getChar(): string { return $this->input[$this->pos]; }
    public function advancePos(): void { $this->pos++; }
    public function getSubstring(int $start, int $length): string { return substr($this->input, $start, $length); }

    // Les autres méthodes (stripComments, readUntil, peek, isNextSelectorBlock, getErrorPosition, expect)
    // restent inchangées, mais certaines ont été ajoutées pour supporter l'encapsulation.
    // ...
    public function stripComments($css) {
        return preg_replace_callback('#/\*.*?\*/#s', function($matches) {
            $comment = $matches[0];
            $newlines = substr_count($comment, "\n");
            $replacement = str_repeat("\n", $newlines);
            $replacement .= str_repeat(' ', strlen($comment) - strlen($replacement));
            return $replacement;
        }, $css);
    }

    public function readUntilOpeningBrace(): string {
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


    public function parseAtRule() {
        // La logique est maintenant dans CSSAtRule::parse, mais nous avons besoin de la méthode ici
        // pour la compatibilité avec le reste du code.
        return CSSAtRule::parse($this);
    }

    public function parseRule($debug = false) {
        $selector = $this->readUntil(['{']);
if($debug) var_dump(['rule selector?', $selector]);
        $this->expect('{', $selector);
        $rule = new CSSRule($selector);

        while (true) {
            $this->skipWhitespace();
if($debug) var_dump(['current block', $selector, 'peek character', $this->peek(), 'current_pos', $this->pos]);
            if ($this->peek() === '}') {
                $this->expect('}', 'fin de bloc ' . $selector);
                break;
            }

            if ($this->peek() === '') {
                [$line, $col, $snippet, $arrowPos] = $this->getErrorPosition();
                throw new \Exception("Erreur de parsing CSS : bloc '" . $selector . "' non fermé, fin de fichier inattendue" .
                    " à la ligne $line, colonne $col :\n\n" .
                    "Extrait : '" . $snippet . "'\n" .
                    str_repeat(' ', $arrowPos) . "↑");
            }

            if ($this->peek() === '@') {
                $rule->children[] = $this->parseAtRule();
                continue;
            } elseif ($this->isNextSelectorBlock()) {
                $rule->children[] = $this->parseRule();
                continue;
            } else {
                $startDecl = $this->pos;
                $decl = $this->readUntil([';']);
                $this->skipWhitespace();

                if ($this->peek() === ';') {
                    if (strpos($decl, ':') !== false) {
                        $this->expect(';', $decl);
                        [$prop, $val] = explode(':', $decl, 2);
                        $rule->declarations[] = new CSSDeclaration(trim($prop), trim($val));
                    } else {
                        $this->pos = $startDecl;
                        [$line, $col, $snippet, $arrowPos] = $this->getErrorPosition();
                        throw new \Exception("Erreur de parsing CSS dans @rule : déclaration mal formée. Attendu 'prop: val;'. " .
                            "Trouvé '" . $decl . "' à la ligne $line, colonne $col :\n\n" .
                            "Extrait : '" . $snippet . "'\n" .
                            str_repeat(' ', $arrowPos) . "↑");
                    }
                } else {
                    $this->pos = $startDecl;
                    [$line, $col, $snippet, $arrowPos] = $this->getErrorPosition();
                    throw new \Exception("Erreur de parsing CSS : syntaxe inattendue. Attendu ';', '{' ou '}'. " .
                        "Segment: '" . $decl . "' Trouvé '" . $this->peek() .
                        "' à la ligne $line, colonne $col :\n\n" .
                        "Extrait : '" . $snippet . "'\n" .
                        str_repeat(' ', $arrowPos) . "↑");
                }
            }
        }
        return $rule;
    }

    public function parseBlock() {
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

    public function skipWhitespace() {
        while ($this->pos < $this->len && ctype_space($this->input[$this->pos])) {
            $this->pos++;
        }
    }

    public function readUntil(array $chars) {
        $start = $this->pos;
        while ($this->pos < $this->len && !in_array($this->input[$this->pos], $chars)) {
            $this->pos++;
        }
        return trim(substr($this->input, $start, $this->pos - $start));
    }

    public function peek() {
        return $this->pos < $this->len ? $this->input[$this->pos] : '';
    }

    public function isNextSelectorBlock(): bool {
        $tmp = $this->pos;
        $this->skipWhitespace();
        while ($this->pos < $this->len && $this->input[$this->pos] !== '{' && $this->input[$this->pos] !== ';' && $this->input[$this->pos] !== '}') {
            $this->pos++;
        }
        $result = ($this->pos < $this->len && $this->input[$this->pos] === '{');
        $this->pos = $tmp; // Réinitialiser la position
        return $result;
    }

    public function getErrorPosition(): array {
        $before = substr($this->input, 0, $this->pos);
        $line = substr_count($before, "\n") + 1;

        $lastNewline = strrpos($before, "\n");
        $lineStart = $lastNewline === false ? 0 : $lastNewline + 1;
        $col = $this->pos - $lineStart + 1;

        $snippetLength = 30;
        $snippetStart = max(0, $this->pos - floor($snippetLength / 2));
        $snippetEnd = min($this->len, $this->pos + ceil($snippetLength / 2));

        if ($snippetEnd - $snippetStart < $snippetLength) {
            if ($snippetStart === 0) {
                $snippetEnd = min($this->len, $snippetLength);
            } elseif ($snippetEnd === $this->len) {
                $snippetStart = max(0, $this->len - $snippetLength);
            }
        }

        $snippet = substr($this->input, $snippetStart, $snippetEnd - $snippetStart);
        $arrowPositionInSnippet = $this->pos - $snippetStart;

        return [$line, $col, $snippet, $arrowPositionInSnippet];
    }

    public function expect($char, string $contextSegment = '') {
        $this->skipWhitespace();
        if ($this->peek() !== $char) {
            [$line, $col, $snippet, $arrowPos] = $this->getErrorPosition();

            $errorMessage = "Erreur de parsing CSS : attendu '$char', trouvé '" . $this->peek() . "'";
            if (!empty($contextSegment)) {
                $errorMessage .= " après '$contextSegment'";
            }
            $errorMessage .= " à la ligne $line, colonne $col :\n\n" .
                             "Extrait : '" . $snippet . "'\n" .
                             str_repeat(' ', $arrowPos) . "↑";

            throw new \Exception($errorMessage);
        }
        $this->pos++;
    }
}
