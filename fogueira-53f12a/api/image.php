<?php
/**
 * Image inspection and resizing with GD only (no Imagick on this host).
 *
 * Every failure here is a message for the uploader, carried by FgImageError and
 * reported in the response's "errors" array, so one bad file never kills a batch.
 */

class FgImageError extends Exception
{
}

define('FG_HEIC_ADVICE',
    'Essa foto está em HEIC (o formato do iPhone) e o servidor não consegue abrir esse tipo. '
    . 'Escolhendo a foto pelo app Fotos/Galeria o iPhone normalmente já converte para JPEG sozinho — '
    . 'tenta de novo por ali, em vez do app Arquivos. Se ela insistir em sair HEIC, muda em '
    . 'Ajustes > Câmera > Formatos para "Mais Compatível", ou manda a foto pelo WhatsApp, que ela já chega como JPG.');

define('FG_AVIF_ADVICE',
    'Essa foto está em AVIF e o servidor não consegue abrir esse formato. '
    . 'Manda ela como JPG ou PNG (abrir e salvar/compartilhar pela galeria já resolve).');

define('FG_NOT_AN_IMAGE', 'Esse arquivo não é uma foto que eu consiga abrir (aceito JPG, PNG, WEBP e GIF).');

function fg_accepted_types()
{
    return array(
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_WEBP => 'webp',
        IMAGETYPE_GIF => 'gif',
    );
}

function fg_type_loader($type)
{
    $loaders = array(
        IMAGETYPE_JPEG => 'imagecreatefromjpeg',
        IMAGETYPE_PNG => 'imagecreatefrompng',
        IMAGETYPE_WEBP => 'imagecreatefromwebp',
        IMAGETYPE_GIF => 'imagecreatefromgif',
    );
    return isset($loaders[$type]) ? $loaders[$type] : null;
}

function fg_type_keeps_alpha($type)
{
    return $type === IMAGETYPE_PNG || $type === IMAGETYPE_WEBP || $type === IMAGETYPE_GIF;
}

function fg_finfo_mime($path)
{
    if (!function_exists('finfo_open')) {
        return null;
    }
    $finfo = @finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo === false) {
        return null;
    }
    $mime = @finfo_file($finfo, $path);
    finfo_close($finfo);
    return is_string($mime) && $mime !== '' ? strtolower($mime) : null;
}

/** ISO-BMFF brand, which is how HEIC/HEIF/AVIF announce themselves. */
function fg_isobmff_brand($path)
{
    $handle = @fopen($path, 'rb');
    if ($handle === false) {
        return null;
    }
    $head = @fread($handle, 16);
    fclose($handle);
    if (!is_string($head) || strlen($head) < 12 || substr($head, 4, 4) !== 'ftyp') {
        return null;
    }
    return strtolower(substr($head, 8, 4));
}

/**
 * Decides what a file really is, from its bytes. The client's name and declared
 * type are only hints for the error message; the output extension never is.
 *
 * Returns array('ok' => true, 'type' => IMAGETYPE_*, 'ext' => string,
 * 'width' => int, 'height' => int) or array('ok' => false, 'message' => string).
 */
function fg_inspect_image($path, $clientName)
{
    $accepted = fg_accepted_types();
    $info = @getimagesize($path);
    if (is_array($info) && isset($info[2], $info[0], $info[1]) && isset($accepted[$info[2]])) {
        $type = (int) $info[2];
        $mime = fg_finfo_mime($path);
        if ($mime !== null && strpos($mime, 'image/') !== 0) {
            fg_log('type mismatch: getimagesize says ' . $type . ', finfo says ' . $mime);
            return array('ok' => false, 'message' => FG_NOT_AN_IMAGE);
        }
        $loader = fg_type_loader($type);
        if ($loader === null || !function_exists($loader)) {
            return array(
                'ok' => false,
                'message' => 'O servidor não tem suporte para esse formato de imagem. Manda a foto como JPG, que funciona sempre.',
            );
        }
        $width = (int) $info[0];
        $height = (int) $info[1];
        if ($width < 1 || $height < 1) {
            return array('ok' => false, 'message' => FG_NOT_AN_IMAGE);
        }
        return array(
            'ok' => true,
            'type' => $type,
            'ext' => $accepted[$type],
            'width' => $width,
            'height' => $height,
        );
    }

    // Not something GD reads. Say *why*, when we can tell.
    $brand = fg_isobmff_brand($path);
    $mime = (string) fg_finfo_mime($path);
    $suffix = strtolower((string) pathinfo((string) $clientName, PATHINFO_EXTENSION));

    $avifBrands = array('avif', 'avis', 'av01');
    if (in_array($brand, $avifBrands, true) || strpos($mime, 'avif') !== false || $suffix === 'avif') {
        return array('ok' => false, 'message' => FG_AVIF_ADVICE);
    }

    $heifBrands = array('heic', 'heix', 'heim', 'heis', 'hevc', 'hevx', 'hevm', 'hevs', 'mif1', 'msf1');
    $heifSuffixes = array('heic', 'heif', 'hif');
    if (in_array($brand, $heifBrands, true)
        || strpos($mime, 'heic') !== false
        || strpos($mime, 'heif') !== false
        || in_array($suffix, $heifSuffixes, true)
    ) {
        return array('ok' => false, 'message' => FG_HEIC_ADVICE);
    }

    return array('ok' => false, 'message' => FG_NOT_AN_IMAGE);
}

