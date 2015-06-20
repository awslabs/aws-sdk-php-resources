<?php
namespace Aws\Resource\Test;

use Aws\AwsClientInterface;
use Aws\MockHandler;
use Aws\Result;
use Aws\Resource\Aws;
use Aws\Resource\Model;
use Aws\Sdk;

/**
 * @internal
 */
trait TestHelperTrait
{
    /**
     * @param array $args
     *
     * @return Aws
     */
    private function getTestAws(array $args = [])
    {
        return new Aws($this->getTestSdk($args));
    }

    /**
     * @param array $args
     *
     * @return Sdk
     */
    private function getTestSdk(array $args = [])
    {
        // Disable network access unless INTEGRATION
        if (!isset($_SERVER['INTEGRATION'])) {
            $args['http_handler'] = function () {
                throw new \RuntimeException('No network access.');
            };
        }

        return new Sdk($args + [
            'region'      => 'us-east-1',
            'version'     => 'latest',
            'credentials' => false,
            'retries'     => 0
        ]);
    }

    /**
     * @param string $service
     * @param array  $args
     *
     * @return AwsClientInterface
     */
    private function getTestClient($service, array $args = [])
    {
        return $this->getTestSdk()->createClient($service, $args);
    }

    /**
     * Queues up mock Result objects for a client.
     *
     * @param AwsClientInterface $client
     * @param Result[]           $results
     */
    private function setMockResults(AwsClientInterface $client, array $results)
    {
        $client->getHandlerList()->setHandler(new MockHandler($results));
    }

    /**
     * @param string $service
     *
     * @return Model|array
     */
    private function getModel($service, $raw = false, callable $modifyFn = null)
    {
        static $models = [];
        if (!isset($models[$service])) {
            $paths = glob(dirname(__DIR__) . "/src/models/{$service}-*.resources.php");
            rsort($paths);
            $models[$service] = include reset($paths);
        }

        $data = $models[$service];
        if ($modifyFn) {
            $data = $modifyFn($data);
        }

        return $raw ? $data : new Model($service, $data);
    }
}
