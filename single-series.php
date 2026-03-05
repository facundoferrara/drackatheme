<?php
get_header();
$series_id = get_queried_object_id();

$issues_page = isset($_GET['issues_page']) ? max(1, absint($_GET['issues_page'])) : 1;
$issues_sort = isset($_GET['issues_sort']) ? strtolower(sanitize_text_field(wp_unslash($_GET['issues_sort']))) : 'new';
$issues_sort = $issues_sort === 'old' ? 'old' : 'new';
$issues_order = $issues_sort === 'old' ? 'ASC' : 'DESC';

$issues = new WP_Query([
    'post_type'      => 'issue',
    'post_status'    => 'publish',
    'posts_per_page' => 20,
    'paged'          => $issues_page,
    'meta_query'     => [
        [
            'key'     => 'dracka_series_id',
            'value'   => $series_id,
            'compare' => '=',
            'type'    => 'NUMERIC',
        ],
    ],
    'orderby'        => 'date',
    'order'          => $issues_order,
]);
?>

<main class="series-single">
    <?php if (have_posts()) : ?>
        <?php while (have_posts()) : the_post(); ?>
            <?php
            $splash_id = (int) get_post_meta(get_the_ID(), DRACKA_SERIES_SPLASH_META_KEY, true);
            $series_author = (string) get_post_meta(get_the_ID(), DRACKA_SERIES_AUTHOR_META_KEY, true);
            $series_year = (string) get_post_meta(get_the_ID(), DRACKA_SERIES_YEAR_META_KEY, true);
            $series_description = (string) get_post_meta(get_the_ID(), DRACKA_SERIES_DESCRIPTION_META_KEY, true);
            $genre_terms = get_the_terms(get_the_ID(), 'dracka_series_genre');
            $genre_label = '';

            if (!is_wp_error($genre_terms) && is_array($genre_terms) && !empty($genre_terms)) {
                $genre_names = wp_list_pluck($genre_terms, 'name');
                $genre_label = implode(', ', array_filter($genre_names));
            }
            ?>

            <?php if ($splash_id > 0) : ?>
                <div class="series-splash">
                    <?php echo wp_get_attachment_image($splash_id, 'full', false, ['class' => 'series-splash-image']); ?>
                </div>
            <?php endif; ?>

            <article <?php post_class(); ?>>
                <?php if (has_post_thumbnail()) : ?>
                    <div class="series-thumb"><?php the_post_thumbnail('large'); ?></div>
                <?php endif; ?>

                <h1><?php the_title(); ?></h1>

                <div class="series-content series-description">
                    <?php echo $series_description !== '' ? wpautop(esc_html($series_description)) : '<p>No description available.</p>'; ?>
                </div>

                <div class="series-meta-list">
                    <p><strong>Author:</strong> <?php echo $series_author !== '' ? esc_html($series_author) : 'Unknown'; ?></p>
                    <p><strong>Year:</strong> <?php echo $series_year !== '' ? esc_html($series_year) : 'N/A'; ?></p>
                    <p><strong>Genre:</strong> <?php echo $genre_label !== '' ? esc_html($genre_label) : 'Unspecified'; ?></p>
                </div>
            </article>
        <?php endwhile; ?>
    <?php endif; ?>

    <section class="series-issues">
        <div class="series-issues-header">
            <h2>Released Issues</h2>
            <?php
            $toggle_sort = $issues_sort === 'new' ? 'old' : 'new';
            $toggle_label = $issues_sort === 'new' ? 'Sort: Old to New' : 'Sort: New to Old';
            $sort_url = add_query_arg([
                'issues_sort' => $toggle_sort,
                'issues_page' => 1,
            ], get_permalink($series_id));
            ?>
            <a class="series-issues-sort" href="<?php echo esc_url($sort_url); ?>"><?php echo esc_html($toggle_label); ?></a>
        </div>

        <?php if ($issues->have_posts()) : ?>
            <div class="series-issues-list">
                <?php while ($issues->have_posts()) : $issues->the_post(); ?>
                    <article <?php post_class('series-issue-row'); ?>>
                        <div class="series-issue-row__media">
                            <?php if (has_post_thumbnail()) : ?>
                                <a href="<?php the_permalink(); ?>" class="series-issue-row__thumb-link" aria-label="<?php echo esc_attr(get_the_title()); ?>">
                                    <?php the_post_thumbnail('medium'); ?>
                                </a>
                            <?php else : ?>
                                <div class="series-issue-row__thumb-placeholder" aria-hidden="true"></div>
                            <?php endif; ?>
                        </div>
                        <div class="series-issue-row__content">
                            <div class="series-issue-row__title-wrap">
                                <h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
                                <div class="series-issue-row__views"><?php echo do_shortcode('[views]'); ?></div>
                            </div>
                            <p class="series-issue-row__date"><?php echo esc_html(get_the_date()); ?></p>
                        </div>
                    </article>
                <?php endwhile; ?>
            </div>

            <?php
            echo '<div class="pagination">';
            echo paginate_links([
                'base'      => esc_url(add_query_arg('issues_page', '%#%')),
                'format'    => '',
                'current'   => $issues_page,
                'total'     => max(1, (int) $issues->max_num_pages),
                'add_args'  => ['issues_sort' => $issues_sort],
                'prev_text' => '«',
                'next_text' => '»',
            ]);
            echo '</div>';
            ?>
        <?php else : ?>
            <p>No released issues yet for this series.</p>
        <?php endif; ?>

        <?php wp_reset_postdata(); ?>
    </section>
</main>

<?php
get_footer();
