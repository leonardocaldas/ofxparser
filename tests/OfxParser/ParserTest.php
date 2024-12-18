<?php

namespace OfxParserTest;

use OfxParser\Parser;
use PHPUnit\Framework\TestCase;

/**
 * @covers OfxParser\Parser
 */
class ParserTest extends TestCase
{
    public function testCreditCardStatementTransactionsAreLoaded()
    {
        $parser = new Parser();
        $ofx = $parser->loadFromFile(__DIR__ . '/../fixtures/ofxdata-credit-card.ofx');

        $account = reset($ofx->bankAccounts);
        self::assertSame('1234567891234567', (string)$account->accountNumber);
    }

    public function testParseHeader()
    {
        $parser = new Parser();
        $ofx = $parser->loadFromFile(__DIR__ . '/../fixtures/ofxdata.ofx');

        $header = [
            'OFXHEADER' => '100',
            'DATA' => 'OFXSGML',
            'VERSION' => '103',
            'SECURITY' => 'NONE',
            'ENCODING' => 'USASCII',
            'CHARSET' => '1252',
            'COMPRESSION' => 'NONE',
            'OLDFILEUID' => 'NONE',
            'NEWFILEUID' => 'NONE',
        ];

        self::assertSame($header, $ofx->header);
    }

    public function testParseXMLHeader()
    {
        $parser = new Parser();
        $ofx = $parser->loadFromFile(__DIR__ . '/../fixtures/ofxdata-xml.ofx');

        $header = [
            'OFXHEADER' => '200',
            'VERSION' => '200',
            'SECURITY' => 'NONE',
            'OLDFILEUID' => 'NONE',
            'NEWFILEUID' => 'NONE',
        ];

        self::assertSame($header, $ofx->header);
    }

    public function testXmlLoadStringThrowsExceptionWithInvalidXml()
    {
        $invalidXml = '<invalid xml>';

        $method = new \ReflectionMethod(Parser::class, 'xmlLoadString');
        $method->setAccessible(true);

        try {
            $method->invoke(new Parser(), $invalidXml);
        } catch (\Exception $e) {
            if (stripos($e->getMessage(), 'Failed to parse OFX') !== false) {
                $this->assertTrue(true);
                return true;
            }

            throw $e;
        }

        self::fail('Method xmlLoadString did not raise an expected exception parsing an invalid XML string');
    }

    public function testXmlLoadStringLoadsValidXml()
    {
        $validXml = '<fooRoot><foo>bar</foo></fooRoot>';

        $method = new \ReflectionMethod(Parser::class, 'xmlLoadString');
        $method->setAccessible(true);

        $xml = $method->invoke(new Parser(), $validXml);

        self::assertInstanceOf('SimpleXMLElement', $xml);
        self::assertEquals('bar', (string)$xml->foo);
    }

    /**
     * @return array
     */
    public static function closeUnclosedXmlTagsProvider()
    {
        return [
            ['<SOMETHING>', '<SOMETHING>'],
            ['<SOMETHING>foo</SOMETHING>', '<SOMETHING>foo'],
            ['<SOMETHING>foo</SOMETHING>', '<SOMETHING>foo</SOMETHING>'],
            ['<BANKID>XXXXX</BANKID>', '<BANKID>XXXXX</BANKID>'],
            ['<ACCTID>XXXXXXXXXXX</ACCTID>', '<ACCTID>XXXXXXXXXXX</ACCTID>'],
            ['<ACCTID>-198.98</ACCTID>', '<ACCTID>-198.98</ACCTID>'],
            ['<ACCTID>-198.98</ACCTID>', '<ACCTID>-198.98'],
            ['<MEMO></MEMO>', '<MEMO>'],
        ];
    }

    /**
     * @dataProvider closeUnclosedXmlTagsProvider
     * @param $expected
     * @param $input
     */
    public function testCloseUnclosedXmlTags($expected, $input)
    {
        $method = new \ReflectionMethod(Parser::class, 'closeUnclosedXmlTags');
        $method->setAccessible(true);

        $parser = new Parser();

        self::assertEquals($expected, $method->invoke($parser, $input));
    }

