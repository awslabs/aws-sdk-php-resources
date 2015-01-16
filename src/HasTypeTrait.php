<?php

namespace Aws\Resource;

use Aws\AwsClientInterface;

/**
 * Contains common properties and methods of the Resource, Batch, and Collection
 * objects and is not intended for external use.
 *
 * @internal
 */
trait HasTypeTrait
{
    /** @var ResourceClient */
    private $client;

    /** @var string */
    private $type;

    /**
     * @return AwsClientInterface
     */
    public function getClient()
    {
        return $this->client->getApiClient();
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }
}
