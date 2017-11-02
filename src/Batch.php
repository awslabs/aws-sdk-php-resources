<?php

namespace Aws\Resource;

class Batch implements \Countable, \Iterator
{
    use ResourceTrait;

    private $resources;
    private $index = 0;

    public function __construct(ResourceClient $client, $type, array $resources = [])
    {
        $this->init($client, $type);
        $this->resources = $resources;
    }

    public function respondsTo($name = null)
    {
        if ($name) {
            return in_array($name, $this->meta['batchActions']);
        } else {
            return $this->meta['batchActions'];
        }
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
            'object'       => 'batch',
            'type'         => $this->type,
            'count'        => $this->count(),
            'serviceName'  => $this->meta['serviceName'],
            'batchActions' => $this->meta['batchActions'],
        ];
    }

    public function __call($name, array $args)
    {
        $name = ucfirst($name);
        if (!in_array($name, $this->meta['batchActions'])) {
            print_r($this->meta);
            throw new \BadMethodCallException(
                "You cannot call {$name} on a batch of {$this->type} resources."
            );
        }

        return $this->client->performBatchAction($name, $args, $this);
    }
}