/**
 * GD decompresses the whole bitmap into RAM (a 12 MP photo is ~48 MB as a
 * truecolor canvas, plus the resized copy). A fatal "allowed memory exhausted"
 * would answer with HTML and break the front, so refuse before opening it.
 */
function fg_fits_in_memory($width, $height, $willRotate = true)
{
    $pixels = (float) $width * (float) $height;
    if ($pixels > FG_MAX_PIXELS) {
        return false;
    }
    $limit = fg_memory_bytes(ini_get('memory_limit'));
    if ($limit === -1) {
        return true;
    }
    // The source canvas is 4 bytes per pixel. imagerotate holds a second full
    // copy alive, but only runs when the EXIF flag asks for it; without it the
    // extra live memory is the 2000px variant plus GD's own slack. Measured
    // peak for a 24 MP JPEG with no rotation: 116 MB.
    $factor = $willRotate ? 2.2 : 1.15;
    $slack = $willRotate ? 2 * 1024 * 1024 : 20 * 1024 * 1024;
    $needed = (int) ($pixels * 4 * $factor) + $slack;
    $available = $limit - memory_get_usage(true);
    return $needed < $available;
}

function fg_too_big_message($width, $height)
{
    $megapixels = round(($width * $height) / 1000000, 1);
    return 'Essa foto é grande demais para o servidor processar (' . $megapixels . ' megapixels). '
        . 'Manda uma versão menor — compartilhar pelo WhatsApp já reduz o tamanho.';
}

function fg_load_image($path, $type)
{
    $loader = fg_type_loader($type);
    if ($loader === null || !function_exists($loader)) {
        throw new FgImageError(FG_NOT_AN_IMAGE);
    }
    $image = @call_user_func($loader, $path);
    if (!$image) {
        throw new FgImageError('Não consegui abrir essa foto — o arquivo pode ter chegado incompleto. Tenta enviar de novo?');
    }
    return $image;
}

/**
 * Applies the EXIF orientation flag so a portrait phone photo is not stored
 * sideways. Only JPEG carries it, and the exif extension may be missing.
 */
function fg_orientation_steps($path, $type)
{
    $none = array('rotate' => 0, 'flip' => 0);
    if ($type !== IMAGETYPE_JPEG || !function_exists('exif_read_data')) {
        return $none;
    }
    $exif = @exif_read_data($path, 'IFD0', false);
    if (!is_array($exif) || !isset($exif['Orientation'])) {
        return $none;
    }
    $orientation = (int) $exif['Orientation'];
    if ($orientation < 2 || $orientation > 8) {
        return $none;
    }
    // imagerotate turns counter-clockwise, so a "rotate 90 CW" flag is -90.
    $rotate = 0;
    $flip = 0;
    if ($orientation === 2) {
        $flip = IMG_FLIP_HORIZONTAL;
    } elseif ($orientation === 3) {
        $rotate = 180;
    } elseif ($orientation === 4) {
        $flip = IMG_FLIP_VERTICAL;
    } elseif ($orientation === 5) {
        $rotate = -90;
        $flip = IMG_FLIP_HORIZONTAL;
    } elseif ($orientation === 6) {
        $rotate = -90;
    } elseif ($orientation === 7) {
        $rotate = 90;
        $flip = IMG_FLIP_HORIZONTAL;
    } elseif ($orientation === 8) {
        $rotate = 90;
    }
    return array('rotate' => $rotate, 'flip' => $flip);
}

/** Whether a second full-size bitmap will be alive during processing. */
function fg_will_rotate($path, $type)
{
    $steps = fg_orientation_steps($path, $type);
    return $steps['rotate'] !== 0 && function_exists('imagerotate');
}

function fg_apply_orientation($image, $path, $type)
{
    $steps = fg_orientation_steps($path, $type);
    $rotate = $steps['rotate'];
    $flip = $steps['flip'];

    if ($rotate !== 0 && function_exists('imagerotate')) {
        $rotated = @imagerotate($image, $rotate, 0);
        if ($rotated) {
            imagedestroy($image);
            $image = $rotated;
        }
    }
    if ($flip !== 0 && function_exists('imageflip')) {
        @imageflip($image, $flip);
    }
    return $image;
}

