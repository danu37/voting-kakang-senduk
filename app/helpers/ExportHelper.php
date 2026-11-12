<?php
declare(strict_types=1);

/**
 * Helper Ekspor Laporan Hasil Voting (Excel .xlsx & PDF .pdf)
 * Menggunakan PHP Native murni tanpa ketergantungan framework/ekstensi eksternal.
 */

if (PHP_SAPI !== 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(403);
    exit('Akses langsung ditolak.');
}

if (!function_exists('buildVotingHasilXlsxBinary')) {
    /**
     * Menghasilkan file binary Excel (.xlsx / OOXML) berisi Ringkasan, Hasil Kakang, dan Hasil Senduk.
     */
    function buildVotingHasilXlsxBinary(array $data): string
    {
        $periode = $data['periode_terpilih'] ?? null;
        $namaPeriode = is_array($periode) ? (string) ($periode['nama_periode'] ?? '-') : 'Belum Ada Periode';
        $jadwalStatus = is_array($periode) ? (string) ($periode['jadwal_status'] ?? 'Nonaktif') : '-';
        $jadwalTeks = is_array($periode)
            ? formatTanggalIndonesia((string) ($periode['tanggal_mulai'] ?? ''), true)
                . ' s.d. '
                . formatTanggalIndonesia((string) ($periode['tanggal_selesai'] ?? ''), true)
            : '-';
        $waktuCetak = (string) ($data['waktu_pembaruan_detik'] ?? $data['waktu_pembaruan'] ?? date('Y-m-d H:i:s'));

        $statKat = $data['statistik_kategori'] ?? [];
        $itemsKakang = $data['hasil_kakang']['items'] ?? [];
        $itemsSenduk = $data['hasil_senduk']['items'] ?? [];

        $xmlEsc = static function (string $val): string {
            $cleaned = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $val) ?? $val;
            return htmlspecialchars($cleaned, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        };

        $colLetter = static function (int $colIndex): string {
            $letter = '';
            $n = $colIndex;
            while ($n > 0) {
                $mod = ($n - 1) % 26;
                $letter = chr(65 + $mod) . $letter;
                $n = (int) (($n - 1) / 26);
            }
            return $letter;
        };

        $buildSheetXml = static function (array $rows, array $colWidths = []) use ($xmlEsc, $colLetter): string {
            $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';

            if (!empty($colWidths)) {
                $xml .= '<cols>';
                foreach ($colWidths as $idx => $width) {
                    $cNum = $idx + 1;
                    $xml .= '<col min="' . $cNum . '" max="' . $cNum . '" width="' . $width . '" customWidth="1"/>';
                }
                $xml .= '</cols>';
            }

            $xml .= '<sheetData>';
            foreach ($rows as $rIdx => $rowSpec) {
                $rowNum = $rIdx + 1;
                $cells = $rowSpec['cells'] ?? [];
                $style = (int) ($rowSpec['style'] ?? 0);
                $xml .= '<row r="' . $rowNum . '">';

                foreach ($cells as $cIdx => $cellVal) {
                    $ref = $colLetter($cIdx + 1) . $rowNum;
                    $cellStyle = is_array($cellVal) ? (int) ($cellVal['style'] ?? $style) : $style;
                    $rawVal = is_array($cellVal) ? ($cellVal['value'] ?? '') : $cellVal;
                    $isNum = (is_int($rawVal) || is_float($rawVal));

                    if ($isNum) {
                        $xml .= '<c r="' . $ref . '" s="' . $cellStyle . '"><v>' . $rawVal . '</v></c>';
                    } else {
                        $xml .= '<c r="' . $ref . '" s="' . $cellStyle . '" t="inlineStr"><is><t xml:space="preserve">'
                            . $xmlEsc((string) $rawVal)
                            . '</t></is></c>';
                    }
                }

                $xml .= '</row>';
            }
            $xml .= '</sheetData></worksheet>';
            return $xml;
        };

        // Sheet 1: Ringkasan & Statistik Kategori
        $ringkasanRows = [
            ['style' => 1, 'cells' => ['LAPORAN HASIL VOTING PEMILIHAN KAKANG SENDUK PONOROGO']],
            ['style' => 0, 'cells' => ['Periode Pemilihan', $namaPeriode]],
            ['style' => 0, 'cells' => ['Jadwal Periode', $jadwalTeks]],
            ['style' => 0, 'cells' => ['Status Jadwal', $jadwalStatus]],
            ['style' => 0, 'cells' => ['Waktu Ekspor (Server)', $waktuCetak]],
            ['style' => 0, 'cells' => []],
            ['style' => 2, 'cells' => ['Metrik Keseluruhan', 'Nilai']],
            ['style' => 3, 'cells' => ['Total Suara Keseluruhan', (int) ($data['total_semua'] ?? 0)]],
            ['style' => 3, 'cells' => ['Total Suara Kakang', (int) ($data['total_kakang'] ?? 0)]],
            ['style' => 3, 'cells' => ['Total Suara Senduk', (int) ($data['total_senduk'] ?? 0)]],
            ['style' => 3, 'cells' => ['Total Pemilih Unik Berpartisipasi', (int) ($data['total_pemilih_unik'] ?? 0)]],
            ['style' => 0, 'cells' => []],
            ['style' => 2, 'cells' => ['Kategori', 'Jumlah Kontestan Aktif', 'Total Suara', 'Persentase Total (%)', 'Pemimpin Sementara']],
        ];

        foreach (['Kakang', 'Senduk'] as $kat) {
            $st = $statKat[$kat] ?? [];
            $leader = $st['pemimpin'] ?? null;
            $leaderText = is_array($leader)
                ? ($leader['nomor_peserta'] . ' - ' . $leader['nama'] . ' (' . (int) $leader['jumlah_suara'] . ' suara)')
                : 'Belum ada suara';
            $ringkasanRows[] = [
                'style' => 3,
                'cells' => [
                    $kat,
                    (int) ($st['jumlah_kontestan'] ?? 0),
                    (int) ($st['total_suara'] ?? 0),
                    (float) ($st['persentase_keseluruhan'] ?? 0.0),
                    $leaderText,
                ],
            ];
        }

        $buildCategorySheetRows = static function (string $kategori, array $items, int $totalKat) use ($namaPeriode, $waktuCetak): array {
            $rows = [
                ['style' => 1, 'cells' => ["HASIL PEROLEHAN SUARA KATEGORI " . strtoupper($kategori)]],
                ['style' => 0, 'cells' => ['Periode', $namaPeriode, 'Total Suara Kategori', $totalKat]],
                ['style' => 0, 'cells' => ['Waktu Pembaruan', $waktuCetak]],
                ['style' => 0, 'cells' => []],
                ['style' => 2, 'cells' => ['Ranking', 'Nomor Peserta', 'Nama Kontestan', 'Asal Instansi', 'Jumlah Suara', 'Persentase (%)', 'Keterangan']],
            ];

            foreach ($items as $item) {
                $ket = !empty($item['is_seri']) ? 'Suara Seri (Tie-Breaker)' : '-';
                $rows[] = [
                    'style' => 3,
                    'cells' => [
                        (int) ($item['ranking'] ?? 0),
                        (string) ($item['nomor_peserta'] ?? ''),
                        (string) ($item['nama'] ?? ''),
                        (string) ($item['asal_instansi'] ?? ''),
                        (int) ($item['jumlah_suara'] ?? 0),
                        (float) ($item['persentase'] ?? 0.0),
                        $ket,
                    ],
                ];
            }

            if (empty($items)) {
                $rows[] = ['style' => 3, 'cells' => ['Belum ada kontestan aktif pada kategori ' . $kategori . '.']];
            }

            return $rows;
        };

        $sheet1Xml = $buildSheetXml($ringkasanRows, [34, 24, 20, 22, 38]);
        $sheet2Xml = $buildSheetXml(
            $buildCategorySheetRows('Kakang', $itemsKakang, (int) ($data['total_kakang'] ?? 0)),
            [10, 16, 30, 30, 16, 16, 26]
        );
        $sheet3Xml = $buildSheetXml(
            $buildCategorySheetRows('Senduk', $itemsSenduk, (int) ($data['total_senduk'] ?? 0)),
            [10, 16, 30, 30, 16, 16, 26]
        );

        $contentTypesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet3.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '</Types>';

        $rootRelsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';

        $workbookXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets>'
            . '<sheet name="Ringkasan" sheetId="1" r:id="rId1"/>'
            . '<sheet name="Klasemen Kakang" sheetId="2" r:id="rId2"/>'
            . '<sheet name="Klasemen Senduk" sheetId="3" r:id="rId3"/>'
            . '</sheets>'
            . '</workbook>';

        $workbookRelsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet3.xml"/>'
            . '<Relationship Id="rId4" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';

        $stylesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="3">'
            . '<font><sz val="11"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="13"/><color rgb="FF022C22"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
            . '</fonts>'
            . '<fills count="3">'
            . '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF047857"/></patternFill></fill>'
            . '</fills>'
            . '<borders count="2">'
            . '<border><left/><right/><top/><bottom/><diagonal/></border>'
            . '<border><left style="thin"><color rgb="FFD1D5DB"/></left><right style="thin"><color rgb="FFD1D5DB"/></right><top style="thin"><color rgb="FFD1D5DB"/></top><bottom style="thin"><color rgb="FFD1D5DB"/></bottom></border>'
            . '</borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="4">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            . '<xf numFmtId="0" fontId="2" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/>'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"/>'
            . '</cellXfs>'
            . '</styleSheet>';

        $entries = [
            '[Content_Types].xml'        => $contentTypesXml,
            '_rels/.rels'                => $rootRelsXml,
            'xl/workbook.xml'            => $workbookXml,
            'xl/_rels/workbook.xml.rels' => $workbookRelsXml,
            'xl/styles.xml'              => $stylesXml,
            'xl/worksheets/sheet1.xml'   => $sheet1Xml,
            'xl/worksheets/sheet2.xml'   => $sheet2Xml,
            'xl/worksheets/sheet3.xml'   => $sheet3Xml,
        ];

        return ksBuildZipArchiveBinary($entries);
    }
}

