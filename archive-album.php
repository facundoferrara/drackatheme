<?php
get_header();

$active_tab = dracka_get_gallery_tab();
$tabs = [
    'artwork' => 'Artwork',
    'albums'  => 'Albums',
];

$empty_messages = [
    'artwork' => 'No artwork yet.',
    'albums'  => 'No albums yet.',
];

$archive_class = $active_tab === 'albums' ? 'album-grid' : 'artwork-grid';
$card_class = $active_tab === 'albums' ? 'album-card' : 'artwork-card';
?>

<main class="album-archive">
    <h1>Albums</h1>

    <nav class="archive-tabs" aria-label="Gallery sections">
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
        <form role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>" aria-label="Gallery search">
            <input type="search" name="s" value="<?php echo esc_attr(get_search_query()); ?>" placeholder="Search the gallery">
            <input type="hidden" name="dracka_scope" value="gallery">
            <button type="submit">Search</button>
        </form>
    </div>

    <?php if (have_posts()) : ?>
        <div class="<?php echo esc_attr($archive_class); ?>">
            <?php while (have_posts()) : the_post(); ?>
                <?php
                $post_type = get_post_type();
                $type_badge = $post_type === 'album' ? 'Album' : 'Artwork';
                $is_standalone = false;
                $album_elements_count = 0;

                if ($post_type === 'artwork') {
                    $album_id = (int) get_post_meta(get_the_ID(), 'dracka_album_id', true);
                    $is_standalone = $album_id <= 0;
                } elseif ($post_type === 'album') {
                    $album_artwork_query = new WP_Query([
                        'post_type'      => 'artwork',
                        'post_status'    => 'publish',
                        'posts_per_page' => 1,
                        'fields'         => 'ids',
                        'no_found_rows'  => false,
                        'meta_query'     => [
                            [
                                'key'     => 'dracka_album_id',
                                'value'   => get_the_ID(),
                                'compare' => '=',
                                'type'    => 'NUMERIC',
                            ],
                        ],
                    ]);
                    $album_elements_count = (int) $album_artwork_query->found_posts;
                    wp_reset_postdata();
                }
                ?>
                <article <?php post_class($card_class); ?>>
                    <?php if ($active_tab !== 'albums') : ?>
                        <div class="card-badges">
                            <span class="content-badge"><?php echo esc_html($type_badge); ?></span>
                            <?php if ($is_standalone) : ?>
                                <span class="content-badge content-badge--muted">Standalone</span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($post_type === 'album') : ?>
                        <?php if (has_post_thumbnail()) : ?>
                            <a class="album-thumb" href="<?php the_permalink(); ?>" aria-label="<?php echo esc_attr(get_the_title()); ?>"><?php the_post_thumbnail('medium'); ?></a>
                        <?php endif; ?>
                        <h2 class="album-card__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                        <?php
                        $album_elements_label = sprintf(
                            /* translators: %s: artwork count */
                            _n('Includes (%s element)', '%s elements', $album_elements_count, 'dracka'),
                            number_format_i18n($album_elements_count)
                        );
                        ?>
                        <p class="album-card__meta"><?php echo esc_html($album_elements_label); ?></p>
                    <?php else : ?>
                        <?php if (has_post_thumbnail()) : ?>
                            <a class="album-thumb" href="<?php the_permalink(); ?>" aria-label="<?php echo esc_attr(get_the_title()); ?>"><?php the_post_thumbnail('medium'); ?></a>
                        <?php endif; ?>
                        <div class="album-excerpt"><?php the_excerpt(); ?></div>
                    <?php endif; ?>
                </article>
            <?php endwhile; ?>
        </div>

        <?php the_posts_pagination(); ?>
    <?php else : ?>
        <p><?php echo esc_html($empty_messages[$active_tab]); ?></p>
    <?php endif; ?>
</main>

<?php
get_footer();
