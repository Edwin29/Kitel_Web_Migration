<?php
function qr_svg($text, $scale = 5)
{
    $backend = config('qr_backend') ?: 'qrencode';
    if ($backend === 'qrencode') {
        return qr_svg_with_qrencode($text, $scale);
    }
    throw new RuntimeException('지원하지 않는 QR 생성 방식입니다: ' . $backend);
}

function qr_svg_with_qrencode($text, $scale)
{
    $binary = config('qrencode_path') ?: 'qrencode';
    $command = escapeshellcmd($binary) . ' -t SVG -m 4 -s ' . max(1, (int)$scale) . ' -o - ' . escapeshellarg($text);
    $descriptors = array(
        0 => array('pipe', 'r'),
        1 => array('pipe', 'w'),
        2 => array('pipe', 'w'),
    );
    $process = proc_open($command, $descriptors, $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('QR 생성기를 실행할 수 없습니다.');
    }
    fclose($pipes[0]);
    $svg = stream_get_contents($pipes[1]);
    stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);
    if ($exitCode !== 0 || trim($svg) === '') {
        throw new RuntimeException('QR 생성에 실패했습니다. 서버에 qrencode를 설치하거나 qrencode_path를 확인하세요.');
    }
    if (stripos($svg, '<svg') === false) {
        throw new RuntimeException('QR 생성기가 SVG를 반환하지 않았습니다.');
    }
    return qr_normalize_svg($svg);
}

function qr_normalize_svg($svg)
{
    $svg = preg_replace('/<\?xml[^>]*>\s*/i', '', $svg);
    $svg = preg_replace('/<!DOCTYPE[^>]*>\s*/i', '', $svg);
    $svg = preg_replace('/<svg\s/i', '<svg class="qr-svg" ', $svg, 1);
    return $svg;
}

function qr_backend_available()
{
    $backend = config('qr_backend') ?: 'qrencode';
    if ($backend !== 'qrencode') {
        return false;
    }
    $binary = config('qrencode_path') ?: 'qrencode';
    $command = escapeshellcmd($binary) . ' --version';
    $descriptors = array(
        0 => array('pipe', 'r'),
        1 => array('pipe', 'w'),
        2 => array('pipe', 'w'),
    );
    $process = proc_open($command, $descriptors, $pipes);
    if (!is_resource($process)) {
        return false;
    }
    fclose($pipes[0]);
    stream_get_contents($pipes[1]);
    stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return proc_close($process) === 0;
}
