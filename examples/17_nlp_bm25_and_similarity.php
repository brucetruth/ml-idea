<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ML\IDEA\NLP\Rag\Bm25Retriever;
use ML\IDEA\NLP\Rag\CitationFormatter;
use ML\IDEA\NLP\Similarity\CosineSimilarity;
use ML\IDEA\NLP\Vectorize\HashingVectorizer;

$docs = [
    'PHP machine learning library with TF-IDF and BM25 retrieval.',
    'Football analytics with match events and possession features.',
    'NLP text cleaning, tokenization, and keyword extraction in PHP.',
];

$retriever = new Bm25Retriever();
$retriever->index($docs);
$hits = $retriever->retrieve('php nlp retrieval', 2);

echo "Example 17 - NLP BM25 + Similarity\n";
echo "Top BM25 hits:\n";
echo (new CitationFormatter())->format($hits) . PHP_EOL;

$hv = new HashingVectorizer(64);
$vectors = $hv->transform([$docs[0], $docs[2]]);
$sim = CosineSimilarity::between($vectors[0], $vectors[1]);

echo 'Cosine similarity(doc0, doc2): ' . round($sim, 4) . PHP_EOL;
