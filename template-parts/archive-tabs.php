<?php

/**
 * Archive Tabs Navigation
 *
 * Renders a tabbed navigation for library/gallery archive views.
 */

$active_tab = isset($active_tab) ? $active_tab : dracka_get_library_tab();
$base_url = isset($base_url) ? $base_url : '/library/';

$tabs = [
    'series'      => 'Series',
    'issues'      => 'Issues',
    'standalones' => 'Standalones',
];
?>

<nav class="archive-tabs" aria-label="Library sections">
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