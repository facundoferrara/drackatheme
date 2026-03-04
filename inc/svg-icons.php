<?php
// Icons acquired from https://www.svgrepo.com/collection/jam-interface-icons/
// also https://www.svgrepo.com/vectors/social/2

/**
 * Loads an SVG icon file from the theme icon directory.
 *
 * The helper constructs a path using the requested icon name, returns
 * the file contents when present, and falls back to an empty string when
 * no matching SVG exists so callers can fail gracefully.
 *
 * @param string $name Icon basename without extension.
 * @return string
 */
function dracka_get_svg($name)
{

    $file = get_template_directory() . '/assets/icons/' . $name . '.svg';

    if (file_exists($file)) {
        return file_get_contents($file);
    }

    return '';
}
