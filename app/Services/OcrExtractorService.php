<?php

namespace App\Services;

use Smalot\PdfParser\Parser as PdfParser;
use Illuminate\Support\Facades\Log;

class OcrExtractorService
{
    /**
     * Extract text from a PDF file.
     * Tries digital text extraction first, falls back to Tesseract OCR.
     */
    public function extract(string $filePath): array
    {
        $text = $this->extractDigitalText($filePath);
        $method = 'pdfparser';

        // If too little text extracted, try OCR
        if (mb_strlen(trim($text)) < 50) {
            $ocrText = $this->extractWithTesseract($filePath);
            if (mb_strlen(trim($ocrText)) > mb_strlen(trim($text))) {
                $text = $ocrText;
                $method = 'tesseract';
            }
        }

        return [
            'text'   => $text,
            'method' => $method,
            'length' => mb_strlen($text),
        ];
    }

    /**
     * Extract selectable text from a digital PDF using smalot/pdfparser.
     */
    private function extractDigitalText(string $filePath): string
    {
        try {
            $parser = new PdfParser();
            $pdf = $parser->parseFile($filePath);
            return $pdf->getText();
        } catch (\Exception $e) {
            Log::warning('PdfParser extraction failed: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Extract text from a scanned PDF using Tesseract OCR.
     * Requires: tesseract-ocr and tesseract-ocr-fra installed on the system.
     */
    private function extractWithTesseract(string $filePath): string
    {
        // Check if tesseract is available
        $tesseractBin = $this->findTesseract();
        if (!$tesseractBin) {
            Log::warning('Tesseract not found on system. OCR fallback unavailable.');
            return '';
        }

        try {
            $tempDir = sys_get_temp_dir() . '/ocr_' . uniqid();
            mkdir($tempDir, 0755, true);

            // Convert PDF pages to images using Imagick
            if (extension_loaded('imagick')) {
                $imagick = new \Imagick();
                $imagick->setResolution(300, 300);
                $imagick->readImage($filePath);

                $fullText = '';
                $pageCount = $imagick->getNumberImages();

                for ($i = 0; $i < $pageCount; $i++) {
                    $imagick->setIteratorIndex($i);
                    $imagick->setImageFormat('png');
                    $imgPath = "$tempDir/page_$i.png";
                    $imagick->writeImage($imgPath);

                    // Run Tesseract on the image
                    $outputBase = "$tempDir/page_$i";
                    $cmd = escapeshellarg($tesseractBin) . ' '
                         . escapeshellarg($imgPath) . ' '
                         . escapeshellarg($outputBase)
                         . ' -l fra 2>&1';
                    exec($cmd, $output, $returnCode);

                    if ($returnCode === 0 && file_exists("$outputBase.txt")) {
                        $fullText .= file_get_contents("$outputBase.txt") . "\n";
                    }
                }

                $imagick->clear();
                $imagick->destroy();
            } else {
                // Fallback: try pdftoppm + tesseract
                $cmd = "pdftoppm -r 300 -png " . escapeshellarg($filePath) . " " . escapeshellarg("$tempDir/page");
                exec($cmd, $output, $returnCode);

                $fullText = '';
                $images = glob("$tempDir/page-*.png");
                sort($images);

                foreach ($images as $imgPath) {
                    $outputBase = str_replace('.png', '', $imgPath);
                    $cmd = escapeshellarg($tesseractBin) . ' '
                         . escapeshellarg($imgPath) . ' '
                         . escapeshellarg($outputBase)
                         . ' -l fra 2>&1';
                    exec($cmd);
                    if (file_exists("$outputBase.txt")) {
                        $fullText .= file_get_contents("$outputBase.txt") . "\n";
                    }
                }
            }

            // Cleanup temp dir
            array_map('unlink', glob("$tempDir/*"));
            rmdir($tempDir);

            return $fullText;
        } catch (\Exception $e) {
            Log::warning('Tesseract OCR failed: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Find the tesseract binary on the system.
     */
    private function findTesseract(): ?string
    {
        $paths = [
            'tesseract',                                      // Linux/Mac (in PATH)
            '/usr/bin/tesseract',                             // Linux
            'C:\\Program Files\\Tesseract-OCR\\tesseract.exe', // Windows
            'C:\\laragon\\bin\\tesseract\\tesseract.exe',      // Laragon
        ];

        foreach ($paths as $path) {
            $cmd = PHP_OS_FAMILY === 'Windows'
                ? 'where ' . escapeshellarg($path) . ' 2>NUL'
                : 'which ' . escapeshellarg($path) . ' 2>/dev/null';
            exec($cmd, $output, $returnCode);

            if ($returnCode === 0 || file_exists($path)) {
                return $path;
            }
        }

        return null;
    }
}
