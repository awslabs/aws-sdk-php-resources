<?php

namespace Aws\Resource;

use GuzzleHttp\HasDataTrait;
use GuzzleHttp\ToArrayInterface;

class Resource implements ToArrayInterface, \IteratorAggregate, \ArrayAccess
{
    use ResourceTrait;

    /** @var array */
    protected $data = [];

    /** @var array */
    private $identity;

    /** @var bool */
    private $loaded;

    /** @var array */
    private $meta;

    /**
     * @param ResourceClient $client
     * @param string         $type
     * @param array          $identity
     * @param array          $data
     */
    public function __construct(
        ResourceClient $client,
        $type,
        array $identity,
        array $data = null
    ) {
        $this->client = $client;
        $this->type = $type;
        $this->identity = $identity;
        $this->meta = $this->client->getMetaData($type);

        if (is_array($data)) {
            $this->data = $data;
            $this->loaded = true;
        } else {
            $this->data = [];
            $this->loaded = false;
        }
    }

    public function getMeta()
    {
        return $this->meta;
    }

    public function getIdentity()
    {
        return $this->identity;
    }

    public function getData()
    {
        if (!$this->loaded) {
            $this->load();
        }

        return $this->data;
    }

    public function isLoaded()
    {
        return $this->loaded;
    }

    public function load()
    {
        $this->data = $this->client->loadResourceData($this);
        $this->loaded = true;

        return $this;
    }

    /**
     * Introspects which resources and actions are accessible on this resource.
     *
     * @param string|null $name
     *
     * @return array|bool
     */
    public function respondsTo($name = null)
    {
        if ($name) {
            return isset($this->meta['methods'][$name]);
        } else {
            return array_keys($this->meta['methods']);
        }
    }

    public function getIterator()
    {
        return new \ArrayIterator($this->getData());
    }

    public function offsetGet($offset)
    {
        $data = $this->getData();
        if (isset($data[$offset])) {
            return $data[$offset];
        }

        if (isset($this->identity[$offset])) {
            return $this->identity[$offset];
        }

        return null;
    }

    public function offsetSet($offset, $value)
    {
        throw new \RuntimeException('You cannot mutate a resource\'s data.');
    }

    public function offsetExists($offset)
    {
        $data = $this->getData();

        return isset($data[$offset]) || isset($this->identity[$offset]);
    }

    public function offsetUnset($offset)
    {
        throw new \RuntimeException('You cannot mutate a resource\'s data.');
    }

    public function toArray()
    {
        return $this->getData();
    }

    public function __get($name)
    {
        return $this->handleMethod($name);
    }

    public function __call($name, array $args)
    {
        return $this->handleMethod($name, $args);
    }

    public function __toString()
    {
        $id = '[ ';
        foreach ($this->identity as $k => $v) {
            $id .= "{$k} => {$v}, ";
        }
        $id = rtrim($id, ', ') . ' ]';

        $service = ucfirst($this->meta['serviceName']);

        return "Resource <{$service}.{$this->type}> {$id}";
    }

    public function __debugInfo()
    {
        return $this->meta + [
            'object'   => 'resource',
            'type'     => $this->type,
            'identity' => $this->identity,
            'loaded'   => $this->loaded,
            'data'     => $this->data,
        ];
    }

    private function handleMethod($name, array $args = [])
    {
        $type = isset($this->meta['methods'][$name])
            ? $this->meta['methods'][$name]
            : null;
        $name = ucfirst($name);
        switch ($type) {
            case 'subResources':
                return $this->client->makeSubResource($name, $args, $this);
            case 'belongsTo':
                return $this->client->makeBelongsToResource($name, $args, $this);
            case 'collections':
                return $this->client->makeCollection($name, $args, $this);
            case 'actions':
                return $this->client->performAction($name, $args, $this);
            default:
                throw new \BadMethodCallException(
                    "You cannot call {$name} on the {$this->type} resource."
                );
        }
    }
}