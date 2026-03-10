<?php
get_header();
$album_id = get_the_ID();
?>

<main class="album-single">
    <?php if (have_posts()) : ?>
        <?php while (have_posts()) : the_post(); ?>
            <article <?php post_class(); ?>>
                <div class="card-badges">
                    <span class="content-badge">Album</span>
                </div>
                <h1><?php the_title(); ?></h1>

                <?php if (has_post_thumbnail()) : ?>
                    <div class="album-thumb"><?php the_post_thumbnail('large'); ?></div>
                <?php endif; ?>

                <div class="album-content">
                    <?php the_content(); ?>
                </div>
            </article>
        <?php endwhile; ?>
    <?php endif; ?>

    <?php
    $artwork_items = new WP_Query([
        'post_type'      => 'artwork',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'no_found_rows'  => true,
        'meta_query'     => [
            [
                'key'     => 'dracka_album_id',
                'value'   => $album_id,
                'compare' => '=',
                'type'    => 'NUMERIC',
            ],
        ],
        'orderby'        => ['menu_order' => 'ASC', 'date' => 'DESC'],
    ]);
    ?>

    <section class="album-artwork">
        <h2>Artwork</h2>

        <?php if ($artwork_items->have_posts()) : ?>
            <div class="artwork-grid">
                <?php while ($artwork_items->have_posts()) : $artwork_items->the_post(); ?>
                    <?php
                    $artwork_album_id = (int) get_post_meta(get_the_ID(), 'dracka_album_id', true);
                    $artwork_is_standalone = $artwork_album_id <= 0;
                    ?>
                    <article <?php post_class('artwork-card'); ?>>
                        <div class="card-badges">
                            <span class="content-badge">Artwork</span>
                            <?php if ($artwork_is_standalone) : ?>
                                <span class="content-badge content-badge--muted">Standalone</span>
                            <?php endif; ?>
                        </div>
                        <h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
                        <?php if (has_post_thumbnail()) : ?>
                            <div class="artwork-thumb"><?php the_post_thumbnail('medium'); ?></div>
                        <?php endif; ?>
                    </article>
                <?php endwhile; ?>
            </div>
        <?php else : ?>
            <p>No artwork in this album yet.</p>
        <?php endif; ?>

        <?php wp_reset_postdata(); ?>
    </section>
</main>

<?php
get_footer();
