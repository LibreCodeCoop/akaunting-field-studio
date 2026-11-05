<?php
// SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Modules\FieldStudio\Tests\Unit;

use DomainException;
use Modules\FieldStudio\Domain\ProductionMetadataText;
use PHPUnit\Framework\TestCase;

final class ProductionMetadataTextTest extends TestCase
{
    public function testOnlyRecognizedLinesAreRemovedAndOtherContentIsBytePreserved(): void
    {
        $original = "Informacoes adicionais\r\nNFSe: 0056\r\nCNPJ cliente: 00.000.000/0001-91\r\nSetor: ECidade\r\nTransação do mês: 2026-9\r\nPercentual desconto fixo: 29,50%\r\nArquivar: não\r\nContrato: permanece\r\n";
        $result = (new ProductionMetadataText())->extract($original);
        self::assertSame([
            'nfse' => '0056',
            'customer_cnpj' => '00000000000191',
            'sector' => 'ecidade',
            'transaction_of_month' => '2026-09',
            'discount_percentage' => '29.50',
            'archive' => '0',
        ], $result['values']);
        self::assertSame(6, $result['matched_lines']);
        self::assertSame("Informacoes adicionais\r\nContrato: permanece\r\n", $result['cleaned']);
    }

    public function testConflictingDuplicateStopsMigration(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Conflicting');
        (new ProductionMetadataText())->extract("Transação do mês: 2026-09\nTransação do mês: 2026-10\n");
    }

    public function testUnrecognizedNotesAreNeverDeleted(): void
    {
        $original = "Aviso fiscal: nao modificar\nOutro dado\n";
        $result = (new ProductionMetadataText())->extract($original);
        self::assertSame([], $result['values']);
        self::assertSame($original, $result['cleaned']);
    }

    public function testInvalidPercentageStopsMigration(): void
    {
        $this->expectException(DomainException::class);
        (new ProductionMetadataText())->extract("Percentual desconto fixo: 105\n");
    }

    public function testInvalidCnpjStopsMigration(): void
    {
        $this->expectException(DomainException::class);
        (new ProductionMetadataText())->extract("CNPJ cliente: 1234\n");
    }

    public function testDuplicateIdenticalValueIsSafe(): void
    {
        $result = (new ProductionMetadataText())->extract("Setor: ecidade\nSetor: ECIDADE\n");
        self::assertSame(['sector' => 'ecidade'], $result['values']);
        self::assertSame('', $result['cleaned']);
    }
}
