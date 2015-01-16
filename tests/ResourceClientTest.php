<?php
namespace Aws\Resource\Test;

use Aws\AwsClientInterface;
use Aws\Result;
use Aws\Resource\Model;
use Aws\Resource\Resource;
use Aws\Resource\ResourceClient;
use GuzzleHttp\Command\Event\PreparedEvent;

/**
 * @covers Aws\Resource\ResourceClient
 */
class ResourceClientTest extends \PHPUnit_Framework_TestCase
{
    use TestHelperTrait;

    public function testInstantiatingClientAndGettersWork()
    {
        $apiClient = $this->getMock(AwsClientInterface::class);
        $model = new Model('s3', ['service' => [], 'resources' => []]);

        $resourceClient = new ResourceClient($apiClient, $model);
        $this->assertSame($apiClient, $resourceClient->getApiClient());
        $this->assertSame($model, $resourceClient->getModel());

        $meta = $resourceClient->getMetaData('service');
        $this->assertEquals('s3', $meta['serviceName']);
        $this->assertArrayHasKey('actions', $meta);
    }

    /**
     * @dataProvider getTestCasesForLoadingAResource
     */
    public function testLoadingAResourceRetrievesItsData($path, array $expected)
    {
        // Setup API client
        $client = $this->getTestClient('s3');
        $client->getEmitter()->on(
            'prepared',
            function (PreparedEvent $e) use (&$command) {
                $command = $e->getCommand();
            }
        );
        $this->setMockResults($client, [
            new Result(['A' => 1, 'B' => ['C' => 3]])
        ]);

        // Setup model
        $model = $this->getModel('s3');
        $model->setPath('resources/Object/load/path', $path);

        // Setup resource client and call loadResourceData
        $rc = new ResourceClient($client, $model);
        $data = $rc->loadResourceData(new Resource($rc, 'Object', [
            'BucketName' => 'foo',
            'Key' => 'bar',
        ]));

        $this->assertEquals($expected, $data);
        $this->assertEquals(
            ['Bucket' => 'foo', 'Key' => 'bar'],
            $command->toArray()
        );
    }

    public function getTestCasesForLoadingAResource()
    {
        return [
            ['@', ['A' => 1, 'B' => ['C' => 3]]],
            ['B', ['C' => 3]],
            ['D', []],
        ];
    }

    public function testLoadingAResourceWithoutALoadOperationReturnsEmptyData()
    {
        $rc = new ResourceClient(
            $this->getTestClient('s3'),
            $this->getModel('s3')
        );
        $data = $rc->loadResourceData(
            new Resource($rc, 'Bucket', ['Name' => 'foo'])
        );

        $this->assertEquals([], $data);
    }

    public function testCreatingSubresourceUsesDataFromParentAndProvidedArgs()
    {
        $rc = new ResourceClient(
            $this->getTestClient('s3'),
            $this->getModel('s3')
        );

        $parent = new Resource($rc, 'Bucket', ['Name' => 'foo']);
        $resource = $rc->makeSubResource('Object', ['bar'], $parent);

        $this->assertEquals('Object', $resource->getType());
        $this->assertEquals(
            ['BucketName' => 'foo', 'Key' => 'bar'],
            $resource->getIdentity()
        );
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Invalid identity.
     */
    public function testCreatingSubresourceFailsWhenMissingIdentityParts()
    {
        $rc = new ResourceClient(
            $this->getTestClient('s3'),
            $this->getModel('s3')
        );

        $parent = new Resource($rc, 'Bucket', ['Name' => 'foo']);
        $resource = $rc->makeSubResource('Object', [], $parent);
    }

    public function testAccessingABelongsToRelationshipReturnsNewResource()
    {
        $rc = new ResourceClient(
            $this->getTestClient('iam'),
            $this->getModel('iam')
        );

        $parent = new Resource(
            $rc,
            'VirtualMfaDevice',
            ['SerialNumber' => 'foo'],
            [
                'User' => [
                    'UserName' => 'a',
                    'UserId'   => 'b',
                    'Arn'      => 'c',
                ]
            ]
        );

        $resource = $rc->makeBelongsToResource('User', [], $parent);

        $this->assertInstanceOf('Aws\Resource\Resource', $resource);
        $this->assertEquals(['Name' => 'a'], $resource->getIdentity());
    }

    public function testResolvingABelongsToMultiRelationshipReturnsABatch()
    {
        $rc = new ResourceClient(
            $this->getTestClient('iam'),
            $this->getModel('iam')
        );

        $parent = new Resource(
            $rc,
            'InstanceProfile',
            ['Name' => 'foo'],
            ['Roles' => [
                ['RoleName' => 'a'],
                ['RoleName' => 'b'],
                ['RoleName' => 'c'],
            ]]
        );

        $resources = $rc->makeBelongsToResource('Roles', [], $parent);

        $this->assertInstanceOf('Aws\Resource\Batch', $resources);

        $data = [];
        foreach ($resources as $resource) {
            $data[] = $resource->getIdentity();
        }

        $this->assertEquals([
            ['Name' => 'a'],
            ['Name' => 'b'],
            ['Name' => 'c'],
        ], $data);
    }

    public function testPerformingAnActionCanReturnResourcesOrResults()
    {
        // Setup client and parent resource for test.
        $client = $this->getTestClient('s3');
        $this->setMockResults($client, [
            new Result(['bucket' => 1]),
            new Result(['bucket' => 2])
        ]);
        $rc = new ResourceClient($client, $this->getModel('s3'));
        $s3 = new Resource($rc, 'service', [], []);;

        // Perform action that returns resource.
        $bucket = $rc->performAction('CreateBucket', [['Bucket' => 'foo']], $s3);
        $this->assertInstanceOf(Resource::class, $bucket);
        $this->assertEquals('Bucket', $bucket->getType());
        $this->assertEquals(['Name' => 'foo'], $bucket->getIdentity());

        // Perform action that returns result.
        $result = $rc->performAction('Create', [], $bucket);
        $this->assertInstanceOf('Aws\ResultInterface', $result);
    }
}