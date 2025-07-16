<?php
namespace Nocto\Tools\CSSFlattener;

class CSSFlattener {
    public function flatten(array $nodes, string $parentSelector = ''): array {
        $output = [];
		$lastIsDeclaration = false;
		
        foreach ($nodes as $node) {
            if ($node instanceof CSSRule) {
				if($lastIsDeclaration) $output[] = "}\n";
                $selector = $this->resolveSelector($node->selector, $parentSelector);
                if (!empty($node->declarations)) {
                    $output[] = $selector . " {\n" . $this->renderDeclarations($node->declarations) . "}\n";
                }
                $output = array_merge($output, $this->flatten($node->children, $selector));
                $lastIsDeclaration = false;
            } elseif ($node instanceof CSSAtRule) {
				if($lastIsDeclaration) $output[] = "}\n";
                $inner = $this->flatten($node->children, $parentSelector);
                if(!$node->isBlock)
					$output[] = "@{$node->name} {$node->params};\n";
				else
					$output[] = "@{$node->name} {$node->params} {\n" . $this->indent($inner) . "}\n";
                $lastIsDeclaration = false;
            } elseif ($node instanceof CSSDeclaration) {
                //$inner = $this->flatten($node->children, $parentSelector);
                //$output[] = "@{$node->name} {$node->params} {\n" . $this->indent($inner) . "}\n";
                if(!$lastIsDeclaration) $output[] = $parentSelector . " {\n";
                $output[] = $this->renderDeclarations([$node]);
                $lastIsDeclaration = true;
            }
        }
        if($lastIsDeclaration) $output[] = "}\n";

        return $output;
    }

    private function resolveSelector($selector, $parent) {
		$selector = trim($selector);

		// Séparation des sélecteurs parents et enfants par virgule
		$parentParts = array_map('trim', explode(',', $parent));
		$selectorParts = array_map('trim', explode(',', $selector));

		$result = [];

		foreach ($parentParts as $p) {
			foreach ($selectorParts as $s) {
				if (strpos($s, '&') !== false) {
					$result[] = str_replace('&', $p, $s);
				} else {
					$result[] = trim($p . ' ' . $s);
				}
			}
		}

		return implode(', ', $result);
    }

    private function renderDeclarations(array $decls) {
        return implode("\n", array_map(function($d) {
            return "  {$d->property}: {$d->value};";
        }, $decls)) . "\n";
    }

    private function indent(array $lines) {
        return implode('', array_map(fn($line) => '  ' . $line, $lines));
    }
}
