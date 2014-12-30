<?php
namespace Aws\Resource\Test;

use Aws\Resource\Model;

/**
 * @covers Aws\Resource\Model
 */
class ModelTest extends \PHPUnit_Framework_TestCase
{
    use TestHelperTrait;

    public function testInstantiatingAModelAddsAdditionalModelData()
    {
        $data = $this->getModel('s3', true);
        $model = new Model('s3', $data);

        $this->assertArrayHasKey('_meta', $model['service']);
        $this->assertArrayHasKey('subResources', $model['service']);
        $this->assertArrayHasKey('_meta', $model['resources']['Bucket']);
    }
}