/**
 * WebP only when this PHP's GD was built with it; otherwise JPEG, or PNG when
 * the source format can carry transparency. The DTO carries the real URL, so
 * the front never needs to know which one it got.
 */
function fg_variant_extension($keepsAlpha)
{
    if (function_exists('imagewebp')) {
        return 'webp';
    }
    return $keepsAlpha ? 'png' : 'jpg';
}

/**
 * Writes one resized copy, never upscaling.
 * Returns array('path' => string, 'ext' => string, 'width' => int, 'height' => int).
 */
function fg_write_variant($image, $directory, $id, $maxSide, $quality, $keepsAlpha)
{
    $sourceWidth = imagesx($image);
    $sourceHeight = imagesy($image);
    $scale = min(1.0, $maxSide / max($sourceWidth, $sourceHeight));
    $width = max(1, (int) round($sourceWidth * $scale));
    $height = max(1, (int) round($sourceHeight * $scale));

    $canvas = @imagecreatetruecolor($width, $height);
    if (!$canvas) {
        throw new FgImageError('Não consegui redimensionar essa foto aqui no servidor. Tenta uma versão menor?');
    }

    $extension = fg_variant_extension($keepsAlpha);
    $transparent = $keepsAlpha && $extension !== 'jpg';
    if ($transparent) {
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $fill = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefilledrectangle($canvas, 0, 0, $width, $height, $fill);
        imagealphablending($canvas, true);
    } else {
        // Flattening onto white keeps transparent PNGs from turning black in JPEG.
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, $width, $height, $white);
    }

    $copied = @imagecopyresampled($canvas, $image, 0, 0, 0, 0, $width, $height, $sourceWidth, $sourceHeight);
    if (!$copied) {
        imagedestroy($canvas);
        throw new FgImageError('Não consegui redimensionar essa foto aqui no servidor. Tenta de novo?');
    }

    // Encoded in memory first, written in one checked call. Writing straight to
    // a path is what hides a full disk: imagewebp/imagejpeg/imagepng all return
    // TRUE after leaving a truncated file, and a truncated WebP still reports
    // its size to getimagesize(). file_put_contents reports the bytes it wrote.
    ob_start();
    if ($extension === 'webp') {
        $encoded = @imagewebp($canvas, null, $quality);
    } elseif ($extension === 'png') {
        $encoded = @imagepng($canvas, null, 6);
    } else {
        $encoded = @imagejpeg($canvas, null, $quality);
    }
    $bytes = ob_get_clean();
    imagedestroy($canvas);

    $path = $directory . '/' . $id . '.' . $extension;
    $length = is_string($bytes) ? strlen($bytes) : 0;
    $stored = $encoded && $length > 0 ? @file_put_contents($path, $bytes) : false;
    if ($stored !== $length) {
        fg_log('variant write failed: ' . basename($path) . ' wrote ' . var_export($stored, true) . ' of ' . $length);
        fg_unlink_quietly($path);
        throw new FgImageError('Não consegui salvar essa foto aqui no servidor. Avisa o Felipe e tenta de novo?');
    }
    @chmod($path, 0644);

    return array('path' => $path, 'ext' => $extension, 'width' => $width, 'height' => $height);
}

/**
 * Display + thumb from a single decode. Frees the bitmap before returning, so a
 * 20-photo batch does not keep two decoded images alive at the same time.
 * Returns array('ext','width','height','files').
 */
function fg_render_variants($sourcePath, $id, $type)
{
    $image = fg_load_image($sourcePath, $type);
    $written = array();
    try {
        $image = fg_apply_orientation($image, $sourcePath, $type);
        $keepsAlpha = fg_type_keeps_alpha($type);

        $display = fg_write_variant($image, FG_DISPLAY_DIR, $id, FG_DISPLAY_MAX_SIDE, FG_DISPLAY_QUALITY, $keepsAlpha);
        $written[] = $display['path'];
        $thumb = fg_write_variant($image, FG_THUMB_DIR, $id, FG_THUMB_MAX_SIDE, FG_THUMB_QUALITY, $keepsAlpha);
        $written[] = $thumb['path'];

        return array(
            'ext' => $display['ext'],
            'width' => $display['width'],
            'height' => $display['height'],
            'files' => $written,
        );
    } catch (Exception $error) {
        foreach ($written as $path) {
            fg_unlink_quietly($path);
        }
        throw $error;
    } finally {
        if ($image) {
            imagedestroy($image);
        }
    }
}
