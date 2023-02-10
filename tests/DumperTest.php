<?php

declare(strict_types=1);

namespace Nuglif\Nacl;

class DumperTest extends \PHPUnit\Framework\TestCase
{
    public static function getJsonFiles(): iterable
    {
        $files = glob(__DIR__ . '/json/*.json');

        $testCases = [];
        for ($i = 0; $i <= Dumper::QUOTE_STR + 1; ++$i) {
            foreach ($files as $file) {
                $testCases[] = [ $i, file_get_contents($file) ];
            }
        }

        return $testCases;
    }

    /**
     * @dataProvider getJsonFiles
     * @test
     */
    public function parsedDumpOutputIsEqualToDumpInput($options, $json)
    {
        $expected = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        $dumper = new Dumper($options);
        $nacl   = $dumper->dump($expected);

        try {
            $result = Nacl::parse($nacl);
        } catch (\Exception) {
            $result = null;
        }

        $this->assertSame($expected, $result, $nacl);
    }
}
