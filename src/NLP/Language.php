<?php

declare(strict_types=1);

namespace ML\IDEA\NLP;

use ML\IDEA\NLP\Contracts\NlpModelBackendInterface;
use ML\IDEA\NLP\Contracts\PipelineComponentInterface;
use ML\IDEA\NLP\Detect\LanguageDetector;
use ML\IDEA\NLP\Doc\Doc;
use ML\IDEA\NLP\Pipeline\BuiltinPipelineFactory;
use ML\IDEA\NLP\Text\NlpPipeline;

/** Process text through a loaded pipeline (spaCy `Language` equivalent). */
final class Language
{
    /** @var array<string, PipelineComponentInterface> */
    private array $components;

    /** @var list<string> */
    private array $componentOrder;

    /** @var array<string, bool> */
    private array $enabled;

    /**
     * @param array<string, PipelineComponentInterface>|null $components
     * @param list<string>|null $componentOrder
     * @param array<string, bool>|null $enabled
     */
    public function __construct(
        private readonly string $language,
        private readonly NlpPipeline $pipeline,
        private readonly ?string $modelName = null,
        private readonly ?LanguageDetector $detector = null,
        private readonly ?NlpModelBackendInterface $backend = null,
        ?array $components = null,
        ?array $componentOrder = null,
        ?array $enabled = null,
    ) {
        $this->components = $components ?? BuiltinPipelineFactory::defaultComponents(
            $this->pipeline,
            $this->language,
            $this->detector,
            $this->modelName,
        );
        $this->componentOrder = $componentOrder ?? BuiltinPipelineFactory::defaultOrder();
        $this->enabled = $enabled ?? array_fill_keys($this->componentOrder, true);
    }

    public static function blank(string $language = 'en', ?string $modelName = null): self
    {
        return new self($language, NlpPipeline::forLanguage($language), $modelName);
    }

    public function modelName(): ?string
    {
        return $this->modelName;
    }

    public function languageCode(): string
    {
        return $this->language;
    }

    public function pipeline(): NlpPipeline
    {
        return $this->pipeline;
    }

    /** @return list<string> */
    public function pipeNames(): array
    {
        return $this->componentOrder;
    }

    public function hasPipe(string $name): bool
    {
        return isset($this->components[$name]);
    }

    public function addPipe(string $name, PipelineComponentInterface $component, ?string $after = null): self
    {
        $components = $this->components;
        $order = $this->componentOrder;
        $enabled = $this->enabled;

        $components[$name] = $component;
        if (!in_array($name, $order, true)) {
            if ($after !== null && ($pos = array_search($after, $order, true)) !== false) {
                array_splice($order, (int) $pos + 1, 0, [$name]);
            } else {
                $order[] = $name;
            }
        }
        $enabled[$name] = true;

        return new self(
            $this->language,
            $this->pipeline,
            $this->modelName,
            $this->detector,
            $this->backend,
            $components,
            $order,
            $enabled,
        );
    }

    /** @param list<string> $names */
    public function disablePipes(array $names): self
    {
        $enabled = $this->enabled;
        foreach ($names as $name) {
            $enabled[$name] = false;
        }

        return new self(
            $this->language,
            $this->pipeline,
            $this->modelName,
            $this->detector,
            $this->backend,
            $this->components,
            $this->componentOrder,
            $enabled,
        );
    }

    public function process(string $text): Doc
    {
        $doc = new Doc(
            text: $text,
            attrs: ['model' => $this->modelName],
        );

        foreach ($this->componentOrder as $name) {
            if (($this->enabled[$name] ?? false) === false) {
                continue;
            }
            $component = $this->components[$name] ?? null;
            if ($component === null) {
                continue;
            }
            $doc = $component->process($doc, $text);
        }

        if ($this->backend === null) {
            return $doc;
        }

        return $this->backend->process($text, $doc);
    }

    /** @param array<int, string> $texts @return array<int, Doc> */
    public function pipe(array $texts): array
    {
        return array_map(fn (string $text): Doc => $this->process($text), $texts);
    }

    public function withBackend(NlpModelBackendInterface $backend): self
    {
        return new self(
            $this->language,
            $this->pipeline,
            $this->modelName,
            $this->detector,
            $backend,
            $this->components,
            $this->componentOrder,
            $this->enabled,
        );
    }
}
