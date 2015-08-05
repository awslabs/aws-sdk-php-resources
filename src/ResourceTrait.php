<?php

namespace Aws\Resource;

use Aws\AwsClientInterface;
use Aws\Result;

/**
 * Contains common properties and methods of Resource, Batch, and Collection
 * objects in order to standardize their APIs.
 */
trait ResourceTrait
{
    /** @var ResourceClient Low-level API client associated with this object. */
    private $client;

    /** @var string Resource type associated with this object. */
    private $type;

    /** @var array Resource metadata (e.g., actions, relationships, etc.) */
    private $meta;

    /**
     * Common constructor work for Resource, Batch, and Collection objects.
     *
     * @param ResourceClient $client
     * @param string         $type
     */
    private function init(ResourceClient $client, $type)
    {
        $this->client = $client;
        $this->type = $type;
        $this->meta = $client->getMetaData($type);
    }

    /**
     * Returns the low-level API client associated with this object.
     *
     * @return AwsClientInterface
     */
    public function getClient()
    {
        return $this->client->getApiClient();
    }

    /**
     * Returns the type of the resource associated with this object.
     *
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Introspects which actions are accessible on this object.
     *
     * @param string|null $name Action to check, or null to see all actions.
     *
     * @return bool|array
     */
    abstract public function respondsTo($name = null);

    /**
     * Creates an array representation of the object.
     *
     * @return array
     */
    abstract public function toArray();

    /**
     * Handles all actions and relationships based on the resource model.
     *
     * @param $name
     * @param array $args
     * @return Resource|Batch|Collection|Result|void
     */
    abstract public function __call($name, array $args);

    /**
     * Creates a representation of the object for debugging.
     *
     * @return array
     */
    abstract public function __debugInfo();
}
