<?php
// SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Modules\FieldStudio\Domain;

use DomainException;

/**
 * One-time historical migration parser. Not part of the production ETL.
 * Only recognized metadata lines may be removed from free-form text.
 */
final class ProductionMetadataText
{
    private const LABELS = [
        'nfse' => 'nfse',
        'transacao do mes' => 'transaction_of_month',
        'percentual desconto fixo' => 'discount_percentage',
        'cnpj cliente' => 'customer_cnpj',
        'setor' => 'sector',
        'arquivar' => 'archive',
    ];

    /**
     * @return array{values:array<string,string>,cleaned:string,matched_lines:int}
     */
    public function extract(string $text): array
    {
        $parts = preg_split('/(\r\n|\n|\r)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            throw new DomainException('Cannot split metadata lines');
        }
        $values = [];
        $cleaned = '';
        $matched = 0;
        for ($i = 0; $i < count($parts); $i += 2) {
            $line = $parts[$i];
            $newline = $parts[$i + 1] ?? '';
            if (!preg_match('/^\h*(NFSe|Transa\x{00E7}\x{00E3}o do m\x{00EA}s|Percentual desconto fixo|CNPJ cliente|Setor|Arquivar)\h*:\h*(.*?)\h*$/iu', $line, $matches)) {
                $cleaned .= $line . $newline;
                continue;
            }
            $label = strtr(mb_strtolower($matches[1], 'UTF-8'), [
                "\u{00E7}" => 'c', "\u{00E3}" => 'a', "\u{00EA}" => 'e',
            ]);
            $key = self::LABELS[$label] ?? null;
            if ($key === null) {
                throw new DomainException('Unexpected metadata label');
            }
            $value = $this->normalize($key, trim($matches[2]));
            if (array_key_exists($key, $values) && $values[$key] !== $value) {
                throw new DomainException('Conflicting metadata values for ' . $key);
            }
            $values[$key] = $value;
            ++$matched;
        }
        return ['values' => $values, 'cleaned' => $cleaned, 'matched_lines' => $matched];
    }

    private function normalize(string $key, string $value): string
    {
        if ($value === '') {
            throw new DomainException('Empty metadata value for ' . $key);
        }
        switch ($key) {
            case 'transaction_of_month':
                if (!preg_match('/^([0-9]{4})-([0-9]{1,2})$/D', $value, $match)
                    || (int) $match[2] < 1 || (int) $match[2] > 12) {
                    throw new DomainException('Invalid financial month');
                }
                return sprintf('%04d-%02d', (int) $match[1], (int) $match[2]);
            case 'discount_percentage':
                $v = str_replace(',', '.', rtrim($value, '%'));
                if (!preg_match('/^(?:0|[1-9][0-9]?|100)(?:\.[0-9]{1,6})?$/D', $v)
                    || (float) $v > 100) {
                    throw new DomainException('Invalid fixed discount percentage');
                }
                return $v;
            case 'customer_cnpj':
                $digits = preg_replace('/[.\/-]/', '', $value);
                if (!is_string($digits) || !preg_match('/^[0-9]{14}$/D', $digits)) {
                    throw new DomainException('Invalid customer CNPJ');
                }
                return $digits;
            case 'archive':
                return match (strtr(mb_strtolower($value, 'UTF-8'), ["\u{00E3}" => 'a'])) {
                    'sim', 'true', '1' => '1',
                    'nao', 'false', '0' => '0',
                    default => throw new DomainException('Invalid archive flag'),
                };
            case 'nfse':
                if (!preg_match('/^[0-9]+$/D', $value)) {
                    throw new DomainException('Invalid NFS-e reference');
                }
                return $value;
            case 'sector':
                return mb_strtolower($value, 'UTF-8');
            default:
                throw new DomainException('Unknown metadata field');
        }
    }
}
