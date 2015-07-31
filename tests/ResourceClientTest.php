<?php
namespace Aws\Resource\Test;

use Aws\AwsClientInterface;
use Aws\Middleware;
use Aws\Result;
use Aws\Resource\Model;
use Aws\Resource\Resource;
use Aws\Resource\ResourceClient;

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
        $client->getHandlerList()->appendBuild(
            Middleware::tap(function ($c) use (&$command) {
                $command = $c;
            })
        );
        $this->setMockResults($client, [
            new Result(['A' => 1, 'B' => ['C' => 3]])
        ]);

        // Setup model
        $model = $this->getModel('s3', false, function ($data) use ($path) {
            $data['resources']['Object']['load']['path'] = $path;
            return $data;
        });

        // Setup resource client and call loadResourceData
        $rc = new ResourceClient($client, $model);
        $data = $rc->loadResourceData(new Resource($rc, 'Object', [
            'BucketName' => 'foo',
            'Key' => 'bar',
        ]));
        $params = $command->toArray();

        // Filter out stuff we don't want to compare
        unset($data['@metadata']);
        unset($params['@http']);

        // Verify the results are as expected
        $this->assertEquals($expected, $data);
        $this->assertEquals(['Bucket' => 'foo', 'Key' => 'bar'], $params);
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

    public function testCreatingRelatedResourceUsesDataFromParentAndProvidedArgs()
    {
        $rc = new ResourceClient(
            $this->getTestClient('s3'),
            $this->getModel('s3')
        );

        $parent = new Resource($rc, 'Bucket', ['Name' => 'foo']);
        $resource = $rc->makeRelated('Object', ['bar'], $parent);

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
    public function testCreatingRelatedResourceFailsWhenMissingIdentityParts()
    {
        $rc = new ResourceClient(
            $this->getTestClient('s3'),
            $this->getModel('s3')
        );

        $parent = new Resource($rc, 'Bucket', ['Name' => 'foo']);
        $resource = $rc->makeRelated('Object', [], $parent);
    }

    public function testCreatingRelatedResourceReturnsNewResourceForBelongsToTypeRelationship()
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

        $resource = $rc->makeRelated('User', [], $parent);

        $this->assertInstanceOf('Aws\Resource\Resource', $resource);
        $this->assertEquals(['Name' => 'a'], $resource->getIdentity());
    }

    public function testCreatingRelatedResourceWithMultiRelationshipReturnsABatch()
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

        $resources = $rc->makeRelated('Roles', [], $parent);

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

    public function testWaitingForSomethingCallsClientWaiters()
    {
        $client = $this->getTestClient('s3');
        $this->setMockResults($client, [new Result([])]);

        $rc = new ResourceClient($client, $this->getModel('s3'));

        $resource = new Resource($rc, 'Bucket', ['Name' => 'foo']);
        $result = $rc->waitUntil('Exists', [], $resource);

        $this->assertSame($result, $resource);
    }

    public function testCheckingForExistenceCallsClientWaiters()
    {
        $client = $this->getTestClient('s3');
        $this->setMockResults($client, [
            new Result(['@metadata' => ['statusCode' => 200]]),
            new Result(['@metadata' => ['statusCode' => 404]]),
        ]);

        $rc = new ResourceClient($client, $this->getModel('s3'));

        $resource1 = new Resource($rc, 'Bucket', ['Name' => 'existing-bucket']);
        $resource2 = new Resource($rc, 'Bucket', ['Name' => 'not-found-bucket']);
        $this->assertTrue($rc->checkIfExists($resource1));
        $this->assertFalse($rc->checkIfExists($resource2));
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
