<?php

namespace Aws\Resource;

/**
 * An iterator of batches that can also proxy batch actions.
 */
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

    public function __debugInfo()
    {
        return iterator_to_array($this);
    }
}
