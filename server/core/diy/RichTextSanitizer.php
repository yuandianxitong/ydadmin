<?php

declare(strict_types=1);

namespace core\diy;

/**
 * 装修富文本白名单。strip_tags 留得下 onclick 和 javascript: 链接，所以按节点重写。
 * 脚本、样式、框架整段丢掉；不认识的标签只留下里面的文字。
 */
final class RichTextSanitizer
{
    /** @var array<string, list<string>> */
    private const ALLOWED = [
        'p' => [], 'br' => [], 'strong' => [], 'b' => [], 'em' => [], 'i' => [],
        'u' => [], 's' => [], 'ul' => [], 'ol' => [], 'li' => [],
        'h1' => [], 'h2' => [], 'h3' => [], 'h4' => [],
        'blockquote' => [], 'span' => [], 'div' => [],
        'table' => [], 'thead' => [], 'tbody' => [], 'tr' => [], 'th' => [], 'td' => [],
        'a' => ['href', 'title'],
        'img' => ['src', 'alt'],
    ];

    /** @var list<string> */
    private const DROP = ['script', 'style', 'iframe', 'object', 'embed', 'link', 'meta', 'svg', 'math', 'form'];

    public static function clean(string $html): string
    {
        if ($html === '' || !str_contains($html, '<')) {
            return $html;
        }

        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $encoded = mb_encode_numericentity($html, [0x80, 0x10FFFF, 0, 0x1FFFFF], 'UTF-8');
        $dom->loadHTML(
            '<?xml encoding="UTF-8"><html><body>' . $encoded . '</body></html>',
            LIBXML_NONET
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $body = $dom->getElementsByTagName('body')->item(0);
        if (!$body instanceof \DOMElement) {
            return '';
        }
        self::sanitize($body);

        $out = '';
        foreach ($body->childNodes as $child) {
            $out .= $dom->saveHTML($child);
        }

        return mb_decode_numericentity($out, [0x80, 0x10FFFF, 0, 0x1FFFFF], 'UTF-8');
    }

    private static function sanitize(\DOMNode $node): void
    {
        $children = [];
        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }
        foreach ($children as $child) {
            if ($child->parentNode !== null) {
                self::sanitize($child);
            }
        }
        if (!$node instanceof \DOMElement || $node->tagName === 'body' || $node->tagName === 'html') {
            return;
        }

        $tag = strtolower($node->tagName);
        $parent = $node->parentNode;
        if ($parent === null) {
            return;
        }
        if (in_array($tag, self::DROP, true)) {
            $parent->removeChild($node);

            return;
        }
        if (!isset(self::ALLOWED[$tag])) {
            while ($node->firstChild !== null) {
                $parent->insertBefore($node->firstChild, $node);
            }
            $parent->removeChild($node);

            return;
        }

        $keep = self::ALLOWED[$tag];
        $remove = [];
        if ($node->attributes !== null) {
            foreach ($node->attributes as $attr) {
                $name = strtolower($attr->name);
                if (str_starts_with($name, 'on') || !in_array($name, $keep, true)) {
                    $remove[] = $attr->name;
                    continue;
                }
                if (in_array($name, ['href', 'src'], true) && !self::safeUrl($attr->value)) {
                    $remove[] = $attr->name;
                }
            }
        }
        foreach ($remove as $name) {
            $node->removeAttribute($name);
        }
    }

    private static function safeUrl(string $url): bool
    {
        $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($url === '' || preg_match('/[\x00-\x20]/', $url) === 1) {
            return false;
        }
        if (preg_match('#^(https?:|mailto:|tel:)#i', $url) === 1) {
            return true;
        }
        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $url) === 1) {
            return false;
        }

        return !str_starts_with($url, '//');
    }
}
