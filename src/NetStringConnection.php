<?php

namespace IMEdge\Protocol\NetString;

use Amp\ByteStream\ReadableStream;
use Amp\ByteStream\WritableStream;

class NetStringConnection implements NetStringReaderInterface
{
    use NetStringReaderImplementation;
    use NetStringWriterImplementation;

    public function __construct(
        protected readonly ReadableStream $in,
        protected readonly WritableStream $out,
    ) {
    }

    public function close(): void
    {
        $this->out->close();
        $this->in->close();

        // We might want to complain?
        $this->buffer = '';
        $this->bufferLength = 0;
        $this->bufferOffset = 0;
        $this->expectedLength = null;
    }
}
