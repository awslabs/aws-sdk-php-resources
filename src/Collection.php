<?php

namespace Aws\Resource;

use Aws\Common\FlatMapIterator;
use Aws\Common\MapIterator;

class Collection implements \IteratorAggregate
{
    use ResourceTrait;

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
        return new FlatMapIterator($this->results, $this->toBatchFn);
    }

    public function getBatches($size = null)
    {
        if ($size) {
            $chunks = new ChunkedIterator($this->getIterator(), $size);
            return new MapIterator($chunks, function ($resources) {
                return new Batch($this->client, $this->type, $resources);
            });
        } else {
            return new MapIterator($this->results, $this->toBatchFn);
        }
    }

    public function __debugInfo()
    {
        return [
            'object' => 'collection',
            'type'   => $this->type,
        ];
    }
}
