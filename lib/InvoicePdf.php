<?php

declare(strict_types=1);

require_once __DIR__ . '/fpdf/fpdf.php';

/**
 * Branded Wave-style invoice PDF (logo, orange table header, navy accents).
 */
final class InvoicePdf extends FPDF
{
    /** @var array<string, mixed> */
    private array $invoice;
    /** @var list<array<string, mixed>> */
    private array $items;
    /** @var array<string, string> */
    private array $company;
    /** @var list<string> */
    private array $billLines;
    private string $discountLabel;

    /**
     * @param array<string, mixed> $invoice
     * @param list<array<string, mixed>> $items
     * @param array<string, string> $company
     * @param list<string> $billLines
     */
    public function __construct(
        array $invoice,
        array $items,
        array $company,
        array $billLines,
        string $discountLabel
    ) {
        parent::__construct('P', 'mm', 'Letter');
        $this->invoice = $invoice;
        $this->items = $items;
        $this->company = $company;
        $this->billLines = $billLines;
        $this->discountLabel = $discountLabel;
        $this->SetMargins(16, 14, 16);
        $this->SetAutoPageBreak(true, 18);
        $this->AliasNbPages();
    }

    public function build(): string
    {
        $this->AddPage();
        $this->drawHeader();
        $this->drawBillToAndMeta();
        $this->drawServicesTable();
        $this->drawNotesAndTotals();
        $this->drawThanks();
        return $this->Output('S');
    }

