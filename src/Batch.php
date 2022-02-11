<?php

namespace Aws\Resource;

class Batch implements \Countable, \Iterator
{
    use HasTypeTrait;

    private $resources;
    private $index = 0;

    public function __construct(ResourceClient $client, $type, array $resources = [])
    {
        $this->client = $client;
        $this->type = $type;
        $this->resources = $resources;
    }

    #[\ReturnTypeWillChange]
    public function current()
    {
        return $this->resources[$this->index];
    }

    #[\ReturnTypeWillChange]
    public function key()
    {
        return $this->index;
    }

    #[\ReturnTypeWillChange]
    public function valid()
    {
        return isset($this->resources[$this->index]);
    }

    #[\ReturnTypeWillChange]
    public function next()
    {
        $this->index++;
    }

    #[\ReturnTypeWillChange]
    public function rewind()
    {
        $this->index = 0;
    }

    #[\ReturnTypeWillChange]
    public function count()
    {
        return count($this->resources);
    }

    public function toArray()
    {
        return $this->resources;
    }

    public function __debugInfo()
    {
        return [
            'object' => 'batch',
            'type'   => $this->type,
            'count'  => $this->count(),
        ];
    }
}
