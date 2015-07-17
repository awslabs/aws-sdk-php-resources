<?php
namespace Aws\Resource\Test;

use Aws\AwsClientInterface;
use Aws\Command;
use Aws\CommandPool;
use Aws\Middleware;
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

        $this->attachCommandMiddleware($aws->s3->getClient());
        if (!$aws->s3->respondsTo('createBucket')) {
            $this->fail('Methods are not available.');
        }

        $this->log('Creating a bucket...');
        $bucket = $aws->s3->createBucket([
            'Bucket' => uniqid('php-resources-test-')
        ]);
        $bucket->waitUntilExists();

        $this->log('Loading the bucket...');
        $this->assertFalse($bucket->isLoaded());
        $bucket->load();
        $this->assertTrue($bucket->isLoaded());

        $this->log('Uploading an object...');
        $object1 = $bucket->object('test-file1');
        $result = $object1->put(['Body' => 'foo']);
        $this->assertEquals(
            "https://s3.amazonaws.com/{$bucket['Name']}/{$object1['Key']}",
            $result['ObjectURL']
        );

        $this->log('Uploading another object...');
        $object2 = $bucket->putObject(['Key' => 'test-file2', 'Body' => 'foo']);
        $this->assertEquals(
            "https://s3.amazonaws.com/{$bucket['Name']}/test-file2",
            $bucket->getClient()->getObjectUrl($object2['BucketName'], $object2['Key'])
        );

        $this->log('Getting an object...');
        $result = $object1->get();
        $this->assertEquals('foo', (string) $result['Body']);

        $this->log('Deleting the objects and bucket...');
        $object1->delete();
        $object2->delete();
        $object2->bucket->delete();
    }

    public function testWorkflowWithCollections()
    {
        $aws = new Aws([
            'profile' => 'integ',
            'version' => 'latest',
            'region'  => 'us-west-2',
        ]);

        $this->attachCommandMiddleware($aws->s3->getClient());

        $this->log('Creating a bucket...');
        $bucket = $aws->s3->createBucket([
            'Bucket' => uniqid('php-resources-test-'),
            'CreateBucketConfiguration' => [
                'LocationConstraint' => 'us-west-2',
            ]
        ]);
        $bucket->waitUntilExists();

        $this->log('Uploading 20 dummy objects...');
        CommandPool::batch($bucket->getClient(), array_map(
            function ($value) use ($bucket) {
                return $bucket->getClient()->getCommand('PutObject', [
                    'Bucket' => $bucket['Name'],
                    'Key'    => ($value <= 10 ? 'foo' : 'bar') . $value,
                ]);
            },
            range(1, 20)
        ));

        $this->log('Creating a collection of objects with prefix "bar"...');
        $objects = $bucket->objects(['Prefix' => 'bar']);
        $this->assertEquals(10, iterator_count($objects));

        $this->log('Creating a collection from zero results...');
        $objects = $bucket->objects(['Prefix' => 'invalid']);
        $this->assertEquals(0, iterator_count($objects));

        $this->log('Getting batches of objects to count and delete...');
        $batches = $bucket->objects->getBatches(6);
        $counts = [];
        foreach ($batches as $i => $batch) {
            $counts[$i] = 0;
            foreach ($batch as $object) {
                $counts[$i]++;
                $object->delete();
            }
        }
        $this->log('Verifying batch sizes...');
        $this->assertSame([6, 6, 6, 2], $counts);

        $this->log('Deleting the bucket...');
        $bucket->delete();
    }

    private function log($message)
    {
        fwrite(STDOUT, $message . "\n");
    }

    private function attachCommandMiddleware(AwsClientInterface $client)
    {
        $client->getHandlerList()->appendBuild(Middleware::tap(function (Command $command) {
            $this->log(
                '> Executed a "' . $command->getName(). '" command'
                . ($command['Key'] ? " (Key: {$command['Key']})." : '.')
            );
        }));
    }
}
