<?php

namespace IMEdge\Tests\Protocol\NetString;

use IMEdge\Protocol\NetString\NetStringReaderInterface;

class TestHelper
{
    /**
     * @return string[]
     */
    public static function consumeAllPackets(NetStringReaderInterface $netString): array
    {
        $results = [];
        foreach ($netString->packets() as $packet) {
            $results[] = $packet;
        }

        return $results;
    }

    /**
     * @return string[]
     */
    public static function consumeAllPacketsReading(NetStringReaderInterface $netString): array
    {
        $results = [];
        while (null !== ($packet = $netString->packets())) {
            $results[] = $packet;
        }

        return $results;
    }

    public static function catchOutput(callable $callable): string
    {
        ob_start();
        $callable();
        $output = ob_get_contents();
        ob_end_clean();

        return $output ?: '';
    }

    public static function beginCatchingOutput(): void
    {
        ob_start();
    }

    public static function getCatchedOutput(): string
    {
        $output = ob_get_contents();
        ob_end_clean();

        return $output ?: '';
    }
}
