<?php

namespace App\Services;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\MarkdownConverter;

/**
 * Renders user- and author-authored markdown (challenge descriptions,
 * writeups) to HTML. Raw HTML is escaped and unsafe links dropped, so the
 * output is safe to bind with v-html without an extra sanitizer pass.
 */
class MarkdownRenderer
{
    public function toHtml(string $markdown): string
    {
        // html_input=escape drops any inline <script>/<iframe> authored content — safe against XSS.
        $env = new Environment([
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);
        $env->addExtension(new CommonMarkCoreExtension);
        $env->addExtension(new AutolinkExtension);

        return (string) (new MarkdownConverter($env))->convert($markdown);
    }
}
