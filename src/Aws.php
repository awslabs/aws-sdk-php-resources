<?php

namespace Aws\Resource;

use Aws\Sdk;

/**
 * @method Resource cloudformation(array $args = [])
 * @property Resource $cloudformation
 * @method Resource ec2(array $args = [])
 * @property Resource $ec2
 * @method Resource s3(array $args = [])
 * @property Resource $glacier
 * @method Resource glacier(array $args = [])
 * @method Resource iam(array $args = [])
 * @property Resource $iam
 * @property Resource $s3
 * @method Resource sqs(array $args = [])
 * @property Resource $sqs
 * @method Resource sns(array $args = [])
 * @property Resource $sns
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
            $apiClient = $this->sdk->getClient($name, $args);
            $model = $this->loadModel($name, $apiClient->getApi()->getApiVersion());
            $resourceClient = new ResourceClient($apiClient, $model);
            $this->services[$name] = new Resource($resourceClient, 'service', [], []);
        }

        return $this->services[$name];
    }

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
}