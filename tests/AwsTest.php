<?php
namespace Aws\Resource\Test;

use Aws\Resource\Aws;
use Aws\Sdk;
use Aws\Signature\S3SignatureV4;

/**
 * @covers Aws\Resource\Aws
 */
class AwsTest extends \PHPUnit_Framework_TestCase
{
    use TestHelperTrait;

    public function testInstantiatingTheResourceSdkWorksWithArgsOrSdkObject()
    {
        $config = ['version' => 'latest', 'region' => 'us-east-1'];
        $aws_1 = new Aws($config);
        $aws_2 = new Aws(new Sdk($config));

        $this->assertInstanceOf('Aws\\Resource\\Aws', $aws_1);
        $this->assertInstanceOf('Aws\\Resource\\Aws', $aws_2);
    }

    public function testCreatingServiceWithoutArgsReturnsSameObject()
    {
        $aws = $this->getTestAws();
        $s3_1 = $aws->s3;
        $s3_2 = $aws->s3;

        $this->assertInstanceOf('Aws\\Resource\\Resource', $s3_1);
        $this->assertEquals('s3', $s3_1->getMeta()['serviceName']);
        $this->assertSame($s3_1, $s3_2);
    }

    public function testCreatingServiceWithArgsOverwritesConfig()
    {
        $s3 = $this
            ->getTestAws(['signature' => 'v3'])
            ->s3(['signature' => 'v4']);

        $this->assertInstanceOf(
            S3SignatureV4::class,
            $s3->getClient()->getSignature()
        );
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage The resources model file
     */
    public function testAccessingUnsupportedResourceServiceThrowsException()
    {
        $aws = $this->getTestAws();
        $ie = $aws->importexport;
    }

    public function testCallingRespondstoShowsServicesThatCanBeCreated()
    {
        $aws = $this->getTestAws();
        $this->assertTrue($aws->respondsTo('s3'));
        $this->assertTrue($aws->respondsTo('iam'));
        $this->assertFalse($aws->respondsTo('foo'));
        $this->assertContains('s3', $aws->respondsTo());
    }
}