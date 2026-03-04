<?php
get_header();
$series_id = get_queried_object_id();
?>

<main class="series-single">
    <?php if (have_posts()) : ?>
        <?php while (have_posts()) : the_post(); ?>
            <article <?php post_class(); ?>>
                <div class="card-badges">
                    <span class="content-badge">Series</span>
                </div>
                <h1><?php the_title(); ?></h1>

                <?php if (has_post_thumbnail()) : ?>
                    <div class="series-thumb"><?php the_post_thumbnail('large'); ?></div>
                <?php endif; ?>

                <div class="series-content">
                    <?php the_content(); ?>
                </div>
            </article>
        <?php endwhile; ?>
    <?php endif; ?>

    <?php
    $issues = new WP_Query([
        'post_type'      => 'issue',
        'posts_per_page' => -1,
        'meta_query'     => [
            [
                'key'     => 'dracka_series_id',
                'value'   => $series_id,
                'compare' => '=',
                'type'    => 'NUMERIC',
            ],
        ],
        'meta_key'       => 'dracka_series_order',
        'orderby'        => ['meta_value_num' => 'ASC', 'date' => 'DESC'],
    ]);
    ?>

    <section class="series-issues">
        <h2>Issues</h2>

        <?php if ($issues->have_posts()) : ?>
            <div class="issue-grid">
                <?php while ($issues->have_posts()) : $issues->the_post(); ?>
                    <?php
                    $issue_series_id = (int) get_post_meta(get_the_ID(), 'dracka_series_id', true);
                    $issue_is_standalone = $issue_series_id <= 0;
                    ?>
                    <article <?php post_class('issue-card'); ?>>
                        <div class="card-badges">
                            <span class="content-badge">Issue</span>
                            <?php if ($issue_is_standalone) : ?>
                                <span class="content-badge content-badge--muted">Standalone</span>
                            <?php endif; ?>
                        </div>
                        <h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
                        <?php if (has_post_thumbnail()) : ?>
                            <div class="issue-thumb"><?php the_post_thumbnail('medium'); ?></div>
                        <?php endif; ?>
                        <div class="issue-excerpt"><?php the_excerpt(); ?></div>
                    </article>
                <?php endwhile; ?>
            </div>
        <?php else : ?>
            <p>No issues yet for this series.</p>
        <?php endif; ?>

        <?php wp_reset_postdata(); ?>
    </section>
</main>

<?php
get_footer();
