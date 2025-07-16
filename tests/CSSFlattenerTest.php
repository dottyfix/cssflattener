<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../CSSNode.php';
require_once __DIR__ . '/../CSSParser.php';
require_once __DIR__ . '/../CSSFlattener.php';

class CSSFlattenerTest extends TestCase
{
    private function flattenCSS(string $input): string
    {
        $parser = new CSSParser();
        $ast = $parser->parse($input);

        $flattener = new CSSFlattener();
        $output = $flattener->flatten($ast);
        return implode("\n", $output);
    }

    public function testSimpleNesting()
    {
        $input = <<<CSS
.button {
  color: red;
  .icon {
    color: blue;
  }
}
CSS;

        $expected = <<<CSS
.button {
  color: red;
}

.button .icon {
  color: blue;
}
CSS;

        $this->assertEquals(trim($expected), trim($this->flattenCSS($input)));
    }

    public function testAmpersandNesting()
    {
        $input = <<<CSS
.button {
  color: red;
  &:hover {
    color: green;
  }
}
CSS;

        $expected = <<<CSS
.button {
  color: red;
}

.button:hover {
  color: green;
}
CSS;

        $this->assertEquals(trim($expected), trim($this->flattenCSS($input)));
    }

    public function testMediaQuery()
    {
        $input = <<<CSS
.container {
  color: black;
  @media (max-width: 600px) {
    color: gray;
    &:hover {
      color: white;
    }
  }
}
CSS;

        $expected = <<<CSS
.container {
  color: black;
}

@media (max-width: 600px) {
  .container {
    color: gray;
  }
  .container:hover {
    color: white;
  }
}
CSS;

        $this->assertEquals(trim($expected), trim($this->flattenCSS($input)));
    }

    public function testNestedMediaInSelector()
    {
        $input = <<<CSS
.button {
  color: blue;
  .label {
    font-weight: bold;
    @media (min-width: 768px) {
      font-size: 20px;
    }
  }
}
CSS;

        $expected = <<<CSS
.button {
  color: blue;
}

.button .label {
  font-weight: bold;
}

@media (min-width: 768px) {
  .button .label {
    font-size: 20px;
  }
}
CSS;

        $this->assertEquals(trim($expected), trim($this->flattenCSS($input)));
    }
}
