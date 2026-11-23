<?php

namespace App\Services;

use HTMLPurifier;
use HTMLPurifier_Config;

/**
 * Cleans blog content from the rich text editor before it is saved, so only
 * the formatting the editor can produce is kept (no scripts, styles, iframes
 * or event handlers). Mirrors the Tiptap StarterKit + Link setup.
 */
class HtmlSanitizer
{
    private ?HTMLPurifier $purifier = null;

    public function clean(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        return trim($this->purifier()->purify($html));
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
        $config->set('HTML.Allowed', 'p,br,h2,h3,strong,b,em,i,u,s,a[href|target|rel],ul,ol,li,blockquote,code,pre,hr');
        $config->set('Attr.AllowedFrameTargets', ['_blank']);
        $config->set('Attr.AllowedRel', ['noopener', 'noreferrer', 'nofollow']);
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true]);
        $config->set('HTML.TargetNoopener', true);
        $config->set('HTML.TargetNoreferrer', true);
        $config->set('AutoFormat.RemoveEmpty', false);

        return $this->purifier = new HTMLPurifier($config);
    }
}
