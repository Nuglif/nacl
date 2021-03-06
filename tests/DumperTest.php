<?php

namespace Nuglif\Nacl;

class DumperTest extends \PHPUnit\Framework\TestCase
{
    public static function getJsonFiles()
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
        $expected = json_decode($json, true);

        $dumper = new Dumper($options);
        $nacl   = $dumper->dump($expected);

        try {
            $result = Nacl::parse($nacl);
        } catch (\Exception $e) {
            $result = null;
        }

        $this->assertSame($expected, $result, $nacl);
    }
}
