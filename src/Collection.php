<?php

namespace Aws\Resource;

class Collection implements \IteratorAggregate
{
    use HasTypeTrait;

    private $results;

    public function __construct(
        ResourceClient $client,
        $type,
        \Iterator $results,
        callable $toBatchFn
    ) {
        $this->client = $client;
        $this->type = $type;
        $this->results = $results;
        $this->toBatchFn = $toBatchFn;
    }

    public function getIterator()
    {
        return \Aws\flatmap($this->results, $this->toBatchFn);
    }

    public function getBatches($size = null)
    {
        $items = $this->results;
        $mapFn = $this->toBatchFn;

        if ($size) {
            $items = \Aws\partition(\Aws\flatmap($items, $mapFn), $size);
            $mapFn = function ($resources) {
                return new Batch($this->client, $this->type, $resources);
            };
        }

        return \Aws\map($items, $mapFn);
    }

    public function __debugInfo()
    {
        return [
            'object' => 'collection',
            'type'   => $this->type,
        ];
    }
}
