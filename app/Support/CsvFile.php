<?php

namespace App\Support;

use RuntimeException;

/**
 * お店から受け取る CSV を読む。Excel で保存した Shift_JIS でも、UTF-8（BOM あり・なし）でも読める。
 * 1行目は見出し。見出しの名前で列を探すので、列の順番は問わない。
 */
final class CsvFile
{
    /**
     * @param  list<string>  $columns  必要な見出し
     * @return list<array{line: int, row: array<string, string>}>  2行目から。line は CSV の行番号
     */
    public static function read(string $path, array $columns): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("ファイルが見つかりません：{$path}");
        }
        $text = file_get_contents($path);
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text); // UTF-8 の BOM
        if (! mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'SJIS-win');
        }

        $fp = fopen('php://memory', 'r+');
        fwrite($fp, $text);
        rewind($fp);
        $header = array_map(fn ($h) => trim((string) $h), fgetcsv($fp, escape: '') ?: []);
        $missing = array_diff($columns, $header);
        if ($missing) {
            throw new RuntimeException('見出しが足りません：'.implode('、', $missing).'（1行目に '.implode(', ', $columns).' が必要です）');
        }

        $rows = [];
        $line = 1;
        while (($cells = fgetcsv($fp, escape: '')) !== false) {
            $line++;
            if ($cells === [null] || implode('', array_map('trim', $cells)) === '') {
                continue; // 空行
            }
            $row = [];
            foreach ($header as $i => $name) {
                $row[$name] = trim((string) ($cells[$i] ?? ''));
            }
            $rows[] = ['line' => $line, 'row' => $row];
        }
        fclose($fp);

        return $rows;
    }
}
