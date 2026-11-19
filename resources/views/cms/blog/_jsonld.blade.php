<?php
$author = $translation->author !== null && trim($translation->author) !== ''
    ? [ '@type' => 'Person', 'name' => $byline ]
    : [ '@type' => 'Organization', 'name' => $byline ];

$blogPosting = [
    '@context' => 'http://schema.org',
    '@type' => 'BlogPosting',
    'headline' => $translation->title,
    'url' => $canonicalUrl,
    'mainEntityOfPage' => $canonicalUrl,
    'inLanguage' => $translation->locale,
    'datePublished' => $publishedAt ? $publishedAt->toIso8601String() : null,
    'dateModified' => $translation->updated_at ? $translation->updated_at->toIso8601String() : null,
    'author' => $author,
    'publisher' => [ '@type' => 'Organization', 'name' => $organisation->name ],
    'description' => $description,
    'image' => $ogImageUrl,
];
$blogPosting = array_filter($blogPosting, function ($v) { return $v !== null; });
?>
<script type="application/ld+json">{!! json_encode($blogPosting, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