    public static function convertSgmlToXmlProvider()
    {
        return [
            [<<<HERE
<SOMETHING>
    <FOO>bar
    <BAZ>bat</BAZ>
</SOMETHING>
HERE
        , <<<HERE
<SOMETHING>
<FOO>bar</FOO>
<BAZ>bat</BAZ>
</SOMETHING>
HERE
            ], [<<<HERE
<BANKACCTFROM>
<BANKID>XXXXX</BANKID>
<BRANCHID>XXXXX</BRANCHID>
<ACCTID>XXXXXXXXXXX</ACCTID>
<ACCTTYPE>CHECKING</ACCTTYPE>
</BANKACCTFROM>
HERE
                ,<<<HERE
<BANKACCTFROM>
<BANKID>XXXXX</BANKID>
<BRANCHID>XXXXX</BRANCHID>
<ACCTID>XXXXXXXXXXX</ACCTID>
<ACCTTYPE>CHECKING</ACCTTYPE>
</BANKACCTFROM>
HERE
            ],[<<<HERE
<SOMETHING>
    <FOO>bar & restaurant
    <BAZ>bat</BAZ>
</SOMETHING>
HERE
        , <<<HERE
<SOMETHING>
<FOO>bar &amp; restaurant</FOO>
<BAZ>bat</BAZ>
</SOMETHING>
HERE
            ],
        ];
    }

    /**
     * @dataProvider convertSgmlToXmlProvider
     */
    public function testConvertSgmlToXml($sgml, $expected)
    {
        $method = new \ReflectionMethod(Parser::class, 'convertSgmlToXml');
        $method->setAccessible(true);

        self::assertEquals($expected, $method->invoke(new Parser, $sgml));
    }

    public function testLoadFromFileWhenFileDoesNotExist()
    {
        $this->expectException(\InvalidArgumentException::class);

        $parser = new Parser();
        $parser->loadFromFile('a non-existent file');
    }

    /**
     * @dataProvider loadFromStringProvider
     */
    public function testLoadFromFileWhenFileDoesExist($filename)
    {
        if (!file_exists($filename)) {
            self::markTestSkipped('Could not find data file, cannot test loadFromFile method fully');
        }

        /** @var Parser|\PHPUnit_Framework_MockObject_MockObject $parser */
        $parser = $this->getMockBuilder(Parser::class)
                         ->onlyMethods(['loadFromString'])
                         ->getMock();
        $parser->expects(self::once())->method('loadFromString');
        $parser->loadFromFile($filename);
    }

    /**
     * @return array
     */
    public static function loadFromStringProvider()
    {
        return [
            'ofxdata.ofx' => [dirname(__DIR__).'/fixtures/ofxdata.ofx'],
            'ofxdata-oneline.ofx' => [dirname(__DIR__).'/fixtures/ofxdata-oneline.ofx'],
            'ofxdata-cmfr.ofx' => [dirname(__DIR__).'/fixtures/ofxdata-cmfr.ofx'],
            'ofxdata-bb.ofx' => [dirname(__DIR__).'/fixtures/ofxdata-bb.ofx'],
            'ofxdata-bb-two-stmtrs.ofx' => [dirname(__DIR__).'/fixtures/ofxdata-bb-two-stmtrs.ofx'],
            'ofxdata-credit-card.ofx' => [dirname(__DIR__).'/fixtures/ofxdata-credit-card.ofx'],
            'ofxdata-bpbfc.ofx' => [dirname(__DIR__).'/fixtures/ofxdata-bpbfc.ofx'],
            'ofxdata-memoWithQuotes.ofx' => [dirname(__DIR__).'/fixtures/ofxdata-memoWithQuotes.ofx'],
            'ofxdata-emptyDateTime.ofx' => [dirname(__DIR__).'/fixtures/ofxdata-emptyDateTime.ofx'],
            'ofxdata-memoWithAmpersand.ofx' => [dirname(__DIR__).'/fixtures/ofxdata-memoWithAmpersand.ofx'],
            'ofxdata-banking-xml200.ofx' => [dirname(__DIR__).'/fixtures/ofxdata-banking-xml200.ofx'],
        ];
    }

    /**
     * @param string $filename
     * @throws \Exception
     * @dataProvider loadFromStringProvider
     */
    public function testLoadFromString($filename)
    {
        if (!file_exists($filename)) {
            self::markTestSkipped('Could not find data file, cannot test loadFromString method fully');
        }

        $content = file_get_contents($filename);

        $parser = new Parser();

        try {
            $parser->loadFromString($content);
        } catch (\Exception $e) {
            throw $e;
        }

        $this->assertTrue(true);
    }

    public function testXmlLoadStringWithSelfClosingTag()
    {
        $parser = new Parser();

        try {
            $ofx = $parser->loadFromFile(__DIR__ . '/../fixtures/ofxdata-selfclose.ofx');
        } catch (\RuntimeException $e) {
            if (stripos($e->getMessage(), 'Failed to parse OFX') !== false) {
                self::assertTrue(false, 'Xml with invalid self closing tag');
            }
        }

        self::assertTrue(true);
    }
}
