<?php

namespace IMEdge\Protocol\NetString;

class NetStringWriter
{
    use NetStringWriterImplementation;

    public function close(): void
    {
        $this->out->close();
    }
}
