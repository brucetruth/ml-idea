<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use ML\IDEA\RAG\Agents\ToolRoutingAgent;
use ML\IDEA\RAG\Contracts\ToolInterface;
use ML\IDEA\RAG\Contracts\ToolRoutingModelInterface;

/**
 * Example 07:
 * Build and register a custom RAG-style tool with ToolRoutingAgent.
 */
final class LocalFaqTool implements ToolInterface
{
    /** @param array<string, string> $faq */
    public function __construct(private readonly array $faq)
    {
    }

    public function name(): string
    {
        return 'local_faq';
    }

    public function description(): string
    {
        return 'Answer FAQ questions from local in-memory knowledge.';
    }

    public function invoke(array $input): string
    {
        $question = mb_strtolower(trim((string) ($input['question'] ?? '')));
        if ($question === '') {
            return 'Missing required field: question';
        }

        foreach ($this->faq as $pattern => $answer) {
            if (str_contains($question, $pattern)) {
                return $answer;
            }
        }

        return 'No FAQ match found in local_faq tool.';
    }
}

final class FaqFirstRouter implements ToolRoutingModelInterface
{
    public function respond(array $messages, array $tools): array
    {
        $lastUser = '';
        $lastToolOutput = null;

        for ($i = count($messages) - 1; $i >= 0; $i--) {
            if ($lastToolOutput === null && $messages[$i]['role'] === 'tool') {
                $lastToolOutput = $messages[$i]['content'];
            }
            if ($messages[$i]['role'] === 'user') {
                $lastUser = mb_strtolower($messages[$i]['content']);
                break;
            }
        }

        if (is_string($lastToolOutput) && $lastToolOutput !== '') {
            return ['type' => 'final', 'content' => 'Tool result: ' . $lastToolOutput];
        }

        return [
            'type' => 'tool_call',
            'tool' => 'local_faq',
            'input' => ['question' => $lastUser],
        ];
    }
}

$faqTool = new LocalFaqTool([
    'persist model' => 'Use ModelSerializer::save($model, $path) and ModelSerializer::load($path).',
    'vector store' => 'Use InMemoryVectorStore, JsonVectorStore, or SQLiteVectorStore.',
    'tool routing agent' => 'ToolRoutingAgent can route to tools via local or provider-backed models.',
]);

$agent = new ToolRoutingAgent(new FaqFirstRouter(), [$faqTool]);

$query = $argv[1] ?? 'How do I persist model artifacts in ml-idea?';
$result = $agent->chat($query);

echo "Example 07 - Custom RAG tool\n";
echo 'Q: ' . $query . PHP_EOL;
echo 'Answer: ' . $result['answer'] . PHP_EOL;
echo 'Tool calls: ' . json_encode($result['tool_calls'], JSON_THROW_ON_ERROR) . PHP_EOL;
