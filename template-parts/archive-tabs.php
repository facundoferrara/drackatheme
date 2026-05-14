<?php

/**
 * Archive Tabs Navigation
 *
 * Renders a tabbed navigation for library/gallery archive views.
 *
 * Accepts $args:
 *   'tabs'       array<string, string>  Slug => label map. Defaults to library tabs.
 *   'base_url'   string                 URL prefix for tab links. Defaults to '/library/'.
 *   'active_tab' string                 Currently active tab slug.
 *   'nav_label'  string                 aria-label for the <nav>. Defaults to 'Archive sections'.
 */

$active_tab = $args['active_tab'] ?? dracka_get_library_tab();
$base_url   = $args['base_url']   ?? '/library/';
$nav_label  = $args['nav_label']  ?? 'Archive sections';

$tabs = $args['tabs'] ?? [
    'series'      => 'Series',
    'issues'      => 'Issues',
    'standalones' => 'Standalones',
];
?>

<nav class="archive-tabs" aria-label="<?php echo esc_attr($nav_label); ?>">
    <?php foreach ($tabs as $tab_slug => $tab_label) : ?>
        <?php
        $tab_classes = 'archive-tab';
        if ($tab_slug === $active_tab) {
            $tab_classes .= ' is-active';
        }
        ?>
        <a class="<?php echo esc_attr($tab_classes); ?>" href="<?php echo esc_url(home_url($base_url . $tab_slug . '/')); ?>">
            <?php echo esc_html($tab_label); ?>
        </a>
    <?php endforeach; ?>
</nav>