<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomPremiumDomain;
use App\Models\Domain;
use App\Support\SpreadsheetReader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Tab "Domain Premium Custom": daftar nama domain premium tertentu + harga modal,
 * diimpor dari Excel/CSV. Harga jual diisi admin; kosong = tidak dijual ke publik.
 */
class CustomPremiumDomainController extends Controller
{
    private const MAX_IMPORT_ROWS = 5000;

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));

        $domains = CustomPremiumDomain::query()
            ->when($q !== '', fn ($query) => $query->where('domain_name', 'like', '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%'))
            ->orderBy('domain_name')
            ->paginate(50)
            ->withQueryString();

        $taken = Domain::query()
            ->whereIn('domain_name', $domains->pluck('domain_name'))
            ->whereIn('status', ['pending', 'active'])
            ->pluck('domain_name')
            ->map(fn ($d) => strtolower($d))
            ->all();

        return view('admin.tlds.premium-custom', [
            'domains' => $domains,
            'taken' => $taken,
            'q' => $q,
            'totals' => [
                'all' => CustomPremiumDomain::count(),
                'for_sale' => CustomPremiumDomain::forSale()->count(),
                'no_price' => CustomPremiumDomain::query()->where(fn ($w) => $w->whereNull('sell_price')->orWhere('sell_price', '<=', 0))->count(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'domain_name' => ['required', 'string', 'max:120'],
            'age_label' => ['nullable', 'string', 'max:50'],
            'cost_price' => ['required', 'numeric', 'min:0'],
            'sell_price' => ['nullable', 'numeric', 'min:0'],
            'renew_price' => ['nullable', 'numeric', 'min:0'],
        ]);

        $name = preg_replace('/\s+premium$/i', '', trim($data['domain_name']));
        $parts = CustomPremiumDomain::splitDomain($name);

        if (! $parts) {
            return back()->withInput()->withErrors(['domain_name' => 'Nama domain tidak valid. Contoh: abner.id']);
        }

        $sell = isset($data['sell_price']) ? (float) $data['sell_price'] : null;
        if ($sell !== null && $sell > 0 && $sell < (float) $data['cost_price']) {
            return back()->withInput()->withErrors(['sell_price' => 'Harga jual tidak boleh di bawah harga modal.']);
        }

        if (CustomPremiumDomain::where('domain_name', strtolower($name))->exists()) {
            return back()->withInput()->withErrors(['domain_name' => 'Domain ini sudah ada di daftar.']);
        }

        [$label, $ext] = $parts;

        CustomPremiumDomain::create([
            'domain_name' => strtolower($name),
            'label' => $label,
            'extension' => $ext,
            'characters' => strlen($label),
            'age_label' => $data['age_label'] ?? null,
            'years' => $this->yearsFrom($data['age_label'] ?? null),
            'cost_price' => $data['cost_price'],
            'sell_price' => $sell ?: null,
            'renew_price' => ! empty($data['renew_price']) ? $data['renew_price'] : null,
        ]);

        return back()->with('success', strtolower($name) . ' ditambahkan.');
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'rows' => ['required', 'array'],
            'rows.*.sell_price' => ['nullable', 'numeric', 'min:0'],
            'rows.*.renew_price' => ['nullable', 'numeric', 'min:0'],
            'rows.*.is_active' => ['nullable', 'boolean'],
        ]);

        $models = CustomPremiumDomain::whereIn('id', array_keys($data['rows']))->get()->keyBy('id');

        $errors = [];
        foreach ($data['rows'] as $id => $row) {
            $m = $models->get($id);
            $sell = (float) ($row['sell_price'] ?? 0);
            if ($m && $sell > 0 && $sell < (float) $m->cost_price) {
                $errors[] = "{$m->domain_name}: harga jual di bawah modal";
            }
        }

        if ($errors) {
            return back()->with('error', 'Tidak ada yang disimpan. ' . implode('; ', array_slice($errors, 0, 5)) . (count($errors) > 5 ? ' …' : ''));
        }

        DB::transaction(function () use ($data, $models) {
            foreach ($data['rows'] as $id => $row) {
                $m = $models->get($id);
                if (! $m) {
                    continue;
                }
                $sell = (float) ($row['sell_price'] ?? 0);
                $renew = (float) ($row['renew_price'] ?? 0);
                $m->update([
                    'sell_price' => $sell > 0 ? $sell : null,
                    'renew_price' => $renew > 0 ? $renew : null,
                    'is_active' => (bool) ($row['is_active'] ?? false),
                ]);
            }
        });

        return back()->with('success', 'Perubahan disimpan.');
    }

    public function destroy(CustomPremiumDomain $custom): RedirectResponse
    {
        $name = $custom->domain_name;
        $custom->delete();

        return back()->with('success', "{$name} dihapus dari daftar.");
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:5120'],
            'margin_percent' => ['nullable', 'numeric', 'min:0', 'max:1000'],
        ]);

        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());

        if (! in_array($ext, ['xlsx', 'csv', 'txt'], true)) {
            return back()->with('error', 'Format file harus .xlsx atau .csv.');
        }

        try {
            $rows = SpreadsheetReader::read($file->getRealPath(), $ext);
        } catch (\Throwable $e) {
            return back()->with('error', 'File tidak bisa dibaca: ' . $e->getMessage());
        }

        if (count($rows) > self::MAX_IMPORT_ROWS + 1) {
            return back()->with('error', 'Maksimal ' . self::MAX_IMPORT_ROWS . ' baris per file.');
        }

        $map = $this->detectColumns($rows);
        if ($map === null) {
            return back()->with('error', 'Kolom tidak dikenali. Gunakan kolom: #, Domain Premium, Karakter, Usia, Harga modal.');
        }

        $margin = $request->filled('margin_percent') ? (float) $request->input('margin_percent') : null;
        $batch = Str::random(12);
        $created = $updated = 0;
        $skipped = [];

        DB::transaction(function () use ($rows, $map, $margin, $batch, &$created, &$updated, &$skipped) {
            foreach ($rows as $i => $row) {
                if ($i < $map['start']) {
                    continue;
                }

                $rawName = trim((string) ($row[$map['domain']] ?? ''));
                if ($rawName === '') {
                    continue;
                }

                $line = $i + 1;
                $name = strtolower(trim(preg_replace('/\s+premium\s*$/i', '', $rawName)));
                $parts = CustomPremiumDomain::splitDomain($name);
                $cost = SpreadsheetReader::parseMoney($row[$map['cost']] ?? null);

                if (! $parts) {
                    $skipped[] = "baris {$line}: nama \"{$rawName}\" tidak valid";
                    continue;
                }
                if ($cost === null || $cost < 0) {
                    $skipped[] = "baris {$line}: harga modal {$name} tidak terbaca";
                    continue;
                }

                [$label, $extension] = $parts;
                $ageLabel = isset($map['age']) ? trim((string) ($row[$map['age']] ?? '')) : '';
                $chars = isset($map['chars']) && is_numeric($row[$map['chars']] ?? null) ? (int) $row[$map['chars']] : strlen($label);

                $existing = CustomPremiumDomain::where('domain_name', $name)->first();
                $attrs = [
                    'label' => $label,
                    'extension' => $extension,
                    'characters' => min($chars, 63),
                    'age_label' => $ageLabel !== '' ? $ageLabel : null,
                    'years' => $this->yearsFrom($ageLabel),
                    'cost_price' => $cost,
                    'import_batch' => $batch,
                ];

                if ($existing) {
                    // Harga jual yang sudah diisi admin tidak pernah ditimpa; kalau jadi di bawah modal baru, kosongkan.
                    if ($existing->sell_price !== null && (float) $existing->sell_price < $cost) {
                        $attrs['sell_price'] = null;
                    } elseif ($existing->sell_price === null && $margin !== null) {
                        $attrs['sell_price'] = $this->withMargin($cost, $margin);
                    }
                    $existing->update($attrs);
                    $updated++;
                } else {
                    $attrs['domain_name'] = $name;
                    $attrs['sell_price'] = $margin !== null ? $this->withMargin($cost, $margin) : null;
                    CustomPremiumDomain::create($attrs);
                    $created++;
                }
            }
        });

        $msg = "Impor selesai: {$created} baru, {$updated} diperbarui.";
        if ($margin === null && $created > 0) {
            $msg .= ' Harga jual masih kosong, jadi belum tampil di publik. Isi harga jual di tabel.';
        }
        if ($skipped) {
            $msg .= ' Dilewati ' . count($skipped) . ': ' . implode('; ', array_slice($skipped, 0, 5)) . (count($skipped) > 5 ? ' …' : '');
        }

        return back()->with($created + $updated > 0 ? 'success' : 'error', $msg);
    }

    /** Template .xlsx asli: tiap kolom terpisah (tidak bergantung pemisah CSV di Excel/WPS). */
    public function template()
    {
        if (! class_exists(\ZipArchive::class)) {
            return $this->templateCsv();
        }

        $rows = [
            ['#', 'Domain Premium', 'Karakter', 'Usia', 'Harga modal'],
            [1, 'abner.id Premium', 5, '1 tahun', 6700000],
            [2, 'ached.id Premium', 5, '1 tahun', 1600000],
            [3, 'added.id Premium', 5, '1 tahun', 4300000],
        ];

        $sheet = '';
        foreach ($rows as $r => $row) {
            $sheet .= '<row r="' . ($r + 1) . '">';
            foreach ($row as $c => $value) {
                $ref = chr(65 + $c) . ($r + 1);
                if ($r === 0) {
                    $sheet .= '<c r="' . $ref . '" t="inlineStr" s="1"><is><t>' . e($value) . '</t></is></c>';
                } elseif (is_string($value)) {
                    $sheet .= '<c r="' . $ref . '" t="inlineStr"><is><t>' . e($value) . '</t></is></c>';
                } else {
                    $sheet .= '<c r="' . $ref . '"' . ($c === 4 ? ' s="2"' : '') . '><v>' . $value . '</v></c>';
                }
            }
            $sheet .= '</row>';
        }

        $ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $files = [
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="' . $ns . '" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Domain Premium" sheetId="1" r:id="rId1"/></sheets></workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
            'xl/styles.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="' . $ns . '"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/><xf numFmtId="3" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/></cellXfs></styleSheet>',
            'xl/worksheets/sheet1.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="' . $ns . '"><cols><col min="1" max="1" width="6" customWidth="1"/><col min="2" max="2" width="28" customWidth="1"/><col min="3" max="3" width="10" customWidth="1"/><col min="4" max="4" width="12" customWidth="1"/><col min="5" max="5" width="18" customWidth="1"/></cols><sheetData>' . $sheet . '</sheetData></worksheet>',
        ];

        $tmp = tempnam(sys_get_temp_dir(), 'tpl');
        $zip = new \ZipArchive();
        $zip->open($tmp, \ZipArchive::OVERWRITE);
        foreach ($files as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();

        return response()->download($tmp, 'template-domain-premium-custom.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /** CSV dengan titik koma: terbaca per kolom di Excel/WPS berlokal Indonesia. */
    public function templateCsv()
    {
        return response()->streamDownload(function () {
            echo "\xEF\xBB\xBF";
            echo "#;Domain Premium;Karakter;Usia;Harga modal\n";
            echo "1;abner.id Premium;5;1 tahun;6700000\n";
            echo "2;ached.id Premium;5;1 tahun;1600000\n";
            echo "3;added.id Premium;5;1 tahun;4300000\n";
        }, 'template-domain-premium-custom.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Cari baris header (mengandung "domain") dan petakan kolom. Tanpa header:
     * pakai urutan #, Domain, Karakter, Usia, Harga (atau tanpa #).
     */
    private function detectColumns(array $rows): ?array
    {
        foreach (array_slice($rows, 0, 5) as $i => $row) {
            $map = [];
            foreach ($row as $col => $cell) {
                $h = strtolower(trim((string) $cell));
                if ($h === '') {
                    continue;
                }
                if (! isset($map['domain']) && str_contains($h, 'domain')) {
                    $map['domain'] = $col;
                } elseif (! isset($map['chars']) && str_contains($h, 'karakter')) {
                    $map['chars'] = $col;
                } elseif (! isset($map['age']) && (str_contains($h, 'usia') || str_contains($h, 'umur'))) {
                    $map['age'] = $col;
                } elseif (! isset($map['cost']) && (str_contains($h, 'modal') || str_contains($h, 'harga'))) {
                    $map['cost'] = $col;
                }
            }
            if (isset($map['domain'], $map['cost'])) {
                $map['start'] = $i + 1;

                return $map;
            }
        }

        $first = $rows[0] ?? [];
        if (count($first) >= 5) {
            return ['domain' => 1, 'chars' => 2, 'age' => 3, 'cost' => 4, 'start' => 0];
        }
        if (count($first) === 4) {
            return ['domain' => 0, 'chars' => 1, 'age' => 2, 'cost' => 3, 'start' => 0];
        }

        return null;
    }

    private function yearsFrom(?string $label): int
    {
        return preg_match('/(\d+)/', (string) $label, $m) ? max(1, (int) $m[1]) : 1;
    }

    private function withMargin(float $cost, float $percent): float
    {
        return (float) (ceil(($cost * (1 + $percent / 100)) / 1000) * 1000);
    }
}
