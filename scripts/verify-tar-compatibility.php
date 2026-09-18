<?php
declare(strict_types=1);

// Inspect raw records: system tar hides PAX headers and makes a broken package
// appear valid, while Nextcloud's Archive_Tar discards those path overrides.
$archive = $argv[1] ?? '';
$stream = @gzopen($archive, 'rb');
if ($stream === false) {
    fwrite(STDERR, "Cannot read release archive.\n");
    exit(65);
}
try {
    while (!gzeof($stream)) {
        $header = gzread($stream, 512);
        if ($header === str_repeat("\0", 512) || $header === '') {
            break;
        }
        if (strlen($header) !== 512) {
            throw new RuntimeException('Truncated TAR header.');
        }
        $type = $header[156];
        if ($type === 'x' || $type === 'g') {
            throw new RuntimeException('PAX headers are incompatible with Nextcloud extraction. Repack using scripts/tar-create.sh (GNU format).');
        }
        if (!in_array($type, ["\0", '0', '5', 'L'], true)) {
            throw new RuntimeException('Unsupported TAR entry type: ' . bin2hex($type));
        }
        $sizeField = trim(substr($header, 124, 12), " \0");
        if ($sizeField === '' || preg_match('/^[0-7]+$/D', $sizeField) !== 1) {
            throw new RuntimeException('Invalid TAR size field.');
        }
        $remaining = (int) (ceil(octdec($sizeField) / 512) * 512);
        while ($remaining > 0) {
            $part = gzread($stream, min($remaining, 65536));
            if ($part === false || $part === '') {
                throw new RuntimeException('Truncated TAR entry.');
            }
            $remaining -= strlen($part);
        }
    }
    echo "Nextcloud-compatible TAR headers verified.\n";
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(65);
} finally {
    gzclose($stream);
}
