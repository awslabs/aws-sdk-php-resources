<?php
namespace Aws\Resource\Test;

use Aws\Resource\Batch;
use Aws\Resource\Collection;
use Aws\Resource\ResourceClient;
use Aws\Resource\Resource;

/**
 * @covers Aws\Resource\Collection
 */
class CollectionTest extends \PHPUnit_Framework_TestCase
{
    public function testDividingACollectionIntoBatchesYieldsCorrectSizes()
    {
        $collection = $this->createCollection('foo', 3, 4);

        $this->assertEquals(12, iterator_count($collection->getIterator()));
        $this->assertEquals(3, iterator_count($collection->getBatches()));
        $this->assertEquals(2, iterator_count($collection->getBatches(7)));

        $batches = iterator_to_array($collection->getBatches(20), false);
        $this->assertCount(1, $batches);
        $this->assertInstanceOf('Aws\Resource\Batch', $batches[0]);
    }

    public function testDebuggingCollectionReturnsMetaData()
    {
        $collection = $this->createCollection('bar', 2, 2);

        $this->assertEquals(
            ['object' => 'collection', 'type' => 'bar'],
            $collection->__debugInfo()
        );
    }

    private function createCollection($type, $n, $m)
    {
        $rc = $this->getMockBuilder(ResourceClient::class)
            ->disableOriginalConstructor()
            ->getMock();

        $fn = function ($whoCares) use ($rc, $type, $m) {
            $resources = [];
            for ($i = 0; $i < $m; $i++) {
                $resources[$i] = $this->getMockBuilder(Resource::class)
                    ->disableOriginalConstructor()
                    ->getMock();
            }

            return new Batch($rc, $type, $resources);
        };

        $iter = new \ArrayIterator(range(1, $n));

        return new Collection($rc, $type, $iter, $fn);
    }
}