<?php

namespace IMEdge\Protocol\NetString;

use Amp\ByteStream\ReadableStream;
use Amp\ByteStream\StreamException;
use Generator;

use function ctype_digit;
use function ltrim;
use function sprintf;
use function strlen;
use function strpos;
use function substr;

trait NetStringReaderImplementation
{
    protected string $buffer = '';
    protected int $bufferLength = 0;
    protected int $bufferOffset = 0;
    protected ?int $expectedLength = null;

    public function __construct(
        protected readonly ReadableStream $in,
    ) {
    }

    /**
     * @throws NetStringProtocolError
     * @throws StreamException
     * @return Generator<string>
     */
    public function packets(): Generator
    {
        while (null !== ($data = $this->in->read())) {
            $this->buffer .= $data;
            $this->bufferLength += strlen($data);
            while ($this->bufferHasPacket()) {
                yield $this->processNextPacket();
                if ($this->bufferOffset !== 0) {
                    $this->buffer = substr($this->buffer, $this->bufferOffset);
                    $this->bufferOffset = 0;
                    $this->bufferLength = strlen($this->buffer);
                }
            }
        }
    }

    /**
     * @throws NetStringProtocolError
     */
    protected function bufferHasPacket(): bool
    {
        if ($this->expectedLength === null) {
            $maxTenCharacters = substr($this->buffer, $this->bufferOffset, 10);
            if (false === ($pos = strpos($maxTenCharacters, ':'))) {
                if ($this->bufferLength > ($this->bufferOffset + 10)) {
                    $this->throwInvalidBuffer('invalid length indication');
                } elseif (
                    $maxTenCharacters === ''
                    || $maxTenCharacters === ','
                    || preg_match('/^,?[0-9]+$/', $maxTenCharacters)
                ) {
                    return false; // Still waiting for length indication
                } else {
                    $this->throwInvalidBuffer('invalid length indication');
                }
            } else {
                $lengthString = ltrim(substr($this->buffer, $this->bufferOffset, $pos), ',');
                if (ctype_digit($lengthString)) {
                    $this->expectedLength = (int)$lengthString;
                    $this->bufferOffset = $pos + 1;
                } else {
                    $this->throwInvalidBuffer('invalid length indication');
                }
            }
        }

        return $this->bufferLength > ($this->bufferOffset + $this->expectedLength);
    }

    protected function processNextPacket(): string
    {
        $packet = substr($this->buffer, $this->bufferOffset, $this->expectedLength);

        $this->bufferOffset = $this->bufferOffset + $this->expectedLength;
        $this->expectedLength = null;

        return $packet;
    }

    /**
     * @throws NetStringProtocolError
     */
    protected function throwInvalidBuffer(string $message): never
    {
        $this->close();
        $len = strlen($this->buffer);
        $debug = $len < 200 ? $this->buffer : substr($this->buffer, 0, 100)
            . sprintf('[..] truncated %d bytes [..] ', $len)
            . substr($this->buffer, -100);

        throw new NetStringProtocolError("Got invalid NetString data ($message): " . var_export($debug, true));
    }
}
