<?php
namespace Aws\Resource\Test;

use Aws\Resource\Batch;
use Aws\Resource\Collection;

/**
 * @covers Aws\Resource\Collection
 */
class CollectionTest extends \PHPUnit_Framework_TestCase
{
    public function testDividingACollectionIntoBatchesYieldsCorrectSizes()
    {
        $collection = $this->createCollection('foo', 3, 4);

        $this->assertCount(12, $collection);
        $this->assertCount(3, $collection->getBatches());
        $this->assertCount(2, $collection->getBatches(7));

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
        $rc = $this->getMockBuilder('Aws\\Resource\\ResourceClient')
            ->disableOriginalConstructor()
            ->getMock();
        $resource = $this->getMockBuilder('Aws\\Resource\\Resource')
            ->disableOriginalConstructor()
            ->getMock();

        $fn = function ($whoCares) use ($rc, $resource, $type, $m) {
            $resources = [];
            for ($i = 0; $i < $m; $i++) {
                $resources[$i] = clone $resource;
            }

            return new Batch($rc, $type, $resources);
        };

        $iter = new \ArrayIterator(range(1, $n));

        return new Collection($rc, $type, $iter, $fn);
    }
}