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

    public function current()
    {
        return $this->resources[$this->index];
    }

    public function key()
    {
        return $this->index;
    }

    public function valid()
    {
        return isset($this->resources[$this->index]);
    }

    public function next()
    {
        $this->index++;
    }

    public function rewind()
    {
        $this->index = 0;
    }

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
