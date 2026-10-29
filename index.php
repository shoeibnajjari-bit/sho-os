<?php
declare(strict_types=1);
$f = __DIR__ . '/.index_src.gz.b64';
if (!is_file($f)) { http_response_code(500); header('Content-Type: text/plain; charset=utf-8'); exit('missing restore blob'); }
$src = gzdecode(base64_decode(file_get_contents($f), true));
if ($src === false || strncmp($src, '<?php', 5) !== 0) { http_response_code(500); header('Content-Type: text/plain; charset=utf-8'); exit('bad restore blob'); }
if (@file_put_contents(__DIR__ . '/index.php', $src) === false) { http_response_code(500); header('Content-Type: text/plain; charset=utf-8'); exit('cannot write index'); }
@unlink($f);
require __DIR__ . '/index.php';
