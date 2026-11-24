<?php

namespace App\Services;

use App\Support\Media;
use HTMLPurifier;
use HTMLPurifier_Config;

/**
 * Cleans blog content from the rich text editor before it is saved, so only
 * the formatting the editor can produce is kept (no scripts, iframes, event
 * handlers or free-form styles). Mirrors the admin's Tiptap setup:
 *   - text: headings, bold/italic/underline/strike, lists, quotes, code, links
 *   - text-align on paragraphs/headings, text colour, <mark> highlight
 *   - figures: <figure class="wl-figure ..."><img><figcaption></figure>, with
 *     images only from our own media storage (external sources are dropped)
 */
class HtmlSanitizer
{
    /** Classes the editor puts on figures (size + alignment). Anything else is removed. */
    public const FIGURE_CLASSES = [
        'wl-figure',
        'wl-figure--small', 'wl-figure--medium', 'wl-figure--full',
        'wl-figure--left', 'wl-figure--center', 'wl-figure--right',
    ];

    private ?HTMLPurifier $purifier = null;

    public function clean(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        return trim($this->purifier()->purify($html));
    }

    private static function mediaHost(): string
    {
        return (string) (parse_url((string) Media::url('x'), PHP_URL_HOST) ?: parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost');
    }

    /** True when the HTML has no readable text (e.g. "<p></p>"). */
    public static function isEmpty(?string $html): bool
    {
        return trim(html_entity_decode(strip_tags((string) $html))) === '';
    }

    private function purifier(): HTMLPurifier
    {
        if ($this->purifier) {
            return $this->purifier;
        }

        $config = HTMLPurifier_Config::createDefault();
        $cachePath = storage_path('framework/cache/htmlpurifier');
        if (! is_dir($cachePath)) {
            @mkdir($cachePath, 0755, true);
        }
        $config->set('Cache.SerializerPath', $cachePath);
        $config->set('HTML.Doctype', 'HTML 4.01 Transitional');
        $config->set('HTML.Allowed', implode(',', [
            'p[style]', 'br', 'h2[style]', 'h3[style]',
            'strong', 'b', 'em', 'i', 'u', 's', 'mark', 'span[style]',
            'a[href|target|rel]', 'ul', 'ol', 'li', 'blockquote', 'code', 'pre', 'hr',
            'figure[class]', 'figcaption', 'img[src|alt|width|height]',
        ]));
        $config->set('CSS.AllowedProperties', ['text-align', 'color']);
        $config->set('Attr.AllowedClasses', self::FIGURE_CLASSES);
        $config->set('Attr.AllowedFrameTargets', ['_blank']);
        $config->set('Attr.AllowedRel', ['noopener', 'noreferrer', 'nofollow']);
        $config->set('Attr.DefaultImageAlt', '');
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true]);
        // Images may only come from our media storage (no tracking pixels / hotlinks).
        $config->set('URI.Host', self::mediaHost());
        $config->set('URI.DisableExternalResources', true);
        $config->set('HTML.TargetNoopener', true);
        $config->set('HTML.TargetNoreferrer', true);
        $config->set('AutoFormat.RemoveEmpty', false);

        // HTML5 elements HTMLPurifier doesn't know about.
        $config->set('HTML.DefinitionID', 'wiserleap-blog');
        $config->set('HTML.DefinitionRev', 2);
        if ($definition = $config->maybeGetRawHTMLDefinition()) {
            $definition->addElement('figure', 'Block', 'Flow', 'Common');
            $definition->addElement('figcaption', 'Block', 'Flow', 'Common');
            $definition->addElement('mark', 'Inline', 'Inline', 'Common');
        }

        return $this->purifier = new HTMLPurifier($config);
    }
}
