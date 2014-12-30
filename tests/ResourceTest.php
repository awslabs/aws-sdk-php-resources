<?php
namespace Aws\Resource\Test;

use Aws\Resource\Resource;

/**
 * @covers Aws\Resource\Resource
 * @covers Aws\Resource\ResourceTrait
 */
class ResourceTest extends \PHPUnit_Framework_TestCase
{
    use TestHelperTrait;

    public function testAccessingMetaInfoAboutResourceObjectsWorks()
    {
        $bucket = $this->getTestAws()->s3->bucket('foo');

        // Test getType() & getIdentity()
        $this->assertEquals('Bucket', $bucket->getType());
        $this->assertEquals(['Name' => 'foo'], $bucket->getIdentity());

        // Test respondsTo()
        $this->assertTrue($bucket->respondsTo('create'));
        $this->assertTrue($bucket->respondsTo('objectVersions'));
        $this->assertFalse($bucket->respondsTo('fooBar'));
        $this->assertContains('create', $bucket->respondsTo());

        // Test isLoaded()
        $this->assertFalse($bucket->isLoaded());

        // Test getClient()
        $client = $bucket->getClient();
        $this->assertInstanceOf('Aws\\Common\\AwsClientInterface', $client);

        // Test __toString()
        $this->assertEquals('Resource <S3.Bucket> [ Name => foo ]', $bucket);

        // Test __debugInfo() and getMeta()
        $this->assertEmpty(array_diff(
            array_keys($bucket->getMeta()),
            array_keys($bucket->__debugInfo()),
            ['object' => 'resource']
        ));
    }

    public function testAccessingResourceDataTriggersLoad()
    {
        $bucket = $this->getTestAws()->s3->bucket('foo');

        // Test toArray() and getIterator()
        // Note: Implicitly calls getData() and load()
        $this->assertEquals($bucket->toArray(), iterator_to_array($bucket));
    }

    public function testAccessingDataViaArrayAccessChecksDataAndIdentity()
    {
        $resource = new Resource(
            $this->getMockBuilder('Aws\\Resource\\ResourceClient')
                ->disableOriginalConstructor()
                ->getMock(),
            'whatever',
            ['a' => 'b', 'b' => 'c'],
            ['b' => 'z', 'c' => 'd']
        );

        $this->assertEquals('b', $resource['a']);
        $this->assertEquals('z', $resource['b']);
        $this->assertEquals('d', $resource['c']);
        $this->assertEquals(null, $resource['d']);

        $this->assertTrue(isset($resource['a']));
        $this->assertTrue(isset($resource['b']));
        $this->assertTrue(isset($resource['c']));
        $this->assertFalse(isset($resource['d']));
    }

    public function testModifyingDataFromResourceObjectFails()
    {
        $bucket = $this->getTestAws()->s3->bucket('foo');
        $this->setExpectedException('RuntimeException');
        $bucket['Name'] = 'bar';
    }

    public function testDeletingDataFromResourceObjectFails()
    {
        $bucket = $this->getTestAws()->s3->bucket('foo');
        $this->setExpectedException('RuntimeException');
        unset($bucket['Name']);
    }

    public function testAccessingRelationshipsWorksForAllTypes()
    {
        $rc = $this->getMockBuilder('Aws\\Resource\\ResourceClient')
            ->disableOriginalConstructor()
            ->setMethods([
                 'getMetaData',
                 'makeSubResource',
                 'makeBelongsToResource',
                 'performAction',
                 'makeCollection'
            ])
            ->getMock();
        $rc->expects($this->once())->method('getMetaData')->willReturn([
            'actions'      => ['A'],
            'belongsTo'    => ['B'],
            'collections'  => ['C'],
            'subResources' => ['D'],
            'methods'      => [
                'a' => 'actions',
                'b' => 'belongsTo',
                'c' => 'collections',
                'd' => 'subResources'
            ]
        ]);
        $rc->expects($this->once())->method('makeSubResource');
        $rc->expects($this->once())->method('makeBelongsToResource');
        $rc->expects($this->once())->method('performAction');
        $rc->expects($this->once())->method('makeCollection');

        $resource = new Resource($rc, 'Thing', []);
        foreach ($resource->respondsTo() as $property) {
            $resource->{$property};
        }

        $this->setExpectedException('BadMethodCallException');
        $resource->explode();
    }
}