<?php

declare(strict_types=1);

namespace App\MessageHandler\BrickLink;

use Dom\Element;

use function html_entity_decode;
use function mb_trim;

use const ENT_QUOTES;
use const ENT_SUBSTITUTE;
use const ENT_XML1;

class AbstractBrickLinkMessageHandler
{
    protected function getTextContent(Element $element, string $name): string
    {
        $text = $element->getElementsByTagName($name)->item(0)->textContent ?? '';
        return mb_trim(html_entity_decode($text, ENT_QUOTES | ENT_SUBSTITUTE | ENT_XML1, 'UTF-8'));
    }
}
