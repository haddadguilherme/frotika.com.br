<?php

declare(strict_types=1);

namespace App\Domain\Trips\Cte;

use App\Domain\Trips\Cte\Exceptions\InvalidCteException;
use DOMDocument;
use DOMNode;
use DOMXPath;

/**
 * Leitor namespace-agnóstico de XML de CT-e. Usa local-name() no XPath porque
 * o CT-e mistura o namespace do portal fiscal com o da assinatura digital, e a
 * versão/encoding variam entre emissores (regra 9 do projeto).
 */
final class CteReader
{
    private function __construct(
        private readonly DOMXPath $xpath,
        private readonly DOMNode $infCte,
        private readonly ?DOMNode $infProt,
    ) {}

    public static function fromString(string $xml): self
    {
        $xml = self::normalizeEncoding($xml);

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($loaded === false) {
            throw InvalidCteException::unreadable();
        }

        $xpath = new DOMXPath($document);

        $infCte = self::firstNode($xpath, '//*[local-name()="infCte"]');

        if ($infCte === null) {
            throw InvalidCteException::missingInfCte();
        }

        $infProt = self::firstNode($xpath, '//*[local-name()="infProt"]');

        return new self($xpath, $infCte, $infProt);
    }

    public function infCte(): DOMNode
    {
        return $this->infCte;
    }

    /**
     * Texto do primeiro nó que casa com o caminho de local-names, relativo a um
     * contexto (default: infCte). Ex.: value('ide/nCT').
     */
    public function value(string $path, ?DOMNode $context = null): ?string
    {
        $node = $this->node($path, $context);

        if ($node === null) {
            return null;
        }

        $text = trim($node->nodeValue ?? '');

        return $text === '' ? null : $text;
    }

    public function node(string $path, ?DOMNode $context = null): ?DOMNode
    {
        $context ??= $this->infCte;

        return self::firstNode($this->xpath, $this->relativeQuery($path), $context);
    }

    /**
     * Valor do primeiro descendente com o local-name informado (busca profunda
     * a partir do infCte). Útil quando o campo aparece dentro de grupos que
     * variam entre emissores (ex.: vICMS em ICMS00/ICMS45/ICMSSN, RNTRC, vCarga).
     */
    public function deepValue(string $localName, ?DOMNode $context = null): ?string
    {
        $context ??= $this->infCte;

        $node = self::firstNode(
            $this->xpath,
            './/*[local-name()="'.$localName.'"]',
            $context,
        );

        $text = $node === null ? '' : trim($node->nodeValue ?? '');

        return $text === '' ? null : $text;
    }

    /**
     * Valor do primeiro nó de um XPath arbitrário (deve usar local-name()),
     * relativo ao infCte. Para filtros que os helpers de caminho não cobrem.
     */
    public function xpathValue(string $query, ?DOMNode $context = null): ?string
    {
        $context ??= $this->infCte;

        $node = self::firstNode($this->xpath, $query, $context);

        $text = $node === null ? '' : trim($node->nodeValue ?? '');

        return $text === '' ? null : $text;
    }

    public function attribute(string $name): ?string
    {
        if (! $this->infCte instanceof \DOMElement) {
            return null;
        }

        $value = trim($this->infCte->getAttribute($name));

        return $value === '' ? null : $value;
    }

    /**
     * Valor de um campo do protocolo (protCTe/infProt), independente do infCte.
     */
    public function protocolValue(string $localName): ?string
    {
        if ($this->infProt === null) {
            return null;
        }

        $node = self::firstNode(
            $this->xpath,
            './*[local-name()="'.$localName.'"]',
            $this->infProt,
        );

        $text = $node === null ? '' : trim($node->nodeValue ?? '');

        return $text === '' ? null : $text;
    }

    /**
     * Texto de um ObsCont pelo atributo xCampo (motorista, placa, placa2...).
     */
    public function obsCont(string $field): ?string
    {
        $node = self::firstNode(
            $this->xpath,
            './/*[local-name()="ObsCont"][@xCampo="'.$field.'"]/*[local-name()="xTexto"]',
            $this->infCte,
        );

        $text = $node === null ? '' : trim($node->nodeValue ?? '');

        return $text === '' ? null : $text;
    }

    private function relativeQuery(string $path): string
    {
        $segments = array_filter(explode('/', $path), static fn (string $s): bool => $s !== '');

        $query = '.';

        foreach ($segments as $segment) {
            $query .= '/*[local-name()="'.$segment.'"]';
        }

        return $query;
    }

    private static function firstNode(DOMXPath $xpath, string $query, ?DOMNode $context = null): ?DOMNode
    {
        $result = $context === null
            ? $xpath->query($query)
            : $xpath->query($query, $context);

        if ($result === false || $result->length === 0) {
            return null;
        }

        return $result->item(0);
    }

    private static function normalizeEncoding(string $xml): string
    {
        if (preg_match('/encoding=["\']([^"\']+)["\']/i', $xml, $matches) === 1) {
            $encoding = strtoupper($matches[1]);

            if ($encoding !== 'UTF-8' && $encoding !== 'UTF8') {
                $converted = @mb_convert_encoding($xml, 'UTF-8', $encoding);

                if ($converted !== false) {
                    $xml = (string) preg_replace('/(encoding=["\'])[^"\']+(["\'])/i', '${1}UTF-8${2}', $converted, 1);
                }
            }
        }

        return $xml;
    }
}
