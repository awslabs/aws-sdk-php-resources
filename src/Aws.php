<?php

namespace Aws\Resource;

use Aws\Sdk;

/**
 * Service locator and starting point of the AWS Resource APIs.
 *
 * @method Resource cloudformation(array $args = [])
 * @method Resource ec2(array $args = [])
 * @method Resource s3(array $args = [])
 * @method Resource glacier(array $args = [])
 * @method Resource iam(array $args = [])
 * @method Resource sqs(array $args = [])
 * @method Resource sns(array $args = [])
 * @property \Aws\Resource\Resource $cloudformation
 * @property \Aws\Resource\Resource $ec2
 * @property \Aws\Resource\Resource $s3
 * @property \Aws\Resource\Resource $glacier
 * @property \Aws\Resource\Resource $iam
 * @property \Aws\Resource\Resource $sqs
 * @property \Aws\Resource\Resource $sns
 */
class Aws
{
    /** @var Sdk Instance of Sdk for creating API clients. */
    private $sdk;

    /** @var array Cache of service clients. */
    private $services = [];

    /**
     * @param Sdk|array $args
     */
    public function __construct($args = [])
    {
        $this->sdk = ($args instanceof Sdk) ? $args : new Sdk($args);
    }

    /**
     * @param string $name
     *
     * @return Resource
     */
    public function __get($name)
    {
        return $this->makeService($name);
    }

    /**
     * @param string $name
     * @param array  $args
     *
     * @return Resource
     */
    public function __call($name, $args)
    {
        return $this->makeService($name, $args ? $args[0] : []);
    }

    /**
     * Introspects which resources are accessible to call on this object.
     *
     * @param string|null $name
     *
     * @return array|bool
     */
    public function respondsTo($name = null)
    {
        static $services;

        if (!$services) {
            $services = [];
            foreach (glob(__DIR__ . "/models/*.resources.php") as $file) {
                $services[] = substr(basename($file), 0, -25);
            }
            $services = array_unique($services);
        }

        return $name ? in_array($name, $services, true) : $services;
    }

    /**
     * @param string $name
     * @param array  $args
     *
     * @return mixed
     */
    private function makeService($name, array $args = [])
    {
        if (!isset($this->services[$name]) || $args) {
            $apiClient = $this->sdk->createClient($name, $args);
            $model = $this->loadModel($name, $apiClient->getApi()->getApiVersion());
            $resourceClient = new ResourceClient($apiClient, $model);
            $this->services[$name] = new Resource($resourceClient, 'service', [], []);
        }

        return $this->services[$name];
    }

    /**
     * @param string $service
     * @param string $version
     *
     * @return Model
     * @throws \RuntimeException
     */
    private function loadModel($service, $version)
    {
        $path = __DIR__ . "/models/{$service}-{$version}.resources.php";
        if (!is_readable($path)) {
            throw new \RuntimeException(
                "The resources model file \"{$path}\" was not found."
            );
        }

        return new Model($service, include $path);
    }

    public function __toString()
    {
        return "Resource <AWS> [ ]";
    }
}
