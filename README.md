# AWS Resource APIs for PHP

An extension to the [AWS SDK for PHP] for interacting with AWS services using
resource-oriented objects.

## Introduction

The core AWS SDK for PHP is composed of service client objects that have methods
corresponding 1-to-1 with operations in the service's API (e.g.,
[`Ec2Client::runInstances()` method][op-php] maps to the [EC2 service's
RunInstances operation][op-ec2]).

This project builds build upon the SDK to add new types of objects that allow
you to interact with the AWS service APIs in a more _resource-oriented_ way.
This allows you to use a more expressive syntax when working with AWS services,
because you are acting on objects that understand their relationships with other
resources and that encapsulate their identifying information.

## Installation

You must install the AWS Resource APIs using [Composer] by requiring the
[`aws/aws-sdk-php-resources` package][package] in your project.

**Note:** The Resource APIs use [Version 3 of the AWS SDK for PHP][v3].

## Types of Objects

The Resource APIs introduce 4 new objects, all within the `Aws\Resource`
namespace.

### 1. Aws

The `Aws` object acts as the starting point into the resource APIs.

```php
<?php

require 'vendor/autoload.php';

use Aws\Resource\Aws;

$aws = new Aws($config);

// Get a resource representing the S3 service.
$s3 = $aws->s3;
```

The `$config`, as provided in the preceding example, is an array of
configuration options that is the same as what you would provide when
instantiating the `Aws\Sdk` object in the core SDK. This includes things like
`'region'`, `'version'`, your credentials, etc.

You can overwrite the global config options for a service by specifying new
values when you get the service's resource.

```php
$s3 = $aws->s3(['region' => 'eu-central-1']);
```

The AWS Resource APIs currently supports 7 services (`cloudformation`, `ec2`,
`glacier`, `iam`, `s3`, `sns`, `sqs`).

### 2. Resource

Resource objects each represent a single, identifiable AWS resource (e.g., an
Amazon S3 bucket or an Amazon SQS queue). They contain information about how to
identify the resource and load its data, the actions that can be performed on
it, and the other resources to which it is related.

You can access a related resource by calling the related resource's name as a
method and passing in its identity.

```php
$bucket = $aws->s3->bucket('my-bucket');
$object = $bucket->object('image/bird.jpg');
```

Accessing resources this way is evaluated lazily, meaning that the previous
example does not actually make any API calls.

Once you access the data of a resource, an API call will be triggered to "load"
the resource and fetch its data. To retrieve a resource object's data, you can
access it like an array.

```php
echo $object['LastModified'];
```

You can also use the `getIdentity()` and `getData()` methods to extract the
resource's data.

```php
print_r($object->getIdentity());
# Array
# (
#     [BucketName] => my-bucket
#     [Key] => image/bird.jpg
# )

print_r($object->getData());
# Array
# (
#     ...
# )
```

#### Performing Actions

You can perform actions on a resource by calling verb-like methods on the object.

```php
// Create a bucket and object.
$bucket = $aws->s3->createBucket([
    'Bucket' => 'my-new-bucket'
]);
$object = $bucket->putObject([
    'Key'  => 'images/image001.jpg',
    'Body' => fopen('/path/to/image.jpg', 'r'),
]);

// Delete the bucket and object.
$object->delete();
$bucket->delete();
```

Because the resource's identity is encapsulated within the resource object, you
never have to specify it again once the object is created. This way, actions
like `$object->delete()` do not need to require arguments.

### 3. Collection

Some resources have a "has many" type relationship with other resources. For
example, an S3 bucket has many objects. When you access a pluralized property or
method on a resource, you will get back a _Collection_ of resources. Collections
are iterable, but have an unknown length, because the underlying API operation
used to retrieve the resource data may require multiple calls. They leverage the
core SDK's _Paginators_ feature to be able to handle iterating through pages of
resource data on your behalf.

Collections, like resources, are lazily evaluated, so they don't actually
trigger any API calls until you start iterating through the resources.

```php
foreach ($bucket->objects() as $object) {
    echo "Deleting object {$object['Key']}...\n";
    $object->delete();
}
```

### 4. Batch

_Batches_ are similar to collections, but are finite in length. Batches are
returned as the result of performing an action, where multiple resources are
returned. For example, an SQS "Queue" resource has an action named
"ReceiveMessages" that results in returning a batch of up to 10 "Message"
resources.

```php
$messages = $queue->receiveMessages(['VisibilityTimeout' => 60]);
echo "Number of Messages Received: " . count($messages) . "\n";
echo "Receipt Handles:\n";
foreach ($messages as $message) {
    echo "- {$message['ReceiptHandle']}\n";
    $message->delete();
}
```

## Using Resources

We are currently working on providing API documentation for the AWS Resource
APIs. Even without docs, you can programmatically determine what methods are
available on a resource object by calling the `respondsTo()` method.

```php
print_r($bucket->respondsTo());
# Array
# (
#     [0] => create
#     [1] => delete
#     [2] => deleteObjects
#     [3] => putObject
#     [4] => multipartUploads
#     [5] => objectVersions
#     [6] => objects
#     [7] => bucketAcl
#     [8] => bucketCors
#     [9] => bucketLifecycle
#     [10] => bucketLogging
#     [11] => bucketPolicy
#     [12] => bucketNotification
#     [13] => bucketRequestPayment
#     [14] => bucketTagging
#     [15] => bucketVersioning
#     [16] => bucketWebsite
#     [17] => object
# )
```

You can use that same `respondsTo()` method to check if a particular method is
available. The `getMeta()` method may also help you discover more about how your
resource object works as well.

```php
var_dump($bucket->respondsTo('putObject'));
# bool(true)

print_r($bucket->getMeta());
# Array
# (
#     ...
# )
```

## TODO

There is still a lot of work to do on the AWS Resources API for PHP. Here are
some things we have on the list to work on soon.

1. Support for batch actions on Batch and Collection objects (e.g., `$messages->delete();`)
1. Support for waiters on resources (e.g., `$instance->waitUntil('Running');`)
1. Support for more AWS services
1. API documentation for the AWS Resource APIs

Check out the AWS Resource APIs and let us now what questions, feedback, or
ideas you have in the [issue tracker]. Thanks!

[AWS SDK for PHP]: https://github.com/aws/aws-sdk-php
[op-php]: http://docs.aws.amazon.com/aws-sdk-php/v3/api/Aws/Ec2/ec2-2014-06-15.html#runinstances
[op-ec2]: http://docs.aws.amazon.com/AWSEC2/latest/APIReference/API_RunInstances.html
[Composer]: https://getcomposer.org/
[package]: https://packagist.org/packages/aws/aws-sdk-php-resources
[v3]: https://github.com/aws/aws-sdk-php/tree/v3
[issue tracker]: https://github.com/awslabs/aws-sdk-php-resources/issues
