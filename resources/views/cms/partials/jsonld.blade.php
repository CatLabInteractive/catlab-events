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

// FAQ blocks: one FAQPage with every question on the page.
$faqQuestions = [];
foreach ($blocks ?? [] as $renderedBlock) {
    if (($renderedBlock['block']['type'] ?? null) !== 'faq') {
        continue;
    }
    foreach ($renderedBlock['data']['items'] ?? [] as $item) {
        $question = trim((string) ($item['question'] ?? ''));
        $answer = trim(html_entity_decode(
            \App\Tools\StringHelper::htmlToText((string) ($item['answer'] ?? '')),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        ));
        if ($question !== '' && $answer !== '') {
            $faqQuestions[] = [
                '@type' => 'Question',
                'name' => $question,
                'acceptedAnswer' => [ '@type' => 'Answer', 'text' => $answer ],
            ];
        }
    }
}
?>
<script type="application/ld+json">{!! json_encode($webPage, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
@if(count($faqQuestions) > 0)
<script type="application/ld+json">{!! json_encode([ '@context' => 'http://schema.org', '@type' => 'FAQPage', 'mainEntity' => $faqQuestions ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
@endif
