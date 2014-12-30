<?php
namespace Aws\Resource\Test;

use Aws\Resource\Aws;

class IntegrationTest extends \PHPUnit_Framework_TestCase
{
    public function testCreationAndTearDownOfResources()
    {
        $aws = new Aws([
            'profile' => 'integ',
            'version' => 'latest',
            'region'  => 'us-east-1',
        ]);

        if (!$aws->s3->respondsTo('createBucket')) {
            $this->fail('Methods are not available.');
        }

        $bucket = $aws->s3->createBucket([
            'Bucket' => uniqid('php-resources-test-')
        ]);

        $this->assertFalse($bucket->isLoaded());
        $bucket->getClient()->waitUntil('BucketExists', [
            'Bucket' => $bucket['Name']
        ]);
        $this->assertTrue($bucket->isLoaded());

        $object = $bucket->object('test-file');
        $result = $object->put(['Body' => 'foo']);
        $this->assertEquals(
            "https://{$bucket['Name']}.s3.amazonaws.com/{$object['Key']}",
            $result['ObjectURL']
        );
        // Could have also done it this way:
        // $object = $bucket->putObject(['Key' => 'test-file', 'Body' => 'foo']);

        $result = $object->get();
        $this->assertEquals('foo', (string) $result['Body']);

        $object->delete();
        $bucket->delete();
    }

    public function testWorkflowWithCollections()
    {
        $aws = new Aws([
            'profile' => 'integ',
            'version' => 'latest',
            'region'  => 'us-west-2',
        ]);

        $bucket = $aws->s3->createBucket([
            'Bucket' => uniqid('php-resources-test-'),
            'CreateBucketConfiguration' => [
                'LocationConstraint' => 'us-west-2',
            ]
        ]);
        $bucket->getClient()->waitUntil('BucketExists', [
            'Bucket' => $bucket['Name']
        ]);

        $bucket->getClient()->executeAll(array_map(
            function ($value) use ($bucket) {
                return $bucket->getClient()->getCommand('PutObject', [
                    'Bucket' => $bucket['Name'],
                    'Key'    => ($value <= 10 ? 'foo' : 'bar') . $value,
                ]);
            },
            range(1, 20)
        ));

        $objects = $bucket->objects(['Prefix' => 'bar']);
        $this->assertCount(10, $objects);

        $objects = $bucket->objects(['Prefix' => 'invalid']);
        $this->assertCount(0, $objects);

        $batches = $bucket->objects->getBatches(6);
        $this->assertCount(4, $batches);

        $counts = [];
        foreach ($batches as $batch) {
            $counts[] = count($batch);
            foreach ($batch as $object) {
                $object->delete();
            }
        }
        $this->assertSame([6, 6, 6, 2], $counts);

        $bucket->delete();
    }
}