<?php
// Icons acquired from https://www.svgrepo.com/collection/jam-interface-icons/
// also https://www.svgrepo.com/vectors/social/2

/**
 * Loads an SVG icon file from the theme icon directory.
 *
 * The helper sanitizes the requested icon name, validates the resolved path
 * is still inside the icons directory, and returns the SVG contents when
 * available. It falls back to an empty string when validation fails or
 * the icon file is missing so callers can fail gracefully.
 *
 * @param string $name Icon basename without extension.
 * @return string
 */
function dracka_get_svg($name)
{
    $name = sanitize_key((string) $name);
    if ($name === '') {
        return '';
    }

    $icons_dir = get_template_directory() . '/assets/icons';
    $file = $icons_dir . '/' . $name . '.svg';
    $real_icons_dir = realpath($icons_dir);
    $real_file = realpath($file);

    if (!$real_icons_dir || !$real_file) {
        return '';
    }

    if (strpos($real_file, $real_icons_dir . DIRECTORY_SEPARATOR) !== 0) {
        return '';
    }

    $contents = file_get_contents($real_file);
    if ($contents !== false) {
        return (string) $contents;
    }

    return '';
}
