<?php
namespace Dottyfix\CSSFlattener;

class CSSAtRule {
    public static array $inlineAtRules = [
        'charset', 'import', 'namespace'
    ];

    public static array $blockAtRules = [
        'media', 'supports', 'document', 'page', 'font-face', 'keyframes',
        'viewport', 'counter-style', 'font-feature-values', 'swash', 'ornaments',
        'annotation', 'stylistic', 'styleset', 'character-variant',
        'layer', 'scope', 'property', 'container'
    ];

    public bool $isBlock = false;
    public string $name;
    public string $params;
    public array $children = [];

    public function __construct(string $name, string $params) {
        $this->name = $name;
        $this->params = $params;
    }

    public static function parse(CSSParser $parser): CSSAtRule {
        $parser->expect('@');
        $parser->skipWhitespace();
        $name = $parser->readUntil([' ', '(', '{', ';']);
        $parser->skipWhitespace();

        if (in_array($name, self::$inlineAtRules)) {
            return self::parseInline($parser, $name);
        } elseif (in_array($name, self::$blockAtRules)) {
            return self::parseBlock($parser, $name);
        } else {
            // Gérer les at-rules non reconnues
            // Lever une exception ou retourner une at-rule générique
            // Pour le moment, nous les traiterons comme des at-rules de bloc par défaut
            return self::parseBlock($parser, $name);
        }
    }

    private static function parseInline(CSSParser $parser, string $name): CSSAtRule {
        $paramsStart = $parser->getPos();
        $parenDepth = 0;
        $quoteChar = '';

        while ($parser->getPos() < $parser->getLen()) {
            $char = $parser->getChar();
            if ($quoteChar !== '') {
                if ($char === $quoteChar) {
                    $quoteChar = '';
                }
            } elseif ($char === "'" || $char === '"') {
                $quoteChar = $char;
            } elseif ($char === '(') {
                $parenDepth++;
            } elseif ($char === ')') {
                $parenDepth--;
            } elseif ($char === ';' && $parenDepth === 0 && $quoteChar === '') {
                break;
            }
            $parser->advancePos();
        }

        $params = trim($parser->getSubstring($paramsStart, $parser->getPos() - $paramsStart));
        $rule = new self($name, $params);
        $rule->isBlock = false;
        $parser->expect(';', $params);

        return $rule;
    }

    private static function parseBlock(CSSParser $parser, string $name): CSSAtRule {
        $params = $parser->readUntilOpeningBrace();
        $parser->expect('{', $params);

        $rule = new self($name, trim($params));
        $rule->isBlock = true;

        // La boucle de parsing de la logique de bloc est maintenant ici
        while (true) {
            $parser->skipWhitespace();
            if ($parser->peek() === '}') {
                $parser->expect('}', 'fin de bloc ' . $name);
                break;
            }

            if ($parser->peek() === '') {
                list($line, $col, $snippet, $arrowPos) = $parser->getErrorPosition();
                throw new \Exception("Erreur de parsing CSS dans @rule : bloc '" . $name . "' non fermé, fin de fichier inattendue" .
                    " à la ligne $line, colonne $col :\n\n" .
                    "Extrait : '" . $snippet . "'\n" .
                    str_repeat(' ', $arrowPos) . "↑");
            }
            
            // Déléger le parsing des enfants à la classe CSSParser
            if ($parser->peek() === '@') {
                $rule->children[] = CSSAtRule::parse($parser);
                continue;
            } elseif ($parser->isNextSelectorBlock()) {
                $rule->children[] = $parser->parseRule();
                continue;
            } else {
                $startDecl = $parser->getPos();
                $decl = $parser->readUntil([';']);
                $parser->skipWhitespace();

                if ($parser->peek() === ';') {
                    if (strpos($decl, ':') !== false) {
                        $parser->expect(';', $decl);
                        list($prop, $val) = explode(':', $decl, 2);
                        $rule->children[] = new CSSDeclaration(trim($prop), trim($val));
                    } else {
                        $parser->setPos($startDecl);
                        list($line, $col, $snippet, $arrowPos) = $parser->getErrorPosition();
                        throw new \Exception("Erreur de parsing CSS dans @rule : déclaration mal formée. Attendu 'prop: val;'. " .
                            "Trouvé '" . $decl . "' à la ligne $line, colonne $col :\n\n" .
                            "Extrait : '" . $snippet . "'\n" .
                            str_repeat(' ', $arrowPos) . "↑");
                    }
                } else {
                    $parser->setPos($startDecl);
                    list($line, $col, $snippet, $arrowPos) = $parser->getErrorPosition();
                    throw new \Exception("Erreur de parsing CSS dans @rule : syntaxe inattendue. Attendu ';', '{' ou '}'. " .
                        "Segment: '" . $decl . "' Trouvé '" . $parser->peek() .
                        "' à la ligne $line, colonne $col :\n\n" .
                        "Extrait : '" . $snippet . "'\n" .
                        str_repeat(' ', $arrowPos) . "↑");
                }
            }
        }

        return $rule;
    }
}