if (!function_exists('ksBuildZipArchiveBinary')) {
    /**
     * Membentuk arsip PKZIP standar (DEFLATE via zlib atau STORE) tanpa membutuhkan ekstensi zip.
     *
     * @param array<string, string> $entries Peta nama_file => isi_binary
     */
    function ksBuildZipArchiveBinary(array $entries): string
    {
        $now = getdate();
        $dosTime = (($now['hours'] << 11) | ($now['minutes'] << 5) | ($now['seconds'] >> 1)) & 0xFFFF;
        $dosDate = ((max(0, $now['year'] - 1980) << 9) | ($now['mon'] << 5) | $now['mday']) & 0xFFFF;

        $dataBlock = '';
        $centralDir = '';
        $offset = 0;
        $count = 0;

        foreach ($entries as $name => $rawData) {
            $nameLen = strlen($name);
            $uncompressedLen = strlen($rawData);
            $crc = crc32($rawData);

            $compressed = function_exists('gzdeflate') ? gzdeflate($rawData, 6) : false;
            if ($compressed !== false && strlen($compressed) < $uncompressedLen) {
                $method = 8;
                $payload = $compressed;
            } else {
                $method = 0;
                $payload = $rawData;
            }
            $compressedLen = strlen($payload);

            // Local File Header (30 byte + filename)
            $localHeader = pack(
                'VvvvvvVVVvv',
                0x04034b50,
                20,
                0,
                $method,
                $dosTime,
                $dosDate,
                $crc,
                $compressedLen,
                $uncompressedLen,
                $nameLen,
                0
            ) . $name;

            $dataBlock .= $localHeader . $payload;

            // Central Directory File Header (46 byte + filename)
            $centralDir .= pack(
                'VvvvvvvVVVvvvvvVV',
                0x02014b50,
                20,
                20,
                0,
                $method,
                $dosTime,
                $dosDate,
                $crc,
                $compressedLen,
                $uncompressedLen,
                $nameLen,
                0,
                0,
                0,
                0,
                32,
                $offset
            ) . $name;

            $offset += strlen($localHeader) + $compressedLen;
            $count++;
        }

        $cdSize = strlen($centralDir);
        $eocd = pack(
            'VvvvvVVv',
            0x06054b50,
            0,
            0,
            $count,
            $count,
            $cdSize,
            $offset,
            0
        );

        return $dataBlock . $centralDir . $eocd;
    }
}

