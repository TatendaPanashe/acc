<?php

namespace App\Services\Help;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Help Centre content: the user manual split into guides by its "## N. Title" sections,
 * grouped into categories by config/help.php.
 */
class HelpCentre
{
    /** @return Collection<string, array{number: string, title: string, slug: string, markdown: string, summary: string, headings: array, minutes: int, category: ?string}> keyed by slug */
    public function guides(): Collection
    {
        static $cache = [];
        $path = config('help.manual');
        $key = $path.'@'.(is_file($path) ? filemtime($path) : 0);
        if (isset($cache[$key])) {
            return $cache[$key];
        }

        $markdown = is_file($path) ? file_get_contents($path) : '';
        $parts = preg_split('/^## /m', $markdown);
        array_shift($parts); // title and anything before the first section
        // Plain loop: merging collections would renumber numeric section keys such as "1" or "17".
        $categoryOf = [];
        foreach (config('help.categories') as $key => $category) {
            foreach ($category['sections'] as $section) {
                $categoryOf[(string) $section] = $key;
            }
        }

        $guides = collect($parts)->map(function (string $part) use ($categoryOf) {
            [$heading, $body] = array_pad(explode("\n", $part, 2), 2, '');
            preg_match('/^(\d+[A-Z]?)\.\s+(.*)$/', trim($heading), $m);
            $number = $m[1] ?? Str::slug($heading);
            $title = trim($m[2] ?? $heading);
            preg_match_all('/^### (.+)$/m', $body, $subs);
            $words = str_word_count(strip_tags($body));

            return [
                'number' => $number,
                'title' => $title,
                'slug' => Str::slug($title),
                'markdown' => trim($body),
                'summary' => self::summary($body) ?: (count($subs[1]) ? 'Covers: '.Str::limit(implode(', ', array_map(fn ($h) => trim(preg_replace('/^\d+[A-Z]?(\.\d+)*\s+/', '', $h)), $subs[1])), 150) : ''),
                'headings' => array_map(fn ($h) => ['title' => trim(preg_replace('/^\d+[A-Z]?(\.\d+)*\s+/', '', $h)), 'id' => Str::slug($h)], $subs[1]),
                'minutes' => max(1, (int) ceil($words / 200)),
                'category' => $categoryOf[(string) $number] ?? null,
            ];
        })->keyBy('slug');

        return $cache[$key] = $guides;
    }

    /** Guides the user may read (platform-only sections are for superusers). */
    public function visibleTo(?User $user): Collection
    {
        $hidden = collect(config('help.categories'))->filter(fn ($c) => !empty($c['superuser_only']))->keys();

        return $this->guides()->reject(fn ($g) => !$user?->is_superuser && $hidden->contains($g['category']));
    }

    public function byNumber(string $number, ?User $user = null): ?array
    {
        return $this->visibleTo($user)->firstWhere('number', $number);
    }

    /** Categories with their guides, in configured order, plus anything unassigned under "More". */
    public function categories(?User $user): Collection
    {
        $guides = $this->visibleTo($user);
        $categories = collect(config('help.categories'))
            ->map(fn ($c, $key) => $c + ['key' => $key, 'guides' => collect($c['sections'])->map(fn ($n) => $guides->firstWhere('number', $n))->filter()->values()])
            ->filter(fn ($c) => $c['guides']->isNotEmpty());

        $unassigned = $guides->whereNull('category')->values();
        if ($unassigned->isNotEmpty()) {
            $categories->put('more', ['key' => 'more', 'title' => 'More', 'icon' => 'book', 'intro' => 'Other topics.', 'guides' => $unassigned]);
        }

        return $categories;
    }

    /** Rendered HTML; level-3 headings get ids for the in-page contents list. */
    public function html(array $guide): string
    {
        $html = Str::markdown($guide['markdown'], ['html_input' => 'strip', 'allow_unsafe_links' => false]);

        return preg_replace_callback('/<h3>(.*?)<\/h3>/s', fn ($m) => '<h3 id="'.Str::slug(html_entity_decode(strip_tags($m[1]))).'">'.$m[1].'</h3>', $html);
    }

    private static function summary(string $body): string
    {
        foreach (preg_split('/\n\s*\n/', $body) as $paragraph) {
            $paragraph = trim($paragraph);
            if ($paragraph !== '' && !str_starts_with($paragraph, '#') && !str_starts_with($paragraph, '|') && !str_starts_with($paragraph, '-') && !preg_match('/^\d+\./', $paragraph)) {
                return Str::limit(trim(preg_replace('/[*_`]/', '', preg_replace('/\s+/', ' ', $paragraph))), 170);
            }
        }

        return '';
    }
}
