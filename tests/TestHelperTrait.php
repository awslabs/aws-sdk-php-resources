<?php
namespace Aws\Resource\Test;

use Aws\Common\AwsClientInterface;
use Aws\Common\Result;
use Aws\Resource\Aws;
use Aws\Resource\Model;
use Aws\Sdk;
use GuzzleHttp\Command\Event\PreparedEvent;
use GuzzleHttp\Ring\Client\MockHandler;

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
            $args['ringphp_handler'] = new MockHandler(function () {
                return ['error' => new \RuntimeException('No network access.')];
            });
        }

        return new Sdk($args + [
            'region'      => 'us-east-1',
            'version'     => 'latest',
            'credentials' => false,
            'retries'     => false
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
        return $this->getTestSdk()->getClient($service, $args);
    }

    /**
     * Queues up mock Result objects for a client.
     *
     * @param AwsClientInterface $client
     * @param Result[]           $results
     */
    private function setMockResults(AwsClientInterface $client, array $results)
    {
        $client->getEmitter()->on('prepared',
            function (PreparedEvent $event) use (&$results) {
                $result = array_shift($results);
                if ($result instanceof Result) {
                    $event->intercept($result);
                } else {
                    throw new \Exception('There are no more mock results left. '
                        . 'This client executed more commands than expected.');
                }
            },
            'last'
        );
    }

    /**
     * @param string $service
     *
     * @return Model|array
     */
    private function getModel($service, $raw = false)
    {
        static $models = [];
        if (!isset($models[$service])) {
            $paths = glob(dirname(__DIR__) . "/src/models/{$service}-*.resources.php");
            rsort($paths);
            $models[$service] = include reset($paths);
        }

        return $raw ? $models[$service] : new Model($service, $models[$service]);
    }
}
