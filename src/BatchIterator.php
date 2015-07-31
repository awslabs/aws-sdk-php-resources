<?php

namespace Aws\Resource;

class BatchIterator extends \IteratorIterator
{
    public function __construct($batches)
    {
        if (is_array($batches)) {
            $batches = new \ArrayIterator($batches);
        }

        if ($batches instanceof \Traversable) {
            parent::__construct($batches);
        } else {
            throw new \InvalidArgumentException(
                'Must provide an array or Traversable of Batch objects.'
            );
        }
    }

    public function __call($name, array $args)
    {
        foreach ($this as $batch) {
            $batch->__call($name, $args);
        }
    }
}
