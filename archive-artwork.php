<?php
get_header();

$active_tab = dracka_get_gallery_tab();
// Only show artwork/standalones on the artwork archive
if (! in_array($active_tab, ['artwork', 'standalones'], true)) {
    $active_tab = 'artwork';
}

$tabs = [
    'artwork'     => 'Artwork',
    'standalones' => 'Standalones',
];

$empty_messages = [
    'artwork'     => 'No artwork yet.',
    'standalones' => 'No standalone artwork yet.',
];
?>

<main class="artwork-archive">
    <h1>Artwork</h1>

    <nav class="archive-tabs" aria-label="Artwork sections">
        <?php foreach ($tabs as $tab_slug => $tab_label) : ?>
            <?php
            $tab_classes = 'archive-tab';
            if ($tab_slug === $active_tab) {
                $tab_classes .= ' is-active';
            }
            ?>
            <a class="<?php echo esc_attr($tab_classes); ?>" href="<?php echo esc_url(home_url('/gallery/' . $tab_slug . '/')); ?>">
                <?php echo esc_html($tab_label); ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="gallery-search is-hidden">
        <form role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>" aria-label="Artwork search">
            <input type="search" name="s" value="<?php echo esc_attr(get_search_query()); ?>" placeholder="Search artwork">
            <input type="hidden" name="dracka_scope" value="gallery">
            <button type="submit">Search</button>
        </form>
    </div>

    <?php if (have_posts()) : ?>
        <div class="artwork-grid">
            <?php while (have_posts()) : the_post(); ?>
                <article <?php post_class('artwork-card'); ?>>
                    <h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                    <?php if (has_post_thumbnail()) : ?>
                        <div class="artwork-thumb"><?php the_post_thumbnail('medium'); ?></div>
                    <?php endif; ?>
                    <div class="artwork-excerpt"><?php the_excerpt(); ?></div>
                </article>
            <?php endwhile; ?>
        </div>

        <?php the_posts_pagination(); ?>
    <?php else : ?>
        <p>No artwork yet.</p>
    <?php endif; ?>
</main>

<?php
get_footer();
