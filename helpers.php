<?php

function imageMimeMap(): array
{
    return [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];
}

function uploadImageWithValidation(array $file, string $prefix, array &$errors, ?string $missingMessage = null, bool $collectErrors = true): ?string
{
    $hasFile = !empty($file['name']);
    if (!$hasFile) {
        if ($missingMessage !== null) {
            $errors[] = $missingMessage;
        }
        return null;
    }

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        if ($collectErrors) {
            $errors[] = 'Nepavyko įkelti nuotraukos.';
        }
        return null;
    }

    $uploaded = saveUploadedFile($file, imageMimeMap(), $prefix);
    if ($uploaded !== null) {
        return $uploaded;
    }

    if ($collectErrors) {
        $errors[] = 'Leidžiami formatai: jpg, jpeg, png, webp, gif.';
    }

    return null;
}

/**
 * Paverčia tekstą į URL draugišką formatą (slug).
 * Pvz.: "Skanus pyragas!" -> "skanus-pyragas"
 */
function slugify(string $text): string
{
    // Lietuviškų raidžių žemėlapis
    $map = [
        'ą' => 'a', 'č' => 'c', 'ę' => 'e', 'ė' => 'e', 'į' => 'i', 'š' => 's', 'ų' => 'u', 'ū' => 'u', 'ž' => 'z',
        'Ą' => 'A', 'Č' => 'C', 'Ę' => 'E', 'Ė' => 'E', 'Į' => 'I', 'Š' => 'S', 'Ų' => 'U', 'Ū' => 'U', 'Ž' => 'Z'
    ];
    
    // Pakeičiame lietuviškas raides
    $text = strtr($text, $map);
    
    // Paliekame tik raides, skaičius ir tarpus
    // (Naudojame paprastesnį regex, kad veiktų daugelyje serverių)
    $text = preg_replace('/[^a-zA-Z0-9\s-]/', '', $text);
    
    // Pakeičiame tarpus brūkšneliais
    $text = preg_replace('/\s+/', '-', $text);
    
    // Konvertuojame į mažąsias raides
    $text = strtolower($text);
    
    // Panaikiname brūkšnelius pradžioje ir pabaigoje
    $text = trim($text, '-');

    return $text ?: 'item';
}
