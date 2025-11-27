<?php

namespace Codemonkey76\LegacyCleaner\Support;

use Illuminate\Support\Collection;

class UsageResult
{
    protected Collection $unused;
    protected Collection $used;

    public function __construct(Collection $unused, Collection $used)
    {
        $this->unused = $unused;
        $this->used = $used;
    }

    public function getUnused(): Collection
    {
        return $this->unused;
    }

    public function getUsed(): Collection
    {
        return $this->used;
    }

    public function getTotalCount(): int
    {
        return $this->unused->count() + $this->used->count();
    }

    public function getUnusedCount(): int
    {
        return $this->unused->count();
    }

    public function getUsedCount(): int
    {
        return $this->used->count();
    }

    public function toArray(): array
    {
        return [
            'summary' => [
                'total' => $this->getTotalCount(),
                'unused' => $this->getUnusedCount(),
                'used' => $this->getUsedCount(),
                'unused_percentage' => $this->getTotalCount() > 0
                    ? round(($this->getUnusedCount() / $this->getTotalCount()) * 100, 2)
                    : 0,
            ],
            'unused' => $this->unused->toArray(),
            'used' => $this->used->toArray(),
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT);
    }
}
