<?php

namespace IMEdge\Tests\Protocol\NetString;

use Amp\ByteStream\ReadableBuffer;
use Amp\ByteStream\ReadableIterableStream;
use Amp\ByteStream\WritableBuffer;
use Generator;
use IMEdge\Protocol\NetString\NetStringConnection;
use IMEdge\Protocol\NetString\NetStringProtocolError;
use PHPUnit\Framework\TestCase;

use function Amp\async;
use function Amp\delay;
use function Amp\Future\await;

class NetStringTest extends TestCase
{
    public function testProcessesSimpleString(): void
    {
        $netString = new NetStringConnection(new ReadableBuffer('2:ok,'), new WritableBuffer());
        $this->assertEquals(['ok'], TestHelper::consumeAllPackets($netString));
    }

    public function testProcessesWaitsForTrailingComma(): void
    {
        $netString = new NetStringConnection(new ReadableBuffer('2:ok'), new WritableBuffer());
        $this->assertEquals([], TestHelper::consumeAllPackets($netString));
    }

    public function testProcessesDelayedChunks(): void
    {
        $netString = new NetStringConnection(new ReadableIterableStream(self::chunkedSample()), new WritableBuffer());
        $this->assertEquals(['Thomas', 'Long name'], TestHelper::consumeAllPackets($netString));
    }

    public function testProcessesParallelAsynchronousChunks(): void
    {
        $netString1 = new NetStringConnection(new ReadableIterableStream((function (): Generator {
            yield '';
            delay(0.2);
            yield '6:';
            delay(0.01);
            yield 'Hello';
            delay(0.02);
            yield ' ,300:unfinished';
        })()), new WritableBuffer());

        $netString2 = new NetStringConnection(new ReadableIterableStream((function (): Generator {
            delay(0.4);
            yield '5:World,';
            delay(0.2);
            yield '1:';
            delay(0.02);
            yield '!,42';
        })()), new WritableBuffer());

        $result = '';
        await([
            async(function () use ($netString1, &$result) {
                foreach ($netString1->packets() as $packet) {
                    $result .= $packet;
                }
            }),
            async(function () use ($netString2, &$result) {
                foreach ($netString2->packets() as $packet) {
                    $result .= $packet;
                }
            })
        ]);

        $this->assertEquals('Hello World!', $result);
    }

    public function testNestedEncodingIsSupported(): void
    {
        $full = '17:5:hello,6:world!,,';
        $sub = '5:hello,6:world!,';
        $netString1 = new NetStringConnection(new ReadableBuffer($full), new WritableBuffer());
        $this->assertEquals([$sub], TestHelper::consumeAllPackets($netString1));
        $netString2 = new NetStringConnection(new ReadableBuffer($sub), new WritableBuffer());
        $this->assertEquals(['hello', 'world!'], TestHelper::consumeAllPackets($netString2));
    }

    public function testInvalidNetStringFails(): void
    {
        $this->expectException(NetStringProtocolError::class);
        $this->expectExceptionMessageMatches('/invalid length/');
        $netString = new NetStringConnection(new ReadableBuffer('2:go bad, really, really bad'), new WritableBuffer());
        TestHelper::consumeAllPackets($netString);
    }

    public function testInvalidNetStringFailsEarly(): void
    {
        $this->expectException(NetStringProtocolError::class);
        $this->expectExceptionMessageMatches('/invalid length/');
        $netString = new NetStringConnection(new ReadableBuffer('2:got'), new WritableBuffer());
        TestHelper::consumeAllPackets($netString);
    }

    protected static function chunkedSample(): Generator
    {
        yield '';
        delay(0.03);
        yield '6:Thomas,';
        delay(0.01);
        yield '9';
        delay(0.2);
        yield ':';
        delay(0.1);
        yield 'Long nam';
        delay(0.01);
        yield 'e,';
        delay(0.1);
        yield '10:';
    }
}
