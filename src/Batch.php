<?php

namespace Aws\Resource;

class Batch implements \Countable, \Iterator
{
    use HasTypeTrait;

    private $resources;
    private $index = 0;

    public function __construct(ResourceClient $client, $type, array $resources = [])
    {
        $this->init($client, $type);
        $this->resources = $resources;
    }

    /**
     * Introspects which actions are accessible on this batch.
     *
     * @param string|null $name
     *
     * @return array|bool
     */
    public function respondsTo($name = null)
    {
        if ($name) {
            return isset($this->meta['batchActions'][$name]);
        } else {
            return array_keys($this->meta['batchActions']);
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
            'batchActions' => array_keys($this->meta['batchActions']),
        ];
    }

    public function __call($name, array $args)
    {
        $name = ucfirst($name);
        if (!isset($this->meta['batchActions'][$name])) {
            throw new \BadMethodCallException(
                "You cannot call {$name} on a batch of {$this->type} resources."
            );
        }

        return $this->client->performBatchAction($name, $args, $this);
    }
}
