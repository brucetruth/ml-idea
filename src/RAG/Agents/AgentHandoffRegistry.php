<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Agents;

final class AgentHandoffRegistry
{
    /** @var array<string, ToolRoutingAgent> */
    private array $agents = [];

    /** @var array<string, string> */
    private array $descriptions = [];

    public function register(string $name, ToolRoutingAgent $agent, string $description = ''): self
    {
        $key = trim($name);
        if ($key === '') {
            throw new \InvalidArgumentException('Handoff agent name cannot be empty.');
        }

        $this->agents[$key] = $agent;
        $this->descriptions[$key] = trim($description);

        return $this;
    }

    public function get(string $name): ?ToolRoutingAgent
    {
        return $this->agents[trim($name)] ?? null;
    }

    /** @return array<int, string> */
    public function names(): array
    {
        return array_keys($this->agents);
    }

    public function isEmpty(): bool
    {
        return $this->agents === [];
    }

    /** @return array<string, string> */
    public function descriptions(): array
    {
        $out = [];
        foreach ($this->agents as $name => $agent) {
            $desc = $this->descriptions[$name] ?? '';
            if ($desc === '') {
                $desc = $agent->getSystemPrompt();
                $desc = strlen($desc) > 120 ? substr($desc, 0, 117) . '...' : $desc;
            }
            $out[$name] = $desc;
        }

        return $out;
    }
}
