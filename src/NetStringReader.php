<?php

namespace IMEdge\Protocol\NetString;

use Amp\ByteStream\ReadableStream;

class NetStringReader implements NetStringReaderInterface
{
    use NetStringReaderImplementation;

    public function __construct(
        protected readonly ReadableStream $in,
    ) {
    }

    public function close(): void
    {
        $this->in->close();

        // We might want to complain?
        $this->buffer = '';
        $this->bufferLength = 0;
        $this->bufferOffset = 0;
        $this->expectedLength = null;
    }
}
