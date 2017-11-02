<?php

namespace Aws\Resource;

class Collection implements \IteratorAggregate
{
    use ResourceTrait;

    /** @var \Iterator Iterator of multiple resources of the same type. */
    private $results;

    /** @var callable Function that partitions the collection into batches. */
    private $toBatchFn;

    public function __construct(
        ResourceClient $client,
        $type,
        \Iterator $results,
        callable $toBatchFn
    ) {
        $this->init($client, $type);
        $this->results = $results;
        $this->toBatchFn = $toBatchFn;
    }

    public function respondsTo($name = null)
    {
        if ($name) {
            return in_array($name, $this->meta['batchActions']);
        } else {
            return $this->meta['batchActions'];
        }
    }

    public function getIterator()
    {
        return \Aws\flatmap($this->results, $this->toBatchFn);
    }

    public function toArray()
    {
        return iterator_to_array($this->getIterator());
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

        return new BatchIterator(\Aws\map($items, $mapFn));
    }

    public function __call($name, array $args)
    {
        foreach ($this->getBatches() as $batch) {
            $batch->__call($name, $args);
        }
    }

    public function __debugInfo()
    {
        return [
            'object'       => 'collection',
            'type'         => $this->type,
            'serviceName'  => $this->meta['serviceName'],
            'batchActions' => array_keys($this->meta['batchActions']),
        ];
    }
}
