<?php
$webPage = [
    '@context' => 'http://schema.org',
    '@type' => 'WebPage',
    'name' => $pageTitle,
    'url' => $canonicalUrl,
    'inLanguage' => $translation->locale,
    'dateModified' => $translation->updated_at ? $translation->updated_at->toIso8601String() : null,
];
if ($description) {
    $webPage['description'] = $description;
}
if ($ogImageUrl) {
    $webPage['image'] = $ogImageUrl;
}
$webPage = array_filter($webPage, function ($v) { return $v !== null; });
?>
<script type="application/ld+json">{!! json_encode($webPage, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