if (!function_exists('buildVotingHasilPdfBinary')) {
    /**
     * Menghasilkan dokumen laporan PDF 1.4 resmi hasil voting Kakang & Senduk secara native PHP.
     */
    function buildVotingHasilPdfBinary(array $data): string
    {
        $periode = $data['periode_terpilih'] ?? null;
        $namaPeriode = is_array($periode) ? (string) ($periode['nama_periode'] ?? '-') : 'Belum Ada Periode';
        $jadwalStatus = is_array($periode) ? (string) ($periode['jadwal_status'] ?? 'Nonaktif') : '-';
        $jadwalTeks = is_array($periode)
            ? formatTanggalIndonesia((string) ($periode['tanggal_mulai'] ?? ''), true)
                . ' s.d. '
                . formatTanggalIndonesia((string) ($periode['tanggal_selesai'] ?? ''), true)
            : '-';
        $waktuCetak = (string) ($data['waktu_pembaruan_detik'] ?? $data['waktu_pembaruan'] ?? date('Y-m-d H:i:s'));

        $totalSemua = (int) ($data['total_semua'] ?? 0);
        $totalKakang = (int) ($data['total_kakang'] ?? 0);
        $totalSenduk = (int) ($data['total_senduk'] ?? 0);
        $totalPemilihUnik = (int) ($data['total_pemilih_unik'] ?? 0);
        $statKat = $data['statistik_kategori'] ?? [];

        $encodePdfText = static function (string $text, int $maxChars = 0): string {
            $clean = preg_replace('/[\r\n\t]+/', ' ', trim($text)) ?? trim($text);
            if ($maxChars > 3 && mb_strlen($clean, 'UTF-8') > $maxChars) {
                $clean = mb_substr($clean, 0, $maxChars - 3, 'UTF-8') . '...';
            }
            $winAnsi = function_exists('iconv')
                ? (@iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $clean) ?: $clean)
                : $clean;
            $winAnsi = preg_replace('/[\x00-\x1F\x7F]/', ' ', $winAnsi) ?? $winAnsi;
            return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $winAnsi);
        };

        $pagesStreams = [];
        $currentStream = '';
        $y = 800.0;

        $startNewPage = static function (bool $isFirstPage) use (&$pagesStreams, &$currentStream, &$y, $encodePdfText, $namaPeriode): void {
            if ($currentStream !== '') {
                $pagesStreams[] = $currentStream;
            }
            $currentStream = '';

            if ($isFirstPage) {
                // Header banner Deep Green + Gold rule
                $currentStream .= "0.008 0.173 0.133 rg 36 742 523 64 re f\n";
                $currentStream .= "0.831 0.686 0.216 rg 36 740 523 2.5 re f\n";
                $currentStream .= "BT /F2 13 Tf 1 1 1 rg 48 784 Td (" . $encodePdfText('LAPORAN HASIL VOTING KAKANG SENDUK PONOROGO', 65) . ") Tj ET\n";
                $currentStream .= "BT /F1 9.5 Tf 0.953 0.851 0.545 rg 48 766 Td (" . $encodePdfText('Sistem Informasi Pemilihan Duta Wisata Kabupaten Ponorogo', 75) . ") Tj ET\n";
                $currentStream .= "BT /F1 8.5 Tf 0.85 0.9 0.88 rg 48 751 Td (" . $encodePdfText('Periode: ' . $namaPeriode, 78) . ") Tj ET\n";
                $y = 720.0;
            } else {
                $currentStream .= "BT /F2 10 Tf 0.024 0.306 0.231 rg 36 802 Td (" . $encodePdfText('Lanjutan Laporan Hasil Voting - ' . $namaPeriode, 76) . ") Tj ET\n";
                $currentStream .= "0.831 0.686 0.216 RG 1 w 36 794 m 559 794 l S\n";
                $y = 776.0;
            }
        };

        $ensureSpace = static function (float $neededHeight) use (&$y, $startNewPage): bool {
            if (($y - $neededHeight) < 62.0) {
                $startNewPage(false);
                return true;
            }
            return false;
        };

        $startNewPage(true);

        // Blok Metadata & Statistik Ringkas
        $currentStream .= "0.973 0.980 0.969 rg 36 636 523 84 re f\n";
        $currentStream .= "0.898 0.906 0.922 RG 0.8 w 36 636 523 84 re S\n";

        $metaLines = [
            ['Periode Voting', $namaPeriode, 'Total Keseluruhan', number_format($totalSemua, 0, ',', '.') . ' suara'],
            ['Jadwal Periode', $jadwalTeks, 'Total Suara Kakang', number_format($totalKakang, 0, ',', '.') . ' suara'],
            ['Status Jadwal', $jadwalStatus, 'Total Suara Senduk', number_format($totalSenduk, 0, ',', '.') . ' suara'],
            ['Waktu Cetak', $waktuCetak, 'Pemilih Berpartisipasi', number_format($totalPemilihUnik, 0, ',', '.') . ' pemilih'],
        ];
        $my = 704.0;
        foreach ($metaLines as $ml) {
            $currentStream .= "BT /F2 8.5 Tf 0.392 0.455 0.545 rg 46 {$my} Td (" . $encodePdfText($ml[0] . ':') . ") Tj ET\n";
            $currentStream .= "BT /F1 8.5 Tf 0.118 0.161 0.231 rg 122 {$my} Td (" . $encodePdfText($ml[1], 44) . ") Tj ET\n";
            $currentStream .= "BT /F2 8.5 Tf 0.392 0.455 0.545 rg 366 {$my} Td (" . $encodePdfText($ml[2] . ':') . ") Tj ET\n";
            $currentStream .= "BT /F2 8.5 Tf 0.016 0.471 0.341 rg 468 {$my} Td (" . $encodePdfText($ml[3], 24) . ") Tj ET\n";
            $my -= 17.5;
        }
        $y = 614.0;

        // Tabel Statistik Kategori
        $currentStream .= "BT /F2 10.5 Tf 0.008 0.173 0.133 rg 36 {$y} Td (" . $encodePdfText('RINGKASAN STATISTIK KATEGORI') . ") Tj ET\n";
        $y -= 18.0;

        $currentStream .= "0.016 0.471 0.341 rg 36 " . ($y - 5.0) . " 523 20 re f\n";
        $ty = $y + 1.0;
        $currentStream .= "BT /F2 8.5 Tf 1 1 1 rg 44 {$ty} Td (Kategori) Tj ET\n";
        $currentStream .= "BT /F2 8.5 Tf 1 1 1 rg 118 {$ty} Td (Kontestan) Tj ET\n";
        $currentStream .= "BT /F2 8.5 Tf 1 1 1 rg 194 {$ty} Td (Total Suara) Tj ET\n";
        $currentStream .= "BT /F2 8.5 Tf 1 1 1 rg 278 {$ty} Td (Porsi) Tj ET\n";
        $currentStream .= "BT /F2 8.5 Tf 1 1 1 rg 344 {$ty} Td (Pemimpin Sementara) Tj ET\n";
        $y -= 25.0;

        foreach (['Kakang', 'Senduk'] as $idx => $kat) {
            $st = $statKat[$kat] ?? [];
            $leader = $st['pemimpin'] ?? null;
            $leaderTxt = is_array($leader)
                ? ($leader['nomor_peserta'] . ' ' . $leader['nama'] . ' (' . (int) $leader['jumlah_suara'] . ' suara)')
                : 'Belum ada suara';

            if ($idx % 2 === 1) {
                $currentStream .= "0.973 0.980 0.988 rg 36 " . ($y - 5.0) . " 523 19 re f\n";
            }
            $currentStream .= "0.898 0.906 0.922 RG 0.5 w 36 " . ($y - 5.0) . " m 559 " . ($y - 5.0) . " l S\n";
            $ry = $y + 1.0;
            $currentStream .= "BT /F2 8.5 Tf 0.118 0.161 0.231 rg 44 {$ry} Td (" . $encodePdfText($kat) . ") Tj ET\n";
            $currentStream .= "BT /F1 8.5 Tf 0.118 0.161 0.231 rg 118 {$ry} Td (" . (int) ($st['jumlah_kontestan'] ?? 0) . " aktif) Tj ET\n";
            $currentStream .= "BT /F1 8.5 Tf 0.118 0.161 0.231 rg 194 {$ry} Td (" . number_format((int) ($st['total_suara'] ?? 0), 0, ',', '.') . " suara) Tj ET\n";
            $currentStream .= "BT /F1 8.5 Tf 0.118 0.161 0.231 rg 278 {$ry} Td (" . number_format((float) ($st['persentase_keseluruhan'] ?? 0.0), 1, ',', '.') . "%) Tj ET\n";
            $currentStream .= "BT /F1 8.5 Tf 0.118 0.161 0.231 rg 344 {$ry} Td (" . $encodePdfText($leaderTxt, 38) . ") Tj ET\n";
            $y -= 20.0;
        }
        $y -= 14.0;

        // Fungsi internal render tabel per kategori
        $drawTableHeader = static function (string $title) use (&$currentStream, &$y, $encodePdfText): void {
            $currentStream .= "BT /F2 10.5 Tf 0.008 0.173 0.133 rg 36 {$y} Td (" . $encodePdfText($title) . ") Tj ET\n";
            $y -= 18.0;
            $currentStream .= "0.016 0.471 0.341 rg 36 " . ($y - 5.0) . " 523 20 re f\n";
            $hy = $y + 1.0;
            $currentStream .= "BT /F2 8.5 Tf 1 1 1 rg 42 {$hy} Td (Rank) Tj ET\n";
            $currentStream .= "BT /F2 8.5 Tf 1 1 1 rg 76 {$hy} Td (No.) Tj ET\n";
            $currentStream .= "BT /F2 8.5 Tf 1 1 1 rg 132 {$hy} Td (Nama Kontestan) Tj ET\n";
            $currentStream .= "BT /F2 8.5 Tf 1 1 1 rg 302 {$hy} Td (Asal Instansi) Tj ET\n";
            $currentStream .= "BT /F2 8.5 Tf 1 1 1 rg 448 {$hy} Td (Suara) Tj ET\n";
            $currentStream .= "BT /F2 8.5 Tf 1 1 1 rg 504 {$hy} Td (Persen) Tj ET\n";
            $y -= 24.0;
        };

        $renderKategoriSection = static function (string $kategori, array $hasilData) use (
            &$currentStream,
            &$y,
            $ensureSpace,
            $drawTableHeader,
            $encodePdfText
        ): void {
            $items = $hasilData['items'] ?? [];
            $totalKat = (int) ($hasilData['total_suara'] ?? 0);
            $sectionTitle = 'PERINGKAT KATEGORI ' . strtoupper($kategori) . ' (Total: ' . number_format($totalKat, 0, ',', '.') . ' suara)';

            $ensureSpace(75.0);
            $drawTableHeader($sectionTitle);

            if (empty($items)) {
                $currentStream .= "0.898 0.906 0.922 RG 0.5 w 36 " . ($y - 5.0) . " m 559 " . ($y - 5.0) . " l S\n";
                $ry = $y + 1.0;
                $currentStream .= "BT /F1 8.5 Tf 0.392 0.455 0.545 rg 42 {$ry} Td (" . $encodePdfText('Belum ada kontestan aktif pada kategori ' . $kategori . '.') . ") Tj ET\n";
                $y -= 28.0;
                return;
            }

            foreach ($items as $idx => $item) {
                if ($ensureSpace(24.0)) {
                    $drawTableHeader($sectionTitle . ' (Lanjutan)');
                }

                if ($idx % 2 === 1) {
                    $currentStream .= "0.973 0.980 0.988 rg 36 " . ($y - 5.0) . " 523 19 re f\n";
                }
                $currentStream .= "0.898 0.906 0.922 RG 0.5 w 36 " . ($y - 5.0) . " m 559 " . ($y - 5.0) . " l S\n";

                $ry = $y + 1.0;
                $rankText = '#' . (int) ($item['ranking'] ?? ($idx + 1));
                $noText = (string) ($item['nomor_peserta'] ?? '-');
                $namaText = (string) ($item['nama'] ?? '-');
                if (!empty($item['is_seri'])) {
                    $namaText .= ' [Seri]';
                }
                $instansiText = (string) ($item['asal_instansi'] ?? '-');
                $suaraText = number_format((int) ($item['jumlah_suara'] ?? 0), 0, ',', '.');
                $persenText = number_format((float) ($item['persentase'] ?? 0.0), 2, ',', '.') . '%';

                $currentStream .= "BT /F2 8.5 Tf 0.118 0.161 0.231 rg 42 {$ry} Td (" . $encodePdfText($rankText, 8) . ") Tj ET\n";
                $currentStream .= "BT /F1 8.5 Tf 0.118 0.161 0.231 rg 76 {$ry} Td (" . $encodePdfText($noText, 12) . ") Tj ET\n";
                $currentStream .= "BT /F1 8.5 Tf 0.118 0.161 0.231 rg 132 {$ry} Td (" . $encodePdfText($namaText, 30) . ") Tj ET\n";
                $currentStream .= "BT /F1 8.5 Tf 0.392 0.455 0.545 rg 302 {$ry} Td (" . $encodePdfText($instansiText, 25) . ") Tj ET\n";
                $currentStream .= "BT /F2 8.5 Tf 0.016 0.471 0.341 rg 448 {$ry} Td (" . $encodePdfText($suaraText, 10) . ") Tj ET\n";
                $currentStream .= "BT /F1 8.5 Tf 0.118 0.161 0.231 rg 504 {$ry} Td (" . $encodePdfText($persenText, 10) . ") Tj ET\n";

                $y -= 20.0;
            }

            $y -= 14.0;
        };

        $renderKategoriSection('Kakang', $data['hasil_kakang'] ?? []);
        $renderKategoriSection('Senduk', $data['hasil_senduk'] ?? []);

        if ($currentStream !== '') {
            $pagesStreams[] = $currentStream;
        }

        // Tambahkan footer halaman ke setiap halaman
        $totalPages = count($pagesStreams);
        foreach ($pagesStreams as $pIdx => $streamBody) {
            $pageNum = $pIdx + 1;
            $footerCmd = "0.898 0.906 0.922 RG 0.5 w 36 42 m 559 42 l S\n"
                . "BT /F1 8 Tf 0.392 0.455 0.545 rg 36 28 Td (" . $encodePdfText('Laporan Resmi Sistem Voting Pemilihan Kakang Senduk Ponorogo') . ") Tj ET\n"
                . "BT /F1 8 Tf 0.392 0.455 0.545 rg 490 28 Td (" . $encodePdfText("Halaman {$pageNum} / {$totalPages}") . ") Tj ET\n";
            $pagesStreams[$pIdx] = $streamBody . $footerCmd;
        }

        // Susun struktur objek PDF 1.4 dan hitung byte offset XRef yang tepat
        $objects = [];
        $objects[1] = "<< /Type /Catalog /Pages 2 0 R >>";
        $pageObjectIds = [];
        $nextObjId = 5;

        foreach ($pagesStreams as $streamContent) {
            $pageObjId = $nextObjId++;
            $contentObjId = $nextObjId++;
            $pageObjectIds[] = $pageObjId;

            $objects[$pageObjId] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] "
                . "/Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> "
                . "/Contents {$contentObjId} 0 R >>";

            $streamLen = strlen($streamContent);
            $objects[$contentObjId] = "<< /Length {$streamLen} >>\nstream\n{$streamContent}endstream";
        }

        $kidsRef = implode(' ', array_map(static fn (int $id): string => "{$id} 0 R", $pageObjectIds));
        $objects[2] = "<< /Type /Pages /Kids [ {$kidsRef} ] /Count " . count($pageObjectIds) . " >>";
        $objects[3] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>";
        $objects[4] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>";
        ksort($objects);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0 => 0];

        foreach ($objects as $objId => $objBody) {
            $offsets[$objId] = strlen($pdf);
            $pdf .= "{$objId} 0 obj\n{$objBody}\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $maxObjId = count($objects);
        $pdf .= "xref\n0 " . ($maxObjId + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= $maxObjId; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }

        $pdf .= "trailer\n<< /Size " . ($maxObjId + 1) . " /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF\n";
        return $pdf;
    }
}

