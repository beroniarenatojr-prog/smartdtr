<?php

namespace App\Services;

use App\Models\DocumentSignatory;
use App\Models\LocatorRequest;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

/**
 * Fills the official Locator Slip Word template without Microsoft Word / PowerShell,
 * so printing works on Hostinger (Linux) the same way Travel Order printing does.
 */
class TravelAuthorityWordService
{
    public function download(LocatorRequest $request): BinaryFileResponse
    {
        $request->loadMissing(['staffUser', 'personnel', 'reviewer']);

        $tempPath = $this->fill($request);
        $filename = 'Locator_Slip_'.$request->slipNumber().'_'.str_replace(' ', '_', $request->staffUser->officialLetterName() ?: $request->staffUser->name).'.docx';

        return response()->download($tempPath, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ])->deleteFileAfterSend(true);
    }

    public function fill(LocatorRequest $request): string
    {
        $source = $this->resolveTemplatePath();
        $staff = $request->staffUser;

        $position = $request->position_designation
            ?: ($staff->department ?: ($staff->personnel?->role ?? $staff->roleLabel()));

        $nature = (string) ($request->nature ?: 'Official Business');
        if (! in_array($nature, ['Official Business', 'Official Time'], true)) {
            $nature = 'Official Business';
        }

        $dateTime = $request->date->format('F d, Y')
            .' / '.$request->formattedTimeOut()
            .' – '.$request->formattedTimeInExpected();

        $schoolHead = DocumentSignatory::resolve('school_head')
            ?? config('smartdtr.locator_school_head', 'PRISCA ROMELA C. BUENAVENTURA');
        $schoolHeadTitle = DocumentSignatory::resolve('school_head', 'title') ?? 'School Head';

        $dir = storage_path('app/temp');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $tempPath = $dir.DIRECTORY_SEPARATOR.'Locator_Slip_'.$request->id.'_'.uniqid('', true).'.docx';
        if (! copy($source, $tempPath)) {
            throw new RuntimeException('Could not copy Locator Slip template.');
        }

        $zip = new ZipArchive;
        if ($zip->open($tempPath) !== true) {
            throw new RuntimeException('Could not open Locator Slip template for editing.');
        }

        $documentXml = $zip->getFromName('word/document.xml');
        if ($documentXml === false) {
            $zip->close();
            throw new RuntimeException('Locator Slip template is missing word/document.xml.');
        }

        $documentXml = $this->fillDocumentXml(
            $documentXml,
            [
                0 => $staff->officialLetterName() ?: (string) $staff->name,
                1 => (string) $position,
                2 => 'Isabela National High School',
                3 => (string) $request->purpose,
                4 => $this->natureChecks($nature),
                5 => $dateTime,
                6 => (string) $request->destination,
            ],
            (string) $schoolHead,
            (string) $schoolHeadTitle
        );

        $zip->addFromString('word/document.xml', $documentXml);
        $zip->close();

        return $tempPath;
    }

    public function resolveTemplatePath(): string
    {
        foreach ([
            base_path('LOCATOR-SHS.docx'),
            base_path('LOCATOR - SHS.docx'),
            storage_path('app/templates/LOCATOR-SHS.docx'),
        ] as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        throw new RuntimeException('Official LOCATOR-SHS.docx template was not found.');
    }

    /**
     * @param  array<int, string>  $values  first-table row index => column-2 value
     */
    protected function fillDocumentXml(
        string $xml,
        array $values,
        string $schoolHead,
        string $schoolHeadTitle
    ): string {
        $dom = new DOMDocument;
        $dom->preserveWhiteSpace = true;
        $previous = libxml_use_internal_errors(true);
        if (! $dom->loadXML($xml)) {
            libxml_use_internal_errors($previous);
            throw new RuntimeException('Could not parse Locator Slip document.xml.');
        }
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $body = $xpath->query('//w:body')->item(0);
        $tables = $xpath->query('./w:tbl', $body);
        if (! $body || ! $tables || $tables->length < 1) {
            throw new RuntimeException('Locator Slip template table structure is invalid.');
        }

        $mainTable = $tables->item(0);
        if (! $mainTable) {
            throw new RuntimeException('Locator Slip template table structure is invalid.');
        }

        // Official print uses one slip: drop the duplicate slip table and trailing paragraphs.
        $remove = false;
        $toRemove = [];
        foreach (iterator_to_array($body->childNodes) as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }
            if (! $remove) {
                if ($child->isSameNode($mainTable)) {
                    $remove = true;
                }

                continue;
            }
            if ($child->localName === 'sectPr') {
                break;
            }
            $toRemove[] = $child;
        }
        foreach ($toRemove as $child) {
            $body->removeChild($child);
        }

        $rows = $xpath->query('./w:tr', $mainTable);
        if (! $rows || $rows->length < 7) {
            throw new RuntimeException('Locator Slip template table structure is invalid.');
        }

        foreach ($values as $rowIndex => $text) {
            $row = $rows->item($rowIndex);
            if (! $row) {
                continue;
            }
            $cells = $xpath->query('./w:tc', $row);
            if (! $cells || $cells->length < 2) {
                continue;
            }
            $this->setCellPlainText($xpath, $cells->item(1), $text);
        }

        $this->replaceTextEverywhere($xpath, 'PRISCA ROMELA C. BUENAVENTURA', $schoolHead);
        $this->replaceTextEverywhere($xpath, 'Head of Office', $schoolHeadTitle !== '' ? $schoolHeadTitle : 'School Head');

        return $dom->saveXML() ?: $xml;
    }

    protected function natureChecks(string $nature): string
    {
        $checked = "\u{2611}"; // ☑
        $unchecked = "\u{2610}"; // ☐

        if ($nature === 'Official Time') {
            return $unchecked.' Official Business    '.$checked.' Official Time';
        }

        return $checked.' Official Business    '.$unchecked.' Official Time';
    }

    protected function setCellPlainText(DOMXPath $xpath, DOMElement $cell, string $text): void
    {
        $textNodes = $xpath->query('.//w:t', $cell);
        if ($textNodes && $textNodes->length > 0) {
            $first = true;
            foreach ($textNodes as $node) {
                if ($first) {
                    $node->nodeValue = $this->escapeXmlText($text);
                    if ($node instanceof DOMElement) {
                        $node->setAttributeNS(
                            'http://www.w3.org/XML/1998/namespace',
                            'xml:space',
                            'preserve'
                        );
                    }
                    $first = false;
                } else {
                    $node->nodeValue = '';
                }
            }

            return;
        }

        $pList = $xpath->query('./w:p', $cell);
        $p = ($pList && $pList->length > 0) ? $pList->item(0) : null;
        if (! $p) {
            $p = $cell->ownerDocument->createElementNS(
                'http://schemas.openxmlformats.org/wordprocessingml/2006/main',
                'w:p'
            );
            $cell->appendChild($p);
        }

        $r = $cell->ownerDocument->createElementNS(
            'http://schemas.openxmlformats.org/wordprocessingml/2006/main',
            'w:r'
        );
        $t = $cell->ownerDocument->createElementNS(
            'http://schemas.openxmlformats.org/wordprocessingml/2006/main',
            'w:t',
            $this->escapeXmlText($text)
        );
        $t->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
        $r->appendChild($t);
        $p->appendChild($r);
    }

    protected function replaceTextEverywhere(DOMXPath $xpath, string $find, string $replace): void
    {
        if ($find === '' || $replace === $find) {
            return;
        }

        $nodes = $xpath->query('//w:t');
        if (! $nodes) {
            return;
        }

        foreach ($nodes as $node) {
            if (! $node instanceof DOMNode) {
                continue;
            }
            $value = (string) $node->nodeValue;
            if ($value !== '' && str_contains($value, $find)) {
                $node->nodeValue = $this->escapeXmlText(str_replace($find, $replace, $value));
            }
        }
    }

    protected function escapeXmlText(string $text): string
    {
        return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
