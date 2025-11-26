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
