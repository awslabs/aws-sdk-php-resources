<?php

namespace Aws\Resource;

/**
 * Pulls out chunks from an inner iterator and yields the chunks as arrays.
 *
 * @TODO Move into core SDK.
 */
class ChunkedIterator extends \IteratorIterator
{
    /** @var int Size of each chunk. */
    protected $size;

    /** @var array Current chunk. */
    protected $chunk;

    /**
     * @param \Traversable $iter Traversable iterator.
     * @param int          $size Size to make each chunk.
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(\Traversable $iter, $size)
    {
        if (is_int($size) && $size >= 0) {
            parent::__construct($iter);
            $this->size = (int) $size;
        } else {
            throw new \InvalidArgumentException(
                "The size must be greater than or equal to zero; {$size} given."
            );
        }
    }

    public function next()
    {
        $this->chunk = [];
        for ($i = 0; $i < $this->size && parent::valid(); $i++) {
            $this->chunk[] = parent::current();
            parent::next();
        }
    }

    public function current()
    {
        return $this->chunk;
    }

    public function valid()
    {
        if (!$this->chunk) {
            $this->next();
        }

        return (bool) $this->chunk;
    }
}
