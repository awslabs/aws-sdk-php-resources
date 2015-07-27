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

        $service = $model->search('service');
        $this->assertArrayHasKey('_meta', $service);
        $this->assertArrayHasKey('has', $service);
        $this->assertArrayHasKey('_meta', $model->search('resources.Bucket'));
    }
}
