<?php
namespace Aws\Resource\Test;

use Aws\Resource\Batch;

/**
 * @covers Aws\Resource\Batch
 */
class BatchTest extends \PHPUnit_Framework_TestCase
{
    public function testCountingBatchReturnsExpectedSize()
    {
        $batch = $this->createBatch('foo', 3, $original);

        $this->assertCount(3, $batch);
        $this->assertSame($original, $batch->toArray());

        $items = [];
        foreach ($batch as $i => $item) {
            $items[$i] = $item;
        }
        $this->assertSame($original, $items);
    }

    public function testDebuggingBatchReturnsMetaData()
    {
        $batch = $this->createBatch('bar', 5);

        $this->assertEquals(
            ['object' => 'batch', 'type' => 'bar', 'count' => 5],
            $batch->__debugInfo()
        );
    }

    private function createBatch($type, $size, &$resources = null)
    {
        $rc = $this->getMockBuilder('Aws\\Resource\\ResourceClient')
            ->disableOriginalConstructor()
            ->getMock();
        $resource = $this->getMockBuilder('Aws\\Resource\\Resource')
            ->disableOriginalConstructor()
            ->getMock();

        $resources = [];
        for ($i = 0; $i < $size; $i++) {
            $resources[$i] = clone $resource;
        }

        return new Batch($rc, $type, $resources);
    }
}