    private function drawHeader(): void
    {
        $logoPath = PROJECT_ROOT . '/' . brand_logo_path();
        $startY = $this->GetY();

        if (is_file($logoPath)) {
            $this->Image($logoPath, 16, $startY, 52);
        }

        $this->SetXY(90, $startY);
        $this->SetFont('Helvetica', 'B', 26);
        $this->SetTextColor(31, 41, 55);
        $this->Cell(104, 10, 'INVOICE', 0, 2, 'R');

        $this->SetFont('Helvetica', '', 9);
        $this->SetTextColor(100, 116, 139);
        $lines = [
            $this->company['name'] ?? '',
            $this->company['street'] ?? '',
            $this->company['city_line'] ?? '',
            $this->company['country'] ?? '',
            $this->company['phone'] ?? '',
            $this->company['website'] ?? '',
        ];
        $first = true;
        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }
            if ($first) {
                $this->SetFont('Helvetica', 'B', 9);
                $this->SetTextColor(31, 41, 55);
                $first = false;
            } else {
                $this->SetFont('Helvetica', '', 9);
                $this->SetTextColor(100, 116, 139);
            }
            $this->Cell(104, 4.4, $this->t($line), 0, 2, 'R');
        }

        $this->SetY(max($this->GetY(), $startY + 28) + 6);
    }

    private function drawBillToAndMeta(): void
    {
        $y = $this->GetY();
        $leftX = 16;
        $rightX = 110;

        $this->SetXY($leftX, $y);
        $this->SetFont('Helvetica', 'B', 8);
        $this->SetTextColor(100, 116, 139);
        $this->Cell(80, 5, 'BILL TO', 0, 2);

        $this->SetFont('Helvetica', 'B', 10);
        $this->SetTextColor(31, 41, 55);
        if ($this->billLines === []) {
            $this->Cell(80, 5, $this->t('—'), 0, 2);
        } else {
            foreach ($this->billLines as $line) {
                $this->Cell(80, 5, $this->t($line), 0, 2);
            }
        }
        $leftBottom = $this->GetY();

        $currency = (string) ($this->invoice['currency'] ?? 'CAD');
        $total = invoice_format_money($this->invoice['total'] ?? 0);
        $rows = [
            ['Invoice Number:', (string) ($this->invoice['invoice_number'] ?? '')],
            ['Invoice Date:', invoice_format_date((string) ($this->invoice['invoice_date'] ?? ''))],
            ['Payment Due:', invoice_format_date((string) ($this->invoice['due_date'] ?? ''))],
        ];

        $metaY = $y;
        foreach ($rows as [$label, $value]) {
            $this->SetXY($rightX, $metaY);
            $this->SetFont('Helvetica', '', 9);
            $this->SetTextColor(100, 116, 139);
            $this->Cell(48, 5.5, $this->t($label), 0, 0, 'L');
            $this->SetFont('Helvetica', 'B', 9);
            $this->SetTextColor(31, 41, 55);
            $this->Cell(36, 5.5, $this->t($value), 0, 1, 'R');
            $metaY = $this->GetY();
        }

        // Amount due highlight box
        $this->SetFillColor(241, 245, 249);
        $this->Rect($rightX, $metaY + 1, 84, 8, 'F');
        $this->SetXY($rightX + 1.5, $metaY + 2.2);
        $this->SetFont('Helvetica', 'B', 9);
        $this->SetTextColor(30, 58, 138);
        $this->Cell(46, 5.5, $this->t('Amount Due (' . $currency . '):'), 0, 0, 'L');
        $this->Cell(34.5, 5.5, $this->t($total), 0, 1, 'R');
        $rightBottom = $metaY + 11;

        $this->SetY(max($leftBottom, $rightBottom) + 8);
    }

    private function drawServicesTable(): void
    {
        $pageWidth = 215.9 - 32; // Letter width minus margins
        $colService = $pageWidth - 56;
        $colPrice = 28;
        $colAmount = 28;

        // Orange header
        $this->SetFillColor(249, 115, 22);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Helvetica', 'B', 9);
        $x = 16;
        $y = $this->GetY();
        $this->Rect($x, $y, $pageWidth, 8, 'F');
        $this->SetXY($x + 2, $y + 2);
        $this->Cell($colService - 2, 5, 'Services', 0, 0, 'L');
        $this->Cell($colPrice, 5, 'Price', 0, 0, 'R');
        $this->Cell($colAmount - 2, 5, 'Amount', 0, 1, 'R');
        $this->SetY($y + 8);

        foreach ($this->items as $item) {
            $name = $this->t((string) ($item['name'] ?? ''));
            $desc = trim((string) ($item['description'] ?? ''));
            $price = invoice_format_money($item['unit_price'] ?? 0);
            $amount = invoice_format_money($item['amount'] ?? 0);

            $startY = $this->GetY();
            if ($startY > 245) {
                $this->AddPage();
                $startY = $this->GetY();
            }

            $this->SetFont('Helvetica', 'B', 10);
            $this->SetTextColor(31, 41, 55);
            $this->SetXY(18, $startY + 3);
            $this->MultiCell($colService - 4, 4.5, $name, 0, 'L');
            $nameBottom = $this->GetY();

            $descBottom = $nameBottom;
            if ($desc !== '') {
                $this->SetFont('Helvetica', '', 8.5);
                $this->SetTextColor(100, 116, 139);
                $this->SetX(18);
                $this->MultiCell($colService - 4, 4, $this->t($desc), 0, 'L');
                $descBottom = $this->GetY();
            }

            $rowBottom = max($descBottom, $startY + 10) + 2;
            $midY = $startY + 3;
            $this->SetXY(16 + $colService, $midY);
            $this->SetFont('Helvetica', '', 10);
            $this->SetTextColor(31, 41, 55);
            $this->Cell($colPrice, 5, $this->t($price), 0, 0, 'R');
            $this->Cell($colAmount, 5, $this->t($amount), 0, 1, 'R');

            $this->SetDrawColor(226, 232, 240);
            $this->Line(16, $rowBottom, 16 + $pageWidth, $rowBottom);
            $this->SetY($rowBottom);
        }

        $this->Ln(4);
    }

    private function drawNotesAndTotals(): void
    {
        $y = $this->GetY();
        $currency = (string) ($this->invoice['currency'] ?? 'CAD');
        $subtotal = invoice_format_money($this->invoice['subtotal'] ?? 0);
        $discountAmount = (float) ($this->invoice['discount_amount'] ?? 0);
        $discountPercent = (float) ($this->invoice['discount_percent'] ?? 0);
        $total = invoice_format_money($this->invoice['total'] ?? 0);

        $notesText = trim((string) ($this->invoice['notes'] ?? ''));
        $thankYou = 'Thank you for choosing our service!';
        if ($notesText !== '') {
            $notesText = trim(preg_replace(
                '/(?:\r?\n)?\s*' . preg_quote($thankYou, '/') . '\s*$/iu',
                '',
                $notesText
            ) ?? $notesText);
        }

        // Notes (left)
        if ($notesText !== '') {
            $this->SetXY(16, $y);
            $this->SetFont('Helvetica', 'B', 8);
            $this->SetTextColor(100, 116, 139);
            $this->Cell(90, 5, 'NOTES / TERMS', 0, 2);
            $this->SetFont('Helvetica', '', 9);
            $this->SetTextColor(100, 116, 139);
            $this->MultiCell(90, 4.5, $this->t($notesText), 0, 'L');
        }

        // Totals (right)
        $totalsX = 118;
        $this->SetY($y);
        $this->drawTotalRow($totalsX, 'Subtotal:', $subtotal, false);
        if ($discountPercent > 0 || $discountAmount > 0) {
            $this->drawTotalRow(
                $totalsX,
                $this->discountLabel . '% Discount:',
                '(' . invoice_format_money($discountAmount) . ')',
                false
            );
        }

        $this->SetDrawColor(226, 232, 240);
        $lineY = $this->GetY() + 1;
        $this->Line($totalsX, $lineY, 199.9, $lineY);
        $this->SetY($lineY + 2);
        $this->drawTotalRow($totalsX, 'Total:', $total, true);

        $this->SetDrawColor(226, 232, 240);
        $lineY = $this->GetY() + 1;
        $this->Line($totalsX, $lineY, 199.9, $lineY);
        $this->SetY($lineY + 2);
        $this->SetFont('Helvetica', 'B', 10);
        $this->SetTextColor(30, 58, 138);
        $this->SetX($totalsX);
        $this->Cell(48, 6, $this->t('Amount Due (' . $currency . '):'), 0, 0, 'R');
        $this->Cell(34, 6, $this->t($total), 0, 1, 'R');
    }

    private function drawTotalRow(float $x, string $label, string $value, bool $bold): void
    {
        $this->SetX($x);
        $this->SetFont('Helvetica', $bold ? 'B' : '', 9);
        $this->SetTextColor($bold ? 31 : 100, $bold ? 41 : 116, $bold ? 55 : 139);
        $this->Cell(48, 5.5, $this->t($label), 0, 0, 'R');
        $this->SetFont('Helvetica', $bold ? 'B' : '', 9);
        $this->SetTextColor(31, 41, 55);
        $this->Cell(34, 5.5, $this->t($value), 0, 1, 'R');
    }

    private function drawThanks(): void
    {
        $this->Ln(14);
        $this->SetFont('Helvetica', '', 10);
        $this->SetTextColor(100, 116, 139);
        $this->Cell(0, 6, $this->t('Thank you for choosing our service!'), 0, 1, 'C');
    }

    /** Convert UTF-8 text for core Helvetica fonts. */
    private function t(string $text): string
    {
        $converted = @iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $text);
        if ($converted === false) {
            $converted = preg_replace('/[^\x20-\x7E]/', '?', $text) ?? $text;
        }
        return $converted;
    }
}

/**
 * @param array<string, mixed> $invoice
 * @param list<array<string, mixed>> $items
 * @return array{filename: string, bytes: string, mime: string}
 */
function invoice_build_pdf(array $invoice, array $items): array
{
    $company = invoice_company_settings();
    $billLines = invoice_bill_to_lines($invoice);
    $discountPercent = (float) ($invoice['discount_percent'] ?? 0);
    $discountLabel = rtrim(rtrim(number_format($discountPercent, 4, '.', ''), '0'), '.');
    if ($discountLabel === '') {
        $discountLabel = '0';
    }

    $pdf = new InvoicePdf($invoice, $items, $company, $billLines, $discountLabel);
    $bytes = $pdf->build();
    $number = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) ($invoice['invoice_number'] ?? 'invoice')) ?: 'invoice';
    $date = preg_replace('/[^0-9-]+/', '', (string) ($invoice['invoice_date'] ?? '')) ?: date('Y-m-d');

    return [
        'filename' => 'Invoice_' . $number . '_' . $date . '.pdf',
        'bytes' => $bytes,
        'mime' => 'application/pdf',
    ];
}
