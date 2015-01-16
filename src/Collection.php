<?php

namespace Aws\Resource;

use transducers as t;

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
        return t\to_iter($this->results, t\mapcat($this->toBatchFn));
    }

    public function getBatches($size = null)
    {
        if ($size) {
            $xf = t\comp(
                t\mapcat($this->toBatchFn),
                t\partition($size),
                t\map(function ($resources) {
                    return new Batch($this->client, $this->type, $resources);
                })
            );
        } else {
            $xf = t\map($this->toBatchFn);
        }

        return t\to_iter($this->results, $xf);
    }

    public function __debugInfo()
    {
        return [
            'object' => 'collection',
            'type'   => $this->type,
        ];
    }
}
