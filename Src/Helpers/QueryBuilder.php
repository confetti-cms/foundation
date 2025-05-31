<?php

namespace ConfettiCms\Foundation\Helpers;

class QueryBuilder
{
    private const MODEL_PREFIX = '/model/';

    private array $queryStack = [];
    private array $query;

    public function __construct(string $from = '', string $as = null)
    {
        $this->newQuery($from, $as);
    }

    public function getQueryStack(): array
    {
        return $this->queryStack;
    }

    public function replaceFrom(string $relativeId): void
    {
        $this->query['from'] = $relativeId;
    }

    /**
     * @throws \JsonException
     */
    public function run(): array
    {
        $client   = new Client();

        // Use static to exit when second time called
        static $nr = 0;
        $nr++;

        $response = $client->get('confetti-cms__db/contents', [
            'accept' => 'application/json',
        ], $this->getFullQuery());

        if ($nr === 10000) {
            throw new \RuntimeException('Too many database requests (10000)');
        }

        return json_decode($response, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @param string[] $options
     */
    public function setOptions(array $options): self
    {
        $this->query['options'] = $options;
        return $this;
    }

    /**
     * @param string[] $select
     */
    public function setSelect(array $select): self
    {
        $this->query['select'] = $select;
        return $this;
    }

    /**
     * @param string[] $select
     */
    public function appendSelect(...$select): self
    {
        $this->query['select'] = array_merge($this->query['select'], $select);
        return $this;
    }

    /**
     * @param string[] $select
     */
    public function appendSelectInRoot(...$select): void
    {
        if (empty($this->queryStack)) {
            $this->appendSelect(...$select);
            return;
        }
        $this->queryStack[0]['select'] = array_merge($this->queryStack[0]['select'], $select);
    }

    public function wrapJoin(string $parent, string $relativeFrom, string $as = null): void
    {
        // We don't want to select anything from the parent
        $this->query['select'] = [];
        $this->query['from']   = $parent;
        $this->queryStack[]    = $this->query;
        $this->newQuery($relativeFrom, $as);
    }

    public function ignoreFirstRow(): void
    {
        // Ignore first row of this level
        $limit = $this->getLimit();
        if ($limit !== null) {
            $this->setLimit($limit - 1);
        }
        $offset = $this->getOffset();
        $this->setOffset($offset + 1);
    }

    public function appendWhere(string $key, string $operator, mixed $value): self
    {
        if ($value !== null && str_starts_with($value, self::MODEL_PREFIX)) {
            $this->query['where'][] = [
                'key'            => $key,
                'operator'       => $operator,
                'expression_key' => $value,
            ];
            return $this;
        }

        $this->query['where'][] = [
            'key'              => $key,
            'operator'         => $operator,
            'expression_value' => $value,
        ];

        return $this;
    }

    public function getWhere(): array
    {
        return $this->query['where'] ?? [];
    }

    public function appendOrderBy(string $key, string $direction = 'ascending'): self
    {
        $this->query['order_by'][] = [
            'key'       => $key,
            'direction' => $direction,
        ];

        return $this;
    }

    public function getOrderBy(): array
    {
        return $this->query['order_by'] ?? [];
    }

    public function getLimit(): ?int
    {
        return $this->query['limit'] ?? null;
    }

    public function setLimit(int $limit): self
    {
        $this->query['limit'] = $limit;
        return $this;
    }

    public function getOffset(): int
    {
        return $this->query['offset'] ?? 0;
    }

    public function setOffset(int $offset): self
    {
        $this->query['offset'] = $offset;
        return $this;
    }

    public function resetConditions(): void
    {
        $this->query['where'] = [];
        $this->query['order_by'] = [];
        $this->query['limit'] = null;
        $this->query['offset'] = 0;
    }

    private function getFullQuery(): array
    {
        $result  = $this->query;
        $options = $result['options'] ?? [];
        unset($result['options']);
        foreach (array_reverse($this->queryStack) as $parent) {
            $parent['join'] = [$result];
            $result         = $parent;
        }
        // Only the root query should have the options
        $result['options'] = $options;
        return $result;
    }

    /**
     * This function returns the current condition of the query
     * With this condition, we can check if the desired condition
     * is met with the condition of the already retrieved content.
     */
    public function getCurrentCondition($query): string
    {
        $parentQuery = $query ?? null;
        $result = "";
        foreach ($parentQuery['where'] ?? [] as $i => $where) {
            $prefix = $i == 0 ? 'where' : 'and';
            $expression = $where['expression_key'] ?? '';
            if ($expression === '') {
                $expression = $where['expression_value'] ?? 'null';
            }
            $result .= sprintf(" %s %s %s %s", $prefix, $where['key'], $where['operator'], $expression);
        }
        foreach ($parentQuery['order_by'] ?? [] as $i => $orderBy) {
            $prefix = $i == 0 ? ' order_by' : ',';
            $result .= "{$prefix} {$orderBy['key']} {$orderBy['direction']}";
        }
        if (($parentQuery['limit'] ?? 0) > 0) {
            $result .= " limit {$parentQuery['limit']}";
        }
        if (($parentQuery['offset'] ?? 0) > 0) {
            $result .= " offset {$parentQuery['offset']}";
        }
        return ltrim($result, ' ');
    }

    private function newQuery(string $from, ?string $as): void
    {
        $this->query = [
            'select'  => [],
        ];
        $this->query['from'] = $from;
        if ($as) {
            $this->query['as'] = $as;
        }
    }
}