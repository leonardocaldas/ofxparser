<?php

namespace OfxParser\Parsers;

use OfxParser\Ofx\Investment as InvestmentOfx;
use OfxParser\Parser;
use SimpleXMLElement;

class Investment extends Parser
{
    /**
     * Factory to support Investment OFX document structure.
     * @param SimpleXMLElement $xml
     * @return InvestmentOfx
     */
    protected function createOfx(SimpleXMLElement $xml)
    {
        return new InvestmentOfx($xml);
    }
